-- Migration: Create resolution history system
-- This creates a new resolutions table to track complete resolution/reopening history
-- and removes resolution-specific fields from the problems table

USE u798276650_psc;

-- Create the new resolutions table
CREATE TABLE IF NOT EXISTS `resolutions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `problem_id` INT NOT NULL,
    `action` ENUM('RESOLVE', 'REOPEN') NOT NULL,
    `action_by` INT NOT NULL,
    `action_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL,
    `attachments` TEXT NULL COMMENT 'JSON array of attachment file information',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`problem_id`) REFERENCES `problems`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`action_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_problem_id` (`problem_id`),
    INDEX `idx_action_at` (`action_at`),
    INDEX `idx_action_by` (`action_by`),
    INDEX `idx_action` (`action`)
);

-- Migrate existing resolution data to the new table
-- Only migrate problems that have been resolved (have resolved_by set)
INSERT INTO `resolutions` (`problem_id`, `action`, `action_by`, `action_at`, `notes`, `attachments`)
SELECT 
    `id` as `problem_id`,
    'RESOLVE' as `action`,
    `resolved_by` as `action_by`,
    `resolved_at` as `action_at`,
    `resolution_notes` as `notes`,
    `resolution_attachments` as `attachments`
FROM `problems` 
WHERE `resolved_by` IS NOT NULL AND `resolved_at` IS NOT NULL;

-- Add a backup table with current data before dropping columns
CREATE TABLE IF NOT EXISTS `problems_backup_before_resolution_migration` AS SELECT * FROM `problems`;

-- Remove resolution-specific columns from problems table
-- Keep status field as it represents current state
ALTER TABLE `problems` 
    DROP COLUMN `resolved_by`,
    DROP COLUMN `resolved_at`, 
    DROP COLUMN `resolution_notes`,
    DROP COLUMN `resolution_attachments`;

-- Add a comment to track migration
ALTER TABLE `problems` COMMENT = 'Updated for resolution history system - resolution data moved to resolutions table';