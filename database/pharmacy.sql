-- Khan Pharmacy Management System SQL Database Schema
-- Database Name: pharmacy_db

CREATE DATABASE IF NOT EXISTS `pharmacy_db`;
USE `pharmacy_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `purchases`;
DROP TABLE IF EXISTS `inventory`;
DROP TABLE IF EXISTS `medicines`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Admin', 'Pharmacist', 'Employee') NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Suppliers Table
CREATE TABLE `suppliers` (
    `supplier_id` INT AUTO_INCREMENT PRIMARY KEY,
    `supplier_name` VARCHAR(100) NOT NULL,
    `contact_number` VARCHAR(20) NOT NULL,
    `company_name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Medicines Table
CREATE TABLE `medicines` (
    `medicine_id` INT AUTO_INCREMENT PRIMARY KEY,
    `medicine_name` VARCHAR(100) NOT NULL,
    `company_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'Medicine',
    `purchase_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `expire_date` DATE NOT NULL,
    `manufacture_date` DATE NOT NULL,
    `supplier_id` INT DEFAULT NULL,
    `sale_price` DECIMAL(10, 2) NOT NULL,
    `batch_number` VARCHAR(50) NOT NULL DEFAULT '',
    CONSTRAINT `fk_medicine_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`supplier_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Inventory Table
CREATE TABLE `inventory` (
    `inventory_id` INT AUTO_INCREMENT PRIMARY KEY,
    `medicine_id` INT NOT NULL UNIQUE,
    `current_stock` INT NOT NULL DEFAULT 0,
    `stock_status` ENUM('Normal', 'Warning', 'Critical') NOT NULL DEFAULT 'Normal',
    `updated_by` INT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inventory_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inventory_user` FOREIGN KEY (`updated_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Purchases Table
CREATE TABLE `purchases` (
    `purchase_id` INT AUTO_INCREMENT PRIMARY KEY,
    `supplier_id` INT NOT NULL,
    `purchase_date` DATE NOT NULL,
    `medicine_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `purchase_price` DECIMAL(10, 2) NOT NULL,
    `total_amount` DECIMAL(12, 2) NOT NULL,
    `purchase_status` ENUM('Pending', 'Received', 'Cancelled') NOT NULL DEFAULT 'Received',
    CONSTRAINT `fk_purchase_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`supplier_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_purchase_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Sales Table
CREATE TABLE `sales` (
    `sale_id` INT AUTO_INCREMENT PRIMARY KEY,
    `medicine_id` INT NOT NULL,
    `quantity_sold` INT NOT NULL,
    `sale_date_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `sold_by` INT DEFAULT NULL,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'Cash',
    CONSTRAINT `fk_sales_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`medicine_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sales_user` FOREIGN KEY (`sold_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Users (passwords: admin123, pharma123, emp123)
INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `email`) VALUES
(1, 'admin', '$2y$10$Tepe3xcCWtH0HFCY38LTIOe4k2/KtU9DMvscn1gXsFEc0Vv7yRLHO', 'Admin', 'admin@khanpharmacy.com'),
(2, 'rafiq', '$2y$10$gvAxFVJvIFfRBw8LBvc60eKVjBxS2GL9SWMkN3Eqzt/jb12ttz6bq', 'Pharmacist', 'rafiq@khanpharmacy.com'),
(3, 'tariq', '$2y$10$Ca574M2jm2/OZi8//7ExBOdXtIndmDyVYAKZ2OA3CcvaeXqIa3ZOy', 'Employee', 'tariq@khanpharmacy.com');

-- Seed Suppliers
INSERT INTO `suppliers` (`supplier_id`, `supplier_name`, `contact_number`, `company_name`) VALUES
(1, 'Beximco Pharma Ltd', '+8801711001122', 'Beximco'),
(2, 'Square Pharmaceuticals', '+8801812345678', 'Square'),
(3, 'Incepta Pharmaceuticals', '+8801911998877', 'Incepta'),
(4, 'Healthcare Pharmaceuticals', '+8801755443322', 'Healthcare');

-- Seed Medicines
INSERT INTO `medicines` (`medicine_id`, `medicine_name`, `company_name`, `category`, `purchase_price`, `expire_date`, `manufacture_date`, `supplier_id`, `sale_price`, `batch_number`) VALUES
(1, 'Napa Extra', 'Beximco', 'Medicine', 6.00, '2027-10-15', '2024-10-15', 1, 10.00, 'BX-NP-2024-001'),
(2, 'Seclo 20mg', 'Square', 'Medicine', 7.00, '2028-01-10', '2025-01-10', 2, 12.00, 'SQ-SC-2025-002'),
(3, 'Sergel 20mg', 'Healthcare', 'Medicine', 9.00, '2028-05-20', '2025-05-20', 4, 15.00, 'HC-SG-2025-003'),
(4, 'Ciprocin 500mg', 'Square', 'Antibiotics', 15.00, '2028-06-14', '2025-06-14', 2, 25.00, 'SQ-CP-2025-004'),
(5, 'Azithrocin 500mg', 'Beximco', 'Antibiotics', 21.00, '2027-11-30', '2024-11-30', 1, 35.00, 'BX-AZ-2024-005'),
(6, 'Fexo 120mg', 'Incepta', 'Medicine', 5.00, '2027-03-18', '2024-03-18', 3, 9.00, 'IN-FX-2024-006'),
(7, 'Moxaclav 625mg', 'Incepta', 'Antibiotics', 24.00, '2027-08-22', '2024-08-22', 3, 40.00, 'IN-MX-2024-007'),
(8, 'Entacyd', 'Square', 'Medicine', 50.00, '2026-12-01', '2023-12-01', 2, 85.00, 'SQ-EN-2023-008');

-- Seed Inventory
INSERT INTO `inventory` (`inventory_id`, `medicine_id`, `current_stock`, `stock_status`, `updated_by`) VALUES
(1, 1, 150, 'Normal', 1),
(2, 2, 80, 'Normal', 2),
(3, 3, 200, 'Normal', 2),
(4, 4, 45, 'Normal', 2),
(5, 5, 30, 'Warning', 1),
(6, 6, 110, 'Normal', 2),
(7, 7, 15, 'Critical', 1),
(8, 8, 30, 'Warning', 2);

-- Seed Purchases
INSERT INTO `purchases` (`purchase_id`, `supplier_id`, `purchase_date`, `medicine_id`, `quantity`, `purchase_price`, `total_amount`, `purchase_status`) VALUES
(1, 1, '2026-07-01', 1, 200, 6.00, 1200.00, 'Received'),
(2, 2, '2026-07-05', 2, 100, 7.00, 700.00, 'Received'),
(3, 2, '2026-07-10', 4, 50, 15.00, 750.00, 'Received'),
(4, 1, '2026-07-15', 5, 40, 21.00, 840.00, 'Received');

-- Seed Sales
INSERT INTO `sales` (`sale_id`, `medicine_id`, `quantity_sold`, `sale_date_time`, `sold_by`, `unit_price`, `total_amount`, `payment_method`) VALUES
(1, 1, 10, '2026-07-20 10:30:00', 3, 10.00, 100.00, 'Cash'),
(2, 2, 5, '2026-07-20 10:30:00', 2, 12.00, 60.00, 'Cash'),
(3, 4, 2, '2026-07-21 14:15:00', 2, 25.00, 50.00, 'Cash');
