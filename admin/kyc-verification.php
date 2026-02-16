<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/kyc-functions.php';
require_once __DIR__ . '/../includes/Notifications.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];

// Handle quick actions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['kyc_id'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $kyc_id = (int)$_POST['kyc_id'];
        $action = $_POST['action'];
        
        $kyc = getKYCById($pdo, $kyc_id);
        if ($kyc) {
            if ($action === 'approve') {
                if (updateKYCStatus($pdo, $kyc_id, 'verified', null, $_SESSION['admin_id'] ?? null)) {
                    // Send notification
                    try {
                        $notifications = new Notifications($pdo);
                        $notifications->sendTemplateNotification(
                            'kyc_verified',
                            $kyc['user_id'],
                            $kyc['email'],
                            [
                                'user_name' => $kyc['username'],
                                'full_name' => $kyc['full_name']
                            ],
                            'email'
                        );
                    } catch (Exception $e) {
                        error_log("KYC approval notification error: {$e->getMessage()}");
                    }
                    $_SESSION['success'] = 'KYC approved successfully.';
                } else {
                    $_SESSION['error'] = 'Failed to approve KYC.';
                }
            } elseif ($action === 'reject') {
                $reason = $_POST['reason'] ?? 'Documents verification failed';
                if (updateKYCStatus($pdo, $kyc_id, 'rejected', $reason, $_SESSION['admin_id'] ?? null)) {
                    // Send notification
                    try {
                        $notifications = new Notifications($pdo);
                        $notifications->sendTemplateNotification(
                            'kyc_rejected',
                            $kyc['user_id'],
                            $kyc['email'],
                            [
                                'user_name' => $kyc['username'],
                                'full_name' => $kyc['full_name'],
                                'rejection_reason' => $reason
                            ],
                            'email'
                        );
                    } catch (Exception $e) {
                        error_log("KYC rejection notification error: {$e->getMessage()}");
                    }
                    $_SESSION['success'] = 'KYC rejected successfully.';
                } else {
                    $_SESSION['error'] = 'Failed to reject KYC.';
                }
            }
        }
    }
    header('Location: ' . ADMIN_URL . '/kyc-verification.php');
    exit;
}

// Get filter status
$filter_status = $_GET['status'] ?? null;
if ($filter_status && !in_array($filter_status, ['pending', 'verified', 'rejected'])) {
    $filter_status = null;
}

// Get KYC applications
$kyc_applications = getAllKYC($pdo, $filter_status);

// Get KYC stats
$kyc_stats = getKYCStats($pdo);
$total_kyc = (int)($kyc_stats['total'] ?? 0);
$pending_kyc = (int)($kyc_stats['pending'] ?? 0);
$verified_kyc = (int)($kyc_stats['verified'] ?? 0);
$rejected_kyc = (int)($kyc_stats['rejected'] ?? 0);

// Get badges for sidebar
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM wallet_recharge_requests WHERE status = 'pending'");
    $pending_wallet_recharges = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND is_read = 0");
    $unread_messages = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_questions = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Badge count error: {$e->getMessage()}");
    $pending_tasks = $pending_withdrawals = $pending_wallet_recharges = $unread_messages = $unanswered_questions = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verification - <?php echo APP_NAME; ?></title>
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
        .sidebar-menu a.logout:hover{background:rgba(231,76,60,0.1)}
        
        .main-content{padding:25px;overflow-x:hidden}
        
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:15px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .page-subtitle{color:#64748b;font-size:14px;margin-top:5px}
        
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .alert.success{background:#ecfdf5;color:#059669;border:1px solid #a7f3d0}
        .alert.error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
        
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:15px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,0.04);position:relative;overflow:hidden;transition:transform 0.2s}
        .stat-card:hover{transform:translateY(-3px);box-shadow:0 5px 20px rgba(0,0,0,0.08)}
        .stat-card::after{content:'';position:absolute;top:0;right:0;width:100px;height:100px;border-radius:50%;opacity:0.1;transform:translate(30%,-30%)}
        .stat-card.blue::after{background:#3b82f6}
        .stat-card.orange::after{background:#f59e0b}
        .stat-card.green::after{background:#10b981}
        .stat-card.red::after{background:#ef4444}
        .stat-icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:15px}
        .stat-card.blue .stat-icon{background:#eff6ff;color:#3b82f6}
        .stat-card.orange .stat-icon{background:#fffbeb;color:#f59e0b}
        .stat-card.green .stat-icon{background:#ecfdf5;color:#10b981}
        .stat-card.red .stat-icon{background:#fef2f2;color:#ef4444}
        .stat-value{font-size:28px;font-weight:700;color:#1e293b;margin-bottom:5px}
        .stat-label{font-size:13px;color:#64748b}
        
        .filter-tabs{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
        .filter-tab{padding:10px 20px;border-radius:10px;background:#fff;border:2px solid #e2e8f0;color:#64748b;text-decoration:none;font-weight:600;font-size:14px;transition:all 0.2s}
        .filter-tab:hover{border-color:#667eea;color:#667eea}
        .filter-tab.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-color:transparent}
        
        .table-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,0.04);overflow:hidden}
        .table-header{padding:20px;border-bottom:1px solid #f1f5f9}
        .table-title{font-size:16px;font-weight:600;color:#1e293b}
        table{width:100%;border-collapse:collapse}
        th{background:#f8fafc;padding:12px 20px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px}
        td{padding:15px 20px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#334155}
        tr:last-child td{border-bottom:none}
        tr:hover{background:#f8fafc}
        
        .user-cell{display:flex;align-items:center;gap:12px}
        .user-avatar{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:14px}
        .user-info{line-height:1.4}
        .user-name{font-weight:600;color:#1e293b}
        .user-email{font-size:12px;color:#64748b}
        
        .status-badge{padding:5px 12px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
        .status-badge.pending{background:#fffbeb;color:#d97706}
        .status-badge.verified{background:#ecfdf5;color:#059669}
        .status-badge.rejected{background:#fef2f2;color:#dc2626}
        
        .action-buttons{display:flex;gap:8px}
        .btn{padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;border:none;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all 0.2s}
        .btn:hover{transform:translateY(-2px);box-shadow:0 3px 10px rgba(0,0,0,0.15)}
        .btn-view{background:#f1f5f9;color:#475569}
        .btn-view:hover{background:#e2e8f0}
        .btn-approve{background:#ecfdf5;color:#059669}
        .btn-approve:hover{background:#d1fae5}
        .btn-reject{background:#fef2f2;color:#dc2626}
        .btn-reject:hover{background:#fee2e2}
        
        .empty-state{text-align:center;padding:60px 20px;color:#64748b}
        .empty-state-icon{font-size:64px;margin-bottom:20px;opacity:0.3}
        .empty-state-title{font-size:20px;font-weight:600;color:#1e293b;margin-bottom:10px}
        
        .modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:15px;padding:30px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
        .modal-header{font-size:20px;font-weight:700;margin-bottom:20px;color:#1e293b}
        .modal-body{margin-bottom:20px}
        .form-group{margin-bottom:15px}
        .form-label{display:block;font-size:14px;font-weight:600;color:#334155;margin-bottom:8px}
        .form-control{width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px}
        .form-control:focus{outline:none;border-color:#667eea}
        .modal-footer{display:flex;gap:10px;justify-content:flex-end}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-secondary{background:#e2e8f0;color:#64748b}
        
        @media(max-width:992px){
            .admin-layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .stats-grid{grid-template-columns:repeat(2,1fr)}
        }
        @media(max-width:576px){
            .stats-grid{grid-template-columns:1fr}
            .action-buttons{flex-direction:column}
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div>
                <div class="page-title">KYC Verification</div>
                <div class="page-subtitle">Review and verify user KYC applications</div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo escape($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?php echo escape($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?php echo $total_kyc; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?php echo $pending_kyc; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo $verified_kyc; ?></div>
                <div class="stat-label">Verified</div>
            </div>
            <div class="stat-card red">
                <div class="stat-icon">❌</div>
                <div class="stat-value"><?php echo $rejected_kyc; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
        
        <div class="filter-tabs">
            <a href="<?php echo ADMIN_URL; ?>/kyc-verification.php" class="filter-tab <?php echo !$filter_status ? 'active' : ''; ?>">All Applications</a>
            <a href="<?php echo ADMIN_URL; ?>/kyc-verification.php?status=pending" class="filter-tab <?php echo $filter_status === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="<?php echo ADMIN_URL; ?>/kyc-verification.php?status=verified" class="filter-tab <?php echo $filter_status === 'verified' ? 'active' : ''; ?>">Verified</a>
            <a href="<?php echo ADMIN_URL; ?>/kyc-verification.php?status=rejected" class="filter-tab <?php echo $filter_status === 'rejected' ? 'active' : ''; ?>">Rejected</a>
        </div>
        
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">KYC Applications</div>
            </div>
            <?php if (count($kyc_applications) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Full Name</th>
                        <th>Submitted Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kyc_applications as $kyc): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar"><?php echo strtoupper(substr($kyc['username'], 0, 2)); ?></div>
                                <div class="user-info">
                                    <div class="user-name"><?php echo escape($kyc['username']); ?></div>
                                    <div class="user-email"><?php echo escape($kyc['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo escape($kyc['full_name']); ?></td>
                        <td><?php echo date('d M Y, H:i', strtotime($kyc['submitted_at'])); ?></td>
                        <td>
                            <span class="status-badge <?php echo $kyc['status']; ?>">
                                <?php echo ucfirst($kyc['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="<?php echo ADMIN_URL; ?>/kyc-view.php?id=<?php echo $kyc['id']; ?>" class="btn btn-view">👁️ View</a>
                                <?php if ($kyc['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="kyc_id" value="<?php echo $kyc['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Approve this KYC application?')">✓ Approve</button>
                                </form>
                                <button type="button" class="btn btn-reject" onclick="showRejectModal(<?php echo $kyc['id']; ?>)">✗ Reject</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <div class="empty-state-title">No KYC Applications Found</div>
                <p>There are no KYC applications matching your filter.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">Reject KYC Application</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="kyc_id" id="reject_kyc_id" value="">
            <input type="hidden" name="action" value="reject">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Rejection Reason</label>
                    <textarea name="reason" class="form-control" rows="4" placeholder="Enter reason for rejection..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Reject Application</button>
            </div>
        </form>
    </div>
</div>

<script>
function showRejectModal(kycId) {
    document.getElementById('reject_kyc_id').value = kycId;
    document.getElementById('rejectModal').classList.add('show');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('show');
}

document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});
</script>
</body>
</html>
