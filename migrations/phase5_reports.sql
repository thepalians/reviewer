-- ============================================
-- Phase 5: Advanced Reporting System
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Report templates table
CREATE TABLE IF NOT EXISTS report_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    report_type ENUM('revenue', 'users', 'tasks', 'payments', 'custom') NOT NULL,
    columns JSON NOT NULL,
    filters JSON,
    created_by INT NOT NULL,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_report_type (report_type),
    INDEX idx_created_by (created_by),
    INDEX idx_system (is_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled reports table
CREATE TABLE IF NOT EXISTS scheduled_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    frequency ENUM('daily', 'weekly', 'monthly') NOT NULL,
    day_of_week TINYINT DEFAULT NULL CHECK (day_of_week >= 0 AND day_of_week <= 6),
    day_of_month TINYINT DEFAULT NULL CHECK (day_of_month >= 1 AND day_of_month <= 31),
    time_of_day TIME DEFAULT '09:00:00',
    recipients JSON NOT NULL,
    format ENUM('pdf', 'excel', 'csv') DEFAULT 'pdf',
    is_active TINYINT(1) DEFAULT 1,
    last_sent_at DATETIME DEFAULT NULL,
    next_run_at DATETIME NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES report_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id),
    INDEX idx_active (is_active),
    INDEX idx_next_run (next_run_at),
    INDEX idx_frequency (frequency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report history table
CREATE TABLE IF NOT EXISTS report_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT NOT NULL,
    scheduled_id INT DEFAULT NULL,
    file_path VARCHAR(255),
    file_size INT,
    status ENUM('pending', 'generating', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT,
    generated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (template_id) REFERENCES report_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (scheduled_id) REFERENCES scheduled_reports(id) ON DELETE SET NULL,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_template_id (template_id),
    INDEX idx_scheduled_id (scheduled_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default report templates
INSERT INTO report_templates (name, description, report_type, columns, filters, created_by, is_system) VALUES
('Revenue Summary', 'Monthly revenue and payments overview', 'revenue', 
 '["date", "total_revenue", "completed_tasks", "withdrawals", "admin_commission"]', 
 '{"date_range": "last_30_days"}', 1, 1),
('User Activity Report', 'User registration and activity metrics', 'users', 
 '["user_id", "name", "email", "total_tasks", "total_earned", "join_date"]', 
 '{"date_range": "last_30_days"}', 1, 1),
('Task Completion Report', 'Task completion statistics and performance', 'tasks', 
 '["task_id", "title", "assigned_to", "status", "completion_date", "payment"]', 
 '{"status": ["completed", "paid"]}', 1, 1),
('Payment Transaction Report', 'All payment transactions and withdrawals', 'payments', 
 '["transaction_id", "user", "type", "amount", "status", "date"]', 
 '{"date_range": "last_30_days"}', 1, 1)
ON DUPLICATE KEY UPDATE name=name;
