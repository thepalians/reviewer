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
<title>Refund Policy — <?= h($siteName) ?></title>
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
      <a href="<?= STORE_URL ?>/pages/about.php" class="nav-link">About</a>
      <a href="<?= STORE_URL ?>/pages/contact.php" class="nav-link">Contact</a>
    </div>
  </div>
</nav>

<div class="breadcrumb">
  <div class="container">
    <div class="breadcrumb-inner">
      <a href="<?= STORE_URL ?>">Home</a><span>/</span><span class="current">Refund Policy</span>
    </div>
  </div>
</div>

<section class="legal-page">
  <div class="container">
    <div class="legal-content">
      <div class="legal-header">
        <h1>Refund Policy</h1>
        <p class="legal-date">Last Updated: January 2026</p>
      </div>
      <div class="legal-body">
        <p>At <?= h($siteName) ?>, we take pride in the quality of our digital products. Please read this policy carefully before making a purchase, as our refund policy for digital goods is specific.</p>

        <h2>1. General Policy — No Refunds on Digital Products</h2>
        <p>Due to the nature of digital goods, <strong>all sales are generally final and non-refundable</strong> once the product has been downloaded or the download link has been accessed. This is standard practice for digital product marketplaces worldwide, and by completing your purchase, you acknowledge and accept this policy.</p>

        <h2>2. Exceptions — When Refunds Are Considered</h2>
        <p>We will consider a refund or replacement in the following exceptional circumstances:</p>
        <ul>
          <li><strong>Significantly Different Product</strong> — The product you received is materially different from what was described on the product page.</li>
          <li><strong>Critical Bugs</strong> — The product contains major bugs that prevent its core functionality from working, and our support team is unable to resolve the issue within 7 business days of being notified.</li>
          <li><strong>Duplicate Purchase</strong> — You accidentally purchased the same product twice within the same session. Contact us immediately before downloading.</li>
          <li><strong>Download Not Received</strong> — You paid but never received a download link and cannot access the product (even after checking spam).</li>
        </ul>

        <h2>3. Non-Refundable Situations</h2>
        <p>Refunds will <strong>not</strong> be issued for:</p>
        <ul>
          <li>Change of mind after downloading</li>
          <li>Incompatibility with your specific server configuration (always check requirements before purchasing)</li>
          <li>Not having the technical skills to install or use the product</li>
          <li>Products that have already been fully downloaded and accessed</li>
          <li>Minor bugs that do not affect core functionality</li>
          <li>Features you wish the product had but that were not listed</li>
          <li>Purchases made more than 7 days ago</li>
        </ul>

        <h2>4. How to Request a Refund</h2>
        <ol>
          <li>Email us at <a href="mailto:support@palians.com">support@palians.com</a> within <strong>7 days</strong> of your purchase date.</li>
          <li>Use the subject line: <strong>"Refund Request — [Your Order ID]"</strong></li>
          <li>Include: Your Order ID, email used for purchase, reason for refund request, and screenshots/details of the issue.</li>
          <li>Our team will review your request and respond within 3 business days.</li>
        </ol>

        <h2>5. Refund Process and Timeline</h2>
        <p>If your refund request is approved:</p>
        <ul>
          <li>Refunds are processed back to the original payment method (UPI, card, wallet, etc.)</li>
          <li>Processing time: 5–7 business days after approval</li>
          <li>Bank processing may take an additional 3–5 business days</li>
          <li>You will receive an email confirmation when the refund is initiated</li>
        </ul>

        <h2>6. Pre-Download Cancellation</h2>
        <p>If you contact us <strong>before</strong> accessing your download link, we can cancel your order and issue a full refund, regardless of reason. Contact us at <a href="mailto:support@palians.com">support@palians.com</a> immediately after payment if you change your mind.</p>

        <h2>7. Product Replacement</h2>
        <p>In cases where a refund may not apply but a genuine technical issue exists, we may offer:</p>
        <ul>
          <li>A fixed/updated version of the product</li>
          <li>Extended support time</li>
          <li>Store credit toward another product</li>
        </ul>

        <h2>8. Contact Us</h2>
        <p>For refund-related queries:<br>
        Email: <a href="mailto:support@palians.com">support@palians.com</a><br>
        Response time: Within 24–48 hours (Mon–Sat, 10AM–7PM IST)</p>
      </div>
    </div>
  </div>
</section>

<footer class="footer">
  <div class="container">
    <div class="footer-bottom">
      <p><?= h(getSetting('footer_text', '© 2026 Palians. All Rights Reserved.')) ?> ·
        <a href="<?= STORE_URL ?>/pages/privacy-policy.php">Privacy Policy</a> ·
        <a href="<?= STORE_URL ?>/pages/terms.php">Terms</a> ·
        <a href="<?= STORE_URL ?>/pages/refund-policy.php">Refund Policy</a>
      </p>
    </div>
  </div>
</footer>
<script src="<?= STORE_URL ?>/assets/js/main.js"></script>
</body>
</html>
