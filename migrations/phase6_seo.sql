-- ============================================
-- Phase 6: SEO & Social Sharing
-- Database Migration Script
-- ============================================

USE reviewflow;

-- SEO settings table
CREATE TABLE IF NOT EXISTS seo_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page_slug VARCHAR(100) NOT NULL UNIQUE,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    og_title VARCHAR(255),
    og_description TEXT,
    og_image VARCHAR(500),
    canonical_url VARCHAR(500),
    no_index TINYINT(1) DEFAULT 0,
    no_follow TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_page_slug (page_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default SEO settings for main pages
INSERT INTO seo_settings (page_slug, meta_title, meta_description, meta_keywords, og_title, og_description, no_index, no_follow) VALUES
('home', 'ReviewFlow - Earn Money by Writing Reviews', 'Join ReviewFlow and earn money by writing authentic product reviews. Get paid for your honest opinions and feedback.', 'reviews, earn money, product reviews, paid reviews', 'ReviewFlow - Earn Money by Writing Reviews', 'Join ReviewFlow and earn money by writing authentic product reviews', 0, 0),
('about', 'About Us - ReviewFlow', 'Learn more about ReviewFlow and how we connect reviewers with businesses looking for authentic feedback.', 'about reviewflow, review platform', 'About ReviewFlow', 'Learn more about ReviewFlow and our mission', 0, 0),
('contact', 'Contact Us - ReviewFlow', 'Get in touch with ReviewFlow support team. We are here to help you with any questions or concerns.', 'contact, support, help', 'Contact ReviewFlow', 'Get in touch with our support team', 0, 0),
('faq', 'FAQ - Frequently Asked Questions', 'Find answers to common questions about ReviewFlow, earning, withdrawals, and more.', 'faq, help, questions', 'ReviewFlow FAQ', 'Find answers to common questions', 0, 0),
('login', 'Login - ReviewFlow', 'Login to your ReviewFlow account to start earning by writing reviews.', 'login, signin', 'Login to ReviewFlow', 'Access your ReviewFlow account', 1, 0),
('register', 'Register - Create Account', 'Create a free ReviewFlow account and start earning money by writing product reviews.', 'register, signup, create account', 'Join ReviewFlow Today', 'Create your free account and start earning', 0, 0)
ON DUPLICATE KEY UPDATE
    meta_title = VALUES(meta_title),
    meta_description = VALUES(meta_description),
    og_title = VALUES(og_title),
    og_description = VALUES(og_description),
    updated_at = CURRENT_TIMESTAMP;
