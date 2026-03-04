-- Seed: Social Hub announcement blog post
-- Run once; the INSERT IGNORE guards against duplicate slugs.

INSERT IGNORE INTO blog_posts
    (title, slug, excerpt, content, category, tags, author, status, is_auto_generated, read_time_minutes, published_at)
VALUES
(
    '📱 Introducing Social Media Hub — Watch, Engage & Earn!',
    'introducing-social-media-hub-watch-engage-earn',
    'Discover the brand-new Social Media Hub on ReviewFlow! Watch YouTube videos, engage with social content, and earn real money — all from your dashboard.',
    '<h2>Welcome to Social Media Hub! 🎉</h2>
<p>We are thrilled to introduce the <strong>Social Media Hub</strong> — a brand-new way to earn rewards on our platform! Watch YouTube videos, engage with Instagram content, and complete social tasks to unlock real cash rewards directly to your wallet.</p>
<h2>How Does It Work? 🤔</h2>
<ol>
  <li>Head to <strong>Social Hub</strong> from your dashboard.</li>
  <li>Browse available campaigns across YouTube, Instagram, and more.</li>
  <li>Watch the required percentage of a video or complete the social task.</li>
  <li>Hit <strong>Claim Reward</strong> once the progress bar turns green.</li>
  <li>Your wallet is credited instantly!</li>
</ol>
<h2>For Content Creators &amp; Sellers 🎬</h2>
<p>Are you a seller looking to promote your content? Create a campaign in the <strong>Seller Dashboard → Create Campaign</strong> section. Set your reward per view, total viewers needed, and let genuine users discover your content.</p>
<h2>Anti-Cheat Protection 🛡️</h2>
<p>Our platform uses server-side heartbeat tracking and seek-detection to ensure only genuine watch time counts. Fast-forwarding or skipping will reduce your earned seconds, keeping the system fair for everyone.</p>
<h2>Start Earning Today! 🚀</h2>
<p>Open the <strong>Social Hub</strong> from your dashboard and start watching campaigns to earn rewards. New campaigns are added regularly — check back often!</p>',
    'social-media',
    'social hub,watch,earn,youtube,instagram,campaigns',
    'ReviewFlow Team',
    'published',
    0,
    4,
    '2026-02-01 00:00:00'
);
