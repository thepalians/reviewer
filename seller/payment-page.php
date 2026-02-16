<?php
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['seller_id'])) {
    header('Location: index.php');
    exit;
}

$seller_id = $_SESSION['seller_id'];
$order_id = $_GET['order_id'] ?? '';

if (empty($order_id) || !isset($_SESSION['payment_order'])) {
    header('Location: orders.php?error=invalid_session');
    exit;
}

$payment_order = $_SESSION['payment_order'];
$request_id = $payment_order['request_id'];

$stmt = $pdo->prepare("SELECT rr.*, s.name, s.email, s.mobile FROM review_requests rr JOIN sellers s ON rr.seller_id = s.id WHERE rr.id = ? AND rr.seller_id = ?");
$stmt->execute([$request_id, $seller_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: orders.php?error=request_not_found');
    exit;
}

// Get seller wallet balance
$wallet_stmt = $pdo->prepare("SELECT balance FROM seller_wallet WHERE seller_id = ?");
$wallet_stmt->execute([$seller_id]);
$wallet = $wallet_stmt->fetch();
$wallet_balance = $wallet ? (float)$wallet['balance'] : 0;
$has_sufficient_balance = $wallet_balance >= $request['grand_total'];

$razorpay_key = getSetting('razorpay_key_id', '');
$site_name = getSetting('site_name', 'ReviewFlow');

require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Complete Payment</h4>
                </div>
                <div class="card-body">
                    <h5 class="text-center mb-3"><?= htmlspecialchars($request['product_name']) ?></h5>
                    <table class="table mt-3">
                        <tr><td>Product Price</td><td class="text-end">₹<?= number_format($request['product_price'], 2) ?></td></tr>
                        <tr><td>Commission</td><td class="text-end">₹<?= number_format($request['admin_commission'] * $request['reviews_needed'], 2) ?></td></tr>
                        <tr><td>GST (18%)</td><td class="text-end">₹<?= number_format($request['gst_amount'], 2) ?></td></tr>
                        <tr class="table-primary"><th>Total</th><th class="text-end">₹<?= number_format($request['grand_total'], 2) ?></th></tr>
                    </table>
                    
                    <!-- Wallet Balance Display -->
                    <div class="alert alert-info mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-wallet2 me-2"></i>
                                <strong>Your Wallet Balance</strong>
                            </div>
                            <h5 class="mb-0">₹<?= number_format($wallet_balance, 2) ?></h5>
                        </div>
                    </div>
                    
                    <h6 class="mb-3">Choose Payment Method:</h6>
                    
                    <!-- Pay with Wallet -->
                    <div class="card mb-3 <?= $has_sufficient_balance ? 'border-success' : 'border-secondary' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="bi bi-wallet2 text-success"></i> Pay with Wallet
                                    </h6>
                                    <small class="text-muted">Instant - No additional fees</small>
                                    <?php if (!$has_sufficient_balance): ?>
                                        <br><small class="text-danger"><i class="bi bi-exclamation-circle"></i> Insufficient balance</small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($has_sufficient_balance): ?>
                                        <form action="wallet-payment.php" method="POST" id="wallet-payment-form">
                                            <input type="hidden" name="request_id" value="<?= $request_id ?>">
                                            <button type="submit" class="btn btn-success" id="wallet-pay-btn">
                                                Pay ₹<?= number_format($request['grand_total'], 2) ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="wallet.php" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-plus-circle"></i> Add Money
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pay with Razorpay -->
                    <div class="card border-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="bi bi-credit-card text-primary"></i> Pay with Razorpay
                                    </h6>
                                    <small class="text-muted">Credit/Debit/UPI/NetBanking</small>
                                </div>
                                <div>
                                    <button id="pay-btn" class="btn btn-primary">
                                        Pay ₹<?= number_format($request['grand_total'], 2) ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
// Wallet payment confirmation
document.getElementById('wallet-payment-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    if (confirm('Are you sure you want to pay ₹<?= number_format($request['grand_total'], 2) ?> from your wallet?')) {
        this.submit();
    }
});

// Razorpay payment
document.getElementById('pay-btn').onclick = function(e) {
    var options = {
        "key": "<?= $razorpay_key ?>",
        "amount": "<?= (int)($request['grand_total'] * 100) ?>",
        "currency": "INR",
        "name": "<?= $site_name ?>",
        "description": "Review Request #<?= $request_id ?>",
        "order_id": "<?= $order_id ?>",
        "handler": function (response) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'payment-callback.php?action=callback';
            ['razorpay_payment_id','razorpay_order_id','razorpay_signature'].forEach(function(k) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = k; inp.value = response[k];
                form.appendChild(inp);
            });
            var g = document.createElement('input');
            g.type='hidden'; g.name='gateway'; g.value='razorpay';
            form.appendChild(g);
            document.body.appendChild(form);
            form.submit();
        },
        "prefill": {
            "name": "<?= htmlspecialchars($request['name']) ?>",
            "email": "<?= htmlspecialchars($request['email']) ?>",
            "contact": "<?= htmlspecialchars($request['mobile']) ?>"
        },
        "theme": {"color": "#6366f1"}
    };
    var rzp = new Razorpay(options);
    rzp.open();
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
