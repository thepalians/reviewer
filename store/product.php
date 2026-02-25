<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . STORE_URL);
    exit;
}

$product = getProductBySlug($slug);
if (!$product) {
    http_response_code(404);
    $siteName = getSetting('site_name', 'Palians');
    include __DIR__ . '/includes/404.php';
    exit;
}

$siteName    = getSetting('site_name', 'Palians');
$screenshots = !empty($product['screenshots']) ? json_decode($product['screenshots'], true) : [];
if (!is_array($screenshots)) $screenshots = [];
$features = array_filter(array_map('trim', explode("\n", $product['features'] ?? '')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($product['name']) ?> — <?= h($siteName) ?></title>
<meta name="description" content="<?= h($product['short_description']) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
      <span></span><span></span><span></span>
    </button>
    <div class="nav-menu" id="navMenu">
      <a href="<?= STORE_URL ?>" class="nav-link">Home</a>
      <a href="<?= STORE_URL ?>/search.php" class="nav-link active">Products</a>
      <a href="<?= STORE_URL ?>/pages/about.php" class="nav-link">About</a>
      <a href="<?= STORE_URL ?>/pages/contact.php" class="nav-link">Contact</a>
      <a href="<?= STORE_URL ?>/admin/" class="nav-link nav-btn">Admin</a>
    </div>
  </div>
</nav>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <div class="container">
    <div class="breadcrumb-inner">
      <a href="<?= STORE_URL ?>">Home</a>
      <span>/</span>
      <a href="<?= STORE_URL ?>/search.php">Products</a>
      <span>/</span>
      <span class="current"><?= h($product['name']) ?></span>
    </div>
  </div>
</div>

<!-- Product Detail -->
<section class="product-detail">
  <div class="container">
    <div class="product-detail-grid">

      <!-- Main Content -->
      <div class="product-detail-main">
        <h1 class="product-detail-title"><?= h($product['name']) ?></h1>
        <p class="product-detail-tagline"><?= h($product['tagline']) ?></p>

        <div class="product-detail-meta">
          <div class="detail-rating">
            <span class="stars"><?= starsHtml((float)$product['average_rating']) ?></span>
            <span><?= h($product['average_rating']) ?> rating</span>
          </div>
          <span class="detail-version">v<?= h($product['version']) ?></span>
          <span class="detail-sales"><i class="bi bi-cart-check"></i> <?= (int)$product['total_sales'] ?> sales</span>
          <span class="detail-sales"><i class="bi bi-calendar3"></i> Updated <?= h($product['last_updated'] ?? date('Y-m-d')) ?></span>
        </div>

        <!-- Screenshot Gallery -->
        <?php if (!empty($screenshots) || !empty($product['thumbnail'])): ?>
        <div class="screenshot-gallery">
          <?php
          $allImages = [];
          if (!empty($product['thumbnail'])) $allImages[] = $product['thumbnail'];
          foreach ($screenshots as $s) { if (!empty($s)) $allImages[] = $s; }
          $firstImg = $allImages[0] ?? '';
          ?>
          <div class="gallery-main" id="galleryMainWrap">
            <img id="galleryMain"
                 src="<?= STORE_URL ?>/uploads/products/<?= h($firstImg) ?>"
                 alt="<?= h($product['name']) ?> screenshot">
          </div>
          <?php if (count($allImages) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($allImages as $i => $img): ?>
            <div class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>"
                 data-src="<?= STORE_URL ?>/uploads/products/<?= h($img) ?>">
              <img src="<?= STORE_URL ?>/uploads/products/<?= h($img) ?>" alt="Screenshot <?= $i+1 ?>">
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
          <div class="tab-nav">
            <button class="tab-btn active" data-tab="description">Description</button>
            <button class="tab-btn" data-tab="features">Features</button>
            <button class="tab-btn" data-tab="tech">Tech Stack</button>
          </div>

          <div class="tab-content active" id="tab-description">
            <div class="product-description">
              <?= $product['full_description'] ?>
            </div>
          </div>

          <div class="tab-content" id="tab-features">
            <div class="features-list">
              <?php foreach ($features as $feature): ?>
                <?php $text = ltrim($feature, '✅ '); ?>
                <div class="feature-item"><?= h($text) ?></div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="tab-content" id="tab-tech">
            <div class="tech-stack-badges mt-2">
              <?php foreach (explode(',', $product['tech_stack']) as $tech): ?>
                <span class="tech-badge" style="font-size:0.9rem;padding:8px 14px"><?= h(trim($tech)) ?></span>
              <?php endforeach; ?>
            </div>
            <?php if (!empty($product['demo_url'])): ?>
            <div class="mt-3">
              <a href="<?= h($product['demo_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline">
                <i class="bi bi-box-arrow-up-right"></i> View Live Demo
              </a>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <aside class="product-sidebar">
        <div class="sidebar-card">
          <div class="sidebar-price-display">
            <div class="sidebar-price-label">Price</div>
            <div class="sidebar-price-amount" id="selectedPrice"><?= formatPrice((float)$product['price_regular']) ?></div>
            <div class="sidebar-price-note">One-time payment · Instant download</div>
          </div>

          <div class="license-selector">
            <div class="license-selector-label">Choose License</div>

            <label class="license-option">
              <input type="radio" name="license_type" value="regular"
                     data-price="<?= (int)$product['price_regular'] ?>" checked>
              <div class="license-option-inner">
                <div>
                  <div class="license-name">Regular License</div>
                  <div class="license-desc">1 project · Personal &amp; client use</div>
                </div>
                <div class="license-price"><?= formatPrice((float)$product['price_regular']) ?></div>
              </div>
            </label>

            <label class="license-option">
              <input type="radio" name="license_type" value="extended"
                     data-price="<?= (int)$product['price_extended'] ?>">
              <div class="license-option-inner">
                <div>
                  <div class="license-name">Extended License <span class="license-badge badge-popular">Popular ⭐</span></div>
                  <div class="license-desc">3 projects · Commercial use allowed</div>
                </div>
                <div class="license-price"><?= formatPrice((float)$product['price_extended']) ?></div>
              </div>
            </label>

            <label class="license-option">
              <input type="radio" name="license_type" value="developer"
                     data-price="<?= (int)$product['price_developer'] ?>">
              <div class="license-option-inner">
                <div>
                  <div class="license-name">Developer License <span class="license-badge badge-best">Best 👑</span></div>
                  <div class="license-desc">Unlimited projects · White-label rights</div>
                </div>
                <div class="license-price"><?= formatPrice((float)$product['price_developer']) ?></div>
              </div>
            </label>
          </div>

          <div class="sidebar-actions">
            <a href="<?= STORE_URL ?>/checkout.php?product_id=<?= (int)$product['id'] ?>&amp;license_type=regular&amp;amount=<?= (int)$product['price_regular'] ?>"
               class="btn btn-primary btn-block btn-lg" id="buyNowBtn">
              <i class="bi bi-cart-plus"></i> Buy Now
            </a>
            <?php if (!empty($product['demo_url'])): ?>
            <a href="<?= h($product['demo_url']) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-block">
              <i class="bi bi-play-circle"></i> View Demo
            </a>
            <?php endif; ?>
          </div>

          <div class="sidebar-guarantee">
            <i class="bi bi-shield-check"></i>
            <span>Secure payment via Razorpay · Instant download after payment</span>
          </div>

          <div class="sidebar-meta">
            <div class="sidebar-meta-item">
              <span>Category</span>
              <span><?= h($product['category']) ?></span>
            </div>
            <div class="sidebar-meta-item">
              <span>Version</span>
              <span>v<?= h($product['version']) ?></span>
            </div>
            <div class="sidebar-meta-item">
              <span>Last Updated</span>
              <span><?= h($product['last_updated'] ?? date('Y-m-d')) ?></span>
            </div>
            <div class="sidebar-meta-item">
              <span>Total Sales</span>
              <span><?= (int)$product['total_sales'] ?></span>
            </div>
            <div class="sidebar-meta-item">
              <span>Support</span>
              <span>6 Months</span>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</section>

<!-- Lightbox -->
<div class="lightbox" id="lightbox">
  <div class="lightbox-inner">
    <button class="lightbox-close" id="lightboxClose" aria-label="Close">&times;</button>
    <img id="lightboxImg" src="" alt="Screenshot">
  </div>
</div>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="<?= STORE_URL ?>" class="footer-logo">🏪 <?= h($siteName) ?></a>
        <p><?= h(getSetting('site_tagline', 'Premium PHP Scripts & Web Applications')) ?></p>
        <div class="footer-social">
          <a href="#" class="social-link"><i class="bi bi-twitter-x"></i></a>
          <a href="#" class="social-link"><i class="bi bi-github"></i></a>
          <a href="#" class="social-link"><i class="bi bi-telegram"></i></a>
        </div>
      </div>
      <div class="footer-links">
        <h4>Products</h4>
        <a href="<?= STORE_URL ?>/search.php">All Products</a>
        <a href="<?= STORE_URL ?>/pages/about.php">About Us</a>
      </div>
      <div class="footer-links">
        <h4>Support</h4>
        <a href="<?= STORE_URL ?>/pages/contact.php">Contact Us</a>
        <a href="<?= STORE_URL ?>/pages/refund-policy.php">Refund Policy</a>
        <a href="mailto:support@palians.com">support@palians.com</a>
      </div>
      <div class="footer-links">
        <h4>Legal</h4>
        <a href="<?= STORE_URL ?>/pages/privacy-policy.php">Privacy Policy</a>
        <a href="<?= STORE_URL ?>/pages/terms.php">Terms &amp; Conditions</a>
        <a href="<?= STORE_URL ?>/pages/refund-policy.php">Refund Policy</a>
      </div>
    </div>
    <div class="footer-bottom">
      <p><?= h(getSetting('footer_text', '© 2026 Palians. All Rights Reserved.')) ?></p>
    </div>
  </div>
</footer>

<script src="<?= STORE_URL ?>/assets/js/main.js"></script>
</body>
</html>
