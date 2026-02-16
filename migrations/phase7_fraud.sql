-- ============================================
-- Phase 7: Fraud Detection System
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Fraud scores table
CREATE TABLE IF NOT EXISTS fraud_scores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    overall_score DECIMAL(5,2) DEFAULT 0,
    ip_score DECIMAL(5,2) DEFAULT 0,
    device_score DECIMAL(5,2) DEFAULT 0,
    behavior_score DECIMAL(5,2) DEFAULT 0,
    content_score DECIMAL(5,2) DEFAULT 0,
    velocity_score DECIMAL(5,2) DEFAULT 0,
    risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    flags JSON,
    last_calculated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_risk_level (risk_level),
    INDEX idx_score (overall_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fraud alerts table
CREATE TABLE IF NOT EXISTS fraud_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    description TEXT,
    evidence JSON,
    status ENUM('new', 'investigating', 'confirmed', 'dismissed') DEFAULT 'new',
    reviewed_by INT,
    reviewed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_type (alert_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IP intelligence table
CREATE TABLE IF NOT EXISTS ip_intelligence (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    is_vpn TINYINT(1) DEFAULT 0,
    is_proxy TINYINT(1) DEFAULT 0,
    is_tor TINYINT(1) DEFAULT 0,
    is_datacenter TINYINT(1) DEFAULT 0,
    country_code VARCHAR(2),
    city VARCHAR(100),
    isp VARCHAR(255),
    risk_score DECIMAL(5,2) DEFAULT 0,
    last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_risk (risk_score),
    INDEX idx_vpn (is_vpn),
    INDEX idx_proxy (is_proxy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
