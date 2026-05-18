-- Maintenance Events Calendar Schema
-- This script creates the maintenance_events table for the admin calendar feature

CREATE TABLE IF NOT EXISTS `maintenance_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME NULL,
    `all_day` TINYINT(1) DEFAULT 0,
    `location` VARCHAR(255) NULL,
    `created_by` INT NOT NULL,
    `assigned_to` INT NULL,
    `status` ENUM('SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT 'SCHEDULED',
    `priority` ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') DEFAULT 'MEDIUM',
    
    -- Recurrence fields
    `is_recurring` TINYINT(1) DEFAULT 0,
    `recurrence_type` ENUM('DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY') NULL,
    `recurrence_interval` INT DEFAULT 1 COMMENT 'Every X days/weeks/months/years',
    `recurrence_end_date` DATE NULL,
    `parent_event_id` INT NULL COMMENT 'For recurring events, points to the original event',
    
    -- File attachments (JSON array of file paths)
    `attachments` TEXT NULL COMMENT 'JSON array of attachment file paths',
    
    -- Metadata
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`parent_event_id`) REFERENCES `maintenance_events`(`id`) ON DELETE CASCADE,
    
    -- Indexes for performance
    INDEX `idx_start_datetime` (`start_datetime`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_assigned_to` (`assigned_to`),
    INDEX `idx_recurring` (`is_recurring`, `parent_event_id`),
    INDEX `idx_active_events` (`is_active`, `start_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add migration tracking
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration_name` VARCHAR(255) NOT NULL UNIQUE,
    `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `migrations` (`migration_name`) VALUES ('maintenance_events_2025_10_30');