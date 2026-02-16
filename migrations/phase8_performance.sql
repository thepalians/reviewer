-- ============================================
-- Phase 8: Performance & Optimization
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Job queue table
CREATE TABLE IF NOT EXISTS job_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    priority TINYINT DEFAULT 5,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT,
    scheduled_at DATETIME,
    started_at DATETIME,
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_priority (status, priority),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_job_type (job_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache entries table
CREATE TABLE IF NOT EXISTS cache_entries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cache_key VARCHAR(255) NOT NULL UNIQUE,
    cache_value LONGTEXT,
    cache_type VARCHAR(50),
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expires (expires_at),
    INDEX idx_type (cache_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Performance logs table
CREATE TABLE IF NOT EXISTS performance_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page_url VARCHAR(500),
    load_time DECIMAL(10,4),
    memory_usage INT,
    query_count INT,
    query_time DECIMAL(10,4),
    user_id INT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_page (page_url(255)),
    INDEX idx_load_time (load_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Slow query logs table
CREATE TABLE IF NOT EXISTS slow_query_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    query_hash VARCHAR(64),
    query_text TEXT,
    execution_time DECIMAL(10,4),
    occurrences INT DEFAULT 1,
    last_occurred DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_execution_time (execution_time),
    INDEX idx_hash (query_hash),
    INDEX idx_last_occurred (last_occurred)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
