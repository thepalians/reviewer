-- ============================================
-- Phase 8: Multi-Payment Gateway Integration
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Payment gateways table
CREATE TABLE IF NOT EXISTS payment_gateways (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    gateway_type ENUM('razorpay', 'payu', 'cashfree', 'stripe', 'paypal') NOT NULL,
    config JSON NOT NULL,
    is_active TINYINT(1) DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0,
    priority INT DEFAULT 0,
    supported_methods JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_default (is_default),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auto payouts table
CREATE TABLE IF NOT EXISTS auto_payouts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    frequency ENUM('daily', 'weekly', 'biweekly', 'monthly') NOT NULL,
    day_of_week TINYINT,
    day_of_month TINYINT,
    min_amount DECIMAL(10,2) DEFAULT 100,
    max_amount DECIMAL(10,2),
    gateway_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_run DATETIME,
    next_run DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_next_run (next_run),
    FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payout batches table
CREATE TABLE IF NOT EXISTS payout_batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    batch_number VARCHAR(50) NOT NULL UNIQUE,
    total_amount DECIMAL(15,2) NOT NULL,
    total_count INT NOT NULL,
    success_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    gateway_id INT NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'partial') DEFAULT 'pending',
    processed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_gateway (gateway_id),
    FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gateway transactions table
CREATE TABLE IF NOT EXISTS gateway_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gateway_id INT NOT NULL,
    transaction_type ENUM('payment', 'payout', 'refund') NOT NULL,
    internal_ref VARCHAR(50) NOT NULL,
    gateway_ref VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'INR',
    status ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending',
    response_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_internal_ref (internal_ref),
    INDEX idx_gateway_ref (gateway_ref),
    INDEX idx_status (status),
    INDEX idx_type (transaction_type),
    FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
