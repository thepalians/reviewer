-- ============================================
-- Phase 7: Competitions & Leaderboards
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Competitions table
CREATE TABLE IF NOT EXISTS competitions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    competition_type ENUM('tasks', 'earnings', 'quality', 'referrals', 'speed') NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    prizes JSON NOT NULL,
    rules JSON,
    min_participants INT DEFAULT 0,
    max_participants INT,
    entry_fee DECIMAL(10,2) DEFAULT 0,
    prize_pool DECIMAL(10,2) DEFAULT 0,
    status ENUM('upcoming', 'active', 'ended', 'cancelled') DEFAULT 'upcoming',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_type (competition_type),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Competition participants table
CREATE TABLE IF NOT EXISTS competition_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    competition_id INT NOT NULL,
    user_id INT NOT NULL,
    score DECIMAL(10,2) DEFAULT 0,
    rank INT,
    prize_won DECIMAL(10,2),
    prize_paid TINYINT(1) DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_participation (competition_id, user_id),
    INDEX idx_competition (competition_id),
    INDEX idx_user (user_id),
    INDEX idx_score (competition_id, score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Competition leaderboard table
CREATE TABLE IF NOT EXISTS competition_leaderboard (
    id INT PRIMARY KEY AUTO_INCREMENT,
    competition_id INT NOT NULL,
    user_id INT NOT NULL,
    metric_value DECIMAL(10,2) DEFAULT 0,
    rank INT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_competition_rank (competition_id, rank),
    INDEX idx_user (user_id),
    INDEX idx_metric (competition_id, metric_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
