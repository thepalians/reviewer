<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Check authentication and get seller_id before any output
if (!isset($_SESSION['seller_id'])) {
    header('Location: ' . SELLER_URL . '/index.php');
    exit;
}

$seller_id = $_SESSION['seller_id'];

$error = '';
$success = '';

// Check for success message from redirect
if (isset($_SESSION['wallet_success'])) {
    $success = $_SESSION['wallet_success'];
    unset($_SESSION['wallet_success']);
}

// Get wallet details
try {
    $stmt = $pdo->prepare("SELECT * FROM seller_wallet WHERE seller_id = ?");
    $stmt->execute([$seller_id]);
    $wallet = $stmt->fetch();
    
    $balance = $wallet['balance'] ?? 0;
    $total_spent = $wallet['total_spent'] ?? 0;
    
    // Get transaction history
    $stmt = $pdo->prepare("
        SELECT pt.*, rr.product_name
        FROM payment_transactions pt
        LEFT JOIN review_requests rr ON pt.review_request_id = rr.id
        WHERE pt.seller_id = ?
        ORDER BY pt.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$seller_id]);
    $transactions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Wallet error: ' . $e->getMessage());
    $balance = 0;
    $total_spent = 0;
    $transactions = [];
}

// Handle wallet recharge request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_recharge'])) {
    $amount = (float) ($_POST['amount'] ?? 0);
    $utr_number = trim($_POST['utr_number'] ?? '');
    $transfer_date = $_POST['transfer_date'] ?? '';
    
    // Validation
    if ($amount < 100) {
        $error = 'Minimum amount to add is ₹100';
    } elseif ($amount > 100000) {
        $error = 'Maximum amount to add is ₹1,00,000';
    } elseif (empty($utr_number)) {
        $error = 'UTR Number is required';
    } elseif (empty($transfer_date)) {
        $error = 'Transfer date is required';
    } elseif (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Payment screenshot is required';
    } else {
        // Handle file upload
        $file = $_FILES['payment_screenshot'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload failed. Please try again.';
        } elseif (!in_array($file['type'], $allowed_types)) {
            $error = 'Only JPG, JPEG, and PNG images are allowed';
        } elseif ($file['size'] > $max_size) {
            $error = 'File size must be less than 5MB';
        } else {
            // Additional security: Verify file is actually an image
            $image_info = @getimagesize($file['tmp_name']);
            if ($image_info === false) {
                $error = 'Invalid image file. Please upload a valid image.';
            } else {
                // Validate extension matches MIME type
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, $allowed_extensions)) {
                    $error = 'Invalid file extension';
                } else {
                    // Generate unique filename
                    $filename = 'wallet_' . $seller_id . '_' . time() . '_' . uniqid() . '.' . $extension;
                    $upload_dir = __DIR__ . '/../uploads/wallet_screenshots/';
                    $upload_path = $upload_dir . $filename;
                    
                    // Ensure directory exists
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Set proper file permissions
                        chmod($upload_path, 0644);
                        
                        // Insert recharge request into database
                        try {
                            $stmt = $pdo->prepare("
                                INSERT INTO wallet_recharge_requests 
                                (seller_id, amount, utr_number, screenshot_path, transfer_date, status) 
                                VALUES (?, ?, ?, ?, ?, 'pending')
                            ");
                            $stmt->execute([$seller_id, $amount, $utr_number, $filename, $transfer_date]);
                            
                            // Redirect to prevent form resubmission (Post/Redirect/Get pattern)
                            $_SESSION['wallet_success'] = 'Your wallet recharge request has been submitted successfully! Our team will review it shortly.';
                            header('Location: wallet.php');
                            exit;
                        } catch (PDOException $e) {
                            error_log('Recharge request error: ' . $e->getMessage());
                            $error = 'Failed to submit request. Please try again.';
                            // Delete uploaded file
                            @unlink($upload_path);
                        }
                    } else {
                        $error = 'Failed to upload screenshot. Please try again.';
                    }
                }
            }
        }
    }
}

// Get recharge requests for this seller
$recharge_requests = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM wallet_recharge_requests 
        WHERE seller_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$seller_id]);
    $recharge_requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Failed to fetch recharge requests: ' . $e->getMessage());
}

// Include header after all processing is done to allow redirects
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Wallet</li>
                </ol>
            </nav>
            <h3 class="mb-0">Seller Wallet</h3>
            <p class="text-muted">Manage your wallet balance and transactions</p>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Wallet Overview -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small mb-1">Available Balance</div>
                            <h2 class="mb-0 text-primary">₹<?= number_format($balance, 2) ?></h2>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#addMoneyModal">
                        <i class="bi bi-plus-circle"></i> Add Money
                    </button>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Total Spent</div>
                            <h3 class="mb-0">₹<?= number_format($total_spent, 2) ?></h3>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-graph-down-arrow"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Total Transactions</div>
                            <h3 class="mb-0"><?= count($transactions) ?></h3>
                        </div>
                        <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Transaction History -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Transaction History</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($transactions)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-clock-history" style="font-size: 3rem; color: #cbd5e1;"></i>
                    <p class="text-muted mt-3 mb-0">No transactions yet</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Transaction ID</th>
                                <th>Date & Time</th>
                                <th>Description</th>
                                <th>Payment Gateway</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $txn): ?>
                                <tr>
                                    <td>
                                        <strong>#<?= $txn['id'] ?></strong>
                                        <?php if ($txn['gateway_payment_id']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($txn['gateway_payment_id']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d M Y', strtotime($txn['created_at'])) ?><br>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($txn['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($txn['review_request_id']): ?>
                                            Review Request #<?= $txn['review_request_id'] ?>
                                            <?php if ($txn['product_name']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($txn['product_name']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Wallet Credit
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= strtoupper($txn['payment_gateway']) ?></span>
                                    </td>
                                    <td>
                                        <strong>₹<?= number_format($txn['total_amount'], 2) ?></strong>
                                        <?php if ($txn['gst_amount'] > 0): ?>
                                            <br><small class="text-muted">(Inc. GST ₹<?= number_format($txn['gst_amount'], 2) ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'pending' => 'warning',
                                            'success' => 'success',
                                            'failed' => 'danger',
                                            'refunded' => 'info'
                                        ];
                                        $badge = $status_badges[$txn['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= ucfirst($txn['status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recharge Requests -->
    <?php if (!empty($recharge_requests)): ?>
    <div class="card mt-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Wallet Recharge Requests</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Request ID</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>UTR Number</th>
                            <th>Transfer Date</th>
                            <th>Screenshot</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recharge_requests as $req): ?>
                            <tr>
                                <td><strong>#<?= $req['id'] ?></strong></td>
                                <td>
                                    <?= date('d M Y', strtotime($req['created_at'])) ?><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($req['created_at'])) ?></small>
                                </td>
                                <td><strong>₹<?= number_format($req['amount'], 2) ?></strong></td>
                                <td><code><?= htmlspecialchars($req['utr_number']) ?></code></td>
                                <td><?= date('d M Y', strtotime($req['transfer_date'])) ?></td>
                                <td>
                                    <a href="../uploads/wallet_screenshots/<?= htmlspecialchars($req['screenshot_path']) ?>" 
                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-image"></i> View
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger'
                                    ];
                                    $badge = $status_badges[$req['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>">
                                        <?= ucfirst($req['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($req['admin_remarks']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($req['admin_remarks']) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Money Modal - Offline Bank Transfer -->
<div class="modal fade" id="addMoneyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Add Money to Wallet - Bank Transfer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Bank Account Details -->
                    <div class="alert alert-info">
                        <h6 class="mb-3"><i class="bi bi-bank"></i> Bank Account Details</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td><strong>Bank Name:</strong></td>
                                    <td>State Bank Of India</td>
                                </tr>
                                <tr>
                                    <td><strong>Account Holder:</strong></td>
                                    <td>THE PALIANS</td>
                                </tr>
                                <tr>
                                    <td><strong>Account Number:</strong></td>
                                    <td><span class="badge bg-dark">41457761629</span></td>
                                </tr>
                                <tr>
                                    <td><strong>IFSC Code:</strong></td>
                                    <td><span class="badge bg-dark">SBIN0005362</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Branch:</strong></td>
                                    <td>EKTA NAGAR, BAREILLY</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Instructions:</strong>
                        <ol class="mb-0 mt-2 ps-3">
                            <li>Transfer money to the above bank account</li>
                            <li>Note down the UTR/Transaction reference number</li>
                            <li>Take a screenshot of the payment confirmation</li>
                            <li>Fill the form below and submit</li>
                        </ol>
                    </div>
                    
                    <hr>
                    
                    <!-- Recharge Request Form -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" 
                                   placeholder="1000" min="100" max="100000" step="1" required>
                            <small class="text-muted">Min: ₹100 | Max: ₹1,00,000</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date of Transfer <span class="text-danger">*</span></label>
                            <input type="date" name="transfer_date" class="form-control" 
                                   max="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label">UTR/Transaction Number <span class="text-danger">*</span></label>
                            <input type="text" name="utr_number" class="form-control" 
                                   placeholder="Enter UTR or Transaction Reference Number" 
                                   maxlength="100" required>
                            <small class="text-muted">Enter the unique transaction reference number from your bank</small>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label">Payment Screenshot <span class="text-danger">*</span></label>
                            <input type="file" name="payment_screenshot" class="form-control" 
                                   accept="image/jpeg,image/jpg,image/png" required>
                            <small class="text-muted">Upload screenshot of payment confirmation (JPG, JPEG, PNG | Max: 5MB)</small>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Quick Amount Select:</h6>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="setAmount(500)">₹500</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="setAmount(1000)">₹1000</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="setAmount(2000)">₹2000</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="setAmount(5000)">₹5000</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_recharge" class="btn btn-primary">
                        <i class="bi bi-send"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setAmount(amount) {
    document.querySelector('input[name="amount"]').value = amount;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
