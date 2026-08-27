const express = require('express');
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const db = require('../db');

const router = express.Router();

router.post('/login', async (req, res) => {
  try {
    const { username, password } = req.body;
    if (!username || !password) {
      return res.status(400).json({ status: 'error', message: 'Username and password required' });
    }

    const [rows] = await db.query(
      'SELECT user_id, name, role, username, password, email FROM users WHERE username = ?',
      [username]
    );

    if (rows.length === 0) {
      return res.status(401).json({ status: 'error', message: 'Invalid username or password' });
    }

    const user = rows[0];
    const ok = await bcrypt.compare(password, user.password);
    if (!ok) {
      return res.status(401).json({ status: 'error', message: 'Invalid username or password' });
    }

    const token = jwt.sign(
      { user_id: user.user_id, name: user.name, role: user.role, username: user.username },
      process.env.JWT_SECRET,
      { expiresIn: process.env.JWT_EXPIRES_IN || '8h' }
    );

    res.json({
      status: 'success',
      data: {
        token,
        user: {
          user_id: user.user_id,
          name: user.name,
          role: user.role,
          username: user.username,
          email: user.email,
        },
      },
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Server error during login' });
  }
});

router.get('/me', async (req, res) => {
  try {
    const header = req.headers.authorization;
    if (!header || !header.startsWith('Bearer ')) {
      return res.status(401).json({ status: 'error', message: 'Login required' });
    }
    const payload = jwt.verify(header.slice(7), process.env.JWT_SECRET);
    const [rows] = await db.query(
      'SELECT user_id, name, role, username, email FROM users WHERE user_id = ?',
      [payload.user_id]
    );
    if (rows.length === 0) {
      return res.status(404).json({ status: 'error', message: 'User not found' });
    }
    res.json({ status: 'success', data: rows[0] });
  } catch {
    res.status(401).json({ status: 'error', message: 'Invalid or expired token' });
  }
});

module.exports = router;
