<aside class="admin-sidebar">
  <a href="<?= STORE_URL ?>/admin/dashboard.php" class="admin-sidebar-logo">
    🏪 <span>Palians</span> Admin
  </a>
  <nav class="admin-nav">
    <div class="admin-nav-section">Main</div>
    <a href="<?= STORE_URL ?>/admin/dashboard.php"
       class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
      <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <a href="<?= STORE_URL ?>/admin/products.php"
       class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>">
      <i class="bi bi-box-seam"></i> Products
    </a>
    <a href="<?= STORE_URL ?>/admin/orders.php"
       class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : '' ?>">
      <i class="bi bi-receipt"></i> Orders
    </a>
    <div class="admin-nav-section">Configuration</div>
    <a href="<?= STORE_URL ?>/admin/settings.php"
       class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
      <i class="bi bi-gear"></i> Settings
    </a>
    <div class="admin-nav-section">Store</div>
    <a href="<?= STORE_URL ?>" target="_blank" class="admin-nav-link">
      <i class="bi bi-shop"></i> View Store
    </a>
    <a href="<?= STORE_URL ?>/admin/?logout=1" class="admin-nav-link danger">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </nav>
</aside>
