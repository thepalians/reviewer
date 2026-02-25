<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$siteName = getSetting('site_name', 'Palians');

$success = $error = '';
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $cName    = trim($_POST['contact_name'] ?? '');
        $cEmail   = trim($_POST['contact_email'] ?? '');
        $cSubject = trim($_POST['contact_subject'] ?? 'General Enquiry');
        $cMessage = trim($_POST['contact_message'] ?? '');

        if (strlen($cName) < 2) {
            $error = 'Please enter your name.';
        } elseif (!filter_var($cEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($cMessage) < 10) {
            $error = 'Please enter a message (minimum 10 characters).';
        } else {
            $to      = getSetting('site_email', 'support@palians.com');
            $subject = '[Contact] ' . $cSubject . ' — ' . $cName;
            $body    = "Name: $cName\nEmail: $cEmail\nSubject: $cSubject\n\nMessage:\n$cMessage\n";
            $headers = "From: $cName <$cEmail>\r\nReply-To: $cEmail\r\n";
            @mail($to, $subject, $body, $headers);
            $success = "Thank you, $cName! Your message has been sent. We'll reply within 24–48 hours.";
            $csrfToken = generateCsrfToken(); // Regenerate token
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Us — <?= h($siteName) ?></title>
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
      <a href="<?= STORE_URL ?>/pages/contact.php" class="nav-link active">Contact</a>
    </div>
  </div>
</nav>

<div class="breadcrumb">
  <div class="container">
    <div class="breadcrumb-inner">
      <a href="<?= STORE_URL ?>">Home</a><span>/</span><span class="current">Contact Us</span>
    </div>
  </div>
</div>

<section class="legal-page">
  <div class="container">
    <div class="legal-header" style="text-align:center;margin-bottom:48px">
      <h1>Get in <span class="gradient-text">Touch</span></h1>
      <p style="font-size:1rem;color:var(--text-secondary)">Have a question, need support, or a custom project? We're here to help.</p>
    </div>

    <div class="contact-grid">
      <!-- Contact Form -->
      <div>
        <div class="checkout-form-wrap">
          <h2 style="font-size:1.3rem;margin-bottom:20px">Send Us a Message</h2>

          <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= h($success) ?></div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
          <?php endif; ?>

          <form method="POST" action="<?= STORE_URL ?>/pages/contact.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

            <div class="form-group">
              <label class="form-label" for="contact_name">Your Name <span class="required">*</span></label>
              <input type="text" id="contact_name" name="contact_name" class="form-control"
                     placeholder="Your full name" required
                     value="<?= isset($_POST['contact_name']) ? h($_POST['contact_name']) : '' ?>">
            </div>

            <div class="form-group">
              <label class="form-label" for="contact_email">Email Address <span class="required">*</span></label>
              <input type="email" id="contact_email" name="contact_email" class="form-control"
                     placeholder="your@email.com" required
                     value="<?= isset($_POST['contact_email']) ? h($_POST['contact_email']) : '' ?>">
            </div>

            <div class="form-group">
              <label class="form-label" for="contact_subject">Subject</label>
              <select id="contact_subject" name="contact_subject" class="form-control">
                <option value="General Enquiry">General Enquiry</option>
                <option value="Pre-sales Question">Pre-sales Question</option>
                <option value="Technical Support">Technical Support</option>
                <option value="Refund Request">Refund Request</option>
                <option value="Custom Development">Custom Development</option>
                <option value="Bug Report">Bug Report</option>
                <option value="Other">Other</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="contact_message">Message <span class="required">*</span></label>
              <textarea id="contact_message" name="contact_message" class="form-control"
                        rows="6" placeholder="Tell us how we can help you..." required><?= isset($_POST['contact_message']) ? h($_POST['contact_message']) : '' ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg">
              <i class="bi bi-send"></i> Send Message
            </button>
          </form>
        </div>
      </div>

      <!-- Contact Info -->
      <div>
        <div class="contact-info-card">
          <h3 style="color:var(--text-primary);margin-bottom:20px;font-size:1.1rem">Contact Information</h3>

          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="bi bi-envelope"></i></div>
            <div>
              <div class="contact-info-label">Email</div>
              <div class="contact-info-value">
                <a href="mailto:support@palians.com" style="color:var(--accent-blue)">support@palians.com</a>
              </div>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="bi bi-clock"></i></div>
            <div>
              <div class="contact-info-label">Business Hours</div>
              <div class="contact-info-value">Mon–Sat, 10:00 AM – 7:00 PM IST</div>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="bi bi-geo-alt"></i></div>
            <div>
              <div class="contact-info-label">Location</div>
              <div class="contact-info-value">India</div>
            </div>
          </div>

          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="bi bi-reply"></i></div>
            <div>
              <div class="contact-info-label">Response Time</div>
              <div class="contact-info-value">Within 24–48 hours</div>
            </div>
          </div>
        </div>

        <div class="contact-info-card" style="margin-top:0">
          <h4 style="color:var(--text-primary);margin-bottom:12px;font-size:0.95rem">Quick Help</h4>
          <p style="font-size:0.875rem;margin-bottom:12px">Before contacting support, check:</p>
          <ul style="padding-left:16px">
            <li style="font-size:0.82rem;color:var(--text-muted);margin-bottom:6px;list-style:disc">Product documentation (included in download)</li>
            <li style="font-size:0.82rem;color:var(--text-muted);margin-bottom:6px;list-style:disc"><a href="<?= STORE_URL ?>/pages/refund-policy.php" style="color:var(--accent-blue)">Refund Policy</a></li>
            <li style="font-size:0.82rem;color:var(--text-muted);margin-bottom:6px;list-style:disc"><a href="<?= STORE_URL ?>/pages/terms.php" style="color:var(--accent-blue)">Terms &amp; Conditions</a></li>
          </ul>
        </div>
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
