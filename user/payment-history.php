<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get user's payment history
try {
    $payments = getUserPayments($pdo, $user_id, 100);
    $stats = getPaymentStats($pdo, $user_id);
} catch (PDOException $e) {
    $payments = [];
    $stats = ['total_payments' => 0, 'total_amount' => 0, 'pending_count' => 0];
}

// Set current page for sidebar
$current_page = 'payment-history';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
    /* Sidebar Styles */
    .sidebar {
        width: 260px;
        position: fixed;
        left: 0;
        top: 60px;
        height: calc(100vh - 60px);
        background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        overflow-y: auto;
        transition: all 0.3s ease;
        z-index: 999;
    }
    .admin-layout {
        margin-left: 260px;
        padding: 20px;
        min-height: calc(100vh - 60px);
    }
</style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-layout">
    <div class="container-fluid mt-4">
        <h2 class="mb-4"><i class="bi bi-clock-history"></i> Payment History</h2>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['total_payments']; ?></h3>
                        <p class="mb-0">Total Payments</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h3>₹<?php echo number_format($stats['total_amount'], 2); ?></h3>
                        <p class="mb-0">Total Amount</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h3><?php echo $stats['pending_count']; ?></h3>
                        <p class="mb-0">Pending Payments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list"></i> All Payments</h5>
            </div>
            <div class="card-body">
                <?php if (count($payments) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transaction ID</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
                                <td><code><?php echo htmlspecialchars($payment['transaction_id'] ?? $payment['razorpay_payment_id'] ?? '-'); ?></code></td>
                                <td><strong>₹<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td>
                                    <?php
                                    $badge_class = 'secondary';
                                    if ($payment['status'] == 'success') $badge_class = 'success';
                                    elseif ($payment['status'] == 'pending') $badge_class = 'warning';
                                    elseif ($payment['status'] == 'failed') $badge_class = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['receipt_url']): ?>
                                        <a href="<?php echo htmlspecialchars($payment['receipt_url']); ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-receipt"></i> Receipt
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3">No Payment History</h4>
                    <p class="text-muted">You haven't made any payments yet.</p>
                    <a href="recharge-wallet.php" class="btn btn-primary">
                        <i class="bi bi-wallet2"></i> Recharge Wallet
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
