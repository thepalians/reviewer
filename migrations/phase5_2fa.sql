-- ============================================
-- Phase 5: Two-Factor Authentication (2FA)
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Two-factor authentication table
CREATE TABLE IF NOT EXISTS two_factor_auth (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    secret_key VARCHAR(32) NOT NULL,
    backup_codes JSON,
    is_enabled TINYINT(1) DEFAULT 0,
    method ENUM('totp', 'sms', 'both') DEFAULT 'totp',
    phone_number VARCHAR(15) DEFAULT NULL,
    last_used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trusted devices table
CREATE TABLE IF NOT EXISTS trusted_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_hash VARCHAR(64) NOT NULL,
    device_name VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_device_hash (device_hash),
    INDEX idx_expires_at (expires_at),
    UNIQUE KEY unique_user_device (user_id, device_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add 2FA columns to admin settings if not exists
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS force_2fa TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_2fa_prompt DATETIME DEFAULT NULL;

-- Create index for better performance
CREATE INDEX idx_force_2fa ON users(force_2fa);
