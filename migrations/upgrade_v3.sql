-- ============================================
-- ReviewFlow SaaS Platform Upgrade v3.0
-- Database Migration Script
-- ============================================

-- Run this script to upgrade from v2.0 to v3.0
-- WARNING: This will add new tables and modify existing ones
-- BACKUP YOUR DATABASE BEFORE RUNNING THIS SCRIPT

-- IMPORTANT: Change 'reviewflow' below to your actual database name if different
USE reviewflow;

-- ============================================
-- 1. SELLER MODULE TABLES
-- ============================================

-- Sellers table
CREATE TABLE IF NOT EXISTS sellers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    mobile VARCHAR(15) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    company_name VARCHAR(200),
    gst_number VARCHAR(20),
    billing_address TEXT,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seller wallet
CREATE TABLE IF NOT EXISTS seller_wallet (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0,
    total_spent DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE,
    INDEX idx_seller_id (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Review requests from sellers
CREATE TABLE IF NOT EXISTS review_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    product_link TEXT NOT NULL,
    product_name VARCHAR(255),
    product_price DECIMAL(10,2) NOT NULL,
    brand_name VARCHAR(100),
    platform ENUM('amazon', 'flipkart', 'other') DEFAULT 'amazon',
    reviews_needed INT NOT NULL,
    reviews_completed INT DEFAULT 0,
    admin_commission DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    gst_amount DECIMAL(10,2) DEFAULT 0,
    grand_total DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_id VARCHAR(100),
    payment_method VARCHAR(50),
    admin_status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE,
    INDEX idx_seller_status (seller_id, admin_status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_admin_status (admin_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. PAYMENT GATEWAY TABLES
-- ============================================

-- Payment transactions
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    review_request_id INT,
    amount DECIMAL(10,2) NOT NULL,
    gst_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_gateway ENUM('razorpay', 'payumoney') NOT NULL,
    gateway_order_id VARCHAR(100),
    gateway_payment_id VARCHAR(100),
    gateway_signature VARCHAR(255),
    status ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending',
    response_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE,
    FOREIGN KEY (review_request_id) REFERENCES review_requests(id) ON DELETE SET NULL,
    INDEX idx_seller_id (seller_id),
    INDEX idx_status (status),
    INDEX idx_gateway_order_id (gateway_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. GST BILLING TABLES
-- ============================================

-- GST settings
CREATE TABLE IF NOT EXISTS gst_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gst_number VARCHAR(20) NOT NULL,
    legal_name VARCHAR(200) NOT NULL,
    registered_address TEXT NOT NULL,
    state_code VARCHAR(5) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tax invoices
CREATE TABLE IF NOT EXISTS tax_invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    seller_id INT NOT NULL,
    review_request_id INT NOT NULL,
    payment_transaction_id INT,
    
    -- Seller details
    seller_gst VARCHAR(20),
    seller_legal_name VARCHAR(200),
    seller_address TEXT,
    
    -- Admin/Platform details
    platform_gst VARCHAR(20),
    platform_legal_name VARCHAR(200),
    platform_address TEXT,
    
    -- Amounts
    base_amount DECIMAL(10,2) NOT NULL,
    cgst_amount DECIMAL(10,2) DEFAULT 0,
    sgst_amount DECIMAL(10,2) DEFAULT 0,
    igst_amount DECIMAL(10,2) DEFAULT 0,
    total_gst DECIMAL(10,2) DEFAULT 0,
    grand_total DECIMAL(10,2) NOT NULL,
    
    sac_code VARCHAR(10) DEFAULT '998371',
    invoice_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE,
    FOREIGN KEY (review_request_id) REFERENCES review_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions(id) ON DELETE SET NULL,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_seller_id (seller_id),
    INDEX idx_invoice_date (invoice_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. REVIEWER TIER SYSTEM
-- ============================================

-- Reviewer tiers
CREATE TABLE IF NOT EXISTS reviewer_tiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tier_name VARCHAR(50) NOT NULL,
    tier_code VARCHAR(20) UNIQUE NOT NULL,
    min_points INT NOT NULL,
    max_points INT,
    daily_task_limit INT DEFAULT 2,
    commission_multiplier DECIMAL(3,2) DEFAULT 1.00,
    priority_level INT DEFAULT 0,
    max_withdrawal DECIMAL(10,2) DEFAULT 500,
    color_code VARCHAR(7) DEFAULT '#CD7F32',
    icon VARCHAR(50) DEFAULT '🥉',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tier_code (tier_code),
    INDEX idx_points_range (min_points, max_points)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default tiers
INSERT INTO reviewer_tiers (tier_name, tier_code, min_points, max_points, daily_task_limit, commission_multiplier, priority_level, max_withdrawal, color_code, icon) VALUES
('Bronze', 'bronze', 0, 49, 2, 1.00, 0, 500, '#CD7F32', '🥉'),
('Silver', 'silver', 50, 149, 5, 1.10, 1, 2000, '#C0C0C0', '🥈'),
('Gold', 'gold', 150, 299, 10, 1.25, 2, 5000, '#FFD700', '🥇'),
('Elite', 'elite', 300, NULL, 999, 1.50, 3, 10000, '#9B59B6', '👑')
ON DUPLICATE KEY UPDATE tier_name=VALUES(tier_name);

-- Add tier columns to users table
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS tier_id INT DEFAULT 1,
    ADD COLUMN IF NOT EXISTS tier_points DECIMAL(10,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS quality_score DECIMAL(3,2) DEFAULT 5.00,
    ADD COLUMN IF NOT EXISTS consistency_score DECIMAL(3,2) DEFAULT 5.00,
    ADD COLUMN IF NOT EXISTS active_days INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_active_date DATE,
    ADD INDEX IF NOT EXISTS idx_tier_id (tier_id);

-- ============================================
-- 5. BADGE SYSTEM
-- ============================================

-- Badges master
CREATE TABLE IF NOT EXISTS badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    badge_name VARCHAR(100) NOT NULL,
    badge_code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    criteria_type ENUM('tasks', 'referrals', 'quality', 'streak', 'earnings', 'time') NOT NULL,
    criteria_value INT NOT NULL,
    reward_points INT DEFAULT 0,
    reward_amount DECIMAL(10,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_badge_code (badge_code),
    INDEX idx_criteria (criteria_type, criteria_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User badges
CREATE TABLE IF NOT EXISTS user_badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_badge (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default badges
INSERT INTO badges (badge_name, badge_code, description, icon, criteria_type, criteria_value, reward_points) VALUES
('First Step', 'first_task', 'Complete your first task', '🎯', 'tasks', 1, 5),
('Rising Star', 'ten_tasks', 'Complete 10 tasks', '⭐', 'tasks', 10, 20),
('Task Master', 'fifty_tasks', 'Complete 50 tasks', '🏆', 'tasks', 50, 50),
('Century Club', 'hundred_tasks', 'Complete 100 tasks', '💯', 'tasks', 100, 100),
('Referral King', 'referral_king', 'Refer 10 friends successfully', '👑', 'referrals', 10, 50),
('Quality Champion', 'quality_champion', 'Maintain 4.5+ quality score', '🌟', 'quality', 45, 30),
('Consistent Performer', 'streak_30', '30 days active streak', '🔥', 'streak', 30, 40),
('Top Earner', 'top_earner', 'Earn ₹10,000 or more', '💰', 'earnings', 10000, 75)
ON DUPLICATE KEY UPDATE badge_name=VALUES(badge_name);

-- ============================================
-- 6. FRAUD CONTROL TABLES
-- ============================================

-- Device/Browser Fingerprints
CREATE TABLE IF NOT EXISTS user_fingerprints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    screen_resolution VARCHAR(20),
    timezone VARCHAR(50),
    language VARCHAR(10),
    fingerprint_hash VARCHAR(64),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_fingerprint (fingerprint_hash),
    INDEX idx_ip (ip_address),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Brand review history
CREATE TABLE IF NOT EXISTS reviewer_brand_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    brand_name VARCHAR(100) NOT NULL,
    last_reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    review_count INT DEFAULT 1,
    UNIQUE KEY unique_user_brand (user_id, brand_name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_brand (user_id, brand_name),
    INDEX idx_last_reviewed (last_reviewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suspicious activities
CREATE TABLE IF NOT EXISTS suspicious_activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    activity_type ENUM('multiple_accounts', 'same_brand', 'fake_referral', 'vpn_detected', 'rapid_completion', 'quality_issue') NOT NULL,
    description TEXT,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('pending', 'reviewed', 'actioned', 'dismissed') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_severity (severity),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User penalties
CREATE TABLE IF NOT EXISTS user_penalties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    penalty_type ENUM('warning', 'task_restriction', 'withdrawal_block', 'temporary_ban', 'permanent_ban') NOT NULL,
    reason TEXT,
    expires_at TIMESTAMP NULL,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_penalty (user_id, penalty_type),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. TASK MANAGEMENT ENHANCEMENTS
-- ============================================

-- Add task expiry columns to tasks table
ALTER TABLE tasks 
    ADD COLUMN IF NOT EXISTS deadline TIMESTAMP NULL,
    ADD COLUMN IF NOT EXISTS auto_expired TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS expiry_count INT DEFAULT 0,
    ADD INDEX IF NOT EXISTS idx_deadline (deadline);

-- Task expiry log
CREATE TABLE IF NOT EXISTS task_expiry_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    expired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    penalty_applied VARCHAR(50),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_task_user (task_id, user_id),
    INDEX idx_expired_at (expired_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task rejections
CREATE TABLE IF NOT EXISTS task_rejections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    rejected_by VARCHAR(100),
    rejection_reason TEXT,
    rejection_type ENUM('quality', 'fraud', 'incomplete', 'policy_violation', 'other') NOT NULL,
    can_resubmit TINYINT(1) DEFAULT 1,
    resubmitted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_task_id (task_id),
    INDEX idx_user_id (user_id),
    INDEX idx_rejection_type (rejection_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. BRANDS MASTER TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS brands (
    id INT PRIMARY KEY AUTO_INCREMENT,
    brand_name VARCHAR(100) UNIQUE NOT NULL,
    category VARCHAR(100),
    total_reviews INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_brand_name (brand_name),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. FEATURE TOGGLE SYSTEM
-- ============================================

-- Feature flags
CREATE TABLE IF NOT EXISTS feature_flags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    feature_key VARCHAR(100) UNIQUE NOT NULL,
    feature_name VARCHAR(200) NOT NULL,
    description TEXT,
    is_enabled TINYINT(1) DEFAULT 0,
    is_beta TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_feature_key (feature_key),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beta users
CREATE TABLE IF NOT EXISTS beta_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    feature_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_beta_access (user_id, feature_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (feature_id) REFERENCES feature_flags(id) ON DELETE CASCADE,
    INDEX idx_user_feature (user_id, feature_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default features
INSERT INTO feature_flags (feature_key, feature_name, description, is_enabled) VALUES
('seller_module', 'Seller Module', 'Enable seller registration and dashboard', 1),
('payment_razorpay', 'Razorpay Payments', 'Enable Razorpay payment gateway', 0),
('payment_payumoney', 'PayU Money Payments', 'Enable PayU Money payment gateway', 0),
('gst_billing', 'GST Billing', 'Enable GST compliant invoicing', 1),
('tier_system', 'Reviewer Tiers', 'Enable Bronze/Silver/Gold/Elite tier system', 1),
('badge_system', 'Badge System', 'Enable achievement badges', 1),
('fraud_detection', 'Fraud Detection', 'Enable fraud prevention mechanisms', 1),
('whatsapp_notifications', 'WhatsApp Notifications', 'Enable WhatsApp notification channel', 0)
ON DUPLICATE KEY UPDATE feature_name=VALUES(feature_name);

-- ============================================
-- 10. SYSTEM SETTINGS UPDATES
-- ============================================

-- Add new system settings
INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES
-- Payment Gateway Settings
('razorpay_enabled', '0', NOW()),
('razorpay_key_id', '', NOW()),
('razorpay_key_secret', '', NOW()),
('razorpay_test_mode', '1', NOW()),
('payumoney_enabled', '0', NOW()),
('payumoney_merchant_key', '', NOW()),
('payumoney_merchant_salt', '', NOW()),
('payumoney_test_mode', '1', NOW()),
-- Admin Commission
('admin_commission_per_review', '50', NOW()),
-- GST Settings
('gst_rate', '18', NOW()),
('gst_sac_code', '998371', NOW()),
-- Legal Pages
('terms_content', '', NOW()),
('privacy_content', '', NOW()),
('refund_content', '', NOW()),
('disclaimer_content', '', NOW())
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- ============================================
-- 11. NOTIFICATIONS TABLE ENHANCEMENT
-- ============================================

-- Add notification channel columns
ALTER TABLE notifications 
    ADD COLUMN IF NOT EXISTS channel ENUM('app', 'email', 'whatsapp', 'sms') DEFAULT 'app',
    ADD COLUMN IF NOT EXISTS sent_via_email TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS sent_via_whatsapp TINYINT(1) DEFAULT 0;

-- ============================================
-- END OF MIGRATION
-- ============================================

-- Show completion message
SELECT 'Database migration to v3.0 completed successfully!' as message;
