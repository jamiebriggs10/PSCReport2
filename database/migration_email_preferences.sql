-- Migration for Enhanced Email Notification Preferences
-- This script creates a new table to store individual email notification preferences

-- Create email notification preferences table
CREATE TABLE IF NOT EXISTS `email_notification_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `problem_categories` TEXT NULL COMMENT 'JSON array of problem category IDs this email should receive notifications for',
    `urgency_levels` TEXT NULL COMMENT 'JSON array of urgency levels this email should receive notifications for',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_email` (`email`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_email_active` (`email`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add migration tracking (optional - to track if this migration has been run)
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration_name` VARCHAR(255) NOT NULL UNIQUE,
    `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `migrations` (`migration_name`) VALUES ('email_preferences_2025_10_30');

-- Optional: Migrate existing recipients to the new system with default preferences
-- This will create entries for existing recipients with all categories and urgency levels selected
INSERT IGNORE INTO `email_notification_preferences` (`email`, `problem_categories`, `urgency_levels`)
SELECT 
    nr.email,
    (SELECT JSON_ARRAYAGG(id) FROM problem_categories WHERE is_active = 1),
    '["Safety-Critical","High (Blocks Use)","Medium (Workaround)","Low (Minor)","Cosmetic","Monitoring"]'
FROM notification_recipients nr 
WHERE nr.is_active = 1;