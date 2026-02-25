-- Fix Social Hub: create seller_wallet_transactions table and add video_duration to campaigns
-- Run this after social_media_hub.sql

-- Seller wallet transaction log (used by createCampaign() in social-functions.php)
CREATE TABLE IF NOT EXISTS seller_wallet_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    type ENUM('credit','debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    reference_id INT DEFAULT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seller (seller_id),
    FOREIGN KEY (seller_id) REFERENCES sellers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add video_duration column to social_campaigns for accurate watch-time tracking
ALTER TABLE social_campaigns
    ADD COLUMN IF NOT EXISTS video_duration INT DEFAULT 300 AFTER watch_percent_required;
