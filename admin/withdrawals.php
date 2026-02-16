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

// Handle withdrawal actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $withdrawal_id = intval($_POST['withdrawal_id'] ?? 0);
    $admin_note = sanitizeInput($_POST['admin_note'] ?? '');
    
    if ($withdrawal_id > 0) {
        try {
            // Get withdrawal details
            $stmt = $pdo->prepare("
                SELECT wr.*, u.name as user_name, u.email as user_email, u.mobile as user_mobile
                FROM withdrawal_requests wr
                JOIN users u ON wr.user_id = u.id
                WHERE wr.id = ?
            ");
            $stmt->execute([$withdrawal_id]);
            $withdrawal = $stmt->fetch();
            
            if (!$withdrawal) {
                $errors[] = "Withdrawal request not found";
            } elseif ($withdrawal['status'] !== 'pending') {
                $errors[] = "This withdrawal has already been processed";
            } else {
                $pdo->beginTransaction();
                
                if ($action === 'approve') {
                    // Approve withdrawal
                    $stmt = $pdo->prepare("
                        UPDATE withdrawal_requests 
                        SET status = 'approved', admin_note = ?, processed_by = ?, processed_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$admin_note, $admin_name, $withdrawal_id]);
                    
                    // Update transaction status
                    $stmt = $pdo->prepare("
                        UPDATE wallet_transactions 
                        SET status = 'completed', description = 'Withdrawal approved'
                        WHERE reference_id = ? AND reference_type = 'withdrawal' AND status = 'pending'
                    ");
                    $stmt->execute([$withdrawal_id]);
                    
                    // Notify user
                    createNotification(
                        $withdrawal['user_id'],
                        'success',
                        '✅ Withdrawal Approved',
                        "Your withdrawal of ₹{$withdrawal['amount']} has been approved and will be processed shortly."
                    );
                    
                    sendTaskNotification($withdrawal['user_id'], 'withdrawal_approved', ['amount' => $withdrawal['amount']]);
                    
                    $success = "Withdrawal #$withdrawal_id approved successfully!";
                    
                } elseif ($action === 'complete') {
                    // Mark as completed (paid)
                    $stmt = $pdo->prepare("
                        UPDATE withdrawal_requests 
                        SET status = 'completed', admin_note = ?, processed_by = ?, processed_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$admin_note, $admin_name, $withdrawal_id]);
                    
                    // Update wallet total_withdrawn
                    $stmt = $pdo->prepare("
                        UPDATE user_wallet 
                        SET total_withdrawn = total_withdrawn + ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$withdrawal['amount'], $withdrawal['user_id']]);
                    
                    // Update transaction
                    $stmt = $pdo->prepare("
                        UPDATE wallet_transactions 
                        SET status = 'completed', description = 'Withdrawal completed'
                        WHERE reference_id = ? AND reference_type = 'withdrawal'
                    ");
                    $stmt->execute([$withdrawal_id]);
                    
                    // Notify user
                    createNotification(
                        $withdrawal['user_id'],
                        'success',
                        '💰 Payment Sent!',
                        "Your withdrawal of ₹{$withdrawal['amount']} has been sent to your {$withdrawal['payment_method']}."
                    );
                    
                    $success = "Withdrawal #$withdrawal_id marked as completed!";
                    
                } elseif ($action === 'reject') {
                    $reject_reason = sanitizeInput($_POST['reject_reason'] ?? 'Request rejected by admin');
                    
                    // Reject and refund
                    $stmt = $pdo->prepare("
                        UPDATE withdrawal_requests 
                        SET status = 'rejected', admin_note = ?, processed_by = ?, processed_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$reject_reason, $admin_name, $withdrawal_id]);
                    
                    // Refund to wallet
                    $stmt = $pdo->prepare("UPDATE user_wallet SET balance = balance + ? WHERE user_id = ?");
                    $stmt->execute([$withdrawal['amount'], $withdrawal['user_id']]);
                    
                    // Get new balance
                    $new_balance = getWalletBalance($withdrawal['user_id']);
                    
                    // Update/create transaction
                    $stmt = $pdo->prepare("
                        UPDATE wallet_transactions 
                        SET status = 'failed', description = ?
                        WHERE reference_id = ? AND reference_type = 'withdrawal' AND type = 'withdrawal'
                    ");
                    $stmt->execute(["Withdrawal rejected: $reject_reason", $withdrawal_id]);
                    
                    // Add refund transaction
                    $stmt = $pdo->prepare("
                        INSERT INTO wallet_transactions (user_id, type, amount, balance_after, description, reference_id, reference_type, status)
                        VALUES (?, 'credit', ?, ?, 'Withdrawal rejected - refund', ?, 'withdrawal', 'completed')
                    ");
                    $stmt->execute([$withdrawal['user_id'], $withdrawal['amount'], $new_balance, $withdrawal_id]);
                    
                    // Notify user
                    createNotification(
                        $withdrawal['user_id'],
                        'warning',
                        '❌ Withdrawal Rejected',
                        "Your withdrawal of ₹{$withdrawal['amount']} was rejected. Reason: $reject_reason. Amount has been refunded to your wallet."
                    );
                    
                    $success = "Withdrawal #$withdrawal_id rejected and refunded!";
                }
                
                $pdo->commit();
                
                // Log activity
                logActivity("Withdrawal $action: #$withdrawal_id (₹{$withdrawal['amount']})", null, $withdrawal['user_id']);
                
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to process withdrawal: " . $e->getMessage();
            error_log("Withdrawal Error: " . $e->getMessage());
        }
    }
}

// Filters
$filter_status = $_GET['status'] ?? 'pending';
$filter_method = $_GET['method'] ?? 'all';
$search = sanitizeInput($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where = "1=1";
$params = [];

if ($filter_status !== 'all') {
    $where .= " AND wr.status = ?";
    $params[] = $filter_status;
}

if ($filter_method !== 'all') {
    $where .= " AND wr.payment_method = ?";
    $params[] = $filter_method;
}

if (!empty($search)) {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get withdrawals
try {
    // Count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM withdrawal_requests wr
        JOIN users u ON wr.user_id = u.id
        WHERE $where
    ");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $total_pages = ceil($total / $per_page);
    
    // Get data
    $stmt = $pdo->prepare("
        SELECT wr.*, u.name as user_name, u.email as user_email, u.mobile as user_mobile,
               uw.balance as wallet_balance
        FROM withdrawal_requests wr
        JOIN users u ON wr.user_id = u.id
        LEFT JOIN user_wallet uw ON wr.user_id = uw.user_id
        WHERE $where
        ORDER BY 
            CASE wr.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                ELSE 3 
            END,
            wr.created_at ASC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $withdrawals = $stmt->fetchAll();
    
    // Stats
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_count = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_amount = (float)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'approved'");
    $approved_count = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = 'completed'");
    $total_paid = (float)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'completed' AND DATE(processed_at) = CURDATE()");
    $today_completed = (int)$stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Withdrawals Error: " . $e->getMessage());
    $withdrawals = [];
    $total = 0;
    $total_pages = 0;
    $pending_count = $approved_count = $today_completed = 0;
    $pending_amount = $total_paid = 0;
}

// Get counts for badges
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND is_read = 0");
    $unread_messages = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_questions = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $unread_messages = $unanswered_questions = $pending_tasks = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals - Admin - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        
        /* Sidebar */
        .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
        .sidebar-menu{list-style:none;padding:15px 0}
        .sidebar-menu li{margin-bottom:5px}
        .sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
        .sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
        .sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
        .sidebar-menu a.logout{color:#e74c3c}
        
        .main-content{padding:25px;overflow-x:hidden}
        
        /* Header */
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:15px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        
        /* Alerts */
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        
        /* Stats Grid */
        .stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:15px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .stat-card.highlight{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .stat-value{font-size:28px;font-weight:700;margin-bottom:5px}
        .stat-label{font-size:13px;opacity:0.8}
        
        /* Filters */
        .filters-card{background:#fff;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .filters-row{display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end}
        .filter-group{display:flex;flex-direction:column;gap:5px}
        .filter-group label{font-size:12px;font-weight:600;color:#64748b}
        .filter-group select,.filter-group input{padding:10px 15px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;min-width:150px}
        .filter-group select:focus,.filter-group input:focus{border-color:#667eea;outline:none}
        .filter-actions{margin-left:auto;display:flex;gap:10px}
        .btn{padding:10px 20px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-secondary{background:#f1f5f9;color:#475569}
        .btn-success{background:#10b981;color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-warning{background:#f59e0b;color:#fff}
        .btn-sm{padding:6px 12px;font-size:12px}
        .btn:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        
        /* Table */
        .table-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .table-header{padding:20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center}
        .table-title{font-size:18px;font-weight:600;color:#1e293b}
        table{width:100%;border-collapse:collapse}
        th{background:#f8fafc;padding:12px 15px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase}
        td{padding:15px;border-bottom:1px solid #f1f5f9;font-size:14px}
        tr:last-child td{border-bottom:none}
        tr:hover{background:#f8fafc}
        
        .user-cell{display:flex;align-items:center;gap:10px}
        .user-avatar{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:14px}
        .user-info{line-height:1.4}
        .user-name{font-weight:600;color:#1e293b;font-size:14px}
        .user-email{font-size:12px;color:#64748b}
        
        .amount-cell{font-weight:700;font-size:16px;color:#1e293b}
        
        .method-badge{padding:5px 12px;border-radius:20px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:5px}
        .method-badge.upi{background:#ecfdf5;color:#059669}
        .method-badge.bank{background:#eff6ff;color:#2563eb}
        .method-badge.paytm{background:#fef3c7;color:#d97706}
        
        .status-badge{padding:5px 12px;border-radius:20px;font-size:11px;font-weight:600}
        .status-badge.pending{background:#fef3c7;color:#d97706}
        .status-badge.approved{background:#dbeafe;color:#2563eb}
        .status-badge.completed{background:#dcfce7;color:#16a34a}
        .status-badge.rejected{background:#fee2e2;color:#dc2626}
        
        .payment-details{font-size:12px;color:#64748b;max-width:180px}
        .payment-details code{background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:11px}
        
        .actions-cell{display:flex;gap:5px;flex-wrap:wrap}
        
        /* Modal */
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;padding:20px}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:15px;padding:30px;max-width:500px;width:100%;max-height:90vh;overflow-y:auto}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .modal-title{font-size:20px;font-weight:600;color:#1e293b}
        .modal-close{width:35px;height:35px;border-radius:50%;background:#f1f5f9;border:none;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center}
        
        .detail-row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f1f5f9}
        .detail-row:last-child{border-bottom:none}
        .detail-label{color:#64748b;font-size:14px}
        .detail-value{font-weight:600;color:#1e293b;font-size:14px;text-align:right}
        
        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#1e293b;font-size:14px}
        .form-control{width:100%;padding:12px 15px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px}
        .form-control:focus{border-color:#667eea;outline:none}
        textarea.form-control{min-height:80px;resize:vertical}
        
        .btn-group{display:flex;gap:10px;margin-top:20px}
        .btn-group .btn{flex:1}
        
        /* Pagination */
        .pagination{display:flex;justify-content:center;gap:8px;padding:20px}
        .page-btn{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#fff;color:#64748b;text-decoration:none;font-weight:600;font-size:14px;border:1px solid #e2e8f0;cursor:pointer}
        .page-btn:hover{background:#667eea;color:#fff;border-color:#667eea}
        .page-btn.active{background:#667eea;color:#fff;border-color:#667eea}
        .page-btn.disabled{opacity:0.5;cursor:not-allowed;pointer-events:none}
        
        /* Empty State */
        .empty-state{text-align:center;padding:60px 20px}
        .empty-state .icon{font-size:60px;margin-bottom:20px;opacity:0.5}
        .empty-state h3{color:#64748b;margin-bottom:10px}
        .empty-state p{color:#94a3b8;font-size:14px}
        
        /* Responsive */
        @media(max-width:1200px){
            .stats-grid{grid-template-columns:repeat(3,1fr)}
        }
        @media(max-width:992px){
            .admin-layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .stats-grid{grid-template-columns:repeat(2,1fr)}
        }
        @media(max-width:768px){
            .filters-row{flex-direction:column}
            .filter-group{width:100%}
            .filter-group select,.filter-group input{width:100%}
            .filter-actions{width:100%;margin-left:0}
            .filter-actions .btn{flex:1}
            .stats-grid{grid-template-columns:1fr}
            th,td{padding:10px;font-size:12px}
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div class="page-title">💸 Withdrawal Requests</div>
        </div>
        
        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
        <?php endforeach; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card highlight">
                <div class="stat-value"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₹<?php echo number_format($pending_amount, 0); ?></div>
                <div class="stat-label">Pending Amount</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $approved_count; ?></div>
                <div class="stat-label">Approved (Unpaid)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">₹<?php echo number_format($total_paid, 0); ?></div>
                <div class="stat-label">Total Paid Out</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $today_completed; ?></div>
                <div class="stat-label">Completed Today</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                        <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>✅ Approved</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>💰 Completed</option>
                        <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>❌ Rejected</option>
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Payment Method</label>
                    <select name="method">
                        <option value="all" <?php echo $filter_method === 'all' ? 'selected' : ''; ?>>All Methods</option>
                        <option value="upi" <?php echo $filter_method === 'upi' ? 'selected' : ''; ?>>📱 UPI</option>
                        <option value="bank" <?php echo $filter_method === 'bank' ? 'selected' : ''; ?>>🏦 Bank</option>
                        <option value="paytm" <?php echo $filter_method === 'paytm' ? 'selected' : ''; ?>>💳 Paytm</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search User</label>
                    <input type="text" name="search" placeholder="Name, email, mobile..." value="<?php echo escape($search); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="?status=pending" class="btn btn-secondary">↺ Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Withdrawals Table -->
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">Withdrawal Requests</div>
                <div style="font-size:14px;color:#64748b"><?php echo $total; ?> total</div>
            </div>
            
            <?php if (empty($withdrawals)): ?>
                <div class="empty-state">
                    <div class="icon">💸</div>
                    <h3>No Withdrawals Found</h3>
                    <p>
                        <?php if ($filter_status !== 'all' || $filter_method !== 'all' || !empty($search)): ?>
                            Try adjusting your filters. <a href="?status=pending" style="color:#667eea">Reset</a>
                        <?php else: ?>
                            No withdrawal requests yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Payment Details</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($withdrawals as $wr): ?>
                                <tr>
                                    <td>#<?php echo $wr['id']; ?></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar"><?php echo strtoupper(substr($wr['user_name'], 0, 1)); ?></div>
                                            <div class="user-info">
                                                <div class="user-name"><?php echo escape($wr['user_name']); ?></div>
                                                <div class="user-email"><?php echo escape($wr['user_mobile']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="amount-cell">₹<?php echo number_format($wr['amount'], 2); ?></div>
                                    </td>
                                    <td>
                                        <span class="method-badge <?php echo $wr['payment_method']; ?>">
                                            <?php echo $wr['payment_method'] === 'upi' ? '📱' : ($wr['payment_method'] === 'bank' ? '🏦' : '💳'); ?>
                                            <?php echo strtoupper($wr['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="payment-details">
                                            <?php
                                            if ($wr['payment_method'] === 'bank') {
                                                $bank_details = json_decode($wr['payment_details'], true);
                                                if ($bank_details) {
                                                    echo "<strong>" . escape($bank_details['bank_name'] ?? '') . "</strong><br>";
                                                    echo "A/C: <code>" . escape($bank_details['account'] ?? '') . "</code><br>";
                                                    echo "IFSC: <code>" . escape($bank_details['ifsc'] ?? '') . "</code>";
                                                } else {
                                                    echo escape($wr['payment_details']);
                                                }
                                            } else {
                                                echo "<code>" . escape($wr['payment_details']) . "</code>";
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $wr['status']; ?>">
                                            <?php echo ucfirst($wr['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($wr['created_at'])); ?><br>
                                        <small style="color:#94a3b8"><?php echo date('H:i', strtotime($wr['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <?php if ($wr['status'] === 'pending'): ?>
                                                <button class="btn btn-success btn-sm" onclick="showApproveModal(<?php echo $wr['id']; ?>, '<?php echo escape($wr['user_name']); ?>', <?php echo $wr['amount']; ?>)">✓ Approve</button>
                                                <button class="btn btn-danger btn-sm" onclick="showRejectModal(<?php echo $wr['id']; ?>)">✗ Reject</button>
                                            <?php elseif ($wr['status'] === 'approved'): ?>
                                                <button class="btn btn-success btn-sm" onclick="showCompleteModal(<?php echo $wr['id']; ?>, <?php echo $wr['amount']; ?>)">💰 Mark Paid</button>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" onclick="showDetailsModal(<?php echo htmlspecialchars(json_encode($wr), ENT_QUOTES); ?>)">👁️ View</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        $query_params = $_GET;
                        unset($query_params['page']);
                        $query_string = http_build_query($query_params);
                        $query_string = $query_string ? "&$query_string" : '';
                        ?>
                        <a href="?page=1<?php echo $query_string; ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">«</a>
                        <a href="?page=<?php echo max(1, $page - 1) . $query_string; ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">‹</a>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i . $query_string; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <a href="?page=<?php echo min($total_pages, $page + 1) . $query_string; ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">›</a>
                        <a href="?page=<?php echo $total_pages . $query_string; ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">»</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal" id="approveModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">✅ Approve Withdrawal</div>
            <button class="modal-close" onclick="hideModal('approveModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="withdrawal_id" id="approve_id">
            
            <div class="detail-row">
                <span class="detail-label">User</span>
                <span class="detail-value" id="approve_user"></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount</span>
                <span class="detail-value" id="approve_amount" style="color:#10b981"></span>
            </div>
            
            <div class="form-group" style="margin-top:20px">
                <label>Admin Note (Optional)</label>
                <textarea name="admin_note" class="form-control" placeholder="Add a note..."></textarea>
            </div>
            
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="hideModal('approveModal')">Cancel</button>
                <button type="submit" class="btn btn-success">✓ Approve Withdrawal</button>
            </div>
        </form>
    </div>
</div>

<!-- Complete Modal -->
<div class="modal" id="completeModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">💰 Mark as Paid</div>
            <button class="modal-close" onclick="hideModal('completeModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="withdrawal_id" id="complete_id">
            
            <p style="color:#64748b;margin-bottom:20px">Confirm that you have sent <strong id="complete_amount"></strong> to the user's account.</p>
            
            <div class="form-group">
                <label>Transaction Reference (Optional)</label>
                <input type="text" name="admin_note" class="form-control" placeholder="UTR/Reference number...">
            </div>
            
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="hideModal('completeModal')">Cancel</button>
                <button type="submit" class="btn btn-success">💰 Confirm Payment Sent</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal" id="rejectModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">❌ Reject Withdrawal</div>
            <button class="modal-close" onclick="hideModal('rejectModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="withdrawal_id" id="reject_id">
            
            <p style="color:#64748b;margin-bottom:20px">The withdrawal amount will be refunded to the user's wallet.</p>
            
            <div class="form-group">
                <label>Reason for Rejection <span style="color:#ef4444">*</span></label>
                <textarea name="reject_reason" class="form-control" required placeholder="Enter reason...">Invalid payment details</textarea>
            </div>
            
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="hideModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">❌ Reject & Refund</button>
            </div>
        </form>
    </div>
</div>

<!-- Details Modal -->
<div class="modal" id="detailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">📋 Withdrawal Details</div>
            <button class="modal-close" onclick="hideModal('detailsModal')">×</button>
        </div>
        <div id="detailsContent"></div>
        <div class="btn-group" style="margin-top:20px">
            <button type="button" class="btn btn-secondary" onclick="hideModal('detailsModal')" style="width:100%">Close</button>
        </div>
    </div>
</div>

<script>
function showModal(id) {
    document.getElementById(id).classList.add('show');
}

function hideModal(id) {
    document.getElementById(id).classList.remove('show');
}

function showApproveModal(id, name, amount) {
    document.getElementById('approve_id').value = id;
    document.getElementById('approve_user').textContent = name;
    document.getElementById('approve_amount').textContent = '₹' + amount.toLocaleString('en-IN', {minimumFractionDigits: 2});
    showModal('approveModal');
}

function showCompleteModal(id, amount) {
    document.getElementById('complete_id').value = id;
    document.getElementById('complete_amount').textContent = '₹' + amount.toLocaleString('en-IN', {minimumFractionDigits: 2});
    showModal('completeModal');
}

function showRejectModal(id) {
    document.getElementById('reject_id').value = id;
    showModal('rejectModal');
}

function showDetailsModal(data) {
    let html = `
        <div class="detail-row">
            <span class="detail-label">ID</span>
            <span class="detail-value">#${data.id}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">User</span>
            <span class="detail-value">${data.user_name}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Amount</span>
            <span class="detail-value">₹${parseFloat(data.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Method</span>
            <span class="detail-value">${data.payment_method.toUpperCase()}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value">${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Requested</span>
            <span class="detail-value">${data.created_at}</span>
        </div>
    `;
    
    if (data.processed_at) {
        html += `
            <div class="detail-row">
                <span class="detail-label">Processed</span>
                <span class="detail-value">${data.processed_at}</span>
            </div>
        `;
    }
    
    if (data.processed_by) {
        html += `
            <div class="detail-row">
                <span class="detail-label">Processed By</span>
                <span class="detail-value">${data.processed_by}</span>
            </div>
        `;
    }
    
    if (data.admin_note) {
        html += `
            <div class="detail-row">
                <span class="detail-label">Note</span>
                <span class="detail-value">${data.admin_note}</span>
            </div>
        `;
    }
    
    document.getElementById('detailsContent').innerHTML = html;
    showModal('detailsModal');
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) hideModal(this.id);
    });
});

// Auto refresh if on pending
<?php if ($filter_status === 'pending'): ?>
setTimeout(() => location.reload(), 60000);
<?php endif; ?>
</script>
</body>
</html>
