-- Migration: add persistent login tokens for "remember me" cookie auth
-- Run once via phpMyAdmin against the live database.

CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `selector` CHAR(24) NOT NULL UNIQUE,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_auth_tokens_user` (`user_id`),
    INDEX `idx_auth_tokens_expires` (`expires_at`)
);
