const express = require('express');
const db = require('../db');
const { authenticate, requireRole } = require('../middleware/auth');

const router = express.Router();

router.use(authenticate);

router.get('/', requireRole('Admin', 'Pharmacist'), async (req, res) => {
  try {
    const [rows] = await db.query('SELECT * FROM suppliers ORDER BY supplier_id');
    res.json({ status: 'success', data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch suppliers' });
  }
});

router.post('/', requireRole('Admin', 'Pharmacist'), async (req, res) => {
  try {
    const { supplier_name, contact_no, address, company_name } = req.body;
    if (!supplier_name || !contact_no || !address || !company_name) {
      return res.status(400).json({ status: 'error', message: 'All fields are required' });
    }
    const [result] = await db.query(
      'INSERT INTO suppliers (supplier_name, contact_no, address, company_name) VALUES (?, ?, ?, ?)',
      [supplier_name, contact_no, address, company_name]
    );
    res.status(201).json({ status: 'success', data: { supplier_id: result.insertId } });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to create supplier' });
  }
});

router.put('/:id', requireRole('Admin', 'Pharmacist'), async (req, res) => {
  try {
    const { supplier_name, contact_no, address, company_name } = req.body;
    await db.query(
      'UPDATE suppliers SET supplier_name=?, contact_no=?, address=?, company_name=? WHERE supplier_id=?',
      [supplier_name, contact_no, address, company_name, req.params.id]
    );
    res.json({ status: 'success', message: 'Supplier updated' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to update supplier' });
  }
});

router.delete('/:id', requireRole('Admin', 'Pharmacist'), async (req, res) => {
  try {
    await db.query('DELETE FROM suppliers WHERE supplier_id = ?', [req.params.id]);
    res.json({ status: 'success', message: 'Supplier deleted' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to delete supplier' });
  }
});

module.exports = router;
