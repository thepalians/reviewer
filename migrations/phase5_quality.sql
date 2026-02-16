-- ============================================
-- Phase 5: AI-Powered Review Quality Check
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Review quality scores table
CREATE TABLE IF NOT EXISTS review_quality_scores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    proof_id INT NOT NULL,
    quality_score INT DEFAULT 0 CHECK (quality_score >= 0 AND quality_score <= 100),
    ai_flags JSON,
    plagiarism_score DECIMAL(5,2) DEFAULT 0 CHECK (plagiarism_score >= 0 AND plagiarism_score <= 100),
    spam_probability DECIMAL(5,2) DEFAULT 0 CHECK (spam_probability >= 0 AND spam_probability <= 100),
    is_flagged TINYINT(1) DEFAULT 0,
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proof_id) REFERENCES task_proofs(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_proof_id (proof_id),
    INDEX idx_flagged (is_flagged),
    INDEX idx_quality_score (quality_score),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert a sample quality score for testing
-- This will be removed in production
-- INSERT INTO review_quality_scores (proof_id, quality_score, ai_flags, plagiarism_score, spam_probability, is_flagged)
-- VALUES (1, 85, '{"duplicate_content": false, "spam_detected": false, "short_text": false}', 0.00, 5.50, 0);

-- Create index for better performance
CREATE INDEX idx_spam_probability ON review_quality_scores(spam_probability);
CREATE INDEX idx_plagiarism_score ON review_quality_scores(plagiarism_score);
