-- Database schema update to add problem types and configurable categories
-- Run this after the main schema.sql

USE psc_issues;

-- Create urgency levels configuration table
CREATE TABLE IF NOT EXISTS `urgency_levels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `color` VARCHAR(7) DEFAULT '#007bff' COMMENT 'Hex color code for display',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active_order` (`is_active`, `display_order`)
);

-- Create problem categories configuration table
CREATE TABLE IF NOT EXISTS `problem_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `color` VARCHAR(7) DEFAULT '#28a745' COMMENT 'Hex color code for display',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active_order` (`is_active`, `display_order`)
);

-- Add problem_category_id to problems table if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA='psc_issues' AND TABLE_NAME='problems' AND COLUMN_NAME='problem_category_id') = 0,
    'ALTER TABLE `problems` ADD COLUMN `problem_category_id` INT NULL AFTER `urgency_tags`, ADD FOREIGN KEY (`problem_category_id`) REFERENCES `problem_categories`(`id`) ON DELETE SET NULL;',
    'SELECT ''Column problem_category_id already exists'';'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Seed default urgency levels (migrated from existing SET values)
INSERT INTO `urgency_levels` (`name`, `display_order`, `color`) VALUES
('Safety-Critical', 1, '#dc3545'),
('High (Blocks Use)', 2, '#fd7e14'), 
('Medium (Workaround)', 3, '#ffc107'),
('Low (Minor)', 4, '#20c997'),
('Cosmetic', 5, '#6c757d'),
('Monitoring', 6, '#6f42c1');

-- Seed default problem categories
INSERT INTO `problem_categories` (`name`, `description`, `display_order`, `color`) VALUES
('Maintenance', 'Equipment maintenance and repairs', 1, '#007bff'),
('Safety', 'Safety-related issues and hazards', 2, '#dc3545'),
('Staff Issue', 'Human resources and staff-related matters', 3, '#28a745');