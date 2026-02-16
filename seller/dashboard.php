<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

// Get dashboard statistics
try {
    // Get wallet balance
    $stmt = $pdo->prepare("SELECT balance, total_spent FROM seller_wallet WHERE seller_id = ?");
    $stmt->execute([$seller_id]);
    $wallet = $stmt->fetch();
    $wallet_balance = $wallet['balance'] ?? 0;
    $total_spent = $wallet['total_spent'] ?? 0;
    
    // Get order counts
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN admin_status = 'pending' THEN 1 ELSE 0 END) as pending_approval,
            SUM(CASE WHEN admin_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN admin_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN admin_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payment
        FROM review_requests 
        WHERE seller_id = ?
    ");
    $stmt->execute([$seller_id]);
    $stats = $stmt->fetch();
    
    // Get recent orders
    $stmt = $pdo->prepare("
        SELECT * FROM review_requests 
        WHERE seller_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$seller_id]);
    $recent_orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    $stats = [
        'total_orders' => 0,
        'pending_approval' => 0,
        'approved' => 0,
        'completed' => 0,
        'rejected' => 0,
        'pending_payment' => 0
    ];
    $recent_orders = [];
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h3 class="mb-0">Dashboard</h3>
            <p class="text-muted">Overview of your seller account</p>
        </div>
        <div class="col-auto">
            <a href="new-request.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> New Review Request
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <!-- Wallet Balance -->
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Wallet Balance</div>
                        <h3 class="mb-0">₹<?= number_format($wallet_balance, 2) ?></h3>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-wallet2"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="wallet.php" class="text-success text-decoration-none small">
                        <i class="bi bi-plus-circle"></i> Add Money
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Total Orders -->
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Orders</div>
                        <h3 class="mb-0"><?= $stats['total_orders'] ?></h3>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="orders.php" class="text-primary text-decoration-none small">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Pending Approval -->
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Pending Approval</div>
                        <h3 class="mb-0"><?= $stats['pending_approval'] ?></h3>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-muted small">Awaiting admin review</span>
                </div>
            </div>
        </div>
        
        <!-- Completed Orders -->
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Completed</div>
                        <h3 class="mb-0"><?= $stats['completed'] ?></h3>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="text-success small">
                        <?= $stats['total_orders'] > 0 ? round(($stats['completed'] / $stats['total_orders']) * 100, 1) : 0 ?>% completion rate
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Additional Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small mb-1">Total Spent</div>
                        <h4 class="mb-0">₹<?= number_format($total_spent, 2) ?></h4>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small mb-1">Approved Orders</div>
                        <h4 class="mb-0"><?= $stats['approved'] ?></h4>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-check2-square"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small mb-1">Rejected Orders</div>
                        <h4 class="mb-0 text-danger"><?= $stats['rejected'] ?></h4>
                    </div>
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Orders Table -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Orders</h5>
                <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recent_orders)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                    <p class="text-muted mt-3 mb-0">No orders yet</p>
                    <a href="new-request.php" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-circle"></i> Create Your First Order
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Product</th>
                                <th>Platform</th>
                                <th>Reviews</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td><strong>#<?= $order['id'] ?></strong></td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;">
                                            <?= htmlspecialchars($order['product_name']) ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($order['brand_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= strtoupper($order['platform']) ?></span>
                                    </td>
                                    <td>
                                        <?= $order['reviews_completed'] ?>/<?= $order['reviews_needed'] ?>
                                    </td>
                                    <td>₹<?= number_format($order['grand_total'], 2) ?></td>
                                    <td>
                                        <?php
                                        $payment_badges = [
                                            'pending' => 'warning',
                                            'paid' => 'success',
                                            'failed' => 'danger',
                                            'refunded' => 'info'
                                        ];
                                        $badge_class = $payment_badges[$order['payment_status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge_class ?>"><?= ucfirst($order['payment_status']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'pending' => 'warning',
                                            'approved' => 'primary',
                                            'completed' => 'success',
                                            'rejected' => 'danger'
                                        ];
                                        $status_badge = $status_badges[$order['admin_status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $status_badge ?>"><?= ucfirst($order['admin_status']) ?></span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($order['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
