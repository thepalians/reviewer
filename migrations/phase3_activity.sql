-- Phase 3: User Activity and Login Tracking
-- Run Date: 2026-02-03

-- User Activity Logs
CREATE TABLE IF NOT EXISTS user_activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login History
CREATE TABLE IF NOT EXISTS login_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    location VARCHAR(100),
    device_type VARCHAR(50),
    browser VARCHAR(50),
    status ENUM('success', 'failed') DEFAULT 'success',
    failure_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Levels (Enhanced user management)
CREATE TABLE IF NOT EXISTS user_levels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level_name VARCHAR(50) NOT NULL,
    min_tasks INT DEFAULT 0,
    min_revenue DECIMAL(10,2) DEFAULT 0,
    min_rating DECIMAL(3,2) DEFAULT 0,
    perks JSON,
    withdrawal_limit DECIMAL(10,2),
    commission_bonus DECIMAL(5,2) DEFAULT 0,
    priority_support TINYINT DEFAULT 0,
    badge_color VARCHAR(20),
    sort_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_level_name (level_name),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default user levels
INSERT INTO user_levels (level_name, min_tasks, min_revenue, min_rating, perks, withdrawal_limit, commission_bonus, priority_support, badge_color, sort_order) VALUES
('Bronze', 0, 0, 0, '["Basic support", "Standard withdrawal"]', 10000, 0, 0, '#CD7F32', 1),
('Silver', 10, 1000, 4.0, '["Priority tasks", "Faster withdrawals", "5% bonus commission"]', 25000, 5, 0, '#C0C0C0', 2),
('Gold', 50, 5000, 4.5, '["Premium tasks", "Instant withdrawals", "10% bonus commission", "Priority support"]', 50000, 10, 1, '#FFD700', 3),
('Platinum', 100, 10000, 4.7, '["Exclusive tasks", "Instant withdrawals", "15% bonus commission", "Priority support", "Personal account manager"]', 100000, 15, 1, '#E5E4E2', 4),
('Diamond', 200, 25000, 4.9, '["VIP tasks", "Unlimited withdrawals", "20% bonus commission", "24/7 priority support", "Personal account manager", "Early access to new features"]', 500000, 20, 1, '#B9F2FF', 5)
ON DUPLICATE KEY UPDATE min_tasks = VALUES(min_tasks), min_revenue = VALUES(min_revenue);

-- Add user level column to users table if not exists
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS user_level VARCHAR(50) DEFAULT 'Bronze',
ADD COLUMN IF NOT EXISTS account_status ENUM('active', 'suspended', 'banned', 'inactive') DEFAULT 'active',
ADD COLUMN IF NOT EXISTS suspension_reason TEXT NULL,
ADD COLUMN IF NOT EXISTS suspended_until DATETIME NULL,
ADD COLUMN IF NOT EXISTS last_activity_at TIMESTAMP NULL;

-- Create index for user level
CREATE INDEX IF NOT EXISTS idx_user_level ON users(user_level);
CREATE INDEX IF NOT EXISTS idx_account_status ON users(account_status);
