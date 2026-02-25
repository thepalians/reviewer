<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$token  = trim($_GET['token'] ?? '');
$action = trim($_GET['action'] ?? '');
$siteName = getSetting('site_name', 'Palians');

$order = $token ? getOrderByToken($token) : null;

// Validate order
$error = '';
if (!$order) {
    $error = 'Invalid or expired download link.';
} elseif ($order['payment_status'] !== 'paid') {
    $error = 'Payment not confirmed for this order.';
} elseif (strtotime($order['token_expires_at']) < time()) {
    $error = 'This download link has expired (valid for 48 hours after purchase).';
} elseif ($order['download_count'] >= $order['max_downloads']) {
    $error = 'Maximum download limit (' . (int)$order['max_downloads'] . ') reached for this link.';
}

// Serve file download
if (!$error && $action === 'download') {
    $filePath = STORE_ROOT . '/uploads/files/' . basename($order['download_file'] ?? '');

    if (empty($order['download_file']) || !file_exists($filePath)) {
        $error = 'Download file not found. Please contact support@palians.com with your Order ID.';
    } else {
        try {
            $db = getStoreDB();
            $db->prepare('UPDATE store_orders SET download_count = download_count + 1 WHERE order_id = ?')
               ->execute([$order['order_id']]);
        } catch (Exception $e) {
            error_log('download count update failed: ' . $e->getMessage());
        }

        $filename = $order['product_name'] . '-v' . $order['version'] . '.zip';
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($filePath);
        exit;
    }
}

$remaining = !$error ? max(0, (int)$order['max_downloads'] - (int)$order['download_count']) : 0;
$pct       = !$error ? (int)(($order['download_count'] / $order['max_downloads']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Download — <?= h($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= STORE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Navigation -->
<nav class="navbar">
  <div class="nav-container">
    <a href="<?= STORE_URL ?>" class="nav-brand">
      <span class="brand-icon">🏪</span>
      <span class="brand-name"><?= h($siteName) ?></span>
    </a>
    <div class="nav-menu" id="navMenu">
      <a href="<?= STORE_URL ?>" class="nav-link">Home</a>
      <a href="<?= STORE_URL ?>/search.php" class="nav-link">Products</a>
    </div>
  </div>
</nav>

<div class="download-page">
  <div class="container">
    <?php if ($error): ?>
      <div class="download-error">
        <i class="bi bi-exclamation-triangle"></i>
        <h2>Download Unavailable</h2>
        <p><?= h($error) ?></p>
        <div style="margin-top:24px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
          <a href="<?= STORE_URL ?>" class="btn btn-outline">← Back to Store</a>
          <a href="mailto:support@palians.com" class="btn btn-primary">Contact Support</a>
        </div>
      </div>
    <?php else: ?>
      <div class="download-card">
        <span class="download-icon">✅</span>
        <h1>Payment Confirmed!</h1>
        <p>Thank you for your purchase. Your download is ready below.</p>

        <div class="download-info">
          <div class="download-info-item">
            <span>Order ID</span>
            <span><?= h($order['order_id']) ?></span>
          </div>
          <div class="download-info-item">
            <span>Product</span>
            <span><?= h($order['product_name']) ?></span>
          </div>
          <div class="download-info-item">
            <span>License</span>
            <span><?= ucfirst(h($order['license_type'])) ?></span>
          </div>
          <div class="download-info-item">
            <span>Amount Paid</span>
            <span><?= formatPrice((float)$order['amount']) ?></span>
          </div>
          <div class="download-info-item">
            <span>Version</span>
            <span>v<?= h($order['version']) ?></span>
          </div>
          <div class="download-info-item">
            <span>Link Expires</span>
            <span><?= h(date('d M Y, h:i A', strtotime($order['token_expires_at']))) ?></span>
          </div>
        </div>

        <div class="download-counter">
          Downloads used: <?= (int)$order['download_count'] ?> / <?= (int)$order['max_downloads'] ?>
          <div class="progress-bar" style="margin-top:6px">
            <div class="progress-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <?= $remaining ?> download<?= $remaining !== 1 ? 's' : '' ?> remaining
        </div>

        <?php if ($remaining > 0): ?>
        <div style="margin-top:24px">
          <a href="<?= STORE_URL ?>/download.php?token=<?= urlencode($token) ?>&action=download"
             class="btn btn-primary btn-block btn-lg">
            <i class="bi bi-download"></i> Download Now
          </a>
        </div>
        <?php else: ?>
        <div class="alert alert-error" style="margin-top:20px">
          <i class="bi bi-exclamation-circle"></i>
          Download limit reached. Contact <a href="mailto:support@palians.com">support@palians.com</a> for assistance.
        </div>
        <?php endif; ?>

        <p style="font-size:0.75rem;color:var(--text-muted);margin-top:16px;text-align:center">
          A confirmation email has been sent to <?= h($order['buyer_email']) ?><br>
          Need help? Email <a href="mailto:support@palians.com">support@palians.com</a>
        </p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Footer -->
<footer class="footer" style="margin-top:60px">
  <div class="container">
    <div class="footer-bottom">
      <p><?= h(getSetting('footer_text', '© 2026 Palians. All Rights Reserved.')) ?></p>
    </div>
  </div>
</footer>

<script src="<?= STORE_URL ?>/assets/js/main.js"></script>
</body>
</html>
