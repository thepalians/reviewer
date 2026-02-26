-- Migration: Seed introductory blog post for Social Media Hub feature
-- Run once; the WHERE NOT EXISTS guard prevents duplicate inserts.

INSERT INTO blog_posts (title, slug, excerpt, content, category, tags, author, status, is_auto_generated, read_time_minutes, published_at)
SELECT
    '🚀 Introducing Social Media Hub — Watch, Engage & Earn!',
    'introducing-social-media-hub',
    'Exciting new feature! Now earn money by watching YouTube videos, Instagram reels, and more. Content creators can promote their content to genuine viewers.',
    '<h2>What is Social Media Hub? 📱</h2>
<p>We are thrilled to introduce the <strong>Social Media Hub</strong> — a brand-new way to earn rewards on our platform! Watch YouTube videos, engage with Instagram content, and complete social tasks to unlock real cash rewards directly to your wallet.</p>
<h2>How Does It Work?</h2>
<ol>
  <li>Head to <strong>Social Hub</strong> from your dashboard.</li>
  <li>Browse available campaigns across YouTube, Instagram, and more.</li>
  <li>Watch the required percentage of a video or complete the social task.</li>
  <li>Hit <strong>Claim Reward</strong> once the progress bar turns green.</li>
  <li>Your wallet is credited instantly!</li>
</ol>
<h2>For Content Creators &amp; Sellers</h2>
<p>Are you a seller looking to promote your content? Create a campaign in the <strong>Seller Dashboard → Create Campaign</strong> section. Set your reward per view, total viewers needed, and let genuine users discover your content.</p>
<h2>Anti-Cheat Protection</h2>
<p>Our platform uses server-side heartbeat tracking and seek-detection to ensure only genuine watch time counts. Fast-forwarding or skipping will reduce your earned seconds, keeping the system fair for everyone.</p>
<p>Start earning today — open the <strong>Social Hub</strong> from your dashboard! 🎉</p>',
    'updates',
    'social-media,youtube,earn-money,new-feature,social-hub',
    'ReviewFlow Team',
    'published',
    1,
    3,
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM blog_posts WHERE slug = 'introducing-social-media-hub'
);
