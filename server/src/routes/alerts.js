const express = require('express');
const db = require('../db');
const { authenticate, requireRole } = require('../middleware/auth');

const router = express.Router();

router.use(authenticate);
router.use(requireRole('Admin', 'Pharmacist'));

router.get('/', async (req, res) => {
  try {
    const threshold = Number(process.env.LOW_STOCK_THRESHOLD || 20);
    const days = Number(process.env.EXPIRY_ALERT_DAYS || 30);

    const [lowStock] = await db.query(
      `SELECT medicine_id, medicine_name, quantity_in_stock, category, expiry_date
       FROM medicines
       WHERE quantity_in_stock < ?
       ORDER BY quantity_in_stock ASC`,
      [threshold]
    );

    const [nearExpiry] = await db.query(
      `SELECT medicine_id, medicine_name, quantity_in_stock, category, expiry_date
       FROM medicines
       WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
       ORDER BY expiry_date ASC`,
      [days]
    );

    res.json({
      status: 'success',
      data: {
        low_stock: lowStock,
        near_expiry: nearExpiry,
        low_stock_threshold: threshold,
        expiry_alert_days: days,
      },
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to fetch alerts' });
  }
});

module.exports = router;
