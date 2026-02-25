<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
$siteName = getSetting('site_name', 'Palians');
$siteTagline = getSetting('site_tagline', 'Premium PHP Scripts & Web Applications');
$siteDesc = getSetting('site_description', 'Professional, ready-to-deploy PHP scripts and web applications.');
$products = getProducts('active', 12);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($siteName) ?> — <?= h($siteTagline) ?></title>
<meta name="description" content="<?= h($siteDesc) ?>">
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
      <a href="<?= STORE_URL ?>" class="nav-link active">Home</a>
      <a href="<?= STORE_URL ?>/search.php" class="nav-link">Products</a>
      <a href="<?= STORE_URL ?>/pages/about.php" class="nav-link">About</a>
      <a href="<?= STORE_URL ?>/pages/contact.php" class="nav-link">Contact</a>
      <a href="<?= STORE_URL ?>/admin/" class="nav-link nav-btn">Admin</a>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero">
  <div class="hero-bg">
    <div class="hero-particles" id="particles"></div>
  </div>
  <div class="hero-content">
    <div class="hero-badge"><i class="bi bi-stars"></i> Premium PHP Scripts</div>
    <h1 class="hero-title">Build Faster.<br><span class="gradient-text">Launch Smarter.</span></h1>
    <p class="hero-subtitle">Professional, ready-to-deploy PHP scripts and web applications.<br>Built by developers, for developers.</p>
    <div class="hero-actions">
      <a href="#products" class="btn btn-primary btn-lg"><i class="bi bi-grid-3x3-gap"></i> Browse Products</a>
      <a href="https://palians.com/reviewer/" target="_blank" class="btn btn-outline btn-lg"><i class="bi bi-play-circle"></i> View Demo</a>
    </div>
  </div>
</section>

<!-- Stats Bar -->
<section class="stats-bar">
  <div class="container">
    <div class="stats-grid">
      <div class="stat-item">
        <span class="stat-number">5+</span>
        <span class="stat-label">Products</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">100+</span>
        <span class="stat-label">Happy Customers</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">4.9★</span>
        <span class="stat-label">Average Rating</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">24/7</span>
        <span class="stat-label">Support</span>
      </div>
    </div>
  </div>
</section>

<!-- Products Section -->
<section class="products-section" id="products">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Featured <span class="gradient-text">Products</span></h2>
      <p class="section-subtitle">Production-ready PHP scripts with full source code, documentation, and support.</p>
    </div>
    <div class="products-grid">
      <?php if (empty($products)): ?>
        <div class="no-products">
          <i class="bi bi-box-seam"></i>
          <p>Products coming soon. Check back shortly!</p>
        </div>
      <?php else: ?>
        <?php foreach ($products as $p): ?>
        <div class="product-card">
          <div class="product-thumb">
            <?php if (!empty($p['thumbnail'])): ?>
              <img src="<?= STORE_URL ?>/uploads/products/<?= h($p['thumbnail']) ?>" alt="<?= h($p['name']) ?>">
            <?php else: ?>
              <div class="product-thumb-placeholder">
                <i class="bi bi-code-square"></i>
              </div>
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
      <?php endif; ?>
    </div>
    <div class="section-cta">
      <a href="<?= STORE_URL ?>/search.php" class="btn btn-outline">View All Products <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>
</section>

<!-- Why Choose Section -->
<section class="features-section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">Why Choose <span class="gradient-text">Palians</span>?</h2>
      <p class="section-subtitle">Every product is built with care, tested thoroughly, and supported professionally.</p>
    </div>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">🔒</div>
        <h3>100% Source Code</h3>
        <p>Full access to every line of code. No encryption, no obfuscation. It's yours.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">⚡</div>
        <h3>Ready to Deploy</h3>
        <p>Install in under 30 minutes with our step-by-step documentation.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🛡️</div>
        <h3>6 Months Support</h3>
        <p>Free bug fixes and technical help included with every purchase.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🔄</div>
        <h3>Free Updates</h3>
        <p>Lifetime updates — get new features and security patches at no extra cost.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📱</div>
        <h3>Mobile Responsive</h3>
        <p>Every product is fully responsive and works on all screen sizes.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📚</div>
        <h3>Full Documentation</h3>
        <p>Comprehensive, step-by-step guides for installation, configuration, and customization.</p>
      </div>
    </div>
  </div>
</section>

<!-- Testimonials -->
<section class="testimonials-section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title">What Customers <span class="gradient-text">Say</span></h2>
    </div>
    <div class="testimonials-grid">
      <div class="testimonial-card">
        <div class="testimonial-stars">★★★★★</div>
        <p class="testimonial-text">"TaskHive saved me months of development time! Absolutely worth every rupee. The code quality is excellent and the documentation is thorough."</p>
        <div class="testimonial-author">
          <div class="author-avatar">A</div>
          <div>
            <strong>Arjun Sharma</strong>
            <span>Developer, Mumbai</span>
          </div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="testimonial-stars">★★★★★</div>
        <p class="testimonial-text">"Best PHP admin panel I've ever seen. The features are incredible and the support team responds within hours. Highly recommended!"</p>
        <div class="testimonial-author">
          <div class="author-avatar">P</div>
          <div>
            <strong>Priya Nair</strong>
            <span>Freelancer, Bangalore</span>
          </div>
        </div>
      </div>
      <div class="testimonial-card">
        <div class="testimonial-stars">★★★★★</div>
        <p class="testimonial-text">"Deployed in 20 minutes. The wallet and referral system works flawlessly. My clients love it. Will definitely buy again."</p>
        <div class="testimonial-author">
          <div class="author-avatar">R</div>
          <div>
            <strong>Rahul Verma</strong>
            <span>Entrepreneur, Delhi</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="<?= STORE_URL ?>" class="footer-logo">🏪 <?= h($siteName) ?></a>
        <p><?= h($siteTagline) ?></p>
        <div class="footer-social">
          <a href="#" class="social-link" title="Twitter"><i class="bi bi-twitter-x"></i></a>
          <a href="#" class="social-link" title="GitHub"><i class="bi bi-github"></i></a>
          <a href="#" class="social-link" title="Telegram"><i class="bi bi-telegram"></i></a>
        </div>
      </div>
      <div class="footer-links">
        <h4>Products</h4>
        <a href="<?= STORE_URL ?>/search.php">All Products</a>
        <a href="<?= STORE_URL ?>/product.php?slug=taskhive">TaskHive</a>
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
