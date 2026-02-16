-- Phase 4: Task Categories & Management
-- Database tables for task categorization and management

-- Task Categories Table
CREATE TABLE IF NOT EXISTS task_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(20) DEFAULT '#667eea',
    icon VARCHAR(50) DEFAULT 'bi-tag',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add category_id to tasks table if not exists
ALTER TABLE tasks 
ADD COLUMN IF NOT EXISTS category_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
ADD INDEX IF NOT EXISTS idx_category_id (category_id),
ADD INDEX IF NOT EXISTS idx_priority (priority),
ADD CONSTRAINT fk_task_category FOREIGN KEY (category_id) 
    REFERENCES task_categories(id) ON DELETE SET NULL;

-- Task Tags Table
CREATE TABLE IF NOT EXISTS task_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) DEFAULT '#667eea',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task Tag Mappings
CREATE TABLE IF NOT EXISTS task_tag_mappings (
    task_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (task_id, tag_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES task_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT IGNORE INTO task_categories (name, description, color, icon, sort_order) VALUES
('Product Review', 'Standard product review tasks', '#3b82f6', 'bi-star', 1),
('Video Review', 'Video-based product reviews', '#8b5cf6', 'bi-camera-video', 2),
('Photo Review', 'Photo-based product reviews', '#ec4899', 'bi-camera', 3),
('Social Media', 'Social media engagement tasks', '#10b981', 'bi-share', 4),
('Survey', 'Customer survey tasks', '#f59e0b', 'bi-clipboard-check', 5),
('Other', 'Miscellaneous tasks', '#6b7280', 'bi-three-dots', 99);
