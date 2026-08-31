-- Khan Pharmacy Management System SQL Database Schema
-- Database Name: pharmacy_db

CREATE DATABASE IF NOT EXISTS `pharmacy_db`;
USE `pharmacy_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `employee_salaries`;
DROP TABLE IF EXISTS `sales_reports`;
DROP TABLE IF EXISTS `sales_transactions`;
DROP TABLE IF EXISTS `stock_inventory`;
DROP TABLE IF EXISTS `antibiotic_list`;
DROP TABLE IF EXISTS `medicines`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table (Admin, Pharmacist, Employee)
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `role` ENUM('Admin', 'Pharmacist', 'Employee') NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_reset_token` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Suppliers Table
CREATE TABLE `suppliers` (
    `supplier_id` INT AUTO_INCREMENT PRIMARY KEY,
    `supplier_name` VARCHAR(100) NOT NULL,
    `contact_no` VARCHAR(20) NOT NULL,
    `address` TEXT NOT NULL,
    `company_name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Medicines Table
CREATE TABLE `medicines` (
    `medicine_id` INT AUTO_INCREMENT PRIMARY KEY,
    `medicine_name` VARCHAR(100) NOT NULL,
    `company_name` VARCHAR(100) NOT NULL,
    `category` ENUM('Antibiotic', 'General') NOT NULL DEFAULT 'General',
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `purchase_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `quantity_in_stock` INT NOT NULL DEFAULT 0,
    `expiry_date` DATE NOT NULL,
    `manufacture_date` DATE NOT NULL,
    `supplier_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_medicine_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`supplier_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Antibiotic List (Restricted Access) Table
CREATE TABLE `antibiotic_list` (
    `antibiotic_id` INT AUTO_INCREMENT PRIMARY KEY,
    `medicine_id` INT NOT NULL,
    `allowed_range_limit` INT NOT NULL COMMENT 'Max units allowed per single prescription/sale',
    `current_stock_level` INT NOT NULL,
    `alert_status` ENUM('Normal', 'Warning', 'Critical') NOT NULL DEFAULT 'Normal',
    CONSTRAINT `fk_antibiotic_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Stock / Inventory Table
CREATE TABLE `stock_inventory` (
    `stock_id` INT AUTO_INCREMENT PRIMARY KEY,
    `medicine_id` INT NOT NULL,
    `quantity_added` INT DEFAULT 0,
    `quantity_sold` INT DEFAULT 0,
    `date_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT DEFAULT NULL,
    CONSTRAINT `fk_stock_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stock_user` FOREIGN KEY (`updated_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Sales / Purchase Transaction Table
CREATE TABLE `sales_transactions` (
    `sales_id` INT AUTO_INCREMENT PRIMARY KEY,
    `medicine_id` INT NOT NULL,
    `quantity_sold` INT NOT NULL,
    `sale_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `total_price` DECIMAL(10, 2) NOT NULL,
    `discount_percentage` DECIMAL(5, 2) DEFAULT 0.00,
    `payment_method` VARCHAR(50) DEFAULT 'Cash',
    `sold_by` INT DEFAULT NULL,
    `invoice_id` INT DEFAULT NULL,
    CONSTRAINT `fk_sales_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sales_user` FOREIGN KEY (`sold_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Sales Report Table (optional cached snapshots)
CREATE TABLE `sales_reports` (
    `report_id` INT AUTO_INCREMENT PRIMARY KEY,
    `report_type` ENUM('Daily', 'Monthly') NOT NULL,
    `total_sales_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `total_purchase_amount` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `profit_loss` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    `report_date` DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Employee Salary Table
CREATE TABLE `employee_salaries` (
    `salary_id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `month` VARCHAR(20) NOT NULL,
    `basic_salary` DECIMAL(10, 2) NOT NULL,
    `sales_linked_bonus` DECIMAL(10, 2) DEFAULT 0.00,
    `total_salary` DECIMAL(10, 2) NOT NULL,
    `payment_date` DATE NOT NULL,
    CONSTRAINT `fk_salary_employee` FOREIGN KEY (`employee_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Customers Table
CREATE TABLE `customers` (
    `customer_id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_name` VARCHAR(100) NOT NULL,
    `contact_no` VARCHAR(20) NOT NULL,
    `purchase_history` TEXT COMMENT 'JSON or text summary of purchases',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Invoice / Money Receipt Table
CREATE TABLE `invoices` (
    `invoice_id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `medicine_details` TEXT NOT NULL COMMENT 'JSON string containing medicine IDs, names, quantities',
    `invoice_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `discount_applied` DECIMAL(10, 2) DEFAULT 0.00,
    `payment_method` VARCHAR(50) DEFAULT 'Cash',
    `generated_by` INT DEFAULT NULL,
    CONSTRAINT `fk_invoice_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`customer_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_invoice_user` FOREIGN KEY (`generated_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Users (passwords: admin123, pharma123, emp123)
INSERT INTO `users` (`user_id`, `name`, `role`, `username`, `password`, `email`) VALUES
(1, 'Admin User', 'Admin', 'admin', '$2y$10$Tepe3xcCWtH0HFCY38LTIOe4k2/KtU9DMvscn1gXsFEc0Vv7yRLHO', 'admin@khanpharmacy.com'),
(2, 'Dr. Rafiq Islam', 'Pharmacist', 'rafiq', '$2y$10$gvAxFVJvIFfRBw8LBvc60eKVjBxS2GL9SWMkN3Eqzt/jb12ttz6bq', 'rafiq@khanpharmacy.com'),
(3, 'Tariq Hassan', 'Employee', 'tariq', '$2y$10$Ca574M2jm2/OZi8//7ExBOdXtIndmDyVYAKZ2OA3CcvaeXqIa3ZOy', 'tariq@khanpharmacy.com');

-- Seed Suppliers
INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_no`, `address`, `company_name`) VALUES
(1, 'Beximco Pharma Ltd', '+8801711001122', '19 Dhanmondi, Dhaka', 'Beximco'),
(2, 'Square Pharmaceuticals', '+8801812345678', 'Square Centre, Uttara, Dhaka', 'Square'),
(3, 'Incepta Pharmaceuticals', '+8801911998877', '40 Shahid Tajuddin Ahmed Sarani, Dhaka', 'Incepta'),
(4, 'Healthcare Pharmaceuticals', '+8801755443322', 'Gulshan-2, Dhaka', 'Healthcare');

-- Seed Medicines (purchase_price ~60% of unit_price for demo P&L)
INSERT INTO `medicines` (`medicine_id`, `medicine_name`, `company_name`, `category`, `unit_price`, `purchase_price`, `quantity_in_stock`, `expiry_date`, `manufacture_date`, `supplier_id`) VALUES
(1, 'Napa Extra', 'Beximco', 'General', 10.00, 6.00, 150, '2027-10-15', '2024-10-15', 1),
(2, 'Seclo 20mg', 'Square', 'General', 12.00, 7.00, 80, '2028-01-10', '2025-01-10', 2),
(3, 'Sergel 20mg', 'Healthcare', 'General', 15.00, 9.00, 200, '2028-05-20', '2025-05-20', 4),
(4, 'Ciprocin 500mg', 'Square', 'Antibiotic', 25.00, 15.00, 45, '2028-06-14', '2025-06-14', 2),
(5, 'Azithrocin 500mg', 'Beximco', 'Antibiotic', 35.00, 21.00, 30, '2027-11-30', '2024-11-30', 1),
(6, 'Fexo 120mg', 'Incepta', 'General', 9.00, 5.00, 110, '2027-03-18', '2024-03-18', 3),
(7, 'Moxaclav 625mg', 'Incepta', 'Antibiotic', 40.00, 24.00, 15, '2027-08-22', '2024-08-22', 3),
(8, 'Entacyd', 'Square', 'General', 85.00, 50.00, 30, '2026-12-01', '2023-12-01', 2);

-- Seed Antibiotic List
INSERT INTO `antibiotic_list` (`antibiotic_id`, `medicine_id`, `allowed_range_limit`, `current_stock_level`, `alert_status`) VALUES
(1, 4, 10, 45, 'Normal'),
(2, 5, 6, 30, 'Warning'),
(3, 7, 5, 15, 'Critical');

-- Seed Stock Inventory
INSERT INTO `stock_inventory` (`stock_id`, `medicine_id`, `quantity_added`, `quantity_sold`, `updated_by`) VALUES
(1, 1, 200, 50, 1),
(2, 2, 100, 20, 2),
(3, 4, 50, 5, 2),
(4, 5, 40, 10, 1);

-- Seed Sales Transactions
INSERT INTO `sales_transactions` (`sales_id`, `medicine_id`, `quantity_sold`, `total_price`, `discount_percentage`, `payment_method`, `sold_by`, `invoice_id`) VALUES
(1, 1, 10, 100.00, 0.00, 'Cash', 3, 1),
(2, 2, 5, 57.00, 5.00, 'Cash', 2, 1),
(3, 4, 2, 50.00, 0.00, 'Cash', 2, 2);

-- Seed Sales Reports
INSERT INTO `sales_reports` (`report_id`, `report_type`, `total_sales_amount`, `total_purchase_amount`, `profit_loss`, `report_date`) VALUES
(1, 'Daily', 1250.00, 800.00, 450.00, '2026-07-22'),
(2, 'Monthly', 38500.00, 24000.00, 14500.00, '2026-07-01');

-- Seed Employee Salaries
INSERT INTO `employee_salaries` (`salary_id`, `employee_id`, `month`, `basic_salary`, `sales_linked_bonus`, `total_salary`, `payment_date`) VALUES
(1, 2, 'July 2026', 25000.00, 3000.00, 28000.00, '2026-07-01'),
(2, 3, 'July 2026', 15000.00, 1500.00, 16500.00, '2026-07-01');

-- Seed Customers
INSERT INTO `customers` (`customer_id`, `customer_name`, `contact_no`, `purchase_history`) VALUES
(1, 'Kabir Ahmed', '+8801700112233', 'Napa Extra (10 units), Seclo 20mg (5 units)'),
(2, 'Salma Begum', '+8801822334455', 'Ciprocin 500mg (2 units)');

-- Seed Invoices
INSERT INTO `invoices` (`invoice_id`, `customer_id`, `customer_name`, `medicine_details`, `total_amount`, `discount_applied`, `payment_method`, `generated_by`) VALUES
(1, 1, 'Kabir Ahmed', '[{"medicine_id": 1, "name": "Napa Extra", "qty": 10, "price": 10}, {"medicine_id": 2, "name": "Seclo 20mg", "qty": 5, "price": 12}]', 157.00, 3.00, 'Cash', 3),
(2, 2, 'Salma Begum', '[{"medicine_id": 4, "name": "Ciprocin 500mg", "qty": 2, "price": 25}]', 50.00, 0.00, 'Cash', 2);
