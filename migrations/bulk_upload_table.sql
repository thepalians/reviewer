-- ============================================
-- Bulk Upload System - Database Schema
-- ============================================

USE reviewflow;

-- Bulk upload history table
CREATE TABLE IF NOT EXISTS bulk_upload_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    filename VARCHAR(255) NOT NULL,
    total_rows INT DEFAULT 0,
    success_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    error_log TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    INDEX idx_admin_id (admin_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
