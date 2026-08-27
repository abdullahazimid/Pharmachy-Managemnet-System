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
      `SELECT a.*, m.medicine_name, m.company_name, m.quantity_in_stock
       FROM antibiotic_list a
       JOIN medicines m ON a.medicine_id = m.medicine_id
       ORDER BY a.antibiotic_id`
    );
    res.json({ status: 'success', data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch antibiotics' });
  }
});

router.post('/', async (req, res) => {
  try {
    const { medicine_id, allowed_range_limit } = req.body;
    if (!medicine_id || !allowed_range_limit) {
      return res.status(400).json({ status: 'error', message: 'Medicine and limit required' });
    }

    const [meds] = await db.query(
      'SELECT quantity_in_stock, category FROM medicines WHERE medicine_id = ?',
      [medicine_id]
    );
    if (meds.length === 0) {
      return res.status(404).json({ status: 'error', message: 'Medicine not found' });
    }
    if (meds[0].category !== 'Antibiotic') {
      return res.status(400).json({ status: 'error', message: 'Medicine must be Antibiotic category' });
    }

    const qty = meds[0].quantity_in_stock;
    const [result] = await db.query(
      `INSERT INTO antibiotic_list (medicine_id, allowed_range_limit, current_stock_level, alert_status)
       VALUES (?, ?, ?, ?)`,
      [medicine_id, allowed_range_limit, qty, alertStatusForStock(qty)]
    );
    res.status(201).json({ status: 'success', data: { antibiotic_id: result.insertId } });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to add antibiotic' });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { allowed_range_limit, alert_status } = req.body;
    const [rows] = await db.query(
      `SELECT a.medicine_id, m.quantity_in_stock
       FROM antibiotic_list a
       JOIN medicines m ON a.medicine_id = m.medicine_id
       WHERE a.antibiotic_id = ?`,
      [req.params.id]
    );
    if (rows.length === 0) {
      return res.status(404).json({ status: 'error', message: 'Antibiotic entry not found' });
    }

    const qty = rows[0].quantity_in_stock;
    const status = alert_status || alertStatusForStock(qty);
    await db.query(
      `UPDATE antibiotic_list
       SET allowed_range_limit=?, current_stock_level=?, alert_status=?
       WHERE antibiotic_id=?`,
      [allowed_range_limit, qty, status, req.params.id]
    );
    res.json({ status: 'success', message: 'Antibiotic updated' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to update antibiotic' });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await db.query('DELETE FROM antibiotic_list WHERE antibiotic_id = ?', [req.params.id]);
    res.json({ status: 'success', message: 'Antibiotic removed' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to delete antibiotic' });
  }
});

module.exports = router;
