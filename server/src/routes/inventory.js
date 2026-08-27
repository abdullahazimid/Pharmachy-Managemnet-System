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
router.use(requireRole('Admin', 'Pharmacist'));

router.get('/', async (req, res) => {
  try {
    const [rows] = await db.query(
      `SELECT si.*, m.medicine_name, u.name AS updated_by_name
       FROM stock_inventory si
       JOIN medicines m ON si.medicine_id = m.medicine_id
       LEFT JOIN users u ON si.updated_by = u.user_id
       ORDER BY si.date_updated DESC, si.stock_id DESC`
    );
    res.json({ status: 'success', data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch inventory' });
  }
});

router.post('/adjust', async (req, res) => {
  const conn = await db.getConnection();
  try {
    const { medicine_id, quantity_added, quantity_sold } = req.body;
    const added = Number(quantity_added || 0);
    const sold = Number(quantity_sold || 0);

    if (!medicine_id || (added === 0 && sold === 0)) {
      return res.status(400).json({ status: 'error', message: 'Medicine and quantity required' });
    }

    await conn.beginTransaction();
    const [meds] = await conn.query(
      'SELECT quantity_in_stock, category FROM medicines WHERE medicine_id = ? FOR UPDATE',
      [medicine_id]
    );
    if (meds.length === 0) {
      await conn.rollback();
      return res.status(404).json({ status: 'error', message: 'Medicine not found' });
    }

    const current = meds[0].quantity_in_stock;
    const next = current + added - sold;
    if (next < 0) {
      await conn.rollback();
      return res.status(400).json({ status: 'error', message: 'Stock cannot go below zero' });
    }

    await conn.query(
      'UPDATE medicines SET quantity_in_stock = ? WHERE medicine_id = ?',
      [next, medicine_id]
    );
    await conn.query(
      'INSERT INTO stock_inventory (medicine_id, quantity_added, quantity_sold, updated_by) VALUES (?, ?, ?, ?)',
      [medicine_id, added, sold, req.user.user_id]
    );

    if (meds[0].category === 'Antibiotic') {
      await conn.query(
        'UPDATE antibiotic_list SET current_stock_level=?, alert_status=? WHERE medicine_id=?',
        [next, alertStatusForStock(next), medicine_id]
      );
    }

    await conn.commit();
    res.status(201).json({ status: 'success', data: { quantity_in_stock: next } });
  } catch (err) {
    await conn.rollback();
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to adjust stock' });
  } finally {
    conn.release();
  }
});

module.exports = router;
