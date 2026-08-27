const express = require('express');
const db = require('../db');
const { authenticate, requireRole } = require('../middleware/auth');

const router = express.Router();

router.use(authenticate);
router.use(requireRole('Admin'));

router.get('/', async (req, res) => {
  try {
    const [rows] = await db.query(
      `SELECT es.*, u.name AS employee_name, u.role
       FROM employee_salaries es
       JOIN users u ON es.employee_id = u.user_id
       ORDER BY es.payment_date DESC, es.salary_id DESC`
    );
    res.json({ status: 'success', data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch salaries' });
  }
});

router.post('/', async (req, res) => {
  try {
    const { employee_id, month, basic_salary, sales_linked_bonus, payment_date } = req.body;
    if (!employee_id || !month || basic_salary == null || !payment_date) {
      return res.status(400).json({ status: 'error', message: 'Required fields missing' });
    }
    const bonus = Number(sales_linked_bonus || 0);
    const basic = Number(basic_salary);
    const total = basic + bonus;
    const [result] = await db.query(
      `INSERT INTO employee_salaries
       (employee_id, month, basic_salary, sales_linked_bonus, total_salary, payment_date)
       VALUES (?, ?, ?, ?, ?, ?)`,
      [employee_id, month, basic, bonus, total, payment_date]
    );
    res.status(201).json({ status: 'success', data: { salary_id: result.insertId } });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to create salary' });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { employee_id, month, basic_salary, sales_linked_bonus, payment_date } = req.body;
    const bonus = Number(sales_linked_bonus || 0);
    const basic = Number(basic_salary);
    const total = basic + bonus;
    await db.query(
      `UPDATE employee_salaries SET
        employee_id=?, month=?, basic_salary=?, sales_linked_bonus=?, total_salary=?, payment_date=?
       WHERE salary_id=?`,
      [employee_id, month, basic, bonus, total, payment_date, req.params.id]
    );
    res.json({ status: 'success', message: 'Salary updated' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to update salary' });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    await db.query('DELETE FROM employee_salaries WHERE salary_id = ?', [req.params.id]);
    res.json({ status: 'success', message: 'Salary deleted' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to delete salary' });
  }
});

module.exports = router;
