<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment-functions.php';
require_once __DIR__ . '/../includes/razorpay-config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

// Get Razorpay config
try {
    $razorpay_config = getRazorpayConfig($pdo);
} catch (PDOException $e) {
    $razorpay_config = [];
}

// Get user's wallet balance
try {
    $stmt = $pdo->prepare("SELECT wallet_balance, username, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user = ['wallet_balance' => 0, 'username' => '', 'email' => ''];
}

// Get payment stats
try {
    $payment_stats = getPaymentStats($pdo, $user_id);
} catch (PDOException $e) {
    $payment_stats = ['total_payments' => 0, 'total_amount' => 0, 'pending_count' => 0];
}

// Set current page for sidebar
$current_page = 'recharge-wallet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recharge Wallet - User Panel</title>
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
    .sidebar-header {
        padding: 20px;
        background: rgba(255,255,255,0.05);
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar-header h2 {
        color: #fff;
        font-size: 18px;
        margin: 0;
    }
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .sidebar-menu li {
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .sidebar-menu li a {
        display: block;
        padding: 15px 20px;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: all 0.3s;
        font-size: 14px;
    }
    .sidebar-menu li a:hover {
        background: rgba(255,255,255,0.1);
        color: #fff;
        padding-left: 25px;
    }
    .sidebar-menu li a.active {
        background: linear-gradient(90deg, rgba(66,153,225,0.2) 0%, transparent 100%);
        color: #4299e1;
        border-left: 3px solid #4299e1;
    }
    .sidebar-menu li a.logout {
        color: #fc8181;
    }
    .sidebar-divider {
        height: 1px;
        background: rgba(255,255,255,0.1);
        margin: 10px 0;
    }
    .menu-section-label {
        padding: 15px 20px 5px;
        color: rgba(255,255,255,0.5);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .badge {
        background: #e53e3e;
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        margin-left: 8px;
    }
    .admin-layout {
        margin-left: 260px;
        padding: 20px;
        min-height: calc(100vh - 60px);
    }
    @media (max-width: 768px) {
        .sidebar {
            left: -260px;
        }
        .sidebar.active {
            left: 0;
        }
        .admin-layout {
            margin-left: 0;
        }
    }
    
    .amount-button {
        padding: 30px;
        font-size: 1.5rem;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
    }
    .amount-button:hover {
        transform: scale(1.05);
    }
</style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-layout">
    <div class="container-fluid mt-4">
        <h2 class="mb-4"><i class="bi bi-wallet2"></i> Recharge Wallet</h2>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Current Balance -->
        <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5>Current Wallet Balance</h5>
                        <h1 class="display-3 mb-0">₹<?php echo number_format($user['wallet_balance'], 2); ?></h1>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="bi bi-wallet-fill" style="font-size: 6rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-credit-card" style="font-size: 2rem; color: #667eea;"></i>
                        <h3 class="mt-2"><?php echo $payment_stats['total_payments']; ?></h3>
                        <p class="mb-0 text-muted">Total Recharges</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-cash-stack" style="font-size: 2rem; color: #10b981;"></i>
                        <h3 class="mt-2">₹<?php echo number_format($payment_stats['total_amount'], 2); ?></h3>
                        <p class="mb-0 text-muted">Total Amount</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-history" style="font-size: 2rem; color: #f59e0b;"></i>
                        <h3 class="mt-2"><?php echo $payment_stats['pending_count']; ?></h3>
                        <p class="mb-0 text-muted">Pending Payments</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recharge Options -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-lightning-charge"></i> Quick Recharge</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary amount-button w-100" onclick="selectAmount(100)">
                            ₹100
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary amount-button w-100" onclick="selectAmount(500)">
                            ₹500
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary amount-button w-100" onclick="selectAmount(1000)">
                            ₹1,000
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary amount-button w-100" onclick="selectAmount(5000)">
                            ₹5,000
                        </button>
                    </div>
                </div>

                <div class="mt-4">
                    <label for="customAmount" class="form-label">Or Enter Custom Amount</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control" id="customAmount" 
                               placeholder="Enter amount" min="100" max="50000" step="1">
                        <button class="btn btn-primary" onclick="proceedPayment()">
                            <i class="bi bi-arrow-right"></i> Proceed to Pay
                        </button>
                    </div>
                    <small class="text-muted">Min: ₹100 | Max: ₹50,000</small>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-credit-card-2-front"></i> Payment Methods</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <i class="bi bi-credit-card" style="font-size: 3rem; color: #667eea;"></i>
                                <h6 class="mt-2">Credit/Debit Card</h6>
                                <small class="text-muted">Visa, Mastercard, RuPay</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <i class="bi bi-phone" style="font-size: 3rem; color: #10b981;"></i>
                                <h6 class="mt-2">UPI</h6>
                                <small class="text-muted">Google Pay, PhonePe, Paytm</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card text-center h-100">
                            <div class="card-body">
                                <i class="bi bi-bank" style="font-size: 3rem; color: #f59e0b;"></i>
                                <h6 class="mt-2">Net Banking</h6>
                                <small class="text-muted">All major banks</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Razorpay Script -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
function selectAmount(amount) {
    document.getElementById('customAmount').value = amount;
}

function proceedPayment() {
    const amount = document.getElementById('customAmount').value;
    
    if (!amount || amount < 100 || amount > 50000) {
        alert('Please enter a valid amount between ₹100 and ₹50,000');
        return;
    }
    
    // Create order
    fetch('../api/payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'create_order',
            amount: parseFloat(amount)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            openRazorpay(data.order_id, data.payment_id, data.amount, data.key_id);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to create payment order');
    });
}

function openRazorpay(orderId, paymentId, amount, keyId) {
    const options = {
        key: keyId,
        amount: amount * 100,
        currency: 'INR',
        name: 'Reviewer Platform',
        description: 'Wallet Recharge',
        order_id: orderId,
        handler: function(response) {
            // Verify payment
            fetch('../api/payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'verify_payment',
                    payment_id: paymentId,
                    razorpay_payment_id: response.razorpay_payment_id,
                    razorpay_signature: response.razorpay_signature
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment successful! Your wallet has been recharged.');
                    window.location.reload();
                } else {
                    alert('Payment verification failed: ' + data.message);
                }
            });
        },
        prefill: {
            name: '<?php echo htmlspecialchars($user['username']); ?>',
            email: '<?php echo htmlspecialchars($user['email']); ?>'
        },
        theme: {
            color: '#667eea'
        }
    };
    
    const rzp = new Razorpay(options);
    rzp.open();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
