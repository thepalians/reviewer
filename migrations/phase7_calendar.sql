-- ============================================
-- Phase 7: Task Scheduling & Calendar
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Task schedules table
CREATE TABLE IF NOT EXISTS task_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME,
    reminder_sent TINYINT(1) DEFAULT 0,
    reminder_time DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, scheduled_date),
    INDEX idx_task (task_id),
    INDEX idx_reminder (reminder_sent, reminder_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recurring tasks table
CREATE TABLE IF NOT EXISTS recurring_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    template_data JSON NOT NULL,
    frequency ENUM('daily', 'weekly', 'monthly') NOT NULL,
    day_of_week TINYINT,
    day_of_month TINYINT,
    start_date DATE NOT NULL,
    end_date DATE,
    next_run DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seller (seller_id),
    INDEX idx_next_run (next_run, is_active),
    INDEX idx_frequency (frequency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User availability table
CREATE TABLE IF NOT EXISTS user_availability (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_day (user_id, day_of_week),
    INDEX idx_user (user_id),
    INDEX idx_available (is_available)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
