-- SplitMate Migration: Add Selective Expense Splitting
-- Safe to run multiple times (uses IF NOT EXISTS / IF NOT EXISTS checks)

-- Step 1: Add split_type to expenses table
ALTER TABLE `expenses` ADD COLUMN IF NOT EXISTS `split_type` ENUM('everyone','selected') NOT NULL DEFAULT 'everyone' AFTER `payment_method`;

-- Step 2: Create expense_participants table
CREATE TABLE IF NOT EXISTS `expense_participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `expense_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `admin_id` INT NOT NULL,
    `share_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_expense_participant` (`expense_id`, `user_id`),
    INDEX `idx_ep_expense` (`expense_id`),
    INDEX `idx_ep_user` (`user_id`),
    INDEX `idx_ep_admin` (`admin_id`),
    CONSTRAINT `fk_ep_expense` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`expense_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ep_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ep_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Backfill participants for existing 'everyone' expenses
-- Uses current active membership of each expense's admin group
INSERT IGNORE INTO `expense_participants` (`expense_id`, `user_id`, `admin_id`, `share_amount`)
SELECT e.expense_id, u.user_id, e.admin_id,
       ROUND(e.amount / NULLIF((SELECT COUNT(*) FROM users u2 WHERE u2.admin_id = e.admin_id AND u2.status = 'active'), 0), 2)
FROM `expenses` e
JOIN `users` u ON u.admin_id = e.admin_id AND u.status = 'active';

