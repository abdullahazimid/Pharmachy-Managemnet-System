# Khan Pharmacy Automation System

Beginner-friendly pharmacy management app built with **React + Node.js + MySQL**.

## Features

- Role-based login: **Admin**, **Pharmacist**, **Employee**
- Medicines, suppliers, inventory, antibiotic limits
- Sales billing with stock decrease + invoice
- Low-stock and expiry alerts
- Salaries (Admin) and Daily/Monthly sales & P&L reports

## Requirements

- Node.js 18+
- MySQL (XAMPP / MySQL Server)
- npm

## 1. Database setup

1. Start MySQL (XAMPP Control Panel → Start MySQL).
2. Open phpMyAdmin or MySQL CLI and import:

```bash
mysql -u root < database/pharmacy.sql
```

Or in phpMyAdmin: Import → select `database/pharmacy.sql`.

This creates database `pharmacy_db` with seed data.

## 2. Backend

```bash
cd server
npm install
npm start
```

API runs at `http://localhost:5000`.

Edit `server/.env` if your MySQL password is not empty:

```
DB_PASSWORD=your_password
```

## 3. Frontend

```bash
cd client
npm install
npm run dev
```

Open `http://localhost:5173`.

## Demo logins

| Role        | Username | Password   |
|-------------|----------|------------|
| Admin       | admin    | admin123   |
| Pharmacist  | rafiq    | pharma123  |
| Employee    | tariq    | emp123     |

## Project structure

```
client/     React (Vite) UI
server/     Express REST API
database/   MySQL schema + seed
```

## Role access

| Module              | Admin | Pharmacist | Employee |
|---------------------|:-----:|:----------:|:--------:|
| Dashboard           |  yes  |    yes     |   yes    |
| Users / Salaries    |  yes  |     -      |    -     |
| Medicines / Stock   |  yes  |    yes     |    -     |
| Antibiotics         |  yes  |    yes     |    -     |
| Reports             |  yes  |    yes     |    -     |
| Sales / Invoices    |  yes  |    yes     |   yes    |
| Customers           |  yes  |    yes     |   yes    |
