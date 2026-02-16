-- ============================================
-- Phase 7: Auto Task Assignment System
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Auto assignment rules table
CREATE TABLE IF NOT EXISTS auto_assignment_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    rule_type ENUM('level', 'performance', 'category', 'location', 'round_robin', 'custom') NOT NULL,
    conditions JSON NOT NULL,
    priority INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_priority (priority),
    INDEX idx_type (rule_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User assignment preferences table
CREATE TABLE IF NOT EXISTS user_assignment_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    max_daily_tasks INT DEFAULT 10,
    preferred_categories JSON,
    preferred_platforms JSON,
    blacklisted_brands JSON,
    availability_schedule JSON,
    auto_accept TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_auto_accept (auto_accept)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assignment logs table
CREATE TABLE IF NOT EXISTS assignment_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    rule_id INT,
    assignment_type ENUM('auto', 'manual', 'round_robin') DEFAULT 'auto',
    score DECIMAL(5,2),
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task (task_id),
    INDEX idx_user (user_id),
    INDEX idx_type (assignment_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
