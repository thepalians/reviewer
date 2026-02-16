<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

$success_message = '';
$error_message = '';

// Handle payout request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'request_payout') {
                $amount = (float)$_POST['amount'];
                $payment_method = $_POST['payment_method'];
                $payment_details = $_POST['payment_details'];
                
                // Get available balance
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN status IN ('pending', 'approved') THEN amount ELSE 0 END), 0) as available_balance
                    FROM affiliate_commissions
                    WHERE affiliate_id = ?
                ");
                $stmt->execute([$affiliate_id]);
                $balance = $stmt->fetch();
                $available_balance = $balance['available_balance'];
                
                // Get minimum payout threshold from settings
                $min_payout = 500.00;
                
                if ($amount < $min_payout) {
                    throw new Exception("Minimum payout amount is ₹" . number_format($min_payout, 2));
                }
                
                if ($amount > $available_balance) {
                    throw new Exception("Insufficient balance. Available: ₹" . number_format($available_balance, 2));
                }
                
                // Create payout request
                $stmt = $pdo->prepare("
                    INSERT INTO affiliate_payouts (affiliate_id, amount, payment_method, payment_details, status, requested_at)
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $affiliate_id,
                    $amount,
                    $payment_method,
                    $payment_details
                ]);
                
                $success_message = 'Payout request submitted successfully';
                
            } elseif ($action === 'save_payment_method') {
                $stmt = $pdo->prepare("
                    UPDATE affiliates 
                    SET payment_method = ?, payment_details = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['payment_method'],
                    $_POST['payment_details'],
                    $affiliate_id
                ]);
                
                $success_message = 'Payment method updated successfully';
            }
        } catch (Exception $e) {
            error_log('Payout error: ' . $e->getMessage());
            $error_message = $e->getMessage();
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    // Get balance summary
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN status IN ('pending', 'approved') THEN amount ELSE 0 END), 0) as available_balance,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) as total_paid
        FROM affiliate_commissions
        WHERE affiliate_id = ?
    ");
    $stmt->execute([$affiliate_id]);
    $balance = $stmt->fetch();
    
    // Get pending payout requests
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as pending_payouts
        FROM affiliate_payouts
        WHERE affiliate_id = ? AND status = 'pending'
    ");
    $stmt->execute([$affiliate_id]);
    $pending = $stmt->fetch();
    $pending_payouts = $pending['pending_payouts'] ?? 0;
    
    // Get payout history
    $stmt = $pdo->prepare("
        SELECT *
        FROM affiliate_payouts
        WHERE affiliate_id = ?
        ORDER BY requested_at DESC
    ");
    $stmt->execute([$affiliate_id]);
    $payouts = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Payout fetch error: ' . $e->getMessage());
    $balance = [
        'available_balance' => 0,
        'total_paid' => 0
    ];
    $pending_payouts = 0;
    $payouts = [];
}

$min_payout = 500.00;
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h3 class="mb-0">Payouts</h3>
            <p class="text-muted">Request and manage your affiliate payouts</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#payoutRequestModal">
                <i class="bi bi-cash-stack"></i> Request Payout
            </button>
        </div>
    </div>
    
    <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($success_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Balance Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Available Balance</div>
                        <h3 class="mb-0">₹<?= number_format($balance['available_balance'], 2) ?></h3>
                        <small class="text-muted">Min. payout: ₹<?= number_format($min_payout, 2) ?></small>
                    </div>
                    <div class="text-success">
                        <i class="bi bi-wallet2 fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Pending Payouts</div>
                        <h3 class="mb-0">₹<?= number_format($pending_payouts, 2) ?></h3>
                    </div>
                    <div class="text-warning">
                        <i class="bi bi-hourglass-split fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Paid</div>
                        <h3 class="mb-0">₹<?= number_format($balance['total_paid'], 2) ?></h3>
                    </div>
                    <div class="text-info">
                        <i class="bi bi-check-circle fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Method -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Payment Method</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="save_payment_method">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="">Select method</option>
                            <option value="bank_transfer" <?= ($affiliate['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                            <option value="upi" <?= ($affiliate['payment_method'] ?? '') === 'upi' ? 'selected' : '' ?>>UPI</option>
                            <option value="paypal" <?= ($affiliate['payment_method'] ?? '') === 'paypal' ? 'selected' : '' ?>>PayPal</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Payment Details *</label>
                        <input type="text" class="form-control" name="payment_details" 
                               value="<?= htmlspecialchars($affiliate['payment_details'] ?? '') ?>"
                               placeholder="Account number, UPI ID, or PayPal email" required>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Payout History -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Payout History</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Request Date</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Processed Date</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payouts)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No payout requests yet. Request your first payout when you reach the minimum threshold.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($payouts as $payout): ?>
                        <tr>
                            <td><?= date('M d, Y H:i', strtotime($payout['requested_at'])) ?></td>
                            <td><strong>₹<?= number_format($payout['amount'], 2) ?></strong></td>
                            <td><?= ucwords(str_replace('_', ' ', $payout['payment_method'])) ?></td>
                            <td>
                                <?php if ($payout['status'] === 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                                <?php elseif ($payout['status'] === 'processing'): ?>
                                <span class="badge bg-info">Processing</span>
                                <?php elseif ($payout['status'] === 'completed'): ?>
                                <span class="badge bg-success">Completed</span>
                                <?php elseif ($payout['status'] === 'rejected'): ?>
                                <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($payout['processed_at']): ?>
                                <?= date('M d, Y', strtotime($payout['processed_at'])) ?>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($payout['transaction_reference']): ?>
                                <code><?= htmlspecialchars($payout['transaction_reference']) ?></code>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
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

<!-- Payout Request Modal -->
<div class="modal fade" id="payoutRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Payout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="request_payout">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Available Balance:</strong> ₹<?= number_format($balance['available_balance'], 2) ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payout Amount (₹) *</label>
                        <input type="number" class="form-control" name="amount" 
                               min="<?= $min_payout ?>" 
                               max="<?= $balance['available_balance'] ?>"
                               step="0.01" required>
                        <small class="text-muted">Minimum: ₹<?= number_format($min_payout, 2) ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method *</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="">Select method</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="upi">UPI</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Details *</label>
                        <input type="text" class="form-control" name="payment_details" 
                               placeholder="Account number, UPI ID, or PayPal email" required>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="bi bi-exclamation-triangle"></i>
                            Payouts are processed within 5-7 business days. Ensure your payment details are correct.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
