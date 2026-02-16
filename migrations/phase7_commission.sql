-- ============================================
-- Phase 7: Advanced Commission System
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Commission tiers table
CREATE TABLE IF NOT EXISTS commission_tiers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    min_tasks INT DEFAULT 0,
    max_tasks INT,
    base_multiplier DECIMAL(3,2) DEFAULT 1.00,
    bonus_percentage DECIMAL(5,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_tasks (min_tasks, max_tasks)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Commission bonuses table
CREATE TABLE IF NOT EXISTS commission_bonuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    bonus_type ENUM('first_task', 'streak', 'quality', 'speed', 'referral', 'special') NOT NULL,
    bonus_amount DECIMAL(10,2),
    bonus_percentage DECIMAL(5,2),
    conditions JSON,
    valid_from DATE,
    valid_until DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (bonus_type),
    INDEX idx_active (is_active),
    INDEX idx_dates (valid_from, valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User commission history table
CREATE TABLE IF NOT EXISTS user_commission_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    base_commission DECIMAL(10,2) NOT NULL,
    bonus_amount DECIMAL(10,2) DEFAULT 0,
    bonus_type VARCHAR(50),
    total_commission DECIMAL(10,2) NOT NULL,
    multiplier DECIMAL(3,2) DEFAULT 1.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_task (task_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
