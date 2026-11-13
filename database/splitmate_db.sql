-- ========================================================
-- SplitMate Database Schema & Seed Data
-- ========================================================

CREATE DATABASE IF NOT EXISTS `splitmate_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `splitmate_db`;

-- 1. Admins Table
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `settlements`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `otp_verifications`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `categories`;

CREATE TABLE `admins` (
    `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Table
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `email_verified` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Categories Table
CREATE TABLE `categories` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `icon` VARCHAR(50) DEFAULT 'fa-tags',
    `color` VARCHAR(20) DEFAULT '#4f46e5'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Expenses Table
CREATE TABLE `expenses` (
    `expense_id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `expense_date` DATE NOT NULL,
    `expense_time` TIME NOT NULL,
    `payment_method` ENUM('Cash', 'UPI', 'Card', 'Net Banking', 'Other') DEFAULT 'UPI',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_expense_admin` (`admin_id`),
    INDEX `idx_expense_user` (`user_id`),
    INDEX `idx_expense_date` (`expense_date`),
    CONSTRAINT `fk_expenses_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_expenses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. OTP Verifications Table
CREATE TABLE `otp_verifications` (
    `otp_id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `otp_hash` VARCHAR(255) NOT NULL,
    `plain_otp_dev` VARCHAR(10) NULL,
    `attempts` INT DEFAULT 0,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_otp_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Settlements Table
CREATE TABLE `settlements` (
    `settlement_id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NOT NULL,
    `payer_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `amount` DECIMAL(12, 2) NOT NULL,
    `period_start` DATE NULL,
    `period_end` DATE NULL,
    `status` ENUM('pending', 'paid') DEFAULT 'pending',
    `paid_at` DATETIME NULL,
    `confirmed_by` VARCHAR(100) NULL,
    `payment_method` VARCHAR(50) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_settlement_admin` (`admin_id`),
    INDEX `idx_settlement_payer` (`payer_id`),
    INDEX `idx_settlement_receiver` (`receiver_id`),
    INDEX `idx_settlement_status` (`status`),
    CONSTRAINT `fk_settlements_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_settlements_payer` FOREIGN KEY (`payer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_settlements_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Activity Logs Table
CREATE TABLE `activity_logs` (
    `log_id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NOT NULL,
    `user_id` INT NULL,
    `activity` TEXT NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_admin` (`admin_id`),
    INDEX `idx_log_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- Seed Data
-- ========================================================

-- Categories
INSERT INTO `categories` (`name`, `icon`, `color`) VALUES
('Food & Dining', 'fa-utensils', '#f59e0b'),
('Transportation', 'fa-car', '#3b82f6'),
('Rent & Housing', 'fa-house', '#8b5cf6'),
('Groceries', 'fa-basket-shopping', '#10b981'),
('Utilities & Bills', 'fa-bolt', '#eab308'),
('Entertainment', 'fa-film', '#ec4899'),
('Shopping', 'fa-bag-shopping', '#06b6d4'),
('Health & Medical', 'fa-heart-pulse', '#ef4444'),
('Other', 'fa-receipt', '#64748b');

-- Admins (Password is: Password@123)
-- Admin 1: Avadh Narola (Admin ID: 1)
-- Admin 2: Vikram Malhotra (Admin ID: 2)
INSERT INTO `admins` (`admin_id`, `full_name`, `email`, `phone`, `password`, `status`) VALUES
(1, 'Avadh Narola', 'admin@splitmate.com', '+91 9876543210', '$2y$10$J.n33hhdR8luodkA3eODLOT8uMiuv8Eu65W6ZF59AEwpmIA2wFLDy', 'active'),
(2, 'Vikram Malhotra', 'vikram@splitmate.com', '+91 9876543211', '$2y$10$J.n33hhdR8luodkA3eODLOT8uMiuv8Eu65W6ZF59AEwpmIA2wFLDy', 'active');

-- Users under Admin 1 (Avadh Narola Group - 5 members matching the prompt example!)
-- User 1: Avadh (Member)
-- User 2: Rahul Sharma
-- User 3: Priya Patel
-- User 4: Rohan Verma
-- User 5: Sneha Mehta
INSERT INTO `users` (`user_id`, `admin_id`, `full_name`, `email`, `phone`, `password`, `status`, `email_verified`) VALUES
(1, 1, 'Avadh', 'avadh@gmail.com', '+91 9800000001', '$2y$10$J.n33hhdR8luodkA3eODLOT8uMiuv8Eu65W6ZF59AEwpmIA2wFLDy', 'active', 1),
(2, 1, 'User 2 (Rahul)', 'user2@gmail.com', '+91 9800000002', '$2y$10$J.n33hhdR8luodkA3eODLOT8uMiuv8Eu65W6ZF59AEwpmIA2wFLDy', 'active', 1),
(3, 1, 'User 3 (Priya)', 'user3@gmail.com', '+91 9800000003', '$2y$10$J.n33hhdR8luodkA3eODLOT8uMiuv8Eu65W6ZF59AEwpmIA2wFLDy', 'active', 1),
(4, 1, 'User 4 (Rohan)', 'user4@gmail.com', '+91 9800000004', '$2y$10$J.n33hhdR8luodkA3eODLOT8uMiuv8Eu65W6ZF59AEwpmIA2wFLDy', 'active', 1),
(5, 1, 'User 5 (Sneha)', 'user5@gmail.com', '+91 9800000005', '$2y$10$J.n33hhdR8luodkA3eODLOT8uMiuv8Eu65W6ZF59AEwpmIA2wFLDy', 'active', 1);

-- Users under Admin 2 (Vikram Malhotra Group - 3 members)
-- User A: Amit Kumar
-- User B: Neha Singh
-- User C: Rajesh Gupta
INSERT INTO `users` (`user_id`, `admin_id`, `full_name`, `email`, `phone`, `password`, `status`, `email_verified`) VALUES
(6, 2, 'User A (Amit)', 'usera@gmail.com', '+91 9700000001', '$2y$10$J.n33hhdR8luodkA3eODLOT8uMiuv8Eu65W6ZF59AEwpmIA2wFLDy', 'active', 1),
(7, 2, 'User B (Neha)', 'userb@gmail.com', '+91 9700000002', '$2y$10$J.n33hhdR8luodkA3eODLOT8uMiuv8Eu65W6ZF59AEwpmIA2wFLDy', 'active', 1),
(8, 2, 'User C (Rajesh)', 'userc@gmail.com', '+91 9700000003', '$2y$10$J.n33hhdR8luodkA3eODLOT8uMiuv8Eu65W6ZF59AEwpmIA2wFLDy', 'active', 1);

-- Sample Expenses for Admin 1 (Matching the exact prompt example):
-- Avadh spent: ₹1,000
-- User 2 spent: ₹1,500
-- User 3 spent: ₹0
-- User 4 spent: ₹0
-- User 5 spent: ₹0
-- Total: ₹2,500 across 5 members => Fair share = ₹500
INSERT INTO `expenses` (`expense_id`, `admin_id`, `user_id`, `title`, `description`, `amount`, `category`, `expense_date`, `expense_time`, `payment_method`) VALUES
(1, 1, 1, 'Weekend Grocery Shopping', 'Vegetables, fruits and dairy for the apartment', 1000.00, 'Groceries', '2026-11-10', '10:30:00', 'UPI'),
(2, 1, 2, 'Team Dinner', 'Dinner at spice grill with friends', 1500.00, 'Food & Dining', '2026-11-12', '20:45:00', 'UPI');

-- Sample Expenses for Admin 2 (Completely isolated group):
-- Amit spent: ₹3,000
-- Neha spent: ₹600
-- Rajesh spent: ₹0
INSERT INTO `expenses` (`expense_id`, `admin_id`, `user_id`, `title`, `description`, `amount`, `category`, `expense_date`, `expense_time`, `payment_method`) VALUES
(3, 2, 6, 'Office Internet & WiFi Router', 'Monthly high speed fiber subscription and mesh router', 3000.00, 'Utilities & Bills', '2026-11-08', '14:00:00', 'Card'),
(4, 2, 7, 'Coffee & Snacks', 'Evening refreshers for client discussion', 600.00, 'Food & Dining', '2026-11-11', '16:30:00', 'Cash');

-- Sample Initial Settlement for Admin 1 (as generated by greedy algorithm):
-- User 3 pays Avadh ₹500
-- User 4 pays User 2 ₹500
-- User 5 pays User 2 ₹500
INSERT INTO `settlements` (`settlement_id`, `admin_id`, `payer_id`, `receiver_id`, `amount`, `period_start`, `period_end`, `status`, `paid_at`, `confirmed_by`, `payment_method`, `notes`) VALUES
(1, 1, 3, 1, 500.00, '2026-11-01', '2026-11-30', 'pending', NULL, NULL, NULL, 'Settlement for November 2026'),
(2, 1, 4, 2, 500.00, '2026-11-01', '2026-11-30', 'pending', NULL, NULL, NULL, 'Settlement for November 2026'),
(3, 1, 5, 2, 500.00, '2026-11-01', '2026-11-30', 'pending', NULL, NULL, NULL, 'Settlement for November 2026');

-- Initial Activity Logs
INSERT INTO `activity_logs` (`admin_id`, `user_id`, `activity`, `ip_address`) VALUES
(1, 1, 'Expense "Weekend Grocery Shopping" of ₹1,000.00 added by Avadh', '127.0.0.1'),
(1, 2, 'Expense "Team Dinner" of ₹1,500.00 added by User 2 (Rahul)', '127.0.0.1'),
(2, 6, 'Expense "Office Internet & WiFi Router" of ₹3,000.00 added by User A (Amit)', '127.0.0.1'),
(2, 7, 'Expense "Coffee & Snacks" of ₹600.00 added by User B (Neha)', '127.0.0.1');

