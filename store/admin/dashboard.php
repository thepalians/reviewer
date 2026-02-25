<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireAdminLogin();

$db = getStoreDB();

// Stats
$totalRevenue = $db->query("SELECT COALESCE(SUM(amount),0) FROM store_orders WHERE payment_status='paid'")->fetchColumn();
$totalOrders  = $db->query("SELECT COUNT(*) FROM store_orders")->fetchColumn();
$totalProducts = $db->query("SELECT COUNT(*) FROM store_products")->fetchColumn();
$monthRevenue  = $db->query("SELECT COALESCE(SUM(amount),0) FROM store_orders WHERE payment_status='paid' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

// Recent orders
$recentOrders = $db->query("SELECT o.*, p.name as product_name FROM store_orders o JOIN store_products p ON o.product_id=p.id ORDER BY o.created_at DESC LIMIT 10")->fetchAll();

$pageTitle = 'Dashboard';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="admin-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-main">
  <div class="admin-header">
    <span class="admin-header-title">Dashboard</span>
    <div class="admin-header-right">
      <span style="font-size:0.82rem;color:var(--text-muted)">Welcome, <?= h($_SESSION['admin_username'] ?? 'Admin') ?></span>
      <div class="admin-avatar"><?= strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?></div>
    </div>
  </div>
  <div class="admin-content">

    <!-- Stats Cards -->
    <div class="admin-stats-grid">
      <div class="admin-stat-card green">
        <div class="admin-stat-label">Total Revenue</div>
        <div class="admin-stat-value">₹<?= number_format((float)$totalRevenue, 0) ?></div>
        <div class="admin-stat-icon"><i class="bi bi-currency-rupee"></i></div>
      </div>
      <div class="admin-stat-card blue">
        <div class="admin-stat-label">Total Orders</div>
        <div class="admin-stat-value"><?= (int)$totalOrders ?></div>
        <div class="admin-stat-icon"><i class="bi bi-receipt"></i></div>
      </div>
      <div class="admin-stat-card cyan">
        <div class="admin-stat-label">Products</div>
        <div class="admin-stat-value"><?= (int)$totalProducts ?></div>
        <div class="admin-stat-icon"><i class="bi bi-box-seam"></i></div>
      </div>
      <div class="admin-stat-card purple">
        <div class="admin-stat-label">This Month Revenue</div>
        <div class="admin-stat-value">₹<?= number_format((float)$monthRevenue, 0) ?></div>
        <div class="admin-stat-icon"><i class="bi bi-calendar-month"></i></div>
      </div>
    </div>

    <!-- Recent Orders -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3>Recent Orders</h3>
        <a href="<?= STORE_URL ?>/admin/orders.php" class="btn btn-ghost btn-sm">View All →</a>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Product</th>
              <th>Buyer</th>
              <th>License</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentOrders)): ?>
            <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">No orders yet.</td></tr>
            <?php else: ?>
            <?php foreach ($recentOrders as $o): ?>
            <tr>
              <td class="text-primary"><?= h($o['order_id']) ?></td>
              <td><?= h($o['product_name']) ?></td>
              <td>
                <?= h($o['buyer_name']) ?><br>
                <small style="color:var(--text-muted)"><?= h($o['buyer_email']) ?></small>
              </td>
              <td><?= ucfirst(h($o['license_type'])) ?></td>
              <td>₹<?= number_format((float)$o['amount'], 0) ?></td>
              <td><span class="badge badge-<?= h($o['payment_status']) ?>"><?= ucfirst(h($o['payment_status'])) ?></span></td>
              <td><?= h(date('d M Y', strtotime($o['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
</div>
<script src="<?= STORE_URL ?>/assets/js/main.js"></script>
</body>
</html>
