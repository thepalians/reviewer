-- Phase 3: Review Templates and Scheduling
-- Run Date: 2026-02-03

-- Review Templates
CREATE TABLE IF NOT EXISTS review_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    platform VARCHAR(50) NOT NULL,
    category VARCHAR(50),
    template_text TEXT,
    rating_default INT DEFAULT 5,
    is_active TINYINT DEFAULT 1,
    usage_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_platform (platform),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled Reviews
CREATE TABLE IF NOT EXISTS scheduled_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    scheduled_date DATE NOT NULL,
    scheduled_time TIME,
    status ENUM('pending', 'assigned', 'completed', 'cancelled') DEFAULT 'pending',
    notification_sent TINYINT DEFAULT 0,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_scheduled_date (scheduled_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Review Quality Scores
CREATE TABLE IF NOT EXISTS review_quality_scores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    quality_score INT DEFAULT 0,
    word_count INT DEFAULT 0,
    sentiment_score DECIMAL(5,2),
    has_images TINYINT DEFAULT 0,
    uniqueness_score DECIMAL(5,2),
    flags JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_quality_score (quality_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample templates
INSERT INTO review_templates (name, platform, category, template_text, rating_default, is_active) VALUES
('General Product Review', 'Amazon', 'General', 'I recently purchased this product and I am extremely satisfied with my purchase. The quality is excellent and it works exactly as described. Highly recommend to anyone looking for this type of product!', 5, 1),
('Service Review', 'Google', 'Services', 'Had a great experience with this service. The staff was professional and the service was completed on time. Would definitely use their services again!', 5, 1),
('Restaurant Review', 'Zomato', 'Food', 'Visited this restaurant recently and had an amazing dining experience. The food was delicious, service was prompt, and the ambiance was perfect. Highly recommended!', 5, 1)
ON DUPLICATE KEY UPDATE template_text = VALUES(template_text);
