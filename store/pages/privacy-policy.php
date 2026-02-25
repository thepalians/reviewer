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
<title>Privacy Policy — <?= h($siteName) ?></title>
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
      <a href="<?= STORE_URL ?>">Home</a><span>/</span><span class="current">Privacy Policy</span>
    </div>
  </div>
</div>

<section class="legal-page">
  <div class="container">
    <div class="legal-content">
      <div class="legal-header">
        <h1>Privacy Policy</h1>
        <p class="legal-date">Last Updated: January 2026</p>
      </div>
      <div class="legal-body">
        <p>This Privacy Policy explains how <?= h($siteName) ?> ("we", "us", or "our") collects, uses, stores, and protects your personal information when you use our website and purchase our digital products. By using our service, you agree to this policy.</p>

        <h2>1. Information We Collect</h2>
        <h3>Information You Provide</h3>
        <ul>
          <li><strong>Name and Email Address</strong> — collected at checkout to process your order and deliver your download link.</li>
          <li><strong>Phone Number</strong> — optional, used only if you choose to provide it.</li>
          <li><strong>Contact Form Data</strong> — name, email, subject, and message when you contact us.</li>
        </ul>
        <h3>Information Collected Automatically</h3>
        <ul>
          <li><strong>IP Address</strong> — logged for fraud prevention and security purposes.</li>
          <li><strong>Browser/Device Data</strong> — basic technical information for site functionality.</li>
          <li><strong>Cookies</strong> — session cookies to maintain your browsing session.</li>
        </ul>

        <h2>2. How We Use Your Information</h2>
        <ul>
          <li>Process and fulfill your digital product orders</li>
          <li>Send order confirmation and download link emails</li>
          <li>Respond to support queries</li>
          <li>Prevent fraud and unauthorized access</li>
          <li>Improve our products and services</li>
          <li>Comply with legal obligations under the Indian IT Act 2000</li>
        </ul>

        <h2>3. Payment Processing (Razorpay)</h2>
        <p>We use Razorpay as our payment gateway. When you make a purchase:</p>
        <ul>
          <li>Your payment card/UPI/wallet details are processed directly by Razorpay and are never stored on our servers.</li>
          <li>Razorpay is PCI DSS compliant and uses 256-bit SSL encryption.</li>
          <li>We only receive a payment confirmation token and transaction ID.</li>
          <li>Razorpay's Privacy Policy applies to payment data: <a href="https://razorpay.com/privacy/" target="_blank" rel="noopener">razorpay.com/privacy</a></li>
        </ul>

        <h2>4. Data Storage and Security</h2>
        <p>We store your order information (name, email, product purchased, payment status) in a secure MySQL database hosted on our servers. We implement:</p>
        <ul>
          <li>SSL/TLS encryption for all data transmitted to/from our site</li>
          <li>Prepared statements to prevent SQL injection</li>
          <li>CSRF tokens to prevent cross-site request forgery</li>
          <li>Regular security audits and updates</li>
        </ul>
        <p>However, no method of transmission over the internet is 100% secure. We cannot guarantee absolute security but take all reasonable precautions.</p>

        <h2>5. Cookies</h2>
        <p>We use essential session cookies only. These cookies:</p>
        <ul>
          <li>Are required for the website to function (session management)</li>
          <li>Do not track you across other websites</li>
          <li>Expire when you close your browser or after 24 hours</li>
        </ul>
        <p>We do not use advertising cookies, third-party tracking, or analytics cookies.</p>

        <h2>6. Third-Party Services</h2>
        <p>We may share limited data with:</p>
        <ul>
          <li><strong>Razorpay</strong> — for payment processing (see Section 3)</li>
          <li><strong>Email Providers</strong> — to send transactional emails (order confirmations)</li>
        </ul>
        <p>We do not sell, rent, or share your personal information with any third parties for marketing purposes.</p>

        <h2>7. Your Rights</h2>
        <p>Under applicable Indian law and global best practices, you have the right to:</p>
        <ul>
          <li>Request a copy of the personal data we hold about you</li>
          <li>Request correction of inaccurate data</li>
          <li>Request deletion of your data (subject to legal retention requirements)</li>
          <li>Withdraw consent at any time</li>
        </ul>
        <p>To exercise these rights, email us at <a href="mailto:support@palians.com">support@palians.com</a>.</p>

        <h2>8. Data Retention</h2>
        <p>We retain order records for a minimum of 5 years for accounting and legal compliance purposes. Contact form messages are retained for 12 months. You may request earlier deletion by contacting us.</p>

        <h2>9. Indian IT Act 2000 Compliance</h2>
        <p>This policy is compliant with the Information Technology Act, 2000 (India) and the Information Technology (Amendment) Act, 2008. Sensitive personal data is handled per the IT (Reasonable Security Practices) Rules, 2011.</p>

        <h2>10. Changes to This Policy</h2>
        <p>We may update this Privacy Policy periodically. We will notify you of significant changes via email or a prominent notice on our website. Continued use after changes constitutes acceptance.</p>

        <h2>11. Contact Us</h2>
        <p>For privacy-related queries, contact us at:<br>
        Email: <a href="mailto:support@palians.com">support@palians.com</a><br>
        Website: <a href="<?= STORE_URL ?>/pages/contact.php">Contact Form</a></p>
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
