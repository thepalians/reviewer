-- ============================================
-- Email Notifications System - Database Schema
-- ============================================

USE reviewflow;

-- Notification Templates table
CREATE TABLE IF NOT EXISTS notification_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    sms_body VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notification Queue table
CREATE TABLE IF NOT EXISTS notification_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    type VARCHAR(50) NOT NULL,
    channel ENUM('email', 'sms') DEFAULT 'email',
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    scheduled_at DATETIME,
    sent_at DATETIME,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_user_id (user_id),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default notification templates
INSERT INTO notification_templates (type, subject, body, sms_body) VALUES
('task_assigned', 'New Task Assigned - {{task_name}}', 
'<html><body><h2>Hello {{user_name}},</h2><p>A new task has been assigned to you.</p><p><strong>Task:</strong> {{task_name}}</p><p><strong>Reward:</strong> ₹{{reward_amount}}</p><p>Please login to your dashboard to view and complete the task.</p><p>Best regards,<br>ReviewFlow Team</p></body></html>',
'New task assigned: {{task_name}}. Reward: Rs.{{reward_amount}}. Login to complete.'),

('task_completed', 'Task Completed Successfully', 
'<html><body><h2>Congratulations {{user_name}}!</h2><p>Your task has been completed successfully.</p><p><strong>Task:</strong> {{task_name}}</p><p><strong>Reward:</strong> ₹{{reward_amount}} has been credited to your wallet.</p><p>Best regards,<br>ReviewFlow Team</p></body></html>',
'Task completed! Rs.{{reward_amount}} credited to your wallet.'),

('payment_received', 'Payment Received - ₹{{amount}}', 
'<html><body><h2>Hello {{user_name}},</h2><p>We have received your payment of ₹{{amount}}.</p><p><strong>Transaction ID:</strong> {{transaction_id}}</p><p><strong>Date:</strong> {{payment_date}}</p><p>Thank you for your payment!</p><p>Best regards,<br>ReviewFlow Team</p></body></html>',
'Payment of Rs.{{amount}} received. Transaction ID: {{transaction_id}}'),

('welcome_email', 'Welcome to ReviewFlow!', 
'<html><body><h2>Welcome {{user_name}}!</h2><p>Thank you for joining ReviewFlow. We are excited to have you on board.</p><p>Start earning money by completing simple review tasks. Your first task bonus of ₹{{first_task_bonus}} is waiting for you!</p><p>Login to your dashboard to get started.</p><p>Best regards,<br>ReviewFlow Team</p></body></html>',
'Welcome to ReviewFlow! Start earning today. Login now.'),

('kyc_verified', 'KYC Verification Approved', 
'<html><body><h2>Hello {{user_name}},</h2><p>Your KYC verification has been approved successfully.</p><p>You can now request withdrawals from your wallet.</p><p>Best regards,<br>ReviewFlow Team</p></body></html>',
'Your KYC has been approved. You can now withdraw funds.'),

('kyc_rejected', 'KYC Verification Rejected', 
'<html><body><h2>Hello {{user_name}},</h2><p>Unfortunately, your KYC verification has been rejected.</p><p><strong>Reason:</strong> {{rejection_reason}}</p><p>Please submit your KYC again with correct information.</p><p>Best regards,<br>ReviewFlow Team</p></body></html>',
'KYC rejected. Reason: {{rejection_reason}}. Please resubmit.'),

('withdrawal_approved', 'Withdrawal Request Approved', 
'<html><body><h2>Hello {{user_name}},</h2><p>Your withdrawal request has been approved.</p><p><strong>Amount:</strong> ₹{{amount}}</p><p><strong>Transaction ID:</strong> {{transaction_id}}</p><p>The amount will be transferred to your registered bank account within 2-3 business days.</p><p>Best regards,<br>ReviewFlow Team</p></body></html>',
'Withdrawal approved! Rs.{{amount}} will be transferred to your bank account.'),

('withdrawal_rejected', 'Withdrawal Request Rejected', 
'<html><body><h2>Hello {{user_name}},</h2><p>Your withdrawal request has been rejected.</p><p><strong>Reason:</strong> {{rejection_reason}}</p><p>If you have any questions, please contact support.</p><p>Best regards,<br>ReviewFlow Team</p></body></html>',
'Withdrawal rejected. Reason: {{rejection_reason}}');
