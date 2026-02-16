-- ============================================
-- Phase 5: Multi-Language Support (i18n)
-- Database Migration Script
-- ============================================

USE reviewflow;

-- Languages table
CREATE TABLE IF NOT EXISTS languages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(5) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    native_name VARCHAR(50) NOT NULL,
    is_rtl TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Translations table
CREATE TABLE IF NOT EXISTS translations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    language_code VARCHAR(5) NOT NULL,
    translation_key VARCHAR(255) NOT NULL,
    translation_value TEXT NOT NULL,
    module VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (language_code) REFERENCES languages(code) ON DELETE CASCADE,
    UNIQUE KEY unique_translation (language_code, translation_key),
    INDEX idx_language_code (language_code),
    INDEX idx_module (module),
    INDEX idx_translation_key (translation_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add language preference to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS preferred_language VARCHAR(5) DEFAULT 'en';
CREATE INDEX IF NOT EXISTS idx_preferred_language ON users(preferred_language);

-- Add language preference to sellers table
ALTER TABLE sellers ADD COLUMN IF NOT EXISTS preferred_language VARCHAR(5) DEFAULT 'en';

-- Insert default languages
INSERT INTO languages (code, name, native_name, is_rtl, is_active) VALUES
('en', 'English', 'English', 0, 1),
('hi', 'Hindi', 'हिन्दी', 0, 1),
('ta', 'Tamil', 'தமிழ்', 0, 1),
('te', 'Telugu', 'తెలుగు', 0, 1),
('bn', 'Bengali', 'বাংলা', 0, 1)
ON DUPLICATE KEY UPDATE code=code;

-- Insert default English translations (base translations)
INSERT INTO translations (language_code, translation_key, translation_value, module) VALUES
-- General
('en', 'app_name', 'ReviewFlow', 'general'),
('en', 'welcome', 'Welcome', 'general'),
('en', 'dashboard', 'Dashboard', 'general'),
('en', 'profile', 'Profile', 'general'),
('en', 'settings', 'Settings', 'general'),
('en', 'logout', 'Logout', 'general'),
('en', 'login', 'Login', 'general'),
('en', 'register', 'Register', 'general'),
('en', 'submit', 'Submit', 'general'),
('en', 'cancel', 'Cancel', 'general'),
('en', 'save', 'Save', 'general'),
('en', 'delete', 'Delete', 'general'),
('en', 'edit', 'Edit', 'general'),
('en', 'view', 'View', 'general'),
('en', 'search', 'Search', 'general'),
('en', 'filter', 'Filter', 'general'),
('en', 'loading', 'Loading...', 'general'),
('en', 'success', 'Success', 'general'),
('en', 'error', 'Error', 'general'),
('en', 'warning', 'Warning', 'general'),
('en', 'info', 'Information', 'general'),

-- Tasks
('en', 'tasks', 'Tasks', 'tasks'),
('en', 'my_tasks', 'My Tasks', 'tasks'),
('en', 'available_tasks', 'Available Tasks', 'tasks'),
('en', 'completed_tasks', 'Completed Tasks', 'tasks'),
('en', 'pending_tasks', 'Pending Tasks', 'tasks'),
('en', 'task_details', 'Task Details', 'tasks'),
('en', 'submit_proof', 'Submit Proof', 'tasks'),

-- Wallet
('en', 'wallet', 'Wallet', 'wallet'),
('en', 'balance', 'Balance', 'wallet'),
('en', 'withdraw', 'Withdraw', 'wallet'),
('en', 'transaction_history', 'Transaction History', 'wallet'),
('en', 'earnings', 'Earnings', 'wallet'),

-- Notifications
('en', 'notifications', 'Notifications', 'notifications'),
('en', 'mark_as_read', 'Mark as Read', 'notifications'),
('en', 'no_notifications', 'No notifications', 'notifications')
ON DUPLICATE KEY UPDATE translation_value=translation_value;
