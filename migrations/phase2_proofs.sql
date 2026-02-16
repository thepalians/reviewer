-- Phase 2: Review Proof System Tables
-- Migration file for proof verification system

-- Table for storing task proofs
CREATE TABLE IF NOT EXISTS task_proofs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    proof_type ENUM('screenshot', 'order_id', 'review_link') NOT NULL,
    proof_file VARCHAR(255),
    proof_text TEXT,
    ai_score DECIMAL(5,2) DEFAULT 0.00,
    ai_result JSON,
    status ENUM('pending', 'auto_approved', 'manual_review', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    verified_by INT,
    verified_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_task (task_id),
    KEY idx_user (user_id),
    KEY idx_status (status),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for AI verification logs
CREATE TABLE IF NOT EXISTS proof_verification_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    proof_id INT NOT NULL,
    verification_type ENUM('ai', 'manual') NOT NULL,
    result TEXT,
    confidence_score DECIMAL(5,2),
    verified_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proof_id) REFERENCES task_proofs(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_proof (proof_id),
    KEY idx_type (verification_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create upload directories for proofs
-- (This will be handled by PHP code)
