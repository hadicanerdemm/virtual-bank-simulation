-- TurkPay Admin User Creation Script
-- Run this in phpMyAdmin to create an admin account

-- First, check if admin already exists
SET @admin_id = UUID();

-- Create admin user with password: admin123
-- Password hash generated with password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO users (
    id, 
    email, 
    password, 
    first_name, 
    last_name, 
    phone,
    status, 
    role,
    email_verified_at,
    created_at, 
    updated_at
) VALUES (
    @admin_id,
    'admin@turkpay.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Sistem',
    'Yöneticisi',
    '05001234567',
    'active',
    'admin',
    NOW(),
    NOW(),
    NOW()
) ON DUPLICATE KEY UPDATE role = 'admin';

-- Create TRY wallet for admin
INSERT INTO wallets (id, user_id, currency, balance, available_balance, status, created_at, updated_at)
VALUES (UUID(), @admin_id, 'TRY', 100000.00, 100000.00, 'active', NOW(), NOW());

SELECT 'Admin user created successfully!' AS message;
SELECT 'Email: admin@turkpay.com' AS email;
SELECT 'Password: admin123' AS password;
