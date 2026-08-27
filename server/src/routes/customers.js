const express = require('express');
const db = require('../db');
const { authenticate, requireRole } = require('../middleware/auth');

const router = express.Router();

router.use(authenticate);
router.use(requireRole('Admin', 'Pharmacist', 'Employee'));

router.get('/', async (req, res) => {
  try {
    const [rows] = await db.query('SELECT * FROM customers ORDER BY customer_id DESC');
    res.json({ status: 'success', data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch customers' });
  }
});

router.post('/', async (req, res) => {
  try {
    const { customer_name, contact_no, purchase_history } = req.body;
    if (!customer_name || !contact_no) {
      return res.status(400).json({ status: 'error', message: 'Name and contact required' });
    }
    const [result] = await db.query(
      'INSERT INTO customers (customer_name, contact_no, purchase_history) VALUES (?, ?, ?)',
      [customer_name, contact_no, purchase_history || '']
    );
    res.status(201).json({ status: 'success', data: { customer_id: result.insertId } });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to create customer' });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { customer_name, contact_no, purchase_history } = req.body;
    await db.query(
      'UPDATE customers SET customer_name=?, contact_no=?, purchase_history=? WHERE customer_id=?',
      [customer_name, contact_no, purchase_history || '', req.params.id]
    );
    res.json({ status: 'success', message: 'Customer updated' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to update customer' });
  }
});

router.delete('/:id', requireRole('Admin', 'Pharmacist'), async (req, res) => {
  try {
    await db.query('DELETE FROM customers WHERE customer_id = ?', [req.params.id]);
    res.json({ status: 'success', message: 'Customer deleted' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to delete customer' });
  }
});

module.exports = router;
