-- ============================================
-- Phase 8: Inventory & Product Management
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    sku VARCHAR(50),
    barcode VARCHAR(50),
    category_id INT,
    brand_id INT,
    platform VARCHAR(50),
    product_url TEXT,
    image_url TEXT,
    price DECIMAL(10,2),
    stock_quantity INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_seller (seller_id),
    INDEX idx_sku (sku),
    INDEX idx_status (status),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product reviews table
CREATE TABLE IF NOT EXISTS product_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    review_url TEXT,
    rating TINYINT,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_task (task_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inventory logs table
CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    action ENUM('add', 'remove', 'adjust', 'sale', 'return') NOT NULL,
    quantity INT NOT NULL,
    previous_stock INT,
    new_stock INT,
    reference_type VARCHAR(50),
    reference_id INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock alerts table
CREATE TABLE IF NOT EXISTS stock_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    alert_type ENUM('low_stock', 'out_of_stock', 'overstock') NOT NULL,
    threshold_value INT,
    current_value INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    INDEX idx_type (alert_type),
    INDEX idx_read (is_read),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
