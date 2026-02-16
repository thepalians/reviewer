<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$errors = [];
$success = '';

// Create wallet if not exists
createWallet($user_id);

// Get wallet data
$wallet_balance = getWalletBalance($user_id);

// Get wallet details
try {
    $stmt = $pdo->prepare("SELECT * FROM user_wallet WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch();
    if (!$wallet) {
        $wallet = ['balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0];
    }
    
    // Get transactions
    $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll();
    
    // Get pending withdrawals
    $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $withdrawal_requests = $stmt->fetchAll();
    
    // Get user payment details
    $stmt = $pdo->prepare("SELECT upi_id, bank_name, bank_account, bank_ifsc FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_payment = $stmt->fetch();
    if (!$user_payment) {
        $user_payment = ['upi_id' => '', 'bank_name' => '', 'bank_account' => '', 'bank_ifsc' => ''];
    }
    
} catch (PDOException $e) {
    error_log("Wallet Error: " . $e->getMessage());
    $wallet = ['balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0];
    $transactions = [];
    $withdrawal_requests = [];
    $user_payment = ['upi_id' => '', 'bank_name' => '', 'bank_account' => '', 'bank_ifsc' => ''];
}

$min_withdrawal = (float)getSetting('min_withdrawal', 100);

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $method = sanitizeInput($_POST['payment_method'] ?? '');
    
    // Validation
    if ($amount < $min_withdrawal) {
        $errors[] = "Minimum withdrawal amount is ₹$min_withdrawal";
    } elseif ($amount > $wallet_balance) {
        $errors[] = "Insufficient balance. Available: ₹" . number_format($wallet_balance, 2);
    } elseif (!in_array($method, ['upi', 'bank', 'paytm'])) {
        $errors[] = "Please select a valid payment method";
    } else {
        // Check for pending withdrawal
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawal_requests WHERE user_id = ? AND status = 'pending'");
            $stmt->execute([$user_id]);
            $pending_count = $stmt->fetchColumn();
            
            if ($pending_count > 0) {
                $errors[] = "You already have a pending withdrawal request. Please wait for it to be processed.";
            }
        } catch (PDOException $e) {}
        
        // Get payment details based on method
        $payment_details = '';
        if (empty($errors)) {
            if ($method === 'upi') {
                $upi_id = sanitizeInput($_POST['upi_id'] ?? '');
                if (empty($upi_id)) {
                    $errors[] = "UPI ID is required";
                } elseif (!preg_match('/^[\w.-]+@[\w.-]+$/', $upi_id)) {
                    $errors[] = "Invalid UPI ID format";
                } else {
                    $payment_details = $upi_id;
                }
            } elseif ($method === 'bank') {
                $bank_name = sanitizeInput($_POST['bank_name'] ?? '');
                $bank_account = sanitizeInput($_POST['bank_account'] ?? '');
                $bank_ifsc = strtoupper(sanitizeInput($_POST['bank_ifsc'] ?? ''));
                
                if (empty($bank_name)) $errors[] = "Bank name is required";
                if (empty($bank_account)) $errors[] = "Account number is required";
                if (empty($bank_ifsc)) $errors[] = "IFSC code is required";
                elseif (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $bank_ifsc)) $errors[] = "Invalid IFSC code format";
                
                if (empty($errors)) {
                    $payment_details = json_encode([
                        'bank_name' => $bank_name,
                        'account' => $bank_account,
                        'ifsc' => $bank_ifsc
                    ]);
                }
            } else { // paytm
                $paytm_number = sanitizeInput($_POST['paytm_number'] ?? '');
                if (empty($paytm_number)) {
                    $errors[] = "Paytm number is required";
                } elseif (!preg_match('/^[6-9]\d{9}$/', $paytm_number)) {
                    $errors[] = "Invalid mobile number";
                } else {
                    $payment_details = $paytm_number;
                }
            }
        }
        
        // Process withdrawal
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Deduct from wallet
                $stmt = $pdo->prepare("UPDATE user_wallet SET balance = balance - ? WHERE user_id = ? AND balance >= ?");
                $stmt->execute([$amount, $user_id, $amount]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception("Insufficient balance or wallet not found");
                }
                
                // Create withdrawal request
                $stmt = $pdo->prepare("
                    INSERT INTO withdrawal_requests (user_id, amount, payment_method, payment_details, status, created_at) 
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$user_id, $amount, $method, $payment_details]);
                $withdrawal_id = $pdo->lastInsertId();
                
                // Log transaction
                $new_balance = $wallet_balance - $amount;
                $stmt = $pdo->prepare("
                    INSERT INTO wallet_transactions (user_id, type, amount, balance_after, description, reference_id, reference_type, status, created_at) 
                    VALUES (?, 'withdrawal', ?, ?, 'Withdrawal request', ?, 'withdrawal', 'pending', NOW())
                ");
                $stmt->execute([$user_id, $amount, $new_balance, $withdrawal_id]);
                
                $pdo->commit();
                
                $success = "Withdrawal request of ₹" . number_format($amount, 2) . " submitted successfully! You will receive payment within 24-48 hours.";
                $wallet_balance = $new_balance;
                $wallet['balance'] = $new_balance;
                
                // Create notification
                createNotification($user_id, 'wallet', '💸 Withdrawal Requested', "Your withdrawal request of ₹" . number_format($amount, 2) . " has been submitted and is being processed.");
                
                // Refresh withdrawal requests
                $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt->execute([$user_id]);
                $withdrawal_requests = $stmt->fetchAll();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Failed to process withdrawal: " . $e->getMessage();
                error_log("Withdrawal Error: " . $e->getMessage());
            }
        }
    }
}

// Save payment details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    $upi_id = sanitizeInput($_POST['save_upi_id'] ?? '');
    $bank_name = sanitizeInput($_POST['save_bank_name'] ?? '');
    $bank_account = sanitizeInput($_POST['save_bank_account'] ?? '');
    $bank_ifsc = strtoupper(sanitizeInput($_POST['save_bank_ifsc'] ?? ''));
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET upi_id = ?, bank_name = ?, bank_account = ?, bank_ifsc = ? WHERE id = ?");
        $stmt->execute([$upi_id, $bank_name, $bank_account, $bank_ifsc, $user_id]);
        $success = "Payment details saved successfully!";
        $user_payment = [
            'upi_id' => $upi_id,
            'bank_name' => $bank_name,
            'bank_account' => $bank_account,
            'bank_ifsc' => $bank_ifsc
        ];
    } catch (PDOException $e) {
        $errors[] = "Failed to save payment details";
        error_log("Save Payment Error: " . $e->getMessage());
    }
}

// Cancel withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_withdrawal'])) {
    $withdrawal_id = intval($_POST['withdrawal_id'] ?? 0);
    
    try {
        // Get withdrawal details
        $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE id = ? AND user_id = ? AND status = 'pending'");
        $stmt->execute([$withdrawal_id, $user_id]);
        $withdrawal = $stmt->fetch();
        
        if ($withdrawal) {
            $pdo->beginTransaction();
            
            // Refund to wallet
            $stmt = $pdo->prepare("UPDATE user_wallet SET balance = balance + ? WHERE user_id = ?");
            $stmt->execute([$withdrawal['amount'], $user_id]);
            
            // Update withdrawal status
            $stmt = $pdo->prepare("UPDATE withdrawal_requests SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$withdrawal_id]);
            
            // Log transaction
            $new_balance = $wallet_balance + $withdrawal['amount'];
            $stmt = $pdo->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, balance_after, description, reference_id, reference_type, status, created_at)
                VALUES (?, 'credit', ?, ?, 'Withdrawal cancelled - refund', ?, 'withdrawal', 'completed', NOW())
            ");
            $stmt->execute([$user_id, $withdrawal['amount'], $new_balance, $withdrawal_id]);
            
            $pdo->commit();
            
            $success = "Withdrawal request cancelled. ₹" . number_format($withdrawal['amount'], 2) . " refunded to wallet.";
            $wallet_balance = $new_balance;
            $wallet['balance'] = $new_balance;
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$user_id]);
            $withdrawal_requests = $stmt->fetchAll();
            
        } else {
            $errors[] = "Withdrawal request not found or cannot be cancelled";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Failed to cancel withdrawal";
        error_log("Cancel Withdrawal Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px}
        
        .container{max-width:1000px;margin:0 auto}
        
        .back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fff;color:#333;text-decoration:none;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:14px;transition:transform 0.2s;box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        .back-btn:hover{transform:translateY(-2px)}
        
        /* Wallet Card */
        .wallet-card{background:linear-gradient(135deg,#1a1a2e 0%,#16213e 100%);border-radius:20px;padding:30px;color:#fff;margin-bottom:25px;position:relative;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.3)}
        .wallet-card::before{content:'';position:absolute;top:-50%;right:-30%;width:80%;height:150%;background:radial-gradient(circle,rgba(102,126,234,0.3) 0%,transparent 70%);pointer-events:none}
        .wallet-card::after{content:'';position:absolute;bottom:-50%;left:-30%;width:80%;height:150%;background:radial-gradient(circle,rgba(118,75,162,0.3) 0%,transparent 70%);pointer-events:none}
        .wallet-label{font-size:14px;opacity:0.8;margin-bottom:5px}
        .wallet-balance{font-size:48px;font-weight:700;margin-bottom:5px;text-shadow:0 2px 10px rgba(0,0,0,0.3)}
        .wallet-subtitle{font-size:13px;opacity:0.7}
        .wallet-stats{display:flex;gap:20px;margin-top:25px;flex-wrap:wrap}
        .wallet-stat{background:rgba(255,255,255,0.1);backdrop-filter:blur(10px);padding:15px 20px;border-radius:12px;min-width:140px}
        .wallet-stat .value{font-size:22px;font-weight:700}
        .wallet-stat .label{font-size:12px;opacity:0.8;margin-top:3px}
        
        /* Alerts */
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .alert-warning{background:#fff3cd;color:#856404;border:1px solid #ffeeba}
        .alert-info{background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb}
        
        /* Grid Layout */
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        
        /* Cards */
        .card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .card-title{font-size:18px;font-weight:600;color:#333;margin-bottom:20px;display:flex;align-items:center;gap:10px;padding-bottom:15px;border-bottom:1px solid #eee}
        
        /* Tabs */
        .tabs{display:flex;gap:5px;margin-bottom:20px;background:#f5f5f5;padding:5px;border-radius:10px}
        .tab{flex:1;padding:12px;text-align:center;background:transparent;border:none;border-radius:8px;cursor:pointer;font-weight:600;font-size:13px;color:#666;transition:all 0.2s}
        .tab.active{background:#fff;color:#667eea;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
        .tab-content{display:none}
        .tab-content.active{display:block}
        
        /* Form Elements */
        .form-group{margin-bottom:18px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#333;font-size:14px}
        .form-control{width:100%;padding:12px 15px;border:2px solid #eee;border-radius:10px;font-size:14px;transition:border-color 0.2s}
        .form-control:focus{border-color:#667eea;outline:none}
        .form-control:disabled{background:#f5f5f5;cursor:not-allowed}
        .form-hint{font-size:12px;color:#888;margin-top:5px}
        
        /* Buttons */
        .btn{padding:14px 25px;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px;transition:all 0.2s;display:inline-flex;align-items:center;justify-content:center;gap:8px}
        .btn:disabled{opacity:0.6;cursor:not-allowed}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;width:100%}
        .btn-primary:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 5px 20px rgba(102,126,234,0.4)}
        .btn-success{background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff}
        .btn-danger{background:#e74c3c;color:#fff;padding:8px 15px;font-size:12px}
        .btn-secondary{background:#f5f5f5;color:#666}
        .btn-sm{padding:8px 15px;font-size:12px}
        
        /* Payment Methods */
        .payment-methods{display:flex;gap:10px;margin-bottom:20px}
        .payment-method{flex:1;padding:15px;border:2px solid #eee;border-radius:12px;text-align:center;cursor:pointer;transition:all 0.2s}
        .payment-method:hover{border-color:#ddd;background:#fafafa}
        .payment-method.active{border-color:#667eea;background:#f8f9ff}
        .payment-method .icon{font-size:28px;margin-bottom:8px}
        .payment-method .name{font-size:13px;font-weight:600;color:#333}
        .payment-method .desc{font-size:11px;color:#888;margin-top:3px}
        
        .payment-form{display:none;animation:fadeIn 0.3s}
        .payment-form.active{display:block}
        @keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        
        /* Transactions */
        .transaction-list{max-height:500px;overflow-y:auto}
        .transaction-item{display:flex;justify-content:space-between;align-items:center;padding:15px 0;border-bottom:1px solid #f5f5f5}
        .transaction-item:last-child{border-bottom:none}
        .txn-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-right:12px}
        .txn-icon.credit{background:#e8f5e9;color:#27ae60}
        .txn-icon.debit{background:#ffebee;color:#e74c3c}
        .txn-icon.pending{background:#fff8e1;color:#f39c12}
        .txn-info{flex:1;min-width:0}
        .txn-type{font-weight:600;font-size:14px;color:#333}
        .txn-desc{font-size:12px;color:#888;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .txn-date{font-size:11px;color:#aaa;margin-top:3px}
        .txn-amount{font-weight:700;font-size:15px;text-align:right}
        .txn-amount.credit{color:#27ae60}
        .txn-amount.debit{color:#e74c3c}
        .txn-status{font-size:10px;padding:3px 8px;border-radius:10px;margin-top:3px;display:inline-block}
        .txn-status.pending{background:#fff3cd;color:#856404}
        .txn-status.completed{background:#d4edda;color:#155724}
        .txn-status.failed{background:#f8d7da;color:#721c24}
        
        /* Withdrawal Requests */
        .withdrawal-item{background:#f8f9fa;border-radius:12px;padding:15px;margin-bottom:12px;border-left:4px solid #667eea}
        .withdrawal-item.pending{border-left-color:#f39c12}
        .withdrawal-item.approved{border-left-color:#3498db}
        .withdrawal-item.completed{border-left-color:#27ae60}
        .withdrawal-item.rejected,.withdrawal-item.cancelled{border-left-color:#e74c3c}
        .withdrawal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
        .withdrawal-amount{font-size:20px;font-weight:700;color:#333}
        .withdrawal-status{padding:5px 12px;border-radius:15px;font-size:11px;font-weight:600;text-transform:uppercase}
        .withdrawal-status.pending{background:#fff3cd;color:#856404}
        .withdrawal-status.approved{background:#d1ecf1;color:#0c5460}
        .withdrawal-status.completed{background:#d4edda;color:#155724}
        .withdrawal-status.rejected,.withdrawal-status.cancelled{background:#f8d7da;color:#721c24}
        .withdrawal-details{font-size:13px;color:#666}
        .withdrawal-details span{margin-right:15px}
        .withdrawal-actions{margin-top:10px}
        
        /* Empty State */
        .empty-state{text-align:center;padding:40px 20px;color:#999}
        .empty-state .icon{font-size:50px;margin-bottom:15px;opacity:0.5}
        .empty-state h4{color:#666;margin-bottom:8px}
        .empty-state p{font-size:13px}
        
        /* Responsive */
        @media(max-width:768px){
            .container{padding:0}
            .grid{grid-template-columns:1fr}
            .wallet-balance{font-size:36px}
            .wallet-stats{gap:10px}
            .wallet-stat{flex:1;min-width:100px;padding:12px 15px}
            .wallet-stat .value{font-size:18px}
            .payment-methods{flex-direction:column}
        }
    </style>
</head>
<body>
<div class="container">
    <a href="<?php echo APP_URL; ?>/user/" class="back-btn">← Back to Dashboard</a>
    
    <!-- Wallet Card -->
    <div class="wallet-card">
        <div class="wallet-label">Available Balance</div>
        <div class="wallet-balance">₹<?php echo number_format($wallet_balance, 2); ?></div>
        <div class="wallet-subtitle">Last updated: <?php echo date('d M Y, H:i'); ?></div>
        <div class="wallet-stats">
            <div class="wallet-stat">
                <div class="value">₹<?php echo number_format($wallet['total_earned'] ?? 0, 2); ?></div>
                <div class="label">💰 Total Earned</div>
            </div>
            <div class="wallet-stat">
                <div class="value">₹<?php echo number_format($wallet['total_withdrawn'] ?? 0, 2); ?></div>
                <div class="label">💸 Total Withdrawn</div>
            </div>
            <div class="wallet-stat">
                <div class="value"><?php echo count($transactions); ?></div>
                <div class="label">📊 Transactions</div>
            </div>
        </div>
    </div>
    
    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
    <?php endforeach; ?>
    
    <!-- Pending Withdrawal Notice -->
    <?php 
    $has_pending = false;
    foreach ($withdrawal_requests as $wr) {
        if ($wr['status'] === 'pending') {
            $has_pending = true;
            break;
        }
    }
    if ($has_pending): 
    ?>
        <div class="alert alert-warning">⏳ You have a pending withdrawal request. New withdrawals are disabled until it's processed.</div>
    <?php endif; ?>
    
    <div class="grid">
        <!-- Left Column - Withdraw -->
        <div class="card">
            <div class="card-title">💸 Withdraw Money</div>
            
            <div class="tabs">
                <button class="tab active" onclick="showTab('withdraw')">Withdraw</button>
                <button class="tab" onclick="showTab('payment')">Payment Details</button>
            </div>
            
            <!-- Withdraw Tab -->
            <div class="tab-content active" id="withdrawTab">
                <form method="POST" id="withdrawForm">
                    <div class="form-group">
                        <label>Withdrawal Amount</label>
                        <input type="number" name="amount" class="form-control" min="<?php echo $min_withdrawal; ?>" max="<?php echo $wallet_balance; ?>" step="1" required placeholder="Enter amount" <?php echo ($wallet_balance < $min_withdrawal || $has_pending) ? 'disabled' : ''; ?>>
                        <div class="form-hint">Minimum: ₹<?php echo number_format($min_withdrawal, 0); ?> • Available: ₹<?php echo number_format($wallet_balance, 2); ?></div>
                    </div>
                    
                    <label style="font-weight:600;margin-bottom:12px;display:block;color:#333">Payment Method</label>
                    <div class="payment-methods">
                        <div class="payment-method active" onclick="selectMethod('upi')">
                            <div class="icon">📱</div>
                            <div class="name">UPI</div>
                            <div class="desc">Instant transfer</div>
                        </div>
                        <div class="payment-method" onclick="selectMethod('bank')">
                            <div class="icon">🏦</div>
                            <div class="name">Bank Transfer</div>
                            <div class="desc">1-2 business days</div>
                        </div>
                        <div class="payment-method" onclick="selectMethod('paytm')">
                            <div class="icon">💳</div>
                            <div class="name">Paytm</div>
                            <div class="desc">Instant transfer</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="payment_method" id="paymentMethod" value="upi">
                    
                    <!-- UPI Form -->
                    <div class="payment-form active" id="upiForm">
                        <div class="form-group">
                            <label>UPI ID</label>
                            <input type="text" name="upi_id" class="form-control" placeholder="example@paytm" value="<?php echo escape($user_payment['upi_id'] ?? ''); ?>" <?php echo $has_pending ? 'disabled' : ''; ?>>
                            <div class="form-hint">Enter your UPI ID (e.g., name@upi, number@paytm)</div>
                        </div>
                    </div>
                    
                    <!-- Bank Form -->
                    <div class="payment-form" id="bankForm">
                        <div class="form-group">
                            <label>Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="Enter bank name" value="<?php echo escape($user_payment['bank_name'] ?? ''); ?>" <?php echo $has_pending ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>Account Number</label>
                            <input type="text" name="bank_account" class="form-control" placeholder="Enter account number" value="<?php echo escape($user_payment['bank_account'] ?? ''); ?>" <?php echo $has_pending ? 'disabled' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label>IFSC Code</label>
                            <input type="text" name="bank_ifsc" class="form-control" placeholder="Enter IFSC code" value="<?php echo escape($user_payment['bank_ifsc'] ?? ''); ?>" style="text-transform:uppercase" <?php echo $has_pending ? 'disabled' : ''; ?>>
                            <div class="form-hint">11-character IFSC code (e.g., SBIN0001234)</div>
                        </div>
                    </div>
                    
                    <!-- Paytm Form -->
                    <div class="payment-form" id="paytmForm">
                        <div class="form-group">
                            <label>Paytm Registered Mobile Number</label>
                            <input type="text" name="paytm_number" class="form-control" placeholder="10-digit mobile number" maxlength="10" <?php echo $has_pending ? 'disabled' : ''; ?>>
                            <div class="form-hint">Enter your Paytm registered mobile number</div>
                        </div>
                    </div>
                    
                    <button type="submit" name="withdraw" class="btn btn-primary" <?php echo ($wallet_balance < $min_withdrawal || $has_pending) ? 'disabled' : ''; ?>>
                        <?php if ($has_pending): ?>
                            ⏳ Pending Request in Progress
                        <?php elseif ($wallet_balance < $min_withdrawal): ?>
                            ❌ Insufficient Balance
                        <?php else: ?>
                            💸 Request Withdrawal
                        <?php endif; ?>
                    </button>
                </form>
            </div>
            
            <!-- Payment Details Tab -->
            <div class="tab-content" id="paymentTab">
                <form method="POST">
                    <p style="color:#666;font-size:13px;margin-bottom:20px">Save your payment details for faster withdrawals.</p>
                    
                    <div class="form-group">
                        <label>UPI ID</label>
                        <input type="text" name="save_upi_id" class="form-control" placeholder="example@paytm" value="<?php echo escape($user_payment['upi_id'] ?? ''); ?>">
                    </div>
                    
                    <hr style="border:none;border-top:1px solid #eee;margin:20px 0">
                    
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="save_bank_name" class="form-control" placeholder="Enter bank name" value="<?php echo escape($user_payment['bank_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="save_bank_account" class="form-control" placeholder="Enter account number" value="<?php echo escape($user_payment['bank_account'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>IFSC Code</label>
                        <input type="text" name="save_bank_ifsc" class="form-control" placeholder="Enter IFSC code" value="<?php echo escape($user_payment['bank_ifsc'] ?? ''); ?>" style="text-transform:uppercase">
                    </div>
                    
                    <button type="submit" name="save_payment" class="btn btn-success" style="width:100%">💾 Save Payment Details</button>
                </form>
            </div>
        </div>
        
        <!-- Right Column - Transactions & Withdrawal History -->
        <div>
            <!-- Withdrawal Requests -->
            <div class="card" style="margin-bottom:20px">
                <div class="card-title">📋 Withdrawal Requests</div>
                
                <?php if (empty($withdrawal_requests)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h4>No Withdrawal Requests</h4>
                        <p>Your withdrawal history will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($withdrawal_requests as $wr): ?>
                        <div class="withdrawal-item <?php echo $wr['status']; ?>">
                            <div class="withdrawal-header">
                                <div class="withdrawal-amount">₹<?php echo number_format($wr['amount'], 2); ?></div>
                                <span class="withdrawal-status <?php echo $wr['status']; ?>"><?php echo ucfirst($wr['status']); ?></span>
                            </div>
                            <div class="withdrawal-details">
                                <span>📅 <?php echo date('d M Y, H:i', strtotime($wr['created_at'])); ?></span>
                                <span>💳 <?php echo ucfirst($wr['payment_method']); ?></span>
                                <?php if ($wr['processed_at']): ?>
                                    <span>✅ Processed: <?php echo date('d M Y', strtotime($wr['processed_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($wr['status'] === 'pending'): ?>
                                <div class="withdrawal-actions">
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to cancel this withdrawal request?')">
                                        <input type="hidden" name="withdrawal_id" value="<?php echo $wr['id']; ?>">
                                        <button type="submit" name="cancel_withdrawal" class="btn btn-danger btn-sm">Cancel Request</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                            <?php if ($wr['admin_note']): ?>
                                <div style="margin-top:10px;padding:10px;background:#fff;border-radius:8px;font-size:12px;color:#666">
                                    <strong>Admin Note:</strong> <?php echo escape($wr['admin_note']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Transaction History -->
            <div class="card">
                <div class="card-title">📊 Transaction History</div>
                
                <?php if (empty($transactions)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h4>No Transactions Yet</h4>
                        <p>Your transaction history will appear here</p>
                    </div>
                <?php else: ?>
                    <div class="transaction-list">
                        <?php foreach ($transactions as $txn): 
                            $is_credit = in_array($txn['type'], ['credit', 'bonus', 'referral']);
                        ?>
                            <div class="transaction-item">
                                <div class="txn-icon <?php echo $is_credit ? 'credit' : ($txn['status'] === 'pending' ? 'pending' : 'debit'); ?>">
                                    <?php echo $is_credit ? '↓' : '↑'; ?>
                                </div>
                                <div class="txn-info">
                                    <div class="txn-type"><?php echo ucfirst($txn['type']); ?></div>
                                    <div class="txn-desc"><?php echo escape($txn['description']); ?></div>
                                    <div class="txn-date"><?php echo date('d M Y, H:i', strtotime($txn['created_at'])); ?></div>
                                </div>
                                <div style="text-align:right">
                                    <div class="txn-amount <?php echo $is_credit ? 'credit' : 'debit'; ?>">
                                        <?php echo $is_credit ? '+' : '-'; ?>₹<?php echo number_format($txn['amount'], 2); ?>
                                    </div>
                                    <?php if ($txn['status'] !== 'completed'): ?>
                                        <span class="txn-status <?php echo $txn['status']; ?>"><?php echo ucfirst($txn['status']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?php echo APP_URL; ?>/user/transactions.php" style="display:block;text-align:center;margin-top:15px;color:#667eea;font-size:13px;text-decoration:none">View All Transactions →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching
function showTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    if (tab === 'withdraw') {
        document.querySelectorAll('.tab')[0].classList.add('active');
        document.getElementById('withdrawTab').classList.add('active');
    } else {
        document.querySelectorAll('.tab')[1].classList.add('active');
        document.getElementById('paymentTab').classList.add('active');
    }
}

// Payment method selection
function selectMethod(method) {
    // Update UI
    document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
    event.currentTarget.classList.add('active');
    
    // Update hidden field
    document.getElementById('paymentMethod').value = method;
    
    // Show/hide forms
    document.querySelectorAll('.payment-form').forEach(f => f.classList.remove('active'));
    document.getElementById(method + 'Form').classList.add('active');
}

// Form validation
document.getElementById('withdrawForm')?.addEventListener('submit', function(e) {
    const amount = parseFloat(this.querySelector('[name="amount"]').value);
    const min = <?php echo $min_withdrawal; ?>;
    const max = <?php echo $wallet_balance; ?>;
    
    if (amount < min) {
        e.preventDefault();
        alert('Minimum withdrawal amount is ₹' + min);
        return;
    }
    
    if (amount > max) {
        e.preventDefault();
        alert('Insufficient balance. Available: ₹' + max.toFixed(2));
        return;
    }
    
    if (!confirm('Confirm withdrawal of ₹' + amount.toFixed(2) + '?')) {
        e.preventDefault();
    }
});
</script>
</body>
</html>
