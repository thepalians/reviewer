-- Phase 2: In-App Chat & Support System Tables
-- Migration file for chat system

-- Table for chat conversations
CREATE TABLE IF NOT EXISTS chat_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    admin_id INT,
    status ENUM('open', 'closed', 'pending') DEFAULT 'open',
    subject VARCHAR(255),
    last_message_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_user (user_id),
    KEY idx_admin (admin_id),
    KEY idx_status (status),
    KEY idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for chat messages
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_type ENUM('user', 'admin') NOT NULL,
    message TEXT NOT NULL,
    attachment VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_conversation (conversation_id),
    KEY idx_sender (sender_id),
    KEY idx_read (is_read),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for canned responses
CREATE TABLE IF NOT EXISTS canned_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    category VARCHAR(50),
    shortcode VARCHAR(20) UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_category (category),
    KEY idx_active (is_active),
    KEY idx_shortcode (shortcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for typing indicators (real-time status)
CREATE TABLE IF NOT EXISTS chat_typing_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    is_typing TINYINT(1) DEFAULT 0,
    last_typed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_conversation_user (conversation_id, user_id),
    KEY idx_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default canned responses
INSERT INTO canned_responses (title, message, category, shortcode, is_active) VALUES
('Welcome Message', 'Welcome to ReviewFlow Support! How can I help you today?', 'greeting', 'welcome', 1),
('Task Help', 'I understand you need help with a task. Could you please provide more details or the task ID?', 'task', 'taskhelp', 1),
('Payment Query', 'For payment-related queries, please check your wallet page. If you still need help, provide your transaction ID.', 'payment', 'payment', 1),
('KYC Help', 'For KYC verification, please submit your documents in the KYC section. It typically takes 24-48 hours for approval.', 'kyc', 'kyc', 1),
('Referral Help', 'You can find your unique referral link in the Referrals section. Share it to earn commissions!', 'referral', 'referral', 1),
('Closing Message', 'Thank you for contacting ReviewFlow Support. Is there anything else I can help you with?', 'closing', 'thanks', 1),
('Under Review', 'Your request is under review. We will update you within 24 hours.', 'general', 'review', 1),
('Account Issue', 'Please email us at support@reviewflow.com with your user ID for account-related issues.', 'account', 'account', 1)
ON DUPLICATE KEY UPDATE
    message = VALUES(message),
    category = VALUES(category),
    is_active = VALUES(is_active);

-- Create upload directory for chat attachments
-- (This will be handled by PHP code)
