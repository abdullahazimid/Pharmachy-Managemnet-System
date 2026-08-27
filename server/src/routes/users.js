const express = require('express');
const bcrypt = require('bcryptjs');
const db = require('../db');
const { authenticate, requireRole } = require('../middleware/auth');

const router = express.Router();

router.use(authenticate);
router.use(requireRole('Admin'));

router.get('/', async (req, res) => {
  try {
    const [rows] = await db.query(
      'SELECT user_id, name, role, username, email, created_at FROM users ORDER BY user_id'
    );
    res.json({ status: 'success', data: rows });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch users' });
  }
});

router.post('/', async (req, res) => {
  try {
    const { name, role, username, password, email } = req.body;
    if (!name || !role || !username || !password || !email) {
      return res.status(400).json({ status: 'error', message: 'All fields are required' });
    }
    if (!['Admin', 'Pharmacist', 'Employee'].includes(role)) {
      return res.status(400).json({ status: 'error', message: 'Invalid role' });
    }
    const hash = await bcrypt.hash(password, 10);
    const [result] = await db.query(
      'INSERT INTO users (name, role, username, password, email) VALUES (?, ?, ?, ?, ?)',
      [name, role, username, hash, email]
    );
    res.status(201).json({ status: 'success', data: { user_id: result.insertId } });
  } catch (err) {
    console.error(err);
    if (err.code === 'ER_DUP_ENTRY') {
      return res.status(400).json({ status: 'error', message: 'Username or email already exists' });
    }
    res.status(500).json({ status: 'error', message: 'Failed to create user' });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { name, role, username, email, password } = req.body;
    if (!name || !role || !username || !email) {
      return res.status(400).json({ status: 'error', message: 'Name, role, username and email required' });
    }
    if (password) {
      const hash = await bcrypt.hash(password, 10);
      await db.query(
        'UPDATE users SET name=?, role=?, username=?, email=?, password=? WHERE user_id=?',
        [name, role, username, email, hash, req.params.id]
      );
    } else {
      await db.query(
        'UPDATE users SET name=?, role=?, username=?, email=? WHERE user_id=?',
        [name, role, username, email, req.params.id]
      );
    }
    res.json({ status: 'success', message: 'User updated' });
  } catch (err) {
    console.error(err);
    if (err.code === 'ER_DUP_ENTRY') {
      return res.status(400).json({ status: 'error', message: 'Username or email already exists' });
    }
    res.status(500).json({ status: 'error', message: 'Failed to update user' });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    if (Number(req.params.id) === req.user.user_id) {
      return res.status(400).json({ status: 'error', message: 'Cannot delete your own account' });
    }
    await db.query('DELETE FROM users WHERE user_id = ?', [req.params.id]);
    res.json({ status: 'success', message: 'User deleted' });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to delete user' });
  }
});

module.exports = router;
