const express = require('express');
const db = require('../db');
const { authenticate, requireRole } = require('../middleware/auth');

const router = express.Router();

function alertStatusForStock(qty) {
  const threshold = Number(process.env.LOW_STOCK_THRESHOLD || 20);
  if (qty <= 10) return 'Critical';
  if (qty < threshold) return 'Warning';
  return 'Normal';
}

router.use(authenticate);
router.use(requireRole('Admin', 'Pharmacist', 'Employee'));

router.get('/', async (req, res) => {
  try {
    const [rows] = await db.query(
      `SELECT st.*, m.medicine_name, u.name AS sold_by_name
       FROM sales_transactions st
       JOIN medicines m ON st.medicine_id = m.medicine_id
       LEFT JOIN users u ON st.sold_by = u.user_id
       ORDER BY st.sale_date DESC, st.sales_id DESC`
    );
    res.json({ status: 'success', data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch sales' });
  }
});

/**
 * Body:
 * {
 *   customer_name, customer_contact?, customer_id?,
 *   payment_method, discount_percentage,
 *   items: [{ medicine_id, quantity }]
 * }
 */
router.post('/', async (req, res) => {
  const conn = await db.getConnection();
  try {
    const {
      customer_name,
      customer_contact,
      customer_id,
      payment_method = 'Cash',
      discount_percentage = 0,
      items = [],
    } = req.body;

    if (!customer_name || !Array.isArray(items) || items.length === 0) {
      return res.status(400).json({ status: 'error', message: 'Customer name and at least one item required' });
    }

    await conn.beginTransaction();

    let resolvedCustomerId = customer_id || null;
    if (!resolvedCustomerId && customer_contact) {
      const [existing] = await conn.query(
        'SELECT customer_id FROM customers WHERE contact_no = ? LIMIT 1',
        [customer_contact]
      );
      if (existing.length > 0) {
        resolvedCustomerId = existing[0].customer_id;
      } else {
        const [c] = await conn.query(
          'INSERT INTO customers (customer_name, contact_no, purchase_history) VALUES (?, ?, ?)',
          [customer_name, customer_contact, '']
        );
        resolvedCustomerId = c.insertId;
      }
    } else if (!resolvedCustomerId) {
      const [c] = await conn.query(
        'INSERT INTO customers (customer_name, contact_no, purchase_history) VALUES (?, ?, ?)',
        [customer_name, customer_contact || 'N/A', '']
      );
      resolvedCustomerId = c.insertId;
    }

    const discountPct = Number(discount_percentage) || 0;
    const medicineDetails = [];
    let subtotal = 0;
    const saleRows = [];

    for (const item of items) {
      const qty = Number(item.quantity);
      if (!item.medicine_id || !qty || qty <= 0) {
        await conn.rollback();
        return res.status(400).json({ status: 'error', message: 'Invalid item quantity' });
      }

      const [meds] = await conn.query(
        `SELECT medicine_id, medicine_name, category, unit_price, quantity_in_stock
         FROM medicines WHERE medicine_id = ? FOR UPDATE`,
        [item.medicine_id]
      );
      if (meds.length === 0) {
        await conn.rollback();
        return res.status(404).json({ status: 'error', message: `Medicine ${item.medicine_id} not found` });
      }

      const med = meds[0];
      if (med.quantity_in_stock < qty) {
        await conn.rollback();
        return res.status(400).json({
          status: 'error',
          message: `Insufficient stock for ${med.medicine_name} (available: ${med.quantity_in_stock})`,
        });
      }

      if (med.category === 'Antibiotic') {
        const [ab] = await conn.query(
          'SELECT allowed_range_limit FROM antibiotic_list WHERE medicine_id = ?',
          [med.medicine_id]
        );
        if (ab.length > 0 && qty > ab[0].allowed_range_limit) {
          await conn.rollback();
          return res.status(400).json({
            status: 'error',
            message: `${med.medicine_name} antibiotic limit is ${ab[0].allowed_range_limit} per sale`,
          });
        }
      }

      const line = Number(med.unit_price) * qty;
      subtotal += line;
      medicineDetails.push({
        medicine_id: med.medicine_id,
        name: med.medicine_name,
        qty,
        price: Number(med.unit_price),
      });
      saleRows.push({ medicine: med, qty, line });
    }

    const discountAmount = (subtotal * discountPct) / 100;
    const totalAmount = subtotal - discountAmount;

    const [inv] = await conn.query(
      `INSERT INTO invoices
       (customer_id, customer_name, medicine_details, total_amount, discount_applied, payment_method, generated_by)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        resolvedCustomerId,
        customer_name,
        JSON.stringify(medicineDetails),
        totalAmount,
        discountAmount,
        payment_method,
        req.user.user_id,
      ]
    );
    const invoiceId = inv.insertId;

    for (const row of saleRows) {
      const lineTotal = row.line * (1 - discountPct / 100);
      const nextStock = row.medicine.quantity_in_stock - row.qty;

      await conn.query(
        'UPDATE medicines SET quantity_in_stock = ? WHERE medicine_id = ?',
        [nextStock, row.medicine.medicine_id]
      );

      await conn.query(
        `INSERT INTO sales_transactions
         (medicine_id, quantity_sold, total_price, discount_percentage, payment_method, sold_by, invoice_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)`,
        [row.medicine.medicine_id, row.qty, lineTotal, discountPct, payment_method, req.user.user_id, invoiceId]
      );

      await conn.query(
        'INSERT INTO stock_inventory (medicine_id, quantity_added, quantity_sold, updated_by) VALUES (?, 0, ?, ?)',
        [row.medicine.medicine_id, row.qty, req.user.user_id]
      );

      if (row.medicine.category === 'Antibiotic') {
        await conn.query(
          'UPDATE antibiotic_list SET current_stock_level=?, alert_status=? WHERE medicine_id=?',
          [nextStock, alertStatusForStock(nextStock), row.medicine.medicine_id]
        );
      }
    }

    const historyText = medicineDetails.map((d) => `${d.name} (${d.qty} units)`).join(', ');
    await conn.query(
      `UPDATE customers SET
         customer_name = ?,
         purchase_history = CONCAT(IFNULL(purchase_history, ''), IF(IFNULL(purchase_history,'')='', '', '; '), ?)
       WHERE customer_id = ?`,
      [customer_name, historyText, resolvedCustomerId]
    );

    await conn.commit();

    res.status(201).json({
      status: 'success',
      data: {
        invoice_id: invoiceId,
        customer_id: resolvedCustomerId,
        total_amount: totalAmount,
        discount_applied: discountAmount,
        medicine_details: medicineDetails,
      },
    });
  } catch (err) {
    await conn.rollback();
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to create sale' });
  } finally {
    conn.release();
  }
});

module.exports = router;
