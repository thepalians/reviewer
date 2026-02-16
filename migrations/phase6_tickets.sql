-- ============================================
-- Phase 6: Support Ticket System
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Support tickets table
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    category ENUM('payment', 'technical', 'account', 'task', 'withdrawal', 'other') DEFAULT 'other',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'in_progress', 'waiting', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT DEFAULT NULL,
    sla_deadline DATETIME,
    resolved_at DATETIME,
    closed_at DATETIME,
    satisfaction_rating INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to),
    INDEX idx_ticket_number (ticket_number),
    INDEX idx_priority (priority),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket replies table
CREATE TABLE IF NOT EXISTS ticket_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    attachments JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket attachments table
CREATE TABLE IF NOT EXISTS ticket_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    reply_id INT DEFAULT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    file_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (reply_id) REFERENCES ticket_replies(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ticket (ticket_id),
    INDEX idx_reply (reply_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Canned responses for tickets
CREATE TABLE IF NOT EXISTS ticket_canned_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default canned responses
INSERT INTO ticket_canned_responses (title, content, category, is_active) VALUES
('Thank You', 'Thank you for contacting support. We will look into your issue and get back to you shortly.', 'general', 1),
('Under Review', 'Your ticket is currently under review. We will update you within 24 hours.', 'general', 1),
('Payment Issue', 'We are looking into your payment issue. Please provide your transaction ID for faster resolution.', 'payment', 1),
('Technical Issue', 'Our technical team is investigating this issue. We appreciate your patience.', 'technical', 1),
('Account Verification', 'Your account is under verification. This process typically takes 24-48 hours.', 'account', 1),
('Task Related', 'Regarding your task query, please provide the task ID so we can assist you better.', 'task', 1),
('Withdrawal Query', 'For withdrawal-related queries, please ensure you have completed KYC verification and have sufficient balance.', 'withdrawal', 1),
('Resolved', 'Your issue has been resolved. If you need further assistance, please feel free to reopen this ticket.', 'general', 1)
ON DUPLICATE KEY UPDATE
    content = VALUES(content),
    updated_at = CURRENT_TIMESTAMP;
