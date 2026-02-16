-- ============================================
-- Phase 6: Advanced Notification Center
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Notification categories table
CREATE TABLE IF NOT EXISTS notification_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    icon VARCHAR(50),
    color VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User notification settings table
CREATE TABLE IF NOT EXISTS user_notification_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    email_enabled TINYINT(1) DEFAULT 1,
    push_enabled TINYINT(1) DEFAULT 1,
    sms_enabled TINYINT(1) DEFAULT 0,
    in_app_enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES notification_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_category (user_id, category_id),
    INDEX idx_user (user_id),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification preferences table (enhanced from existing)
CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_type (user_id, notification_type),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default notification categories
INSERT INTO notification_categories (name, icon, color, is_active) VALUES
('System', 'fa-cog', '#6c757d', 1),
('Tasks', 'fa-tasks', '#007bff', 1),
('Payments', 'fa-wallet', '#28a745', 1),
('Messages', 'fa-envelope', '#17a2b8', 1),
('Withdrawals', 'fa-money-bill-wave', '#ffc107', 1),
('Achievements', 'fa-trophy', '#dc3545', 1),
('Referrals', 'fa-users', '#6f42c1', 1),
('Updates', 'fa-bell', '#fd7e14', 1)
ON DUPLICATE KEY UPDATE
    icon = VALUES(icon),
    color = VALUES(color),
    is_active = VALUES(is_active);
