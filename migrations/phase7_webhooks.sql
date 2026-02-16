-- ============================================
-- Phase 7: Webhook System
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Webhooks table
CREATE TABLE IF NOT EXISTS webhooks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    events JSON NOT NULL,
    secret_key VARCHAR(64) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    retry_count INT DEFAULT 3,
    timeout INT DEFAULT 30,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook logs table
CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    webhook_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    payload JSON,
    response_code INT,
    response_body TEXT,
    attempts INT DEFAULT 1,
    status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    next_retry DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webhook (webhook_id),
    INDEX idx_status (status),
    INDEX idx_event (event_type),
    INDEX idx_retry (next_retry, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
