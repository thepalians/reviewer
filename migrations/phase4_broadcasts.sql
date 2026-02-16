-- Phase 4: Broadcast & Communication System
-- Database tables for broadcast messages and communications

-- Broadcast Messages Table
CREATE TABLE IF NOT EXISTS broadcast_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    channel ENUM('email', 'sms', 'push', 'in_app') DEFAULT 'in_app',
    target_users TEXT,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    status ENUM('draft', 'scheduled', 'sending', 'sent', 'failed') DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_channel (channel),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Broadcast Recipients (tracks who received broadcasts)
CREATE TABLE IF NOT EXISTS broadcast_recipients (
    id INT PRIMARY KEY AUTO_INCREMENT,
    broadcast_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'read') DEFAULT 'pending',
    sent_at DATETIME NULL,
    read_at DATETIME NULL,
    error_message TEXT,
    INDEX idx_broadcast_id (broadcast_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (broadcast_id) REFERENCES broadcast_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
