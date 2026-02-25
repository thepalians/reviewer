<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$siteName = getSetting('site_name', 'Palians');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About Us — <?= h($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= STORE_URL ?>/assets/css/style.css">
</head>
<body>
<nav class="navbar">
  <div class="nav-container">
    <a href="<?= STORE_URL ?>" class="nav-brand"><span class="brand-icon">🏪</span><span class="brand-name"><?= h($siteName) ?></span></a>
    <button class="nav-toggle" id="navToggle"><span></span><span></span><span></span></button>
    <div class="nav-menu" id="navMenu">
      <a href="<?= STORE_URL ?>" class="nav-link">Home</a>
      <a href="<?= STORE_URL ?>/search.php" class="nav-link">Products</a>
      <a href="<?= STORE_URL ?>/pages/about.php" class="nav-link active">About</a>
      <a href="<?= STORE_URL ?>/pages/contact.php" class="nav-link">Contact</a>
    </div>
  </div>
</nav>

<div class="breadcrumb">
  <div class="container">
    <div class="breadcrumb-inner">
      <a href="<?= STORE_URL ?>">Home</a><span>/</span><span class="current">About Us</span>
    </div>
  </div>
</div>

<section class="legal-page">
  <div class="container">
    <div class="legal-content">
      <div class="legal-header">
        <h1>About <?= h($siteName) ?></h1>
        <p class="legal-date">Building the web, one script at a time.</p>
      </div>
      <div class="legal-body">
        <p>Welcome to <strong><?= h($siteName) ?></strong> — a marketplace for premium, production-ready PHP scripts and web applications built by experienced developers for developers, entrepreneurs, and businesses.</p>

        <h2>Our Mission</h2>
        <p>We believe that great software should be accessible. Building a full-featured web platform from scratch requires months of work and significant investment. <?= h($siteName) ?> exists to bridge that gap — providing battle-tested, feature-rich applications that you can deploy, customize, and launch in hours, not months.</p>

        <h2>What We Build</h2>
        <p>Every product in our catalog is:</p>
        <ul>
          <li><strong>Production-Ready</strong> — not a tutorial project. Real code, tested in real environments.</li>
          <li><strong>Fully Documented</strong> — step-by-step installation guides, configuration docs, and API references.</li>
          <li><strong>Security-First</strong> — CSRF protection, prepared statements, input validation, and secure session management built in.</li>
          <li><strong>Mobile-First</strong> — responsive design that works on every device and screen size.</li>
          <li><strong>Actively Maintained</strong> — we release updates, security patches, and new features regularly.</li>
        </ul>

        <h2>Our Product Categories</h2>
        <ul>
          <li><strong>Task Management Systems</strong> — micro-task platforms, project trackers, team collaboration tools</li>
          <li><strong>CRM &amp; Admin Panels</strong> — business management dashboards, analytics, reporting</li>
          <li><strong>E-commerce Solutions</strong> — multi-vendor stores, booking systems, subscription platforms</li>
          <li><strong>Community Platforms</strong> — forums, social networks, membership systems</li>
          <li><strong>Utility Scripts</strong> — URL shorteners, link managers, automation tools</li>
        </ul>

        <h2>Our Commitment to Quality</h2>
        <p>Before any product is listed on <?= h($siteName) ?>:</p>
        <ol>
          <li>It goes through a rigorous internal code review</li>
          <li>Security audit — checking for common vulnerabilities (OWASP Top 10)</li>
          <li>Performance testing on standard shared hosting</li>
          <li>Documentation review — ensuring the install guide is clear and complete</li>
          <li>Cross-browser and mobile responsiveness testing</li>
        </ol>

        <h2>Support Philosophy</h2>
        <p>We don't just sell code and disappear. Every purchase comes with:</p>
        <ul>
          <li>6 months of technical support (12 months for Developer License)</li>
          <li>Lifetime free updates</li>
          <li>A genuine commitment to resolve issues</li>
        </ul>
        <p>We respond to support requests within 24–48 hours on business days. Our goal is to make sure your deployment succeeds.</p>

        <h2>Business Details</h2>
        <p><strong>Business Name:</strong> <?= h(getSetting('business_name', 'Palians')) ?><br>
        <strong>Website:</strong> <a href="<?= STORE_URL ?>"><?= STORE_URL ?></a><br>
        <strong>Email:</strong> <a href="mailto:support@palians.com">support@palians.com</a><br>
        <strong>GST:</strong> <?= h(getSetting('gst_number', 'Not registered (under threshold)')) ?>
        </p>

        <h2>Get in Touch</h2>
        <p>Have a question, a custom development request, or just want to say hello?<br>
        We'd love to hear from you — <a href="<?= STORE_URL ?>/pages/contact.php">contact us here</a> or email <a href="mailto:support@palians.com">support@palians.com</a>.</p>
      </div>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="container">
    <div class="footer-bottom">
      <p><?= h(getSetting('footer_text', '© 2026 Palians. All Rights Reserved.')) ?> ·
        <a href="<?= STORE_URL ?>/pages/privacy-policy.php">Privacy Policy</a> ·
        <a href="<?= STORE_URL ?>/pages/terms.php">Terms</a>
      </p>
    </div>
  </div>
</footer>
<script src="<?= STORE_URL ?>/assets/js/main.js"></script>
</body>
</html>
