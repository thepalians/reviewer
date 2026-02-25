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
<title>Terms &amp; Conditions — <?= h($siteName) ?></title>
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
      <a href="<?= STORE_URL ?>">Home</a><span>/</span><span class="current">Terms &amp; Conditions</span>
    </div>
  </div>
</div>

<section class="legal-page">
  <div class="container">
    <div class="legal-content">
      <div class="legal-header">
        <h1>Terms &amp; Conditions</h1>
        <p class="legal-date">Last Updated: January 2026</p>
      </div>
      <div class="legal-body">
        <p>Please read these Terms and Conditions carefully before purchasing from <?= h($siteName) ?>. By completing a purchase, you agree to be bound by these terms.</p>

        <h2>1. Acceptance of Terms</h2>
        <p>By accessing this website and purchasing any product, you confirm that you are at least 18 years of age, have read and understood these terms, and agree to be legally bound by them.</p>

        <h2>2. Digital Product Description</h2>
        <p>All products sold by <?= h($siteName) ?> are digital goods — specifically PHP scripts and web application source code. You receive:</p>
        <ul>
          <li>Full, unencrypted PHP source code</li>
          <li>SQL database migration files</li>
          <li>Installation documentation</li>
          <li>Support for the duration specified in your license</li>
        </ul>
        <p>Products are delivered electronically via a time-limited download link sent to your email address.</p>

        <h2>3. License Types</h2>
        <h3>Regular License (₹2,999 – varies)</h3>
        <ul>
          <li>Use the product in <strong>1 (one) project</strong> only</li>
          <li>Can be used for personal or client projects</li>
          <li>End users are not charged for access (free or subscription-based services are permitted)</li>
          <li>Cannot redistribute, resell, or sublicense the source code</li>
          <li>6 months of technical support included</li>
        </ul>
        <h3>Extended License (₹4,999 – varies)</h3>
        <ul>
          <li>Use the product in up to <strong>3 (three) projects</strong></li>
          <li>All Regular License rights plus:</li>
          <li>Can be used in projects where end users pay for access</li>
          <li>SaaS use permitted</li>
          <li>6 months of technical support included</li>
        </ul>
        <h3>Developer License (₹9,999 – varies)</h3>
        <ul>
          <li>Use the product in <strong>unlimited projects</strong></li>
          <li>All Extended License rights plus:</li>
          <li>White-label rights — remove/replace Palians branding</li>
          <li>Can sell derivative products to clients</li>
          <li>12 months of priority technical support included</li>
        </ul>

        <h2>4. Pricing and Payment</h2>
        <ul>
          <li>All prices are in Indian Rupees (INR) and inclusive of applicable taxes</li>
          <li>Payments are processed securely via Razorpay</li>
          <li>We accept UPI, credit/debit cards, net banking, and wallets</li>
          <li>Prices are subject to change without notice (price at time of purchase is final)</li>
        </ul>

        <h2>5. Digital Delivery</h2>
        <p>Upon successful payment verification:</p>
        <ul>
          <li>You will be redirected to a download page immediately</li>
          <li>A download link (valid for 48 hours, maximum 5 downloads) will be emailed to you</li>
          <li>If you do not receive the email within 30 minutes, check spam or contact support</li>
        </ul>

        <h2>6. Intellectual Property</h2>
        <p>All products remain the intellectual property of <?= h($siteName) ?>. A license grants you the right to <strong>use</strong> the code, not to claim ownership of it. You may not:</p>
        <ul>
          <li>Claim the code as your own original creation</li>
          <li>Resell or redistribute the original, unmodified source code</li>
          <li>Upload the code to code marketplaces (Envato, CodeCanyon, etc.) for resale</li>
          <li>Share download links with third parties</li>
        </ul>

        <h2>7. User Obligations</h2>
        <p>You agree to:</p>
        <ul>
          <li>Use the product only as permitted by your license type</li>
          <li>Keep your download links confidential</li>
          <li>Not use the product for illegal activities</li>
          <li>Comply with all applicable laws in your jurisdiction</li>
        </ul>

        <h2>8. Prohibited Uses</h2>
        <p>You may NOT use our products for:</p>
        <ul>
          <li>Illegal gambling, fraud, or scam platforms</li>
          <li>Pyramid schemes or illegal MLM operations</li>
          <li>Phishing, malware distribution, or hacking tools</li>
          <li>Activities that violate Indian law or the laws of your country</li>
          <li>Creating platforms that harm users or third parties</li>
        </ul>

        <h2>9. Technical Support</h2>
        <ul>
          <li>Support is provided via email at <a href="mailto:support@palians.com">support@palians.com</a></li>
          <li>Support covers installation issues and bug fixes in the original code</li>
          <li>Support does not cover customizations, third-party integrations, or your server configuration</li>
          <li>Response time: within 24-48 hours on business days (Mon–Sat)</li>
        </ul>

        <h2>10. Limitation of Liability</h2>
        <p>To the maximum extent permitted by law, <?= h($siteName) ?> shall not be liable for any indirect, incidental, special, or consequential damages arising from the use of our products, including but not limited to loss of revenue, data, or business opportunities. Our maximum liability is limited to the amount paid for the product.</p>

        <h2>11. Governing Law</h2>
        <p>These Terms are governed by the laws of India. Any disputes shall be subject to the exclusive jurisdiction of the courts in India.</p>

        <h2>12. Dispute Resolution</h2>
        <p>Before initiating legal action, you agree to first attempt to resolve disputes by contacting us at <a href="mailto:support@palians.com">support@palians.com</a>. We commit to responding within 5 business days.</p>

        <h2>13. Modifications</h2>
        <p>We reserve the right to modify these terms at any time. Changes are effective immediately upon posting. Continued use of the site constitutes acceptance of updated terms.</p>

        <h2>14. Contact</h2>
        <p>For questions about these terms: <a href="mailto:support@palians.com">support@palians.com</a></p>
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
