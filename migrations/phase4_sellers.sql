-- Phase 4: Seller Panel Enhancements
-- Database tables for seller orders and tracking

-- Seller Orders Table
CREATE TABLE IF NOT EXISTS seller_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_tasks INT NOT NULL DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_seller_id (seller_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add seller_order_id to tasks table if not exists
ALTER TABLE tasks 
ADD COLUMN IF NOT EXISTS seller_order_id INT DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_seller_order_id (seller_order_id),
ADD CONSTRAINT fk_seller_order FOREIGN KEY (seller_order_id) 
    REFERENCES seller_orders(id) ON DELETE SET NULL;
