-- TurkPay Database Schema
-- Professional Virtual Bank and Payment Gateway Simulation
-- Double-Entry Bookkeeping System

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+03:00";

-- --------------------------------------------------------
-- Database creation
-- --------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `turkpay` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `turkpay`;

-- --------------------------------------------------------
-- Users Table - Bank customers
-- --------------------------------------------------------
CREATE TABLE `users` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `identity_number` VARCHAR(11) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `two_factor_enabled` TINYINT(1) DEFAULT 0,
    `two_factor_secret` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'suspended', 'locked', 'pending') DEFAULT 'pending',
    `role` ENUM('user', 'admin', 'super_admin') DEFAULT 'user',
    `last_login_at` TIMESTAMP NULL DEFAULT NULL,
    `last_login_ip` VARCHAR(45) DEFAULT NULL,
    `failed_login_attempts` INT DEFAULT 0,
    `locked_until` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Merchants Table - Payment gateway users
-- --------------------------------------------------------
CREATE TABLE `merchants` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `user_id` CHAR(36) NOT NULL,
    `business_name` VARCHAR(255) NOT NULL,
    `business_type` ENUM('individual', 'company') DEFAULT 'individual',
    `tax_number` VARCHAR(20) DEFAULT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `logo` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `api_key` VARCHAR(64) NOT NULL UNIQUE,
    `api_secret` VARCHAR(128) NOT NULL,
    `webhook_url` VARCHAR(500) DEFAULT NULL,
    `webhook_secret` VARCHAR(128) DEFAULT NULL,
    `is_sandbox` TINYINT(1) DEFAULT 1,
    `status` ENUM('active', 'suspended', 'pending', 'rejected') DEFAULT 'pending',
    `daily_limit` DECIMAL(15,2) DEFAULT 100000.00,
    `monthly_limit` DECIMAL(15,2) DEFAULT 1000000.00,
    `commission_rate` DECIMAL(5,4) DEFAULT 0.0250,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_api_key` (`api_key`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Wallets Table - Multi-currency wallets
-- --------------------------------------------------------
CREATE TABLE `wallets` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `user_id` CHAR(36) NOT NULL,
    `currency` ENUM('TRY', 'USD', 'EUR') NOT NULL,
    `balance` DECIMAL(15,2) DEFAULT 0.00,
    `available_balance` DECIMAL(15,2) DEFAULT 0.00,
    `pending_balance` DECIMAL(15,2) DEFAULT 0.00,
    `is_default` TINYINT(1) DEFAULT 0,
    `status` ENUM('active', 'frozen', 'closed') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_currency` (`user_id`, `currency`),
    INDEX `idx_currency` (`currency`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Virtual Cards Table
-- --------------------------------------------------------
CREATE TABLE `virtual_cards` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `user_id` CHAR(36) NOT NULL,
    `wallet_id` CHAR(36) NOT NULL,
    `card_number` VARCHAR(16) NOT NULL,
    `card_holder_name` VARCHAR(100) NOT NULL,
    `expiry_month` CHAR(2) NOT NULL,
    `expiry_year` CHAR(4) NOT NULL,
    `cvv` VARCHAR(255) NOT NULL,
    `card_type` ENUM('visa', 'mastercard') DEFAULT 'visa',
    `card_brand` ENUM('debit', 'credit', 'prepaid') DEFAULT 'debit',
    `spending_limit` DECIMAL(15,2) DEFAULT 10000.00,
    `daily_limit` DECIMAL(15,2) DEFAULT 5000.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `is_online_enabled` TINYINT(1) DEFAULT 1,
    `is_contactless_enabled` TINYINT(1) DEFAULT 1,
    `pin_hash` VARCHAR(255) DEFAULT NULL,
    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`wallet_id`) REFERENCES `wallets`(`id`) ON DELETE CASCADE,
    INDEX `idx_card_number` (`card_number`),
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Transactions Table - Double-Entry Bookkeeping
-- --------------------------------------------------------
CREATE TABLE `transactions` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `reference_id` VARCHAR(32) NOT NULL UNIQUE,
    `idempotency_key` VARCHAR(64) DEFAULT NULL UNIQUE,
    `type` ENUM('transfer', 'deposit', 'withdrawal', 'payment', 'refund', 'fee', 'exchange', 'reversal') NOT NULL,
    `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'requires_approval') DEFAULT 'pending',
    `source_wallet_id` CHAR(36) DEFAULT NULL,
    `destination_wallet_id` CHAR(36) DEFAULT NULL,
    `source_user_id` CHAR(36) DEFAULT NULL,
    `destination_user_id` CHAR(36) DEFAULT NULL,
    `merchant_id` CHAR(36) DEFAULT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `fee` DECIMAL(15,2) DEFAULT 0.00,
    `currency` ENUM('TRY', 'USD', 'EUR') NOT NULL,
    `exchange_rate` DECIMAL(10,6) DEFAULT NULL,
    `converted_amount` DECIMAL(15,2) DEFAULT NULL,
    `converted_currency` ENUM('TRY', 'USD', 'EUR') DEFAULT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `metadata` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `approved_by` CHAR(36) DEFAULT NULL,
    `approved_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `failed_at` TIMESTAMP NULL DEFAULT NULL,
    `failure_reason` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`source_wallet_id`) REFERENCES `wallets`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`destination_wallet_id`) REFERENCES `wallets`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`source_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`destination_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_reference_id` (`reference_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`type`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_source_user` (`source_user_id`),
    INDEX `idx_destination_user` (`destination_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Ledger Entries Table - Double-Entry Records
-- --------------------------------------------------------
CREATE TABLE `ledger_entries` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `transaction_id` CHAR(36) NOT NULL,
    `wallet_id` CHAR(36) NOT NULL,
    `entry_type` ENUM('debit', 'credit') NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `balance_before` DECIMAL(15,2) NOT NULL,
    `balance_after` DECIMAL(15,2) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`wallet_id`) REFERENCES `wallets`(`id`) ON DELETE CASCADE,
    INDEX `idx_transaction_id` (`transaction_id`),
    INDEX `idx_wallet_id` (`wallet_id`),
    INDEX `idx_entry_type` (`entry_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Payment Sessions Table - For 3D Secure flow
-- --------------------------------------------------------
CREATE TABLE `payment_sessions` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `merchant_id` CHAR(36) NOT NULL,
    `transaction_id` CHAR(36) DEFAULT NULL,
    `session_token` VARCHAR(128) NOT NULL UNIQUE,
    `amount` DECIMAL(15,2) NOT NULL,
    `currency` ENUM('TRY', 'USD', 'EUR') DEFAULT 'TRY',
    `order_id` VARCHAR(100) DEFAULT NULL,
    `customer_email` VARCHAR(255) DEFAULT NULL,
    `customer_name` VARCHAR(200) DEFAULT NULL,
    `return_url` VARCHAR(500) NOT NULL,
    `cancel_url` VARCHAR(500) DEFAULT NULL,
    `callback_url` VARCHAR(500) DEFAULT NULL,
    `status` ENUM('created', 'pending_3d', 'completed', 'failed', 'expired', 'cancelled') DEFAULT 'created',
    `card_last_four` CHAR(4) DEFAULT NULL,
    `card_type` VARCHAR(20) DEFAULT NULL,
    `otp_code` CHAR(6) DEFAULT NULL,
    `otp_attempts` INT DEFAULT 0,
    `otp_expires_at` TIMESTAMP NULL DEFAULT NULL,
    `metadata` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE SET NULL,
    INDEX `idx_session_token` (`session_token`),
    INDEX `idx_status` (`status`),
    INDEX `idx_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Webhooks Table - Webhook configurations
-- --------------------------------------------------------
CREATE TABLE `webhooks` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `merchant_id` CHAR(36) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `secret` VARCHAR(128) NOT NULL,
    `events` JSON NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE,
    INDEX `idx_merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Webhook Logs Table
-- --------------------------------------------------------
CREATE TABLE `webhook_logs` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `webhook_id` CHAR(36) NOT NULL,
    `transaction_id` CHAR(36) DEFAULT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `payload` JSON NOT NULL,
    `response_code` INT DEFAULT NULL,
    `response_body` TEXT DEFAULT NULL,
    `attempts` INT DEFAULT 1,
    `status` ENUM('pending', 'sent', 'failed', 'retrying') DEFAULT 'pending',
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    `next_retry_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`webhook_id`) REFERENCES `webhooks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_webhook_id` (`webhook_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Jobs Table - Async Queue System
-- --------------------------------------------------------
CREATE TABLE `jobs` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `type` ENUM('email', 'webhook', 'notification', 'report', 'cleanup') NOT NULL,
    `payload` JSON NOT NULL,
    `priority` TINYINT DEFAULT 5,
    `attempts` INT DEFAULT 0,
    `max_attempts` INT DEFAULT 3,
    `status` ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    `scheduled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status_scheduled` (`status`, `scheduled_at`),
    INDEX `idx_type` (`type`),
    INDEX `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Audit Logs Table - Security and compliance
-- --------------------------------------------------------
CREATE TABLE `audit_logs` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `user_id` CHAR(36) DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` CHAR(36) DEFAULT NULL,
    `old_values` JSON DEFAULT NULL,
    `new_values` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `session_id` VARCHAR(128) DEFAULT NULL,
    `risk_level` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    `metadata` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_risk_level` (`risk_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Login Attempts Table - Brute force protection
-- --------------------------------------------------------
CREATE TABLE `login_attempts` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `is_successful` TINYINT(1) DEFAULT 0,
    `failure_reason` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_ip` (`email`, `ip_address`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Exchange Rates Table
-- --------------------------------------------------------
CREATE TABLE `exchange_rates` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `base_currency` ENUM('TRY', 'USD', 'EUR') NOT NULL,
    `target_currency` ENUM('TRY', 'USD', 'EUR') NOT NULL,
    `rate` DECIMAL(10,6) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `effective_from` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `effective_until` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_rate` (`base_currency`, `target_currency`, `is_active`),
    INDEX `idx_currencies` (`base_currency`, `target_currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Notifications Table
-- --------------------------------------------------------
CREATE TABLE `notifications` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `user_id` CHAR(36) NOT NULL,
    `type` ENUM('transaction', 'security', 'system', 'promotion') NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `icon` VARCHAR(50) DEFAULT NULL,
    `action_url` VARCHAR(500) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_unread` (`user_id`, `is_read`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Sessions Table - Custom session handling
-- --------------------------------------------------------
CREATE TABLE `sessions` (
    `id` VARCHAR(128) NOT NULL PRIMARY KEY,
    `user_id` CHAR(36) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `payload` TEXT NOT NULL,
    `last_activity` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- System Vault - Internal bank account for double-entry
-- --------------------------------------------------------
CREATE TABLE `system_vault` (
    `id` CHAR(36) NOT NULL PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `currency` ENUM('TRY', 'USD', 'EUR') NOT NULL,
    `balance` DECIMAL(20,2) DEFAULT 0.00,
    `type` ENUM('main', 'fee', 'reserve', 'promotion') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_vault` (`type`, `currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Insert Default Data
-- --------------------------------------------------------

-- System Vaults (for double-entry bookkeeping)
INSERT INTO `system_vault` (`id`, `name`, `currency`, `balance`, `type`) VALUES
(UUID(), 'Ana Kasa - TRY', 'TRY', 10000000.00, 'main'),
(UUID(), 'Ana Kasa - USD', 'USD', 500000.00, 'main'),
(UUID(), 'Ana Kasa - EUR', 'EUR', 400000.00, 'main'),
(UUID(), 'Komisyon Havuzu - TRY', 'TRY', 0.00, 'fee'),
(UUID(), 'Komisyon Havuzu - USD', 'USD', 0.00, 'fee'),
(UUID(), 'Komisyon Havuzu - EUR', 'EUR', 0.00, 'fee');

-- Default Exchange Rates
INSERT INTO `exchange_rates` (`id`, `base_currency`, `target_currency`, `rate`) VALUES
(UUID(), 'USD', 'TRY', 32.500000),
(UUID(), 'EUR', 'TRY', 35.200000),
(UUID(), 'TRY', 'USD', 0.030769),
(UUID(), 'TRY', 'EUR', 0.028409),
(UUID(), 'USD', 'EUR', 0.920000),
(UUID(), 'EUR', 'USD', 1.086957);

-- Admin User (password: admin123)
INSERT INTO `users` (`id`, `email`, `password`, `first_name`, `last_name`, `phone`, `status`, `role`, `email_verified_at`) VALUES
(UUID(), 'admin@turkpay.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4b6LyAQzKJXjDi.G', 'Admin', 'TurkPay', '+905001234567', 'active', 'super_admin', NOW());

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
