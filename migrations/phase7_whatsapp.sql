-- ============================================
-- Phase 7: WhatsApp Integration
-- Database Migration Script
-- ============================================

USE reviewflow;

-- WhatsApp templates table
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    template_id VARCHAR(100),
    category VARCHAR(50),
    language VARCHAR(10) DEFAULT 'en',
    content TEXT NOT NULL,
    variables JSON,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_template_id (template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp messages table
CREATE TABLE IF NOT EXISTS whatsapp_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    template_id INT,
    message_type ENUM('template', 'text', 'media') DEFAULT 'template',
    content TEXT,
    status ENUM('pending', 'sent', 'delivered', 'read', 'failed') DEFAULT 'pending',
    external_id VARCHAR(100),
    error_message TEXT,
    sent_at DATETIME,
    delivered_at DATETIME,
    read_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_phone (phone_number),
    INDEX idx_external (external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp settings table
CREATE TABLE IF NOT EXISTS whatsapp_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
