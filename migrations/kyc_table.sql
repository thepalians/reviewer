-- ============================================
-- KYC Verification System - Database Schema
-- ============================================

USE reviewflow;

-- User KYC table
CREATE TABLE IF NOT EXISTS user_kyc (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    dob DATE NOT NULL,
    aadhaar_number VARCHAR(12),
    aadhaar_file VARCHAR(255),
    pan_number VARCHAR(10),
    pan_file VARCHAR(255),
    bank_account VARCHAR(20),
    ifsc_code VARCHAR(11),
    bank_name VARCHAR(100),
    passbook_file VARCHAR(255),
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at DATETIME,
    verified_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_user_kyc (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add KYC status to users table if not exists
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS kyc_status ENUM('not_submitted', 'pending', 'verified', 'rejected') DEFAULT 'not_submitted',
ADD INDEX IF NOT EXISTS idx_kyc_status (kyc_status);
