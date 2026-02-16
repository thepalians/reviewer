-- Phase 2: Gamification & Rewards System Tables
-- Migration file for gamification system

-- Table for user points and levels
CREATE TABLE IF NOT EXISTS user_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    points INT DEFAULT 0,
    level VARCHAR(20) DEFAULT 'Bronze',
    total_earned INT DEFAULT 0,
    streak_days INT DEFAULT 0,
    last_login_date DATE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_points (points),
    KEY idx_level (level),
    KEY idx_streak (streak_days)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for point transactions history
CREATE TABLE IF NOT EXISTS point_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    points INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    reference_id INT,
    reference_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_user (user_id),
    KEY idx_type (type),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for badges
CREATE TABLE IF NOT EXISTS badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    icon VARCHAR(100),
    criteria VARCHAR(100),
    points_required INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for user earned badges
CREATE TABLE IF NOT EXISTS user_badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_badge (user_id, badge_id),
    KEY idx_user (user_id),
    KEY idx_badge (badge_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for level settings
CREATE TABLE IF NOT EXISTS level_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    level_name VARCHAR(20) NOT NULL UNIQUE,
    min_points INT NOT NULL,
    max_points INT NOT NULL,
    perks TEXT,
    badge_color VARCHAR(20),
    level_order INT NOT NULL,
    KEY idx_level_order (level_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default level settings
INSERT INTO level_settings (level_name, min_points, max_points, perks, badge_color, level_order) VALUES
('Bronze', 0, 99, 'Basic access to all features', '#CD7F32', 1),
('Silver', 100, 499, 'Priority support, 5% bonus on tasks', '#C0C0C0', 2),
('Gold', 500, 1499, 'Priority support, 10% bonus, exclusive tasks', '#FFD700', 3),
('Platinum', 1500, 4999, 'VIP support, 15% bonus, premium tasks, early access', '#E5E4E2', 4),
('Diamond', 5000, 999999, 'Elite support, 20% bonus, all premium features, personal account manager', '#B9F2FF', 5)
ON DUPLICATE KEY UPDATE
    min_points = VALUES(min_points),
    max_points = VALUES(max_points),
    perks = VALUES(perks),
    badge_color = VALUES(badge_color);

-- Insert default badges
INSERT INTO badges (name, description, icon, criteria, points_required, is_active) VALUES
('First Task', 'Completed your first task', 'fa-star', 'complete_1_task', 10, 1),
('Task Master 10', 'Completed 10 tasks', 'fa-fire', 'complete_10_tasks', 100, 1),
('Task Master 50', 'Completed 50 tasks', 'fa-trophy', 'complete_50_tasks', 500, 1),
('Task Master 100', 'Completed 100 tasks', 'fa-crown', 'complete_100_tasks', 1000, 1),
('First Referral', 'Referred your first user', 'fa-user-plus', 'refer_1_user', 50, 1),
('Referral Pro', 'Referred 10 users', 'fa-users', 'refer_10_users', 500, 1),
('Verified User', 'Completed KYC verification', 'fa-check-circle', 'kyc_verified', 20, 1),
('Top Performer', 'Ranked in top 10 on leaderboard', 'fa-medal', 'top_10_leaderboard', 1000, 1),
('Streak Master', 'Maintained 30 day login streak', 'fa-calendar-check', 'streak_30_days', 300, 1),
('Early Bird', 'Joined in first 100 users', 'fa-dove', 'early_user', 100, 1)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    icon = VALUES(icon),
    criteria = VALUES(criteria),
    points_required = VALUES(points_required);

-- Create user_points entries for existing users
INSERT INTO user_points (user_id, points, level, total_earned, streak_days)
SELECT id, 0, 'Bronze', 0, 0
FROM users
WHERE id NOT IN (SELECT user_id FROM user_points)
ON DUPLICATE KEY UPDATE user_id = user_id;
