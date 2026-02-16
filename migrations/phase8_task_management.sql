-- ============================================
-- Phase 8: Advanced Task Management
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Task dependencies table
CREATE TABLE IF NOT EXISTS task_dependencies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    depends_on_task_id INT NOT NULL,
    dependency_type ENUM('finish_to_start', 'start_to_start', 'finish_to_finish') DEFAULT 'finish_to_start',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_dependency (task_id, depends_on_task_id),
    INDEX idx_task (task_id),
    INDEX idx_depends_on (depends_on_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task milestones table
CREATE TABLE IF NOT EXISTS task_milestones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    seller_id INT NOT NULL,
    total_steps INT DEFAULT 1,
    completed_steps INT DEFAULT 0,
    status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    deadline DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seller (seller_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Milestone steps table
CREATE TABLE IF NOT EXISTS milestone_steps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    milestone_id INT NOT NULL,
    step_number INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    task_id INT,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    completed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_milestone (milestone_id),
    INDEX idx_task (task_id),
    INDEX idx_status (status),
    FOREIGN KEY (milestone_id) REFERENCES task_milestones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Advanced task templates table
CREATE TABLE IF NOT EXISTS advanced_task_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category_id INT,
    template_data JSON NOT NULL,
    steps JSON,
    default_commission DECIMAL(10,2),
    default_deadline_days INT DEFAULT 7,
    is_public TINYINT(1) DEFAULT 0,
    use_count INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category_id),
    INDEX idx_public (is_public),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bulk task operations table
CREATE TABLE IF NOT EXISTS bulk_task_operations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    operation_type ENUM('create', 'update', 'delete', 'assign', 'status_change') NOT NULL,
    task_ids JSON NOT NULL,
    changes JSON,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    processed_count INT DEFAULT 0,
    total_count INT NOT NULL,
    error_log JSON,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    INDEX idx_status (status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
