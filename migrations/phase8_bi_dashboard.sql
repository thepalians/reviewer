-- ============================================
-- Phase 8: BI Dashboard & Advanced Reporting
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Dashboard widgets table
CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    widget_type ENUM('chart', 'counter', 'table', 'list', 'progress') NOT NULL,
    title VARCHAR(100) NOT NULL,
    data_source VARCHAR(100) NOT NULL,
    config JSON,
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    width INT DEFAULT 4,
    height INT DEFAULT 3,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KPI metrics table
CREATE TABLE IF NOT EXISTS kpi_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    metric_type ENUM('count', 'sum', 'average', 'percentage', 'growth') NOT NULL,
    data_source VARCHAR(100) NOT NULL,
    target_value DECIMAL(15,2),
    warning_threshold DECIMAL(15,2),
    critical_threshold DECIMAL(15,2),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KPI history table
CREATE TABLE IF NOT EXISTS kpi_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kpi_id INT NOT NULL,
    value DECIMAL(15,2) NOT NULL,
    recorded_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kpi_date (kpi_id, recorded_date),
    FOREIGN KEY (kpi_id) REFERENCES kpi_metrics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
