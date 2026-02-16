<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$error = '';
$success = '';

// Handle manual wallet adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_wallet'])) {
    $seller_id = intval($_POST['seller_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $type = $_POST['type'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    
    // Validation
    if ($seller_id <= 0) {
        $error = 'Please select a seller';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than 0';
    } elseif (!in_array($type, ['credit', 'debit'])) {
        $error = 'Invalid transaction type';
    } elseif (empty($remarks)) {
        $error = 'Remarks are required for audit trail';
    } else {
        try {
            // Verify seller exists
            $stmt = $pdo->prepare("SELECT id, name FROM sellers WHERE id = ?");
            $stmt->execute([$seller_id]);
            $seller = $stmt->fetch();
            
            if (!$seller) {
                $error = 'Seller not found';
            } else {
                $pdo->beginTransaction();
                
                // Get current balance
                $stmt = $pdo->prepare("SELECT balance FROM seller_wallet WHERE seller_id = ?");
                $stmt->execute([$seller_id]);
                $current_balance = $stmt->fetchColumn();
                
                if ($current_balance === false) {
                    // Create wallet if doesn't exist
                    $stmt = $pdo->prepare("INSERT INTO seller_wallet (seller_id, balance, total_spent) VALUES (?, 0, 0)");
                    $stmt->execute([$seller_id]);
                    $current_balance = 0;
                }
                
                // Check if debit amount is available
                if ($type === 'debit' && $current_balance < $amount) {
                    $error = 'Insufficient balance. Current balance: ₹' . number_format($current_balance, 2);
                    $pdo->rollBack();
                } else {
                    // Update wallet balance
                    if ($type === 'credit') {
                        $stmt = $pdo->prepare("UPDATE seller_wallet SET balance = balance + ? WHERE seller_id = ?");
                        $stmt->execute([$amount, $seller_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE seller_wallet SET balance = balance - ? WHERE seller_id = ?");
                        $stmt->execute([$amount, $seller_id]);
                    }
                    
                    // Insert transaction record for audit trail
                    $stmt = $pdo->prepare("
                        INSERT INTO payment_transactions 
                        (seller_id, review_request_id, amount, gst_amount, total_amount, payment_gateway, gateway_payment_id, status, created_at)
                        VALUES (?, NULL, ?, 0, ?, 'admin_adjustment', ?, 'success', NOW())
                    ");
                    $stmt->execute([
                        $seller_id,
                        $type === 'credit' ? $amount : -$amount,
                        $type === 'credit' ? $amount : -$amount,
                        'ADMIN:' . $admin_name . ' | ' . $remarks
                    ]);
                    
                    $pdo->commit();
                    
                    $action_text = $type === 'credit' ? 'added to' : 'deducted from';
                    $success = "Successfully {$action_text} {$seller['name']}'s wallet. Amount: ₹" . number_format($amount, 2);
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Wallet adjustment error: ' . $e->getMessage());
            $error = 'Failed to process wallet adjustment. Please try again.';
        }
    }
}

// Get all active sellers
try {
    $stmt = $pdo->query("
        SELECT s.id, s.name, s.email, sw.balance
        FROM sellers s
        LEFT JOIN seller_wallet sw ON s.id = sw.seller_id
        WHERE s.status = 'active'
        ORDER BY s.name ASC
    ");
    $sellers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Failed to fetch sellers: ' . $e->getMessage());
    $sellers = [];
}

// Get recent manual adjustments
try {
    $stmt = $pdo->query("
        SELECT pt.*, s.name as seller_name, s.email as seller_email
        FROM payment_transactions pt
        JOIN sellers s ON pt.seller_id = s.id
        WHERE pt.payment_gateway = 'admin_adjustment'
        ORDER BY pt.created_at DESC
        LIMIT 50
    ");
    $recent_adjustments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Failed to fetch recent adjustments: ' . $e->getMessage());
    $recent_adjustments = [];
}

// For sidebar
$current_page = 'seller-wallet-manage';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Seller Wallet - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/styles.php'; ?>
    <style>
        .seller-select-wrapper{position:relative;margin-bottom:20px}
        .seller-search{width:100%;padding:12px 15px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px}
        .seller-search:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.1)}
        .seller-dropdown{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e2e8f0;border-radius:8px;max-height:300px;overflow-y:auto;display:none;z-index:100;box-shadow:0 4px 12px rgba(0,0,0,0.1)}
        .seller-dropdown.show{display:block}
        .seller-option{padding:12px 15px;cursor:pointer;border-bottom:1px solid #f1f5f9}
        .seller-option:hover{background:#f8fafc}
        .seller-option:last-child{border-bottom:none}
        .seller-name{font-weight:600;color:#1e293b}
        .seller-email{font-size:12px;color:#64748b;margin-top:2px}
        .seller-balance{font-size:12px;color:#059669;margin-top:2px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
        @media(max-width:768px){.form-row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="sellers.php">Sellers</a></li>
                        <li class="breadcrumb-item active">Manage Wallet</li>
                    </ol>
                </nav>
                <h3 class="page-title">Manage Seller Wallet</h3>
                <p class="text-muted">Manually add or deduct balance from seller wallets</p>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Wallet Adjustment Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Add/Deduct Wallet Balance</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="seller-select-wrapper">
                        <label class="form-label">Select Seller <span class="text-danger">*</span></label>
                        <input type="text" id="sellerSearch" class="seller-search" 
                               placeholder="Search by name or email..." autocomplete="off">
                        <input type="hidden" name="seller_id" id="selectedSellerId" required>
                        <div id="sellerDropdown" class="seller-dropdown"></div>
                    </div>
                    
                    <div id="selectedSellerInfo" style="display:none;padding:15px;background:#f8fafc;border-radius:8px;margin-bottom:20px">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="seller-name" id="selectedSellerName"></div>
                                <div class="seller-email" id="selectedSellerEmail"></div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-size:12px;color:#64748b">Current Balance</div>
                                <div style="font-size:20px;font-weight:700;color:#059669" id="selectedSellerBalance"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div>
                            <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" 
                                   placeholder="1000" min="1" step="0.01" required>
                        </div>
                        
                        <div>
                            <label class="form-label">Transaction Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="credit">Credit (Add Balance)</option>
                                <option value="debit">Debit (Deduct Balance)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Remarks <span class="text-danger">*</span></label>
                        <textarea name="remarks" class="form-control" rows="3" 
                                  placeholder="Enter reason for this adjustment (required for audit trail)" required></textarea>
                        <small class="text-muted">This will be visible in the seller's transaction history</small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" name="adjust_wallet" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Submit Adjustment
                        </button>
                        <a href="sellers.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Recent Adjustments -->
        <?php if (!empty($recent_adjustments)): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title">Recent Manual Adjustments</h5>
            </div>
            <div class="card-body" style="padding:0">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date & Time</th>
                                <th>Seller</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_adjustments as $adj): ?>
                                <?php
                                $is_credit = $adj['amount'] > 0;
                                $amount_display = $is_credit ? '+' : '';
                                $amount_display .= '₹' . number_format(abs($adj['amount']), 2);
                                $amount_class = $is_credit ? 'text-success' : 'text-danger';
                                
                                // Parse gateway_payment_id for admin and remarks
                                $parts = explode(' | ', $adj['gateway_payment_id']);
                                $admin_info = $parts[0] ?? '';
                                $remarks = $parts[1] ?? '';
                                ?>
                                <tr>
                                    <td><strong>#<?= $adj['id'] ?></strong></td>
                                    <td>
                                        <?= date('d M Y', strtotime($adj['created_at'])) ?><br>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($adj['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="seller-name"><?= htmlspecialchars($adj['seller_name']) ?></div>
                                        <div class="seller-email"><?= htmlspecialchars($adj['seller_email']) ?></div>
                                    </td>
                                    <td>
                                        <strong class="<?= $amount_class ?>"><?= $amount_display ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?= $is_credit ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $is_credit ? 'Credit' : 'Debit' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($admin_info) ?></small><br>
                                        <small class="text-muted"><?= htmlspecialchars($remarks) ?></small>
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
</div>

<script>
const sellers = <?= json_encode($sellers) ?>;
const searchInput = document.getElementById('sellerSearch');
const dropdown = document.getElementById('sellerDropdown');
const selectedSellerId = document.getElementById('selectedSellerId');
const sellerInfo = document.getElementById('selectedSellerInfo');

// Search and filter sellers
searchInput.addEventListener('input', function() {
    const query = this.value.toLowerCase();
    
    if (query.length === 0) {
        dropdown.classList.remove('show');
        return;
    }
    
    const filtered = sellers.filter(s => 
        s.name.toLowerCase().includes(query) || 
        s.email.toLowerCase().includes(query)
    );
    
    if (filtered.length === 0) {
        dropdown.innerHTML = '<div style="padding:15px;text-align:center;color:#64748b">No sellers found</div>';
    } else {
        dropdown.innerHTML = filtered.map(seller => {
            const safeName = seller.name.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            const safeEmail = seller.email.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            return `
                <div class="seller-option" data-id="${seller.id}" data-name="${safeName}" data-email="${safeEmail}" data-balance="${seller.balance || 0}">
                    <div class="seller-name">${safeName}</div>
                    <div class="seller-email">${safeEmail}</div>
                    <div class="seller-balance">Balance: ₹${(seller.balance || 0).toFixed(2)}</div>
                </div>
            `;
        }).join('');
    }
    
    dropdown.classList.add('show');
});

// Focus on search input to show dropdown
searchInput.addEventListener('focus', function() {
    if (this.value.length > 0) {
        searchInput.dispatchEvent(new Event('input'));
    }
});

// Add click handler for seller options
dropdown.addEventListener('click', function(e) {
    const option = e.target.closest('.seller-option');
    if (option) {
        const id = option.dataset.id;
        const name = option.dataset.name;
        const email = option.dataset.email;
        const balance = parseFloat(option.dataset.balance);
        selectSeller(id, name, email, balance);
    }
});

// Click outside to close dropdown
document.addEventListener('click', function(e) {
    if (!e.target.closest('.seller-select-wrapper')) {
        dropdown.classList.remove('show');
    }
});

function selectSeller(id, name, email, balance) {
    selectedSellerId.value = id;
    searchInput.value = name;
    document.getElementById('selectedSellerName').textContent = name;
    document.getElementById('selectedSellerEmail').textContent = email;
    document.getElementById('selectedSellerBalance').textContent = '₹' + balance.toFixed(2);
    sellerInfo.style.display = 'block';
    dropdown.classList.remove('show');
}
</script>
</body>
</html>
