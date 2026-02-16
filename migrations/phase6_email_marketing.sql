-- ============================================
-- Phase 6: Email Marketing System
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Email campaigns table
CREATE TABLE IF NOT EXISTS email_campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    template_id INT DEFAULT NULL,
    content LONGTEXT,
    segment_type ENUM('all', 'active', 'inactive', 'new', 'custom') DEFAULT 'all',
    segment_filters JSON,
    status ENUM('draft', 'scheduled', 'sending', 'sent', 'paused') DEFAULT 'draft',
    scheduled_at DATETIME,
    sent_at DATETIME,
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    open_count INT DEFAULT 0,
    click_count INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_scheduled (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email campaign logs table
CREATE TABLE IF NOT EXISTS email_campaign_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('pending', 'sent', 'opened', 'clicked', 'bounced', 'unsubscribed') DEFAULT 'pending',
    sent_at DATETIME,
    opened_at DATETIME,
    clicked_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_campaign (campaign_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email unsubscribes table
CREATE TABLE IF NOT EXISTS email_unsubscribes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    reason TEXT,
    unsubscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_email (email),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email templates table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    type ENUM('promotional', 'transactional', 'newsletter', 'notification') DEFAULT 'promotional',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_type (type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default email templates
INSERT INTO email_templates (name, subject, content, type, created_by, is_active) VALUES
('Welcome Email', 'Welcome to ReviewFlow!', '<h1>Welcome to ReviewFlow!</h1><p>Thank you for joining ReviewFlow. We are excited to have you on board.</p><p>Get started by completing your first task and earning rewards!</p>', 'transactional', 1, 1),
('Task Completion', 'Task Completed Successfully', '<h1>Congratulations!</h1><p>Your task has been completed successfully.</p><p>Task: {{task_name}}</p><p>Reward: {{reward}}</p>', 'notification', 1, 1),
('Withdrawal Approved', 'Withdrawal Request Approved', '<h1>Withdrawal Approved</h1><p>Your withdrawal request of {{amount}} has been approved and processed.</p><p>Transaction ID: {{transaction_id}}</p>', 'transactional', 1, 1),
('Newsletter', 'ReviewFlow Newsletter - {{month}}', '<h1>What\'s New at ReviewFlow</h1><p>Check out our latest updates and features!</p>', 'newsletter', 1, 1)
ON DUPLICATE KEY UPDATE
    content = VALUES(content),
    updated_at = CURRENT_TIMESTAMP;
