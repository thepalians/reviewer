-- ============================================
-- Phase 6: Seller Dashboard Enhancements
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Seller order templates table
CREATE TABLE IF NOT EXISTS seller_order_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    platform VARCHAR(50),
    product_link TEXT,
    commission_amount DECIMAL(10,2),
    instructions TEXT,
    is_active TINYINT(1) DEFAULT 1,
    use_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_seller (seller_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seller analytics cache table
CREATE TABLE IF NOT EXISTS seller_analytics_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    date DATE NOT NULL,
    total_orders INT DEFAULT 0,
    completed_orders INT DEFAULT 0,
    pending_orders INT DEFAULT 0,
    total_spent DECIMAL(10,2) DEFAULT 0,
    avg_completion_time INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_seller_date (seller_id, date),
    INDEX idx_seller (seller_id),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bulk order batches table
CREATE TABLE IF NOT EXISTS bulk_order_batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    batch_name VARCHAR(100) NOT NULL,
    template_id INT DEFAULT NULL,
    total_orders INT DEFAULT 0,
    created_orders INT DEFAULT 0,
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES seller_order_templates(id) ON DELETE SET NULL,
    INDEX idx_seller (seller_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Review tracking table
CREATE TABLE IF NOT EXISTS review_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    task_id INT NOT NULL,
    product_link TEXT,
    review_date DATE,
    review_rating INT,
    review_text TEXT,
    review_status ENUM('pending', 'live', 'removed', 'flagged') DEFAULT 'pending',
    last_checked_at DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_seller (seller_id),
    INDEX idx_task (task_id),
    INDEX idx_status (review_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
