const express = require('express');
const db = require('../db');
const { authenticate, requireRole } = require('../middleware/auth');

const router = express.Router();

router.use(authenticate);
router.use(requireRole('Admin', 'Pharmacist', 'Employee'));

router.get('/', async (req, res) => {
  try {
    const [rows] = await db.query(
      `SELECT i.*, u.name AS generated_by_name
       FROM invoices i
       LEFT JOIN users u ON i.generated_by = u.user_id
       ORDER BY i.invoice_date DESC, i.invoice_id DESC`
    );
    const data = rows.map((r) => ({
      ...r,
      medicine_details:
        typeof r.medicine_details === 'string'
          ? JSON.parse(r.medicine_details || '[]')
          : r.medicine_details,
    }));
    res.json({ status: 'success', data });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch invoices' });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const [rows] = await db.query(
      `SELECT i.*, u.name AS generated_by_name
       FROM invoices i
       LEFT JOIN users u ON i.generated_by = u.user_id
       WHERE i.invoice_id = ?`,
      [req.params.id]
    );
    if (rows.length === 0) {
      return res.status(404).json({ status: 'error', message: 'Invoice not found' });
    }
    const invoice = rows[0];
    invoice.medicine_details =
      typeof invoice.medicine_details === 'string'
        ? JSON.parse(invoice.medicine_details || '[]')
        : invoice.medicine_details;
    res.json({ status: 'success', data: invoice });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch invoice' });
  }
});

module.exports = router;
