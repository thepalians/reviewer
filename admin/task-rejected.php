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
    $rejection_id = intval($_POST['rejection_id'] ?? 0);
    
    if ($rejection_id > 0) {
        try {
            if ($action === 'mark_reviewed') {
                $admin_note = sanitizeInput($_POST['admin_note'] ?? '');
                $stmt = $pdo->prepare("
                    UPDATE task_rejections 
                    SET admin_reviewed = 1, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$admin_note, $admin_name, $rejection_id]);
                $success = "Marked as reviewed successfully!";
                
            } elseif ($action === 'allow_resubmit') {
                $admin_note = sanitizeInput($_POST['admin_note'] ?? 'Resubmission allowed by admin');
                $stmt = $pdo->prepare("
                    UPDATE task_rejections 
                    SET can_resubmit = 1, admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$admin_note, $admin_name, $rejection_id]);
                
                // Notify user
                $stmt = $pdo->prepare("SELECT user_id, task_id FROM task_rejections WHERE id = ?");
                $stmt->execute([$rejection_id]);
                $rejection = $stmt->fetch();
                
                if ($rejection) {
                    createNotification(
                        $rejection['user_id'],
                        'info',
                        '🔄 Resubmission Allowed',
                        "You can now resubmit Task #{$rejection['task_id']}. Please review the rejection reason and submit again."
                    );
                }
                
                $success = "Resubmission allowed successfully!";
            }
        } catch (PDOException $e) {
            $errors[] = "Action failed: " . $e->getMessage();
        }
    }
}

// Filters
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_rejection_type = $_GET['rejection_type'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_reviewed = $_GET['reviewed'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($filter_date_from) {
    $where_conditions[] = "tr.created_at >= ?";
    $params[] = $filter_date_from . ' 00:00:00';
}

if ($filter_date_to) {
    $where_conditions[] = "tr.created_at <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
}

if ($filter_rejection_type) {
    $where_conditions[] = "tr.rejection_type = ?";
    $params[] = $filter_rejection_type;
}

if ($filter_user) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
}

if ($filter_reviewed !== '') {
    $where_conditions[] = "tr.admin_reviewed = ?";
    $params[] = intval($filter_reviewed);
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get rejected tasks
try {
    $stmt = $pdo->prepare("
        SELECT 
            tr.*,
            u.name as user_name,
            u.email as user_email,
            t.product_name,
            t.platform,
            t.commission
        FROM task_rejections tr
        JOIN users u ON tr.user_id = u.id
        JOIN tasks t ON tr.task_id = t.id
        $where_sql
        ORDER BY tr.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $rejected_tasks = $stmt->fetchAll();
    
    // Get rejection statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN admin_reviewed = 1 THEN 1 ELSE 0 END) as reviewed,
            SUM(CASE WHEN can_resubmit = 1 THEN 1 ELSE 0 END) as resubmittable
        FROM task_rejections
    ");
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $rejected_tasks = [];
    $stats = ['total' => 0, 'reviewed' => 0, 'resubmittable' => 0];
    error_log("Rejected Tasks Error: " . $e->getMessage());
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
    <title>Rejected Tasks - Admin - <?php echo APP_NAME; ?></title>
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
        
        .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .stat-value{font-size:28px;font-weight:700;color:#1e293b}
        .stat-label{font-size:13px;color:#64748b;margin-top:5px}
        
        .card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:25px}
        .card-title{font-size:18px;font-weight:600;color:#1e293b;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f1f5f9}
        
        .filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:25px;background:#fff;padding:20px;border-radius:12px}
        .filter-group{display:flex;flex-direction:column}
        .filter-label{font-size:13px;font-weight:500;color:#475569;margin-bottom:6px}
        .filter-input,.filter-select{padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px}
        .btn{padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-secondary{background:#64748b;color:#fff}
        .btn-success{background:#10b981;color:#fff}
        .btn-small{padding:6px 12px;font-size:12px}
        
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{background:#f8fafc;padding:12px 15px;text-align:left;font-size:13px;font-weight:600;color:#475569;border-bottom:2px solid #e2e8f0}
        .data-table td{padding:15px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#1e293b}
        .data-table tr:hover{background:#f8fafc}
        
        .badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}
        .badge-quality{background:#fef3c7;color:#92400e}
        .badge-guidelines{background:#dbeafe;color:#1e40af}
        .badge-proof{background:#fee2e2;color:#991b1b}
        .badge-duplicate{background:#f3e8ff;color:#6b21a8}
        .badge-reviewed{background:#dcfce7;color:#166534}
        .badge-pending{background:#fef3c7;color:#92400e}
        .badge-yes{background:#dcfce7;color:#166534}
        .badge-no{background:#fee2e2;color:#991b1b}
        
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
                <h1 class="page-title">❌ Rejected Tasks</h1>
                <p class="page-subtitle">Review and manage rejected task submissions</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div>❌ <?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total Rejections</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['reviewed']); ?></div>
                    <div class="stat-label">Reviewed by Admin</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['resubmittable']); ?></div>
                    <div class="stat-label">Resubmission Allowed</div>
                </div>
            </div>
            
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label class="filter-label">From Date</label>
                    <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">To Date</label>
                    <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Rejection Type</label>
                    <select name="rejection_type" class="filter-select">
                        <option value="">All Types</option>
                        <option value="quality" <?php if($filter_rejection_type==='quality')echo 'selected';?>>Quality Issue</option>
                        <option value="guidelines" <?php if($filter_rejection_type==='guidelines')echo 'selected';?>>Guideline Violation</option>
                        <option value="proof" <?php if($filter_rejection_type==='proof')echo 'selected';?>>Invalid Proof</option>
                        <option value="duplicate" <?php if($filter_rejection_type==='duplicate')echo 'selected';?>>Duplicate</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">User Search</label>
                    <input type="text" name="user" class="filter-input" placeholder="Name or email" value="<?php echo htmlspecialchars($filter_user); ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Review Status</label>
                    <select name="reviewed" class="filter-select">
                        <option value="">All</option>
                        <option value="0" <?php if($filter_reviewed==='0')echo 'selected';?>>Not Reviewed</option>
                        <option value="1" <?php if($filter_reviewed==='1')echo 'selected';?>>Reviewed</option>
                    </select>
                </div>
                <div class="filter-group" style="justify-content:flex-end;display:flex;gap:10px;align-items:flex-end">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="task-rejected.php" class="btn btn-secondary">🔄 Reset</a>
                </div>
            </form>
            
            <div class="card">
                <h3 class="card-title">Rejected Tasks (<?php echo count($rejected_tasks); ?>)</h3>
                
                <?php if (empty($rejected_tasks)): ?>
                    <p style="text-align:center;color:#64748b;padding:40px">No rejected tasks found</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Task ID</th>
                                <th>User</th>
                                <th>Product</th>
                                <th>Rejection Type</th>
                                <th>Rejection Reason</th>
                                <th>Can Resubmit</th>
                                <th>Reviewed</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rejected_tasks as $task): ?>
                            <tr>
                                <td><strong>#<?php echo $task['task_id']; ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($task['user_name']); ?><br>
                                    <small style="color:#64748b"><?php echo htmlspecialchars($task['user_email']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($task['product_name']); ?><br>
                                    <small style="color:#64748b"><?php echo htmlspecialchars($task['platform']); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $type_badges = [
                                        'quality' => 'badge-quality',
                                        'guidelines' => 'badge-guidelines',
                                        'proof' => 'badge-proof',
                                        'duplicate' => 'badge-duplicate'
                                    ];
                                    $badge_class = $type_badges[$task['rejection_type']] ?? 'badge-quality';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($task['rejection_type']); ?></span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars(substr($task['rejection_reason'], 0, 60)); ?><?php if(strlen($task['rejection_reason'])>60)echo '...';?></small>
                                </td>
                                <td>
                                    <?php if ($task['can_resubmit']): ?>
                                        <span class="badge badge-yes">✓ Yes</span>
                                    <?php else: ?>
                                        <span class="badge badge-no">✗ No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($task['admin_reviewed']): ?>
                                        <span class="badge badge-reviewed">✓ Reviewed</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($task['created_at'])); ?></td>
                                <td>
                                    <?php if (!$task['admin_reviewed']): ?>
                                        <button onclick="openReviewModal(<?php echo $task['id']; ?>)" class="btn btn-success btn-small">✓ Review</button>
                                    <?php endif; ?>
                                    <?php if (!$task['can_resubmit']): ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="rejection_id" value="<?php echo $task['id']; ?>">
                                            <input type="hidden" name="action" value="allow_resubmit">
                                            <button type="submit" class="btn btn-primary btn-small">🔄 Allow Resubmit</button>
                                        </form>
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
    
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Mark as Reviewed</h3>
            <form method="POST" id="reviewForm">
                <input type="hidden" name="rejection_id" id="modalRejectionId">
                <input type="hidden" name="action" value="mark_reviewed">
                <div class="form-group">
                    <label class="form-label">Admin Note (Optional)</label>
                    <textarea name="admin_note" class="form-textarea" placeholder="Add any notes about this review..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeReviewModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-success">✓ Mark Reviewed</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openReviewModal(id) {
            document.getElementById('modalRejectionId').value = id;
            document.getElementById('reviewModal').classList.add('active');
        }
        
        function closeReviewModal() {
            document.getElementById('reviewModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('reviewModal');
            if (event.target === modal) {
                closeReviewModal();
            }
        }
    </script>
</body>
</html>
