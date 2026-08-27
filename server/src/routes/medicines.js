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

router.get('/', requireRole('Admin', 'Pharmacist'), async (req, res) => {
  try {
    const [rows] = await db.query(
      `SELECT m.*, s.supplier_name
       FROM medicines m
       LEFT JOIN suppliers s ON m.supplier_id = s.supplier_id
       ORDER BY m.medicine_id`
    );
    res.json({ status: 'success', data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch medicines' });
  }
});

router.get('/list-for-sale', requireRole('Admin', 'Pharmacist', 'Employee'), async (req, res) => {
  try {
    const [rows] = await db.query(
      `SELECT medicine_id, medicine_name, company_name, category, unit_price, quantity_in_stock, expiry_date
       FROM medicines
       WHERE quantity_in_stock > 0
       ORDER BY medicine_name`
    );
    res.json({ status: 'success', data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch medicines' });
  }
});

router.post('/', requireRole('Admin', 'Pharmacist'), async (req, res) => {
  const conn = await db.getConnection();
  try {
    const {
      medicine_name, company_name, category, unit_price, purchase_price,
      quantity_in_stock, expiry_date, manufacture_date, supplier_id,
    } = req.body;

    if (!medicine_name || !company_name || !unit_price || !expiry_date || !manufacture_date) {
      return res.status(400).json({ status: 'error', message: 'Required fields missing' });
    }

    await conn.beginTransaction();
    const [result] = await conn.query(
      `INSERT INTO medicines
       (medicine_name, company_name, category, unit_price, purchase_price, quantity_in_stock, expiry_date, manufacture_date, supplier_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      [
        medicine_name,
        company_name,
        category || 'General',
        unit_price,
        purchase_price || 0,
        quantity_in_stock || 0,
        expiry_date,
        manufacture_date,
        supplier_id || null,
      ]
    );

    const medicineId = result.insertId;
    const qty = Number(quantity_in_stock || 0);

    if (qty > 0) {
      await conn.query(
        'INSERT INTO stock_inventory (medicine_id, quantity_added, quantity_sold, updated_by) VALUES (?, ?, 0, ?)',
        [medicineId, qty, req.user.user_id]
      );
    }

    if ((category || 'General') === 'Antibiotic') {
      await conn.query(
        `INSERT INTO antibiotic_list (medicine_id, allowed_range_limit, current_stock_level, alert_status)
         VALUES (?, ?, ?, ?)`,
        [medicineId, 10, qty, alertStatusForStock(qty)]
      );
    }

    await conn.commit();
    res.status(201).json({ status: 'success', data: { medicine_id: medicineId } });
  } catch (err) {
    await conn.rollback();
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to create medicine' });
  } finally {
    conn.release();
  }
});

router.put('/:id', requireRole('Admin', 'Pharmacist'), async (req, res) => {
  try {
    const {
      medicine_name, company_name, category, unit_price, purchase_price,
      quantity_in_stock, expiry_date, manufacture_date, supplier_id,
    } = req.body;

    await db.query(
      `UPDATE medicines SET
        medicine_name=?, company_name=?, category=?, unit_price=?, purchase_price=?,
        quantity_in_stock=?, expiry_date=?, manufacture_date=?, supplier_id=?
       WHERE medicine_id=?`,
      [
        medicine_name, company_name, category, unit_price, purchase_price || 0,
        quantity_in_stock, expiry_date, manufacture_date, supplier_id || null, req.params.id,
      ]
    );

    if (category === 'Antibiotic') {
      const [existing] = await db.query(
        'SELECT antibiotic_id FROM antibiotic_list WHERE medicine_id = ?',
        [req.params.id]
      );
      const qty = Number(quantity_in_stock || 0);
      if (existing.length === 0) {
        await db.query(
          `INSERT INTO antibiotic_list (medicine_id, allowed_range_limit, current_stock_level, alert_status)
           VALUES (?, 10, ?, ?)`,
          [req.params.id, qty, alertStatusForStock(qty)]
        );
      } else {
        await db.query(
          'UPDATE antibiotic_list SET current_stock_level=?, alert_status=? WHERE medicine_id=?',
          [qty, alertStatusForStock(qty), req.params.id]
        );
      }
    }

    res.json({ status: 'success', message: 'Medicine updated' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to update medicine' });
  }
});

router.delete('/:id', requireRole('Admin', 'Pharmacist'), async (req, res) => {
  try {
    await db.query('DELETE FROM medicines WHERE medicine_id = ?', [req.params.id]);
    res.json({ status: 'success', message: 'Medicine deleted' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to delete medicine' });
  }
});

module.exports = router;
