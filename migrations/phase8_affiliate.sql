-- ============================================
-- Phase 8: Affiliate/Partner System
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Affiliates table
CREATE TABLE IF NOT EXISTS affiliates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    affiliate_code VARCHAR(20) NOT NULL UNIQUE,
    tier ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    commission_rate DECIMAL(5,2) DEFAULT 5.00,
    level2_rate DECIMAL(5,2) DEFAULT 2.00,
    level3_rate DECIMAL(5,2) DEFAULT 1.00,
    total_earnings DECIMAL(15,2) DEFAULT 0,
    pending_earnings DECIMAL(15,2) DEFAULT 0,
    total_referrals INT DEFAULT 0,
    status ENUM('pending', 'approved', 'suspended') DEFAULT 'pending',
    approved_by INT,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (affiliate_code),
    INDEX idx_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliate referrals table
CREATE TABLE IF NOT EXISTS affiliate_referrals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    affiliate_id INT NOT NULL,
    referred_user_id INT NOT NULL,
    referral_level TINYINT DEFAULT 1,
    parent_referral_id INT,
    status ENUM('pending', 'active', 'inactive') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_referred (referred_user_id),
    INDEX idx_status (status),
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliate commissions table
CREATE TABLE IF NOT EXISTS affiliate_commissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    affiliate_id INT NOT NULL,
    referral_id INT NOT NULL,
    source_type ENUM('task', 'order', 'subscription') NOT NULL,
    source_id INT NOT NULL,
    level TINYINT DEFAULT 1,
    amount DECIMAL(10,2) NOT NULL,
    rate_applied DECIMAL(5,2),
    status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
    paid_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_status (status),
    INDEX idx_source (source_type, source_id),
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE,
    FOREIGN KEY (referral_id) REFERENCES affiliate_referrals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliate payouts table
CREATE TABLE IF NOT EXISTS affiliate_payouts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    affiliate_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    payment_details JSON,
    transaction_ref VARCHAR(100),
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    processed_by INT,
    processed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_status (status),
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliate links table
CREATE TABLE IF NOT EXISTS affiliate_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    affiliate_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    short_code VARCHAR(20) NOT NULL UNIQUE,
    destination_url TEXT NOT NULL,
    click_count INT DEFAULT 0,
    conversion_count INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_affiliate (affiliate_id),
    INDEX idx_code (short_code),
    INDEX idx_active (is_active),
    FOREIGN KEY (affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
