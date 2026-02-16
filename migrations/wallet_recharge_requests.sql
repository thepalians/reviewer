-- ============================================
-- Wallet Recharge Requests Table
-- Migration for Offline Wallet Recharge System
-- ============================================

USE reviewflow;

-- Create wallet_recharge_requests table
CREATE TABLE IF NOT EXISTS wallet_recharge_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    utr_number VARCHAR(100) NOT NULL,
    screenshot_path VARCHAR(255) NOT NULL,
    transfer_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_remarks TEXT,
    approved_by VARCHAR(100),
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id) ON DELETE CASCADE,
    INDEX idx_seller_id (seller_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Show completion message
SELECT 'Wallet recharge requests table created successfully!' as message;
