const express = require('express');
const db = require('../db');
const { authenticate, requireRole } = require('../middleware/auth');

const router = express.Router();

router.use(authenticate);

router.get('/summary', requireRole('Admin', 'Pharmacist'), async (req, res) => {
  try {
    const type = (req.query.type || 'Daily').toLowerCase() === 'monthly' ? 'Monthly' : 'Daily';

    let rows;
    if (type === 'Daily') {
      [rows] = await db.query(
        `SELECT
           DATE(st.sale_date) AS report_date,
           SUM(st.total_price) AS total_sales_amount,
           SUM(st.quantity_sold * m.purchase_price) AS total_purchase_amount,
           SUM(st.total_price) - SUM(st.quantity_sold * m.purchase_price) AS profit_loss
         FROM sales_transactions st
         JOIN medicines m ON st.medicine_id = m.medicine_id
         GROUP BY DATE(st.sale_date)
         ORDER BY report_date DESC
         LIMIT 60`
      );
    } else {
      [rows] = await db.query(
        `SELECT
           DATE_FORMAT(st.sale_date, '%Y-%m-01') AS report_date,
           SUM(st.total_price) AS total_sales_amount,
           SUM(st.quantity_sold * m.purchase_price) AS total_purchase_amount,
           SUM(st.total_price) - SUM(st.quantity_sold * m.purchase_price) AS profit_loss
         FROM sales_transactions st
         JOIN medicines m ON st.medicine_id = m.medicine_id
         GROUP BY DATE_FORMAT(st.sale_date, '%Y-%m')
         ORDER BY report_date DESC
         LIMIT 24`
      );
    }

    const data = rows.map((r) => ({
      report_type: type,
      report_date: r.report_date,
      total_sales_amount: Number(r.total_sales_amount || 0),
      total_purchase_amount: Number(r.total_purchase_amount || 0),
      profit_loss: Number(r.profit_loss || 0),
    }));

    res.json({ status: 'success', data });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to generate reports' });
  }
});

router.get('/dashboard', requireRole('Admin', 'Pharmacist', 'Employee'), async (req, res) => {
  try {
    const role = req.user.role;
    const [[users]] = await db.query('SELECT COUNT(*) AS c FROM users');
    const [[medicines]] = await db.query('SELECT COUNT(*) AS c FROM medicines');
    const [[stock]] = await db.query('SELECT COALESCE(SUM(quantity_in_stock),0) AS c FROM medicines');
    const [[sales]] = await db.query('SELECT COALESCE(SUM(total_price),0) AS c FROM sales_transactions');
    const [[invoices]] = await db.query('SELECT COUNT(*) AS c FROM invoices');
    const [[customers]] = await db.query('SELECT COUNT(*) AS c FROM customers');

    let profit = 0;
    if (role === 'Admin' || role === 'Pharmacist') {
      const [[p]] = await db.query(
        `SELECT COALESCE(SUM(st.total_price) - SUM(st.quantity_sold * m.purchase_price), 0) AS profit
         FROM sales_transactions st
         JOIN medicines m ON st.medicine_id = m.medicine_id`
      );
      profit = Number(p.profit || 0);
    }

    res.json({
      status: 'success',
      data: {
        users: users.c,
        medicines: medicines.c,
        stock_units: Number(stock.c),
        total_sales: Number(sales.c),
        invoices: invoices.c,
        customers: customers.c,
        profit_loss: profit,
      },
    });
  } catch (err) {
    console.error(err);
    res.status(500).json({ status: 'error', message: 'Failed to load dashboard' });
  }
});

module.exports = router;
