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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = intval($_POST['request_id'] ?? 0);
    
    if ($request_id > 0) {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE review_requests SET admin_status = 'approved', admin_note = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
                $admin_note = sanitizeInput($_POST['admin_note'] ?? 'Approved by admin');
                $stmt->execute([$admin_note, $admin_name, $request_id]);
                
                // Notify seller
                $stmt = $pdo->prepare("SELECT seller_id FROM review_requests WHERE id = ?");
                $stmt->execute([$request_id]);
                $seller_id = $stmt->fetchColumn();
                
                // Create notification for seller (if notification system exists)
                
                $success = "Review request approved successfully!";
                
            } elseif ($action === 'reject') {
                $rejection_reason = sanitizeInput($_POST['rejection_reason'] ?? '');
                if (empty($rejection_reason)) {
                    $errors[] = "Rejection reason is required";
                    $pdo->rollBack();
                } else {
                    $stmt = $pdo->prepare("UPDATE review_requests SET admin_status = 'rejected', rejection_reason = ?, rejected_by = ?, rejected_at = NOW() WHERE id = ?");
                    $stmt->execute([$rejection_reason, $admin_name, $request_id]);
                    $success = "Review request rejected!";
                }
            }
            
            if (empty($errors)) {
                $pdo->commit();
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Action failed: " . $e->getMessage();
        }
    }
}

// Filters
$filter_status = $_GET['admin_status'] ?? 'pending';
$filter_payment = $_GET['payment_status'] ?? 'paid';

$where_conditions = ["payment_status = ?"];
$params = [$filter_payment];

if ($filter_status) {
    $where_conditions[] = "admin_status = ?";
    $params[] = $filter_status;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_conditions);

// Get review requests
try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.*,
            s.name as seller_name,
            s.email as seller_email,
            s.company_name
        FROM review_requests rr
        JOIN sellers s ON rr.seller_id = s.id
        $where_sql
        ORDER BY rr.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN admin_status = 'pending' AND payment_status = 'paid' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN admin_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN admin_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN payment_status = 'paid' THEN grand_total ELSE 0 END) as total_revenue
        FROM review_requests
    ");
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $requests = [];
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'total_revenue' => 0];
    error_log("Review Requests Error: " . $e->getMessage());
}

// Get sidebar badge counts
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND is_read = 0");
    $unread_messages = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_questions = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_withdrawals = $unread_messages = $unanswered_questions = $pending_tasks = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Requests - Admin - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
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
        .main-content{padding:25px}
        .page-header{margin-bottom:25px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .page-subtitle{color:#64748b;font-size:14px;margin-top:5px}
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .stat-value{font-size:28px;font-weight:700;color:#1e293b}
        .stat-label{font-size:13px;color:#64748b;margin-top:5px}
        .card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:25px}
        .card-title{font-size:18px;font-weight:600;color:#1e293b;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f1f5f9}
        .filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:25px;background:#fff;padding:20px;border-radius:12px}
        .filter-group{display:flex;flex-direction:column}
        .filter-label{font-size:13px;font-weight:500;color:#475569;margin-bottom:6px}
        .filter-select{padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px}
        .btn{padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;display:inline-block;text-decoration:none;text-align:center}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-secondary{background:#64748b;color:#fff}
        .btn-success{background:#10b981;color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-small{padding:6px 12px;font-size:12px}
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{background:#f8fafc;padding:12px 15px;text-align:left;font-size:13px;font-weight:600;color:#475569;border-bottom:2px solid #e2e8f0}
        .data-table td{padding:15px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#1e293b}
        .data-table tr:hover{background:#f8fafc}
        .badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}
        .badge-pending{background:#fef3c7;color:#92400e}
        .badge-approved{background:#dcfce7;color:#166534}
        .badge-rejected{background:#fee2e2;color:#991b1b}
        .badge-paid{background:#dcfce7;color:#166534}
        .action-buttons{display:flex;gap:5px;flex-wrap:wrap}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal.active{display:flex}
        .modal-content{background:#fff;border-radius:15px;padding:30px;max-width:500px;width:90%}
        .modal-title{font-size:20px;font-weight:700;color:#1e293b;margin-bottom:20px}
        .form-group{margin-bottom:20px}
        .form-label{font-size:14px;font-weight:500;color:#475569;margin-bottom:8px;display:block}
        .form-textarea{width:100%;padding:12px 15px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;resize:vertical;min-height:100px}
        .modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:25px}
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">📝 Review Requests</h1>
                <p class="page-subtitle">Approve or reject seller review requests</p>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div>❌ <?php echo htmlspecialchars($error); ?></div><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['pending']); ?></div><div class="stat-label">Pending Approval</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['approved']); ?></div><div class="stat-label">Approved</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['rejected']); ?></div><div class="stat-label">Rejected</div></div>
                <div class="stat-card"><div class="stat-value">₹<?php echo number_format($stats['total_revenue']); ?></div><div class="stat-label">Total Revenue</div></div>
            </div>
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label class="filter-label">Admin Status</label>
                    <select name="admin_status" class="filter-select">
                        <option value="pending" <?php if($filter_status==='pending')echo 'selected';?>>Pending</option>
                        <option value="approved" <?php if($filter_status==='approved')echo 'selected';?>>Approved</option>
                        <option value="rejected" <?php if($filter_status==='rejected')echo 'selected';?>>Rejected</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Payment Status</label>
                    <select name="payment_status" class="filter-select">
                        <option value="paid" <?php if($filter_payment==='paid')echo 'selected';?>>Paid</option>
                        <option value="pending" <?php if($filter_payment==='pending')echo 'selected';?>>Payment Pending</option>
                    </select>
                </div>
                <div class="filter-group" style="justify-content:flex-end;display:flex;gap:10px;align-items:flex-end">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="review-requests.php" class="btn btn-secondary">🔄 Reset</a>
                </div>
            </form>
            <div class="card">
                <h3 class="card-title">Review Requests (<?php echo count($requests); ?>)</h3>
                <?php if (empty($requests)): ?>
                    <p style="text-align:center;color:#64748b;padding:40px">No review requests found</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>ID</th><th>Seller</th><th>Product</th><th>Platform</th><th>Reviews</th><th>Amount</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><strong>#<?php echo $req['id']; ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($req['seller_name']); ?><br>
                                    <small style="color:#64748b"><?php echo htmlspecialchars($req['company_name'] ?? $req['seller_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($req['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($req['platform']); ?></td>
                                <td><?php echo $req['reviews_needed']; ?></td>
                                <td><strong>₹<?php echo number_format($req['grand_total'], 2); ?></strong></td>
                                <td><span class="badge badge-<?php echo $req['payment_status']; ?>"><?php echo ucfirst($req['payment_status']); ?></span></td>
                                <td><span class="badge badge-<?php echo $req['admin_status']; ?>"><?php echo ucfirst($req['admin_status']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <?php if ($req['admin_status'] === 'pending' && $req['payment_status'] === 'paid'): ?>
                                    <div class="action-buttons">
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-small">✓ Approve</button>
                                        </form>
                                        <button onclick="openRejectModal(<?php echo $req['id']; ?>)" class="btn btn-danger btn-small">✗ Reject</button>
                                    </div>
                                    <?php else: ?>
                                    <small style="color:#64748b">No action</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Reject Review Request</h3>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="request_id" id="modalRequestId">
                <input type="hidden" name="action" value="reject">
                <div class="form-group">
                    <label class="form-label">Rejection Reason *</label>
                    <textarea name="rejection_reason" class="form-textarea" placeholder="Enter reason for rejection..." required></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeRejectModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-danger">✗ Reject Request</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function openRejectModal(id) {
            document.getElementById('modalRequestId').value = id;
            document.getElementById('rejectModal').classList.add('active');
        }
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
        }
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target === modal) closeRejectModal();
        }
    </script>
</body>
</html>
