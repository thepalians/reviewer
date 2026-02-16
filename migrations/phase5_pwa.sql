-- ============================================
-- Phase 5: Progressive Web App (PWA)
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Push notification subscriptions table
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh_key VARCHAR(255) NOT NULL,
    auth_key VARCHAR(255) NOT NULL,
    user_agent TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_active (is_active),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PWA settings table for admin configuration
CREATE TABLE IF NOT EXISTS pwa_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default PWA settings
INSERT INTO pwa_settings (setting_key, setting_value, description) VALUES
('push_enabled', '1', 'Enable push notifications'),
('push_vapid_public', '', 'VAPID public key for push notifications'),
('push_vapid_private', '', 'VAPID private key for push notifications'),
('offline_enabled', '1', 'Enable offline mode'),
('install_prompt_enabled', '1', 'Show install app prompt')
ON DUPLICATE KEY UPDATE setting_key=setting_key;
