-- Presswick Sailing Club Issue Reporting System Database Schema

CREATE DATABASE IF NOT EXISTS psc_issues DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE psc_issues;

-- Users table
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('ADMIN', 'USER') DEFAULT 'USER' NOT NULL,
    `must_change_password` BOOLEAN DEFAULT FALSE,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login_at` DATETIME NULL,
    INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`),
    INDEX `idx_active` (`is_active`)
);

-- Problems table
CREATE TABLE `problems` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `details` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `status` ENUM('OPEN', 'RESOLVED') DEFAULT 'OPEN' NOT NULL,
    `reported_by` INT NOT NULL,
    `resolved_by` INT NULL,
    `resolved_at` DATETIME NULL,
    `urgency_tags` SET('Safety-Critical', 'High (Blocks Use)', 'Medium (Workaround)', 'Low (Minor)', 'Cosmetic', 'Monitoring') NOT NULL,
    `image_urls` TEXT NULL,
    FOREIGN KEY (`reported_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_reported_by` (`reported_by`),
    INDEX `idx_urgency_tags` (`urgency_tags`)
);

-- Seed the first admin user
-- Password is 'PSC1'
INSERT INTO `users` (`full_name`, `username`, `password_hash`, `role`, `must_change_password`) VALUES 
('Jamie Briggs', 'jamiebriggs', '$2y$10$XjFfxcaQ4jIu813j50X2jOMou/vnN7Mr52/1Eya3qQ2UBP0MU2p4u', 'ADMIN', TRUE);

-- Notification system tables (added for email notifications feature)
CREATE TABLE IF NOT EXISTS `notification_recipients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `notification_settings` (
    `id` TINYINT(1) PRIMARY KEY DEFAULT 1,
    `urgency_levels` TEXT NULL COMMENT 'Comma separated list of urgency levels that trigger notifications',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO `notification_settings` (`id`, `urgency_levels`) VALUES (1, 'Safety-Critical,High (Blocks Use)');