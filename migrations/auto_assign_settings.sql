-- Migration: Auto-Assign Tasks for New Users
-- Creates the auto_assign_tasks table and inserts default settings
-- Requires: system_settings table must exist (created during initial install/upgrade_v3.sql)

CREATE TABLE IF NOT EXISTS auto_assign_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_link VARCHAR(500) NOT NULL,
    brand_name VARCHAR(255) DEFAULT '',
    commission DECIMAL(10,2) NOT NULL DEFAULT 0,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    deadline_days INT DEFAULT 7,
    admin_notes TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    max_assignments INT DEFAULT 0 COMMENT '0 = unlimited',
    total_assigned INT DEFAULT 0,
    created_by VARCHAR(100) DEFAULT 'admin',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES 
('auto_assign_enabled', '0'),
('auto_assign_min_tasks', '1'),
('auto_assign_max_tasks', '3');
