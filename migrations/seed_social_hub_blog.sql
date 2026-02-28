-- Seed: Social Media Hub announcement blog post
-- Run after blog_system.sql has created the blog_posts table.

INSERT IGNORE INTO blog_posts
    (title, slug, excerpt, content, category, tags, author, status, is_auto_generated, read_time_minutes, published_at)
VALUES
(
    '📱 Introducing Social Media Hub — Watch, Engage & Earn!',
    'introducing-social-media-hub-watch-engage-earn',
    'Discover the brand-new Social Media Hub on ReviewFlow! Watch YouTube videos, engage with social content, and earn real money — all from your dashboard.',
    '<h2>Welcome to Social Media Hub! 🎉</h2>
<p>We are thrilled to announce the launch of the <strong>Social Media Hub</strong> — a brand-new way to earn money on ReviewFlow by watching and engaging with social media content.</p>

<h3>What is Social Media Hub?</h3>
<p>Social Media Hub connects <strong>content creators &amp; sellers</strong> who want genuine viewers with <strong>users like you</strong> who earn cash rewards for watching. Think of it as a win-win marketplace for attention.</p>

<h3>How to Earn by Watching Videos</h3>
<ol>
<li>📱 Visit <a href="/user/social-hub.php"><strong>Social Hub</strong></a> from your dashboard.</li>
<li>🎬 Browse available campaigns — YouTube, Instagram, Facebook, and more.</li>
<li>▶️ Click <strong>Watch &amp; Earn</strong> on any campaign.</li>
<li>👁️ Watch the video for the required percentage (shown on the task card).</li>
<li>💰 Click <strong>Claim Reward</strong> once the progress bar reaches 100%.</li>
</ol>
<p>Rewards are credited <strong>instantly</strong> to your wallet!</p>

<h3>For Sellers: Create Social Campaigns</h3>
<p>Do you have a YouTube channel, Instagram page, or other social media presence? Use Social Media Hub to get real viewers and engagement:</p>
<ol>
<li>Log in to your <strong>Seller dashboard</strong>.</li>
<li>Go to <strong>Create Campaign</strong>.</li>
<li>Set your platform, paste your content URL, choose a reward per viewer, and set the minimum watch percentage.</li>
<li>Submit for admin approval — campaigns go live within 24 hours.</li>
</ol>

<h3>Supported Platforms</h3>
<ul>
<li>▶️ YouTube</li>
<li>📸 Instagram</li>
<li>📘 Facebook</li>
<li>🐦 Twitter / X</li>
<li>📌 Pinterest</li>
<li>✈️ Telegram</li>
</ul>

<h3>Fair Play &amp; Anti-Cheat</h3>
<p>Our platform uses intelligent server-side watch-time tracking. Skipping or seeking through videos will not credit watch time — only genuine, sequential viewing earns rewards. This keeps the system fair for all participants.</p>

<h3>Start Earning Now!</h3>
<p>Head to your <a href="/user/social-hub.php"><strong>Social Hub</strong></a> dashboard right now and start browsing available campaigns. New campaigns are added daily!</p>

<p><em>Happy watching &amp; earning! 🚀</em></p>',
    'social-media',
    'social hub,watch,earn,youtube,instagram,campaigns',
    'ReviewFlow Team',
    'published',
    0,
    4,
    '2026-02-01 00:00:00'
);
