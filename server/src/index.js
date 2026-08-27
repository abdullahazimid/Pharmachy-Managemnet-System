require('dotenv').config();
const express = require('express');
const cors = require('cors');

const authRoutes = require('./routes/auth');
const usersRoutes = require('./routes/users');
const suppliersRoutes = require('./routes/suppliers');
const medicinesRoutes = require('./routes/medicines');
const inventoryRoutes = require('./routes/inventory');
const antibioticsRoutes = require('./routes/antibiotics');
const salesRoutes = require('./routes/sales');
const invoicesRoutes = require('./routes/invoices');
const customersRoutes = require('./routes/customers');
const salariesRoutes = require('./routes/salaries');
const reportsRoutes = require('./routes/reports');
const alertsRoutes = require('./routes/alerts');

const app = express();
const PORT = process.env.PORT || 5000;

app.use(cors());
app.use(express.json());

app.get('/api/health', (req, res) => {
  res.json({ status: 'success', message: 'Khan Pharmacy API running' });
});

app.use('/api/auth', authRoutes);
app.use('/api/users', usersRoutes);
app.use('/api/suppliers', suppliersRoutes);
app.use('/api/medicines', medicinesRoutes);
app.use('/api/inventory', inventoryRoutes);
app.use('/api/antibiotics', antibioticsRoutes);
app.use('/api/sales', salesRoutes);
app.use('/api/invoices', invoicesRoutes);
app.use('/api/customers', customersRoutes);
app.use('/api/salaries', salariesRoutes);
app.use('/api/reports', reportsRoutes);
app.use('/api/alerts', alertsRoutes);

app.use((err, req, res, next) => {
  console.error(err);
  res.status(500).json({ status: 'error', message: 'Unexpected server error' });
});

app.listen(PORT, () => {
  console.log(`Khan Pharmacy API listening on http://localhost:${PORT}`);
});
