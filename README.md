# Khan Pharmacy Management System

Beginner-friendly pharmacy app built with **HTML, CSS, JavaScript, PHP and MySQL**. No framework. Run it with XAMPP.

You do **not** need Node.js, npm, Composer, or Laravel.

## Features

- Role-based login: Admin, Pharmacist, Employee
- Medicines, suppliers, inventory, antibiotic limits
- Sales billing with stock decrease and invoice print
- Low-stock and expiry alerts
- Salaries (Admin) and Daily / Monthly sales and P&L reports

## What you need

1. **Git** — [https://git-scm.com/downloads](https://git-scm.com/downloads)
2. **XAMPP** — [https://www.apachefriends.org/](https://www.apachefriends.org/) (Apache + MySQL + PHP + phpMyAdmin)

After installing XAMPP, open **XAMPP Control Panel** and Start **Apache** and **MySQL**. Both should show green.

## Clone from GitHub

Open **Command Prompt** or **Git Bash**, then run:

```bash
cd C:\xampp\htdocs
git clone https://github.com/abdullahazimid/Pharmachy-Managemnet-System.git pharmacy
```

This downloads the project into `C:\xampp\htdocs\pharmacy`.

If the folder already exists, either delete it first or clone with another name:

```bash
cd C:\xampp\htdocs
git clone https://github.com/abdullahazimid/Pharmachy-Managemnet-System.git
```

Then the URL will be `http://localhost/Pharmachy-Managemnet-System/`.

**No other install command is needed.** Do not run `npm install`.

## Database setup

1. Make sure **MySQL** is running in XAMPP
2. Open phpMyAdmin: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
3. Click **Import**
4. Choose file: `C:\xampp\htdocs\pharmacy\database\pharmacy.sql`
5. Click **Go**

This creates database `pharmacy_db` with sample users, medicines, and sales.

If your MySQL `root` password is not empty, open `includes/db.php` and change:

```php
$pass = "";
```

to your password.

## Run the app

Open a browser:

**http://localhost/pharmacy/**

If you cloned with the original folder name, use:

**http://localhost/Pharmachy-Managemnet-System/**

You should see the login page.

## Demo logins (use these to check)

| Role        | Username | Password  |
|-------------|----------|-----------|
| Admin       | admin    | admin123  |
| Pharmacist  | azim    | pharma123 |
| Employee    | ontim    | emp123    |

Wrong password should show an error and stay on login.

## How to check it works

### 1. Admin (`admin` / `admin123`)

- Dashboard shows counts and low-stock / expiry lists
- Sidebar has Users, Medicines, Suppliers, Antibiotics, Inventory, Sales, Reports, Salaries, Invoices, Customers
- Open **Users** — add / edit works; you cannot delete your own account
- Open **Sales** — add a customer name, pick a medicine, click **Add item**, then **Complete sale**
- After sale, stock goes down and an invoice print page opens
- Open **Reports** — Daily and Monthly totals appear from real sales

### 2. Pharmacist (`azim` / `pharma123`)

- Can open Medicines, Inventory, Antibiotics, Sales, Reports
- Cannot open **Users** or **Salaries** (redirects to Dashboard)

### 3. Employee (`ontim` / `emp123`)

- Can open Dashboard, Sales, Invoices, Customers
- Cannot open Medicines, Inventory, Users, Salaries, Reports

### 4. Billing rules

- Selling more than current stock is blocked
- Antibiotic over the allowed limit per sale is blocked
- **Logout** in the sidebar ends the session and goes back to login

## Commands you need (and do not need)

| Task | Command / action |
|------|------------------|
| Clone | `git clone https://github.com/abdullahazimid/Pharmachy-Managemnet-System.git pharmacy` |
| Go to folder | `cd C:\xampp\htdocs\pharmacy` |
| Pull latest | `git pull` |
| Start app | XAMPP → Start Apache + MySQL (no terminal command) |
| Database | phpMyAdmin → Import `database/pharmacy.sql` |
| Open | Browser → `http://localhost/pharmacy/` |

Do **not** run:

```bash
npm install
npm start
composer install
```

Those are for other stacks. This project is only PHP + MySQL.

## If something goes wrong

- **Blank page / 404** — Apache is not started, or the folder is not inside `C:\xampp\htdocs`
- **Database connection failed** — MySQL is not started, or `includes/db.php` password is wrong, or `pharmacy.sql` was not imported
- **Cannot log in with demo users** — import `database/pharmacy.sql` again (it recreates `pharmacy_db`)
- **Port 80 busy** — close Skype / other apps using port 80, or in XAMPP change Apache to port 8080 and open `http://localhost:8080/pharmacy/`

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

## Project structure

```
includes/   db.php, auth.php, header.php, footer.php
css/        style.css
js/         app.js, sales.js
*.php       one page per module
database/   pharmacy.sql
```
