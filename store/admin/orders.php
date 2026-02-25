<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireAdminLogin();

$db = getStoreDB();

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $db->query("SELECT o.*, p.name as product_name FROM store_orders o JOIN store_products p ON o.product_id=p.id ORDER BY o.created_at DESC");
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order ID','Product','License','Buyer Name','Buyer Email','Amount','Status','Date']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['order_id'],$r['product_name'],$r['license_type'],$r['buyer_name'],$r['buyer_email'],$r['amount'],$r['payment_status'],$r['created_at']]);
    }
    fclose($out);
    exit;
}

// Build query
$where  = [];
$params = [];
if ($statusFilter !== 'all') { $where[] = 'o.payment_status = ?'; $params[] = $statusFilter; }
if ($search !== '') {
    $where[] = '(o.buyer_email LIKE ? OR o.order_id LIKE ? OR o.buyer_name LIKE ?)';
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s]);
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $db->prepare("SELECT o.*, p.name as product_name FROM store_orders o JOIN store_products p ON o.product_id=p.id $whereSQL ORDER BY o.created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// View-order detail modal
$viewOrder = null;
if (isset($_GET['view'])) {
    $vstmt = $db->prepare("SELECT o.*, p.name as product_name FROM store_orders o JOIN store_products p ON o.product_id=p.id WHERE o.order_id=? LIMIT 1");
    $vstmt->execute([$_GET['view']]);
    $viewOrder = $vstmt->fetch();
}

$pageTitle = 'Orders';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<div class="admin-layout">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="admin-main">
  <div class="admin-header">
    <span class="admin-header-title">Orders</span>
    <div class="admin-header-right">
      <a href="?export=csv" class="btn btn-ghost btn-sm"><i class="bi bi-download"></i> Export CSV</a>
    </div>
  </div>
  <div class="admin-content">

    <!-- Filters -->
    <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
      <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="text" name="search" value="<?= h($search) ?>" class="form-control"
               placeholder="Search order ID, email, name..." style="width:260px">
        <select name="status" class="form-control" style="width:160px">
          <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
          <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
          <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
          <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="<?= STORE_URL ?>/admin/orders.php" class="btn btn-ghost btn-sm">Reset</a>
      </form>
      <span style="font-size:0.82rem;color:var(--text-muted);margin-left:auto"><?= count($orders) ?> order(s)</span>
    </div>

    <div class="admin-card">
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
              <th>Downloads</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
            <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">No orders found.</td></tr>
            <?php else: ?>
            <?php foreach ($orders as $o): ?>
            <tr>
              <td class="text-primary" style="font-size:0.78rem"><?= h($o['order_id']) ?></td>
              <td style="font-size:0.82rem"><?= h($o['product_name']) ?></td>
              <td>
                <div style="font-size:0.82rem"><?= h($o['buyer_name']) ?></div>
                <div style="font-size:0.72rem;color:var(--text-muted)"><?= h($o['buyer_email']) ?></div>
              </td>
              <td><?= ucfirst(h($o['license_type'])) ?></td>
              <td>₹<?= number_format((float)$o['amount'], 0) ?></td>
              <td><span class="badge badge-<?= h($o['payment_status']) ?>"><?= ucfirst(h($o['payment_status'])) ?></span></td>
              <td><?= (int)$o['download_count'] ?>/<?= (int)$o['max_downloads'] ?></td>
              <td style="font-size:0.75rem"><?= h(date('d M Y', strtotime($o['created_at']))) ?></td>
              <td>
                <a href="?view=<?= urlencode($o['order_id']) ?>" class="btn btn-ghost btn-sm">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
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

<?php if ($viewOrder): ?>
<div class="modal-overlay active" id="viewOrderModal">
  <div class="modal">
    <div class="modal-header">
      <h3>Order Details — <?= h($viewOrder['order_id']) ?></h3>
      <a href="<?= STORE_URL ?>/admin/orders.php" class="modal-close">×</a>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <tr><th>Order ID</th><td class="text-primary"><?= h($viewOrder['order_id']) ?></td></tr>
        <tr><th>Product</th><td><?= h($viewOrder['product_name']) ?></td></tr>
        <tr><th>License</th><td><?= ucfirst(h($viewOrder['license_type'])) ?></td></tr>
        <tr><th>Buyer Name</th><td><?= h($viewOrder['buyer_name']) ?></td></tr>
        <tr><th>Buyer Email</th><td><?= h($viewOrder['buyer_email']) ?></td></tr>
        <tr><th>Phone</th><td><?= h($viewOrder['buyer_phone'] ?: '—') ?></td></tr>
        <tr><th>Amount</th><td>₹<?= number_format((float)$viewOrder['amount'], 2) ?></td></tr>
        <tr><th>Payment Status</th><td><span class="badge badge-<?= h($viewOrder['payment_status']) ?>"><?= ucfirst(h($viewOrder['payment_status'])) ?></span></td></tr>
        <tr><th>Razorpay Payment ID</th><td style="font-size:0.75rem"><?= h($viewOrder['razorpay_payment_id'] ?: '—') ?></td></tr>
        <tr><th>Razorpay Order ID</th><td style="font-size:0.75rem"><?= h($viewOrder['razorpay_order_id'] ?: '—') ?></td></tr>
        <tr><th>Downloads</th><td><?= (int)$viewOrder['download_count'] ?>/<?= (int)$viewOrder['max_downloads'] ?></td></tr>
        <tr><th>Token Expires</th><td><?= h($viewOrder['token_expires_at']) ?></td></tr>
        <tr><th>IP Address</th><td><?= h($viewOrder['ip_address'] ?: '—') ?></td></tr>
        <tr><th>Order Date</th><td><?= h($viewOrder['created_at']) ?></td></tr>
      </table>
    </div>
    <div style="margin-top:16px;text-align:right">
      <a href="<?= STORE_URL ?>/admin/orders.php" class="btn btn-ghost btn-sm">Close</a>
    </div>
  </div>
</div>
<?php endif; ?>

<script src="<?= STORE_URL ?>/assets/js/main.js"></script>
</body>
</html>
