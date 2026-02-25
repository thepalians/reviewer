-- Social Media Hub Migration
-- Phase 9: Social Media Hub Feature
-- Creates new tables for social media campaigns without modifying existing tables

-- Social media platforms
CREATE TABLE IF NOT EXISTS social_platforms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(100) NOT NULL,
    color VARCHAR(20) NOT NULL,
    embed_supported TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed platforms
INSERT IGNORE INTO social_platforms (name, slug, icon, color, embed_supported, sort_order) VALUES
('YouTube', 'youtube', 'fab fa-youtube', '#FF0000', 1, 1),
('Instagram', 'instagram', 'fab fa-instagram', '#E4405F', 1, 2),
('Facebook', 'facebook', 'fab fa-facebook', '#1877F2', 1, 3),
('Twitter/X', 'twitter', 'fab fa-x-twitter', '#000000', 1, 4),
('Telegram', 'telegram', 'fab fa-telegram', '#26A5E4', 0, 5),
('Pinterest', 'pinterest', 'fab fa-pinterest', '#BD081C', 1, 6);

-- Social campaigns (created by sellers/content creators)
CREATE TABLE IF NOT EXISTS social_campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    platform_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content_url VARCHAR(500) NOT NULL,
    embed_code TEXT,
    thumbnail_url VARCHAR(500),
    task_type ENUM('watch','like','comment','follow','share','subscribe') DEFAULT 'watch',
    reward_per_task DECIMAL(10,2) NOT NULL,
    total_tasks_needed INT NOT NULL,
    tasks_completed INT DEFAULT 0,
    budget DECIMAL(10,2) NOT NULL,
    platform_fee DECIMAL(10,2) DEFAULT 0,
    watch_percent_required INT DEFAULT 50,
    status ENUM('pending','active','paused','completed','rejected') DEFAULT 'pending',
    admin_approved TINYINT(1) DEFAULT 0,
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES sellers(id),
    FOREIGN KEY (platform_id) REFERENCES social_platforms(id),
    INDEX idx_status (status),
    INDEX idx_platform (platform_id),
    INDEX idx_seller (seller_id)
);

-- User completions of social tasks
CREATE TABLE IF NOT EXISTS social_task_completions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    watch_duration INT DEFAULT 0,
    watch_percent DECIMAL(5,2) DEFAULT 0,
    reward_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('started','watching','completed','verified','rejected') DEFAULT 'started',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    verified_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (campaign_id) REFERENCES social_campaigns(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_campaign (user_id, campaign_id),
    INDEX idx_campaign_status (campaign_id, status),
    INDEX idx_user (user_id)
);

-- Watch session tracking (anti-fraud)
CREATE TABLE IF NOT EXISTS social_watch_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    completion_id INT NOT NULL,
    heartbeat_count INT DEFAULT 0,
    last_heartbeat TIMESTAMP NULL,
    tab_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (completion_id) REFERENCES social_task_completions(id)
);

-- Auto-generated blog post about Social Media Hub
INSERT IGNORE INTO blog_posts (title, slug, excerpt, content, category, tags, author, status, is_auto_generated, read_time_minutes, published_at)
VALUES (
    '🚀 Introducing Social Media Hub — Watch, Engage & Earn!',
    'introducing-social-media-hub-watch-engage-earn',
    'Exciting new feature! Now earn money by watching YouTube videos, Instagram reels, and more — all within your dashboard. Content creators can promote their content to genuine viewers.',
    '<article class="blog-content">
<h2>🎉 What is Social Media Hub?</h2>
<p>We are thrilled to introduce <strong>Social Media Hub</strong> — a brand-new feature on our platform that bridges the gap between <em>content creators</em> and <em>genuine viewers</em>. Now you can earn real money simply by watching YouTube videos, Instagram reels, Facebook posts, and more — all from within your dashboard!</p>

<h2>💰 How Users Can Earn</h2>
<p>Earning with Social Media Hub is simple and completely legitimate:</p>
<ol>
  <li><strong>Browse Campaigns</strong> — Visit the 📱 Social Hub from your sidebar and browse available campaigns across YouTube, Instagram, Facebook, Twitter/X, Telegram, and Pinterest.</li>
  <li><strong>Pick a Campaign</strong> — Choose a video or post that interests you and click <em>▶️ Watch &amp; Earn</em>.</li>
  <li><strong>Watch the Content</strong> — Watch the embedded video directly on our platform. A progress tracker shows how much you have watched.</li>
  <li><strong>Claim Your Reward</strong> — Once you have watched the required percentage (e.g., 50%), the 🎁 Claim Reward button activates. Click it to receive your earnings instantly in your wallet!</li>
</ol>
<p>Each campaign shows the <strong>reward amount</strong>, <strong>watch requirement</strong>, and the <strong>number of tasks still available</strong> — so you always know what to expect before you start.</p>

<h2>📢 How Content Creators Can Promote</h2>
<p>Are you a seller / content creator looking to grow your audience with genuine engagement? Social Media Hub makes it easy:</p>
<ol>
  <li><strong>Create a Campaign</strong> — Go to 📢 Campaigns in the Seller Dashboard and click <em>Create Campaign</em>.</li>
  <li><strong>Paste Your Content URL</strong> — Enter your YouTube video URL, Instagram post link, or any supported platform URL. The platform auto-detects the embed code and thumbnail.</li>
  <li><strong>Set Your Budget</strong> — Choose the reward per view (in ₹), the number of views needed, and the minimum watch percentage required. Your total budget is auto-calculated including the 20% platform fee.</li>
  <li><strong>Submit for Approval</strong> — Our admin team reviews your campaign to ensure it meets community guidelines. Approved campaigns go live immediately and start receiving genuine views.</li>
</ol>

<h2>📱 Supported Platforms</h2>
<ul>
  <li>🔴 <strong>YouTube</strong> — Videos, Shorts, and Playlists</li>
  <li>📸 <strong>Instagram</strong> — Posts, Reels, and Stories</li>
  <li>🔵 <strong>Facebook</strong> — Videos and Posts</li>
  <li>🐦 <strong>Twitter/X</strong> — Tweets and Videos</li>
  <li>✈️ <strong>Telegram</strong> — Channel posts and links</li>
  <li>📌 <strong>Pinterest</strong> — Pins and Boards</li>
</ul>

<h2>✅ 100% Legitimate &amp; Safe</h2>
<p>All views are <strong>genuine human views</strong> from real users on our platform. Content is embedded directly — users actually watch the content before earning. Our anti-fraud system tracks watch time, tab visibility, and heartbeats to ensure authenticity. This is completely legal and complies with platform terms of service for embedded content.</p>

<h2>🚀 Getting Started</h2>
<p><strong>For Users:</strong> Click on <em>📱 Social Hub</em> in the left sidebar of your dashboard to start browsing campaigns and earning money.</p>
<p><strong>For Content Creators (Sellers):</strong> Go to your Seller Dashboard and click <em>📢 Campaigns</em> to create your first social media campaign.</p>
<p>Happy earning! 🎉</p>
</article>',
    'updates',
    'social media,youtube,instagram,earn money,new feature,content creators',
    'System',
    'published',
    1,
    3,
    NOW()
);
