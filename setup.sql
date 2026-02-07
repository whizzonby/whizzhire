-- ============================================================
-- Whizz Hire — Database Setup
-- Run this script once to create the database and table.
-- ============================================================

-- 1. Create database
CREATE DATABASE IF NOT EXISTS `whizzhire`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `whizzhire`;

-- 2. Create waitlist table
CREATE TABLE IF NOT EXISTS `waitlist` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `email`       VARCHAR(255)    NOT NULL,
    `type`        ENUM('candidate', 'business') NOT NULL,
    `ip_address`  VARCHAR(45)     DEFAULT NULL,
    `user_agent`  TEXT            DEFAULT NULL,
    `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_email_type` (`email`, `type`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
