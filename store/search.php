<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$query    = trim($_GET['q'] ?? '');
$siteName = getSetting('site_name', 'Palians');

if ($query !== '') {
    $products = searchProducts($query);
    $resultText = count($products) . ' result' . (count($products) !== 1 ? 's' : '') . ' for "' . h($query) . '"';
} else {
    $products = getProducts('active', 100);
    $resultText = count($products) . ' product' . (count($products) !== 1 ? 's' : '') . ' available';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Products — <?= h($siteName) ?></title>
<meta name="description" content="Browse all premium PHP scripts and web applications from <?= h($siteName) ?>.">
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
      <a href="<?= STORE_URL ?>/search.php" class="nav-link active">Products</a>
      <a href="<?= STORE_URL ?>/pages/about.php" class="nav-link">About</a>
      <a href="<?= STORE_URL ?>/pages/contact.php" class="nav-link">Contact</a>
      <a href="<?= STORE_URL ?>/admin/" class="nav-link nav-btn">Admin</a>
    </div>
  </div>
</nav>

<!-- Search Header -->
<div class="search-header" style="padding-top:calc(var(--nav-height) + 32px)">
  <div class="container">
    <div class="search-box-wrap">
      <h1 class="section-title">Browse <span class="gradient-text">Products</span></h1>
      <form action="<?= STORE_URL ?>/search.php" method="GET">
        <div class="search-box">
          <input type="text" name="q" value="<?= h($query) ?>" placeholder="Search PHP scripts, admin panels, CRM...">
          <button type="submit"><i class="bi bi-search"></i> Search</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Results -->
<div style="padding:48px 0 96px">
  <div class="container">
    <p class="search-results-info"><?= $resultText ?></p>

    <?php if (empty($products)): ?>
      <div class="no-products" style="padding:80px 0;text-align:center">
        <i class="bi bi-search" style="font-size:3rem;display:block;margin-bottom:16px;color:var(--text-muted)"></i>
        <h3 style="color:var(--text-primary);margin-bottom:8px">No products found</h3>
        <p>Try a different search term or <a href="<?= STORE_URL ?>/search.php">browse all products</a>.</p>
      </div>
    <?php else: ?>
      <div class="products-grid">
        <?php foreach ($products as $p): ?>
        <div class="product-card">
          <div class="product-thumb">
            <?php if (!empty($p['thumbnail'])): ?>
              <img src="<?= STORE_URL ?>/uploads/products/<?= h($p['thumbnail']) ?>" alt="<?= h($p['name']) ?>">
            <?php else: ?>
              <div class="product-thumb-placeholder"><i class="bi bi-code-square"></i></div>
            <?php endif; ?>
            <div class="product-badge"><?= h($p['category']) ?></div>
          </div>
          <div class="product-body">
            <h3 class="product-name"><?= h($p['name']) ?></h3>
            <p class="product-tagline"><?= h($p['tagline']) ?></p>
            <div class="product-meta">
              <span class="product-rating"><span class="stars"><?= starsHtml((float)$p['average_rating']) ?></span> <?= h($p['average_rating']) ?></span>
              <span class="product-sales"><i class="bi bi-cart-check"></i> <?= (int)$p['total_sales'] ?> sales</span>
            </div>
            <div class="product-tech">
              <?php foreach (array_slice(explode(',', $p['tech_stack']), 0, 3) as $tech): ?>
                <span class="tech-badge"><?= h(trim($tech)) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="product-footer">
              <div class="product-price">
                <span class="price-from">From</span>
                <span class="price-amount"><?= formatPrice((float)$p['price_regular']) ?></span>
              </div>
              <div class="product-actions">
                <a href="<?= STORE_URL ?>/product.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-primary btn-sm">View Details</a>
                <?php if (!empty($p['demo_url'])): ?>
                  <a href="<?= h($p['demo_url']) ?>" target="_blank" class="btn btn-ghost btn-sm"><i class="bi bi-box-arrow-up-right"></i> Demo</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="<?= STORE_URL ?>" class="footer-logo">🏪 <?= h($siteName) ?></a>
        <p><?= h(getSetting('site_tagline', 'Premium PHP Scripts & Web Applications')) ?></p>
      </div>
      <div class="footer-links">
        <h4>Legal</h4>
        <a href="<?= STORE_URL ?>/pages/privacy-policy.php">Privacy Policy</a>
        <a href="<?= STORE_URL ?>/pages/terms.php">Terms &amp; Conditions</a>
        <a href="<?= STORE_URL ?>/pages/refund-policy.php">Refund Policy</a>
      </div>
      <div class="footer-links">
        <h4>Support</h4>
        <a href="<?= STORE_URL ?>/pages/contact.php">Contact</a>
        <a href="mailto:support@palians.com">support@palians.com</a>
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
