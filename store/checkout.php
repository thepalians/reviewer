<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/razorpay.php';

// Sanitize inputs
$productId   = (int)($_GET['product_id'] ?? $_POST['product_id'] ?? 0);
$licenseType = trim($_GET['license_type'] ?? $_POST['license_type'] ?? 'regular');
$amount      = (float)($_GET['amount'] ?? $_POST['amount'] ?? 0);

$validLicenses = ['regular', 'extended', 'developer'];
if (!in_array($licenseType, $validLicenses, true)) $licenseType = 'regular';

$product = $productId ? getProductById($productId) : null;
if (!$product || $product['status'] !== 'active') {
    header('Location: ' . STORE_URL);
    exit;
}

// Determine correct amount for selected license
$priceMap = [
    'regular'  => (float)$product['price_regular'],
    'extended' => (float)$product['price_extended'],
    'developer'=> (float)$product['price_developer'],
];
$amount = $priceMap[$licenseType];

$siteName = getSetting('site_name', 'Palians');
$error    = '';
$csrfToken = generateCsrfToken();

// AJAX POST — create Razorpay order and return JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    header('Content-Type: application/json');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['error' => 'Security token mismatch. Please refresh and try again.']);
        exit;
    }

    $buyerName  = trim($_POST['buyer_name'] ?? '');
    $buyerEmail = trim($_POST['buyer_email'] ?? '');
    $buyerPhone = trim($_POST['buyer_phone'] ?? '');

    if (strlen($buyerName) < 2) {
        echo json_encode(['error' => 'Please enter your full name.']);
        exit;
    }
    if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Please enter a valid email address.']);
        exit;
    }

    // Create Razorpay order
    $rzpOrder = createRazorpayOrder($amount, 'PAL-' . time(), [
        'product' => $product['name'],
        'license' => $licenseType,
    ]);

    if (empty($rzpOrder['id'])) {
        echo json_encode(['error' => 'Payment gateway error. Please try again or contact support.']);
        exit;
    }

    // Create pending local order
    $orderId = createOrder([
        'product_id'       => $productId,
        'license_type'     => $licenseType,
        'buyer_name'       => $buyerName,
        'buyer_email'      => $buyerEmail,
        'buyer_phone'      => $buyerPhone,
        'amount'           => $amount,
        'razorpay_order_id'=> $rzpOrder['id'],
    ]);

    echo json_encode([
        'razorpay_key'      => getRazorpayKeyId(),
        'razorpay_order_id' => $rzpOrder['id'],
        'amount'            => (int)($amount * 100),
        'product_name'      => $product['name'],
        'local_order_id'    => $orderId,
        'verify_url'        => STORE_URL . '/verify-payment.php',
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — <?= h($siteName) ?></title>
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
    <button class="nav-toggle" id="navToggle"><span></span><span></span><span></span></button>
    <div class="nav-menu" id="navMenu">
      <a href="<?= STORE_URL ?>" class="nav-link">Home</a>
      <a href="<?= STORE_URL ?>/search.php" class="nav-link">Products</a>
      <a href="<?= STORE_URL ?>/pages/contact.php" class="nav-link">Contact</a>
    </div>
  </div>
</nav>

<div class="checkout-page">
  <div class="container">
    <!-- Breadcrumb -->
    <div style="margin-bottom:24px;font-size:0.82rem;color:var(--text-muted)">
      <a href="<?= STORE_URL ?>" style="color:var(--text-muted)">Home</a> /
      <a href="<?= STORE_URL ?>/product.php?slug=<?= urlencode($product['slug']) ?>" style="color:var(--text-muted)"><?= h($product['name']) ?></a> /
      <span>Checkout</span>
    </div>

    <div class="checkout-grid">
      <!-- Checkout Form -->
      <div class="checkout-form-wrap">
        <h2><i class="bi bi-lock"></i> Complete Your Order</h2>

        <?php if ($error): ?>
          <div class="alert alert-error"><i class="bi bi-exclamation-circle"></i> <?= h($error) ?></div>
        <?php endif; ?>

        <form id="checkoutForm" action="<?= STORE_URL ?>/checkout.php" method="POST" novalidate>
          <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
          <input type="hidden" name="product_id" value="<?= (int)$productId ?>">
          <input type="hidden" name="license_type" value="<?= h($licenseType) ?>">
          <input type="hidden" name="amount" value="<?= $amount ?>">

          <div class="form-group">
            <label class="form-label" for="buyer_name">Full Name <span class="required">*</span></label>
            <input type="text" id="buyer_name" name="buyer_name" class="form-control"
                   placeholder="Enter your full name" required autocomplete="name">
          </div>

          <div class="form-group">
            <label class="form-label" for="buyer_email">Email Address <span class="required">*</span></label>
            <input type="email" id="buyer_email" name="buyer_email" class="form-control"
                   placeholder="Enter your email" required autocomplete="email">
            <span style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;display:block">
              <i class="bi bi-info-circle"></i> Download link will be sent to this email
            </span>
          </div>

          <div class="form-group">
            <label class="form-label" for="buyer_phone">Phone Number <span style="color:var(--text-muted)">(optional)</span></label>
            <input type="tel" id="buyer_phone" name="buyer_phone" class="form-control"
                   placeholder="+91 XXXXX XXXXX" autocomplete="tel">
          </div>

          <div style="margin-top:24px">
            <button type="submit" class="btn btn-primary btn-block btn-lg">
              <i class="bi bi-lock"></i> Proceed to Pay <?= formatPrice($amount) ?>
            </button>
          </div>

          <p style="font-size:0.75rem;color:var(--text-muted);text-align:center;margin-top:12px">
            <i class="bi bi-shield-lock"></i> Secured by Razorpay · 256-bit SSL encrypted
          </p>
        </form>
      </div>

      <!-- Order Summary -->
      <div class="order-summary">
        <h3>Order Summary</h3>

        <div class="summary-product">
          <div class="summary-product-img">
            <?php if (!empty($product['thumbnail'])): ?>
              <img src="<?= STORE_URL ?>/uploads/products/<?= h($product['thumbnail']) ?>"
                   alt="" style="width:100%;height:100%;object-fit:cover;border-radius:6px">
            <?php else: ?>
              <i class="bi bi-code-square"></i>
            <?php endif; ?>
          </div>
          <div>
            <div class="summary-product-name"><?= h($product['name']) ?></div>
            <div class="summary-product-license"><?= ucfirst($licenseType) ?> License</div>
          </div>
        </div>

        <div class="summary-line">
          <span>License Type</span>
          <span><?= ucfirst(h($licenseType)) ?></span>
        </div>
        <div class="summary-line">
          <span>Downloads</span>
          <span>Up to 5 times</span>
        </div>
        <div class="summary-line">
          <span>Link Validity</span>
          <span>48 hours</span>
        </div>
        <div class="summary-line">
          <span>Support</span>
          <span>6 Months</span>
        </div>

        <div class="summary-total">
          <span>Total</span>
          <span><?= formatPrice($amount) ?></span>
        </div>

        <div class="secure-badge">
          <i class="bi bi-shield-check"></i>
          <span>100% secure payment via Razorpay. Your data is protected.</span>
        </div>

        <div style="margin-top:16px;text-align:center">
          <img src="https://razorpay.com/assets/razorpay-glyph.svg"
               alt="Razorpay" style="height:28px;opacity:0.5" onerror="this.style.display='none'">
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Footer -->
<footer class="footer" style="margin-top:60px">
  <div class="container">
    <div class="footer-bottom">
      <p><?= h(getSetting('footer_text', '© 2026 Palians. All Rights Reserved.')) ?> ·
        <a href="<?= STORE_URL ?>/pages/privacy-policy.php">Privacy Policy</a> ·
        <a href="<?= STORE_URL ?>/pages/terms.php">Terms</a>
      </p>
    </div>
  </div>
</footer>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script src="<?= STORE_URL ?>/assets/js/main.js"></script>
</body>
</html>
