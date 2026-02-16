-- Phase 2: Referral & Affiliate System Tables
-- Migration file for referral system

-- Table for storing referral relationships
CREATE TABLE IF NOT EXISTS referrals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    referrer_id INT NOT NULL,
    referee_id INT NOT NULL,
    level INT DEFAULT 1,
    status ENUM('pending', 'active', 'inactive') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_referee (referee_id),
    KEY idx_referrer (referrer_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for storing referral earnings
CREATE TABLE IF NOT EXISTS referral_earnings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    from_user_id INT NOT NULL,
    task_id INT,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    level INT NOT NULL,
    status ENUM('pending', 'credited') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    credited_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_user (user_id),
    KEY idx_status (status),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for storing referral commission settings
CREATE TABLE IF NOT EXISTS referral_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level INT NOT NULL UNIQUE,
    commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add referral_code column to users table if not exists
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS referral_code VARCHAR(10) UNIQUE AFTER id,
ADD COLUMN IF NOT EXISTS referred_by INT AFTER referral_code;

-- Add foreign key for referred_by if not exists
ALTER TABLE users 
ADD CONSTRAINT fk_referred_by FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL;

-- Insert default referral commission settings
INSERT INTO referral_settings (level, commission_percent, is_active) VALUES
(1, 10.00, 1),
(2, 5.00, 1),
(3, 2.00, 1)
ON DUPLICATE KEY UPDATE 
    commission_percent = VALUES(commission_percent),
    is_active = VALUES(is_active);

-- Generate referral codes for existing users without one
UPDATE users 
SET referral_code = CONCAT('REF', LPAD(id, 6, '0'))
WHERE referral_code IS NULL OR referral_code = '';
