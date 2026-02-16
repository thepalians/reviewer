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
$errors = [];
$success = '';

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = intval($_POST['request_id'] ?? 0);
    $admin_remarks = trim($_POST['admin_remarks'] ?? '');
    
    if ($request_id > 0) {
        try {
            // Get request details
            $stmt = $pdo->prepare("
                SELECT wrr.*, s.name as seller_name, s.email as seller_email
                FROM wallet_recharge_requests wrr
                JOIN sellers s ON wrr.seller_id = s.id
                WHERE wrr.id = ?
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if (!$request) {
                $errors[] = "Recharge request not found";
            } elseif ($request['status'] !== 'pending') {
                $errors[] = "This request has already been processed";
            } else {
                $pdo->beginTransaction();
                
                if ($action === 'approve') {
                    // Approve the request
                    $stmt = $pdo->prepare("
                        UPDATE wallet_recharge_requests 
                        SET status = 'approved', admin_remarks = ?, approved_by = ?, approved_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$admin_remarks, $admin_name, $request_id]);
                    
                    // Update seller wallet balance
                    $stmt = $pdo->prepare("
                        INSERT INTO seller_wallet (seller_id, balance, total_spent)
                        VALUES (?, ?, 0)
                        ON DUPLICATE KEY UPDATE balance = balance + ?
                    ");
                    $result = $stmt->execute([$request['seller_id'], $request['amount'], $request['amount']]);
                    
                    if (!$result) {
                        $error_info = $stmt->errorInfo();
                        error_log('Wallet balance update failed for seller_id ' . $request['seller_id'] . ': ' . json_encode($error_info));
                        throw new Exception('Failed to update wallet balance');
                    }
                    
                    error_log('Wallet balance updated successfully for seller_id ' . $request['seller_id'] . ', amount: ' . $request['amount']);
                    
                    // Insert payment transaction record
                    $stmt = $pdo->prepare("
                        INSERT INTO payment_transactions 
                        (seller_id, review_request_id, amount, gst_amount, total_amount, payment_gateway, gateway_payment_id, status, created_at)
                        VALUES (?, NULL, ?, 0, ?, 'bank_transfer', ?, 'success', NOW())
                    ");
                    $result = $stmt->execute([
                        $request['seller_id'], 
                        $request['amount'], 
                        $request['amount'],
                        'UTR:' . $request['utr_number']
                    ]);
                    
                    if (!$result) {
                        $error_info = $stmt->errorInfo();
                        error_log('Payment transaction insert failed for seller_id ' . $request['seller_id'] . ': ' . json_encode($error_info));
                        throw new Exception('Failed to record payment transaction');
                    }
                    
                    $pdo->commit();
                    error_log('Recharge request #' . $request_id . ' approved successfully for seller_id ' . $request['seller_id']);
                    $success = "Recharge request #$request_id approved successfully! ₹" . number_format($request['amount'], 2) . " added to seller wallet.";
                    
                } elseif ($action === 'reject') {
                    // Reject the request
                    if (empty($admin_remarks)) {
                        $errors[] = "Please provide a reason for rejection";
                        $pdo->rollBack();
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE wallet_recharge_requests 
                            SET status = 'rejected', admin_remarks = ?, approved_by = ?, approved_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$admin_remarks, $admin_name, $request_id]);
                        
                        $pdo->commit();
                        $success = "Recharge request #$request_id rejected.";
                    }
                }
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Wallet request processing error: ' . $e->getMessage());
            $errors[] = 'Failed to process request. Please try again.';
        }
    }
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'pending';
$allowed_filters = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'pending';
}

// Fetch wallet recharge requests
try {
    $sql = "
        SELECT wrr.*, s.name as seller_name, s.email as seller_email, s.mobile as seller_mobile
        FROM wallet_recharge_requests wrr
        JOIN sellers s ON wrr.seller_id = s.id
    ";
    
    if ($filter !== 'all') {
        $sql .= " WHERE wrr.status = ?";
    }
    
    $sql .= " ORDER BY wrr.created_at DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    
    if ($filter !== 'all') {
        $stmt->execute([$filter]);
    } else {
        $stmt->execute();
    }
    
    $requests = $stmt->fetchAll();
    
    // Get counts for badges
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM wallet_recharge_requests GROUP BY status");
    $status_counts = [];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['status']] = $row['count'];
    }
    
} catch (PDOException $e) {
    error_log('Failed to fetch wallet requests: ' . $e->getMessage());
    $requests = [];
    $status_counts = [];
}

$pending_count = $status_counts['pending'] ?? 0;
$approved_count = $status_counts['approved'] ?? 0;
$rejected_count = $status_counts['rejected'] ?? 0;
$total_count = array_sum($status_counts);

// For sidebar
$current_page = 'wallet-requests';
$pending_wallet_recharges = $pending_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Recharge Requests - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/styles.php'; ?>
    <style>
        .badge-count {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .table-responsive{overflow-x:auto}
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Wallet Recharge Requests</li>
                    </ol>
                </nav>
                <h3 class="page-title">Wallet Recharge Requests</h3>
                <p class="text-muted">Manage seller wallet recharge requests via bank transfer</p>
            </div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Filter Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="?filter=all">
                    All <span class="badge bg-secondary badge-count"><?= $total_count ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'pending' ? 'active' : '' ?>" href="?filter=pending">
                    Pending <span class="badge bg-warning badge-count"><?= $pending_count ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'approved' ? 'active' : '' ?>" href="?filter=approved">
                    Approved <span class="badge bg-success badge-count"><?= $approved_count ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filter === 'rejected' ? 'active' : '' ?>" href="?filter=rejected">
                    Rejected <span class="badge bg-danger badge-count"><?= $rejected_count ?></span>
                </a>
            </li>
        </ul>
        
        <!-- Requests Table -->
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($requests)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                        <p class="text-muted mt-3 mb-0">No recharge requests found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Seller Details</th>
                                    <th>Amount</th>
                                    <th>UTR Number</th>
                                    <th>Transfer Date</th>
                                    <th>Screenshot</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $req): ?>
                                    <tr>
                                        <td><strong>#<?= $req['id'] ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($req['seller_name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($req['seller_email']) ?></small><br>
                                            <small class="text-muted"><?= htmlspecialchars($req['seller_mobile']) ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-primary">₹<?= number_format($req['amount'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($req['utr_number']) ?></code>
                                        </td>
                                        <td><?= date('d M Y', strtotime($req['transfer_date'])) ?></td>
                                        <td>
                                            <a href="../uploads/wallet_screenshots/<?= htmlspecialchars($req['screenshot_path']) ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-image"></i> View
                                            </a>
                                        </td>
                                        <td>
                                            <?= date('d M Y', strtotime($req['created_at'])) ?><br>
                                            <small class="text-muted"><?= date('H:i', strtotime($req['created_at'])) ?></small>
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
                                            <?php if ($req['admin_remarks']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($req['admin_remarks']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($req['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-success mb-1" 
                                                        onclick="showActionModal(<?= $req['id'] ?>, 'approve', '<?= htmlspecialchars($req['seller_name'], ENT_QUOTES) ?>', <?= $req['amount'] ?>)">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="showActionModal(<?= $req['id'] ?>, 'reject', '<?= htmlspecialchars($req['seller_name'], ENT_QUOTES) ?>', <?= $req['amount'] ?>)">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </button>
                                            <?php else: ?>
                                                <small class="text-muted">
                                                    <?php if ($req['approved_at']): ?>
                                                        <?= date('d M Y H:i', strtotime($req['approved_at'])) ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function showActionModal(requestId, action, sellerName, amount) {
        const modal = document.createElement('div');
        modal.className = 'custom-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'modal-title');
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999';
        
        const isApprove = action === 'approve';
        const bgColor = isApprove ? '#ecfdf5' : '#fef2f2';
        const textColor = isApprove ? '#047857' : '#b91c1c';
        const btnClass = isApprove ? 'btn-success' : 'btn-danger';
        const btnText = isApprove ? 'Approve' : 'Reject';
        
        // Escape strings for HTML
        const escapedSellerName = sellerName.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        
        modal.innerHTML = `
            <div style="background:#fff;border-radius:15px;padding:30px;max-width:500px;width:90%">
                <h3 id="modal-title" style="margin-bottom:20px">${isApprove ? 'Approve' : 'Reject'} Recharge Request</h3>
                <div style="background:${bgColor};color:${textColor};padding:15px;border-radius:10px;margin-bottom:20px">
                    <strong>Confirm ${isApprove ? 'Approval' : 'Rejection'}</strong><br>
                    Seller: <strong>${escapedSellerName}</strong><br>
                    Amount: <strong>₹${amount.toFixed(2)}</strong><br>
                    ${isApprove ? 'This amount will be added to the seller\'s wallet.' : 'Please provide a reason for rejection.'}
                </div>
                <form method="POST" style="margin-bottom:20px">
                    <input type="hidden" name="request_id" value="${requestId}">
                    <input type="hidden" name="action" value="${action}">
                    <label style="display:block;margin-bottom:10px;font-weight:500">Admin Remarks</label>
                    <textarea name="admin_remarks" rows="3" ${!isApprove ? 'required' : ''} 
                        placeholder="${isApprove ? 'Optional remarks' : 'Required - explain reason for rejection'}"
                        style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px"></textarea>
                    <div style="display:flex;gap:10px;margin-top:20px">
                        <button type="button" class="btn btn-secondary" style="flex:1">Cancel</button>
                        <button type="submit" class="btn ${btnClass}" style="flex:1">${btnText}</button>
                    </div>
                </form>
            </div>
        `;
        
        // Close button handler
        const cancelBtn = modal.querySelector('button[type="button"]');
        cancelBtn.addEventListener('click', () => modal.remove());
        
        // Close on ESC key
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                modal.remove();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);
        
        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
                document.removeEventListener('keydown', handleEscape);
            }
        });
        
        document.body.appendChild(modal);
        
        // Focus management
        const firstInput = modal.querySelector('textarea');
        if (firstInput) firstInput.focus();
    }
</script>
</body>
</html>
