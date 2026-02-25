CREATE DATABASE IF NOT EXISTS palians_store;
USE palians_store;

CREATE TABLE store_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE store_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    tagline VARCHAR(500) DEFAULT '',
    short_description TEXT,
    full_description LONGTEXT,
    features LONGTEXT,
    tech_stack VARCHAR(500) DEFAULT 'PHP, MySQL, Bootstrap',
    demo_url VARCHAR(500) DEFAULT '',
    price_regular DECIMAL(10,2) NOT NULL DEFAULT 2999.00,
    price_extended DECIMAL(10,2) NOT NULL DEFAULT 4999.00,
    price_developer DECIMAL(10,2) NOT NULL DEFAULT 9999.00,
    thumbnail VARCHAR(500) DEFAULT '',
    screenshots TEXT DEFAULT '',
    download_file VARCHAR(500) DEFAULT '',
    category VARCHAR(100) DEFAULT 'Web Application',
    tags VARCHAR(500) DEFAULT '',
    status ENUM('active','draft','archived') DEFAULT 'active',
    total_sales INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 5.00,
    version VARCHAR(20) DEFAULT '1.0.0',
    last_updated DATE DEFAULT (CURRENT_DATE),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_category (category)
);

CREATE TABLE store_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL UNIQUE,
    product_id INT NOT NULL,
    license_type ENUM('regular','extended','developer') NOT NULL DEFAULT 'regular',
    buyer_name VARCHAR(200) NOT NULL,
    buyer_email VARCHAR(200) NOT NULL,
    buyer_phone VARCHAR(20) DEFAULT '',
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    razorpay_payment_id VARCHAR(100) DEFAULT '',
    razorpay_order_id VARCHAR(100) DEFAULT '',
    razorpay_signature VARCHAR(255) DEFAULT '',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    download_token VARCHAR(100) DEFAULT '',
    download_count INT DEFAULT 0,
    max_downloads INT DEFAULT 5,
    token_expires_at DATETIME,
    ip_address VARCHAR(45) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES store_products(id),
    INDEX idx_order_id (order_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_download_token (download_token)
);

CREATE TABLE store_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default admin (password: admin123 — must change!)
INSERT INTO store_admins (username, password, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@palians.com');

-- Default settings
INSERT INTO store_settings (setting_key, setting_value) VALUES
('site_name', 'Palians'),
('site_tagline', 'Premium PHP Scripts & Web Applications'),
('site_description', 'Professional, ready-to-deploy PHP scripts and web applications. Built by developers, for developers.'),
('site_email', 'support@palians.com'),
('site_phone', ''),
('site_address', ''),
('razorpay_key_id', ''),
('razorpay_key_secret', ''),
('razorpay_test_mode', '1'),
('currency', 'INR'),
('gst_number', ''),
('business_name', 'Palians'),
('footer_text', '© 2026 Palians. All Rights Reserved.');

-- Seed first product: TaskHive
INSERT INTO store_products (name, slug, tagline, short_description, full_description, features, tech_stack, demo_url, price_regular, price_extended, price_developer, category, tags, version) VALUES
('TaskHive — Micro Task Management Platform', 'taskhive', 'Complete task management platform with wallet, referral, and multi-role system', 
'TaskHive is a powerful, ready-to-deploy micro task management platform. Perfect for building task-based earning platforms, freelance management systems, or project tracking tools. Comes with User, Admin, Client, and Affiliate dashboards.',
'<h3>What is TaskHive?</h3>
<p>TaskHive is a complete, production-ready micro task management platform built with PHP and MySQL. It includes everything you need to run a professional task-based platform — from user registration to wallet withdrawals.</p>

<h3>Who is it for?</h3>
<ul>
<li>Entrepreneurs wanting to launch a task-based earning platform</li>
<li>Businesses needing internal task management with payment tracking</li>
<li>Freelance agencies managing workers and clients</li>
<li>Developers looking for a solid PHP boilerplate with auth, payments, and admin panel</li>
</ul>

<h3>Why TaskHive?</h3>
<p>Building a system like this from scratch takes 3-6 months and costs ₹2-5 lakhs. With TaskHive, you get a battle-tested, feature-rich platform ready to deploy in under 30 minutes.</p>',

'✅ Multi-Role System (Admin, User, Client, Affiliate)
✅ Complete User Dashboard with analytics
✅ Wallet System with UPI/Bank/Paytm withdrawals
✅ Razorpay Payment Gateway integrated
✅ Referral System with bonus tracking
✅ KYC Verification System
✅ Gamification (Points, Levels, Badges)
✅ Telegram Bot Integration (Channel + DM)
✅ Email Notification System
✅ Admin Panel with full analytics
✅ Client Dashboard for task creation
✅ Affiliate System with commission tracking
✅ Bulk Task Upload via CSV
✅ Auto Task Assignment
✅ Blog System with auto-generated articles
✅ Chatbot Integration
✅ Multi-language Support
✅ PWA (Progressive Web App) ready
✅ Mobile Responsive Design
✅ Dark Mode Admin Panel
✅ CSRF Protection & Security
✅ Cron Jobs for automation
✅ SEO Optimized
✅ Comprehensive Documentation',

'PHP 8.0+, MySQL 5.7+, Bootstrap 5.3, jQuery, Razorpay API, Telegram Bot API',
'https://palians.com/reviewer/',
2999.00, 4999.00, 9999.00,
'Web Application',
'task management, admin panel, wallet, referral, PHP script, CRM, earning platform',
'4.0.0');
