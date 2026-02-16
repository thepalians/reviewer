-- ============================================
-- Phase 8: Mobile App Features
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Deep links table
CREATE TABLE IF NOT EXISTS deep_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    link_type ENUM('task', 'payment', 'profile', 'referral', 'custom') NOT NULL,
    short_code VARCHAR(20) NOT NULL UNIQUE,
    target_url TEXT NOT NULL,
    parameters JSON,
    click_count INT DEFAULT 0,
    expires_at DATETIME,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (link_type),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Biometric tokens table
CREATE TABLE IF NOT EXISTS biometric_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_id VARCHAR(100) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    device_name VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    last_used DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_device (user_id, device_id),
    INDEX idx_user (user_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Offline sync queue table
CREATE TABLE IF NOT EXISTS offline_sync_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    data JSON NOT NULL,
    status ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
    synced_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Firebase tokens table
CREATE TABLE IF NOT EXISTS firebase_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    fcm_token TEXT NOT NULL,
    device_type ENUM('android', 'ios', 'web') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_used DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_active (is_active),
    INDEX idx_device (device_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
