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
    $activity_id = intval($_POST['activity_id'] ?? 0);
    
    if ($activity_id > 0) {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'review') {
                $admin_note = sanitizeInput($_POST['admin_note'] ?? '');
                $stmt = $pdo->prepare("
                    UPDATE suspicious_activities 
                    SET status = 'reviewed', admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$admin_note, $admin_name, $activity_id]);
                $success = "Activity marked as reviewed!";
                
            } elseif ($action === 'dismiss') {
                $admin_note = sanitizeInput($_POST['admin_note'] ?? 'Dismissed - false positive');
                $stmt = $pdo->prepare("
                    UPDATE suspicious_activities 
                    SET status = 'dismissed', admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$admin_note, $admin_name, $activity_id]);
                $success = "Activity dismissed!";
                
            } elseif ($action === 'add_penalty') {
                $penalty_type = sanitizeInput($_POST['penalty_type'] ?? '');
                $penalty_reason = sanitizeInput($_POST['penalty_reason'] ?? '');
                $penalty_amount = floatval($_POST['penalty_amount'] ?? 0);
                
                // Get user_id from activity
                $stmt = $pdo->prepare("SELECT user_id FROM suspicious_activities WHERE id = ?");
                $stmt->execute([$activity_id]);
                $user_id = $stmt->fetchColumn();
                
                if ($user_id && !empty($penalty_type)) {
                    // Add penalty
                    $stmt = $pdo->prepare("
                        INSERT INTO user_penalties (user_id, penalty_type, reason, amount, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$user_id, $penalty_type, $penalty_reason, $penalty_amount, $admin_name]);
                    
                    // Update activity status
                    $stmt = $pdo->prepare("
                        UPDATE suspicious_activities 
                        SET status = 'actioned', admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute(["Penalty issued: $penalty_type", $admin_name, $activity_id]);
                    
                    // Notify user
                    createNotification(
                        $user_id,
                        'warning',
                        '⚠️ Account Penalty',
                        "A penalty has been issued to your account. Reason: $penalty_reason"
                    );
                    
                    $success = "Penalty added and user notified!";
                } else {
                    $errors[] = "Invalid penalty data";
                }
            }
            
            if (empty($errors)) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Action failed: " . $e->getMessage();
        }
    }
}

// Filters
$filter_severity = $_GET['severity'] ?? '';
$filter_status = $_GET['status'] ?? 'pending';
$filter_type = $_GET['activity_type'] ?? '';

$where_conditions = [];
$params = [];

if ($filter_severity) {
    $where_conditions[] = "sa.severity = ?";
    $params[] = $filter_severity;
}

if ($filter_status) {
    $where_conditions[] = "sa.status = ?";
    $params[] = $filter_status;
}

if ($filter_type) {
    $where_conditions[] = "sa.activity_type = ?";
    $params[] = $filter_type;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get suspicious activities
try {
    $stmt = $pdo->prepare("
        SELECT 
            sa.*,
            u.name as user_name,
            u.email as user_email,
            u.mobile as user_mobile
        FROM suspicious_activities sa
        JOIN users u ON sa.user_id = u.id
        $where_sql
        ORDER BY sa.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $activities = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
            SUM(CASE WHEN status = 'actioned' THEN 1 ELSE 0 END) as actioned,
            SUM(CASE WHEN severity = 'high' OR severity = 'critical' THEN 1 ELSE 0 END) as high_risk
        FROM suspicious_activities
    ");
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $activities = [];
    $stats = ['total' => 0, 'pending' => 0, 'reviewed' => 0, 'actioned' => 0, 'high_risk' => 0];
    error_log("Suspicious Activities Error: " . $e->getMessage());
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
    <title>Suspicious Users - Admin - <?php echo APP_NAME; ?></title>
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
        .stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:20px;margin-bottom:25px}
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
        .btn-warning{background:#f59e0b;color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-small{padding:6px 12px;font-size:12px}
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{background:#f8fafc;padding:12px 15px;text-align:left;font-size:13px;font-weight:600;color:#475569;border-bottom:2px solid #e2e8f0}
        .data-table td{padding:15px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#1e293b}
        .data-table tr:hover{background:#f8fafc}
        .badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}
        .badge-low{background:#dbeafe;color:#1e40af}
        .badge-medium{background:#fef3c7;color:#92400e}
        .badge-high{background:#fed7aa;color:#9a3412}
        .badge-critical{background:#fee2e2;color:#991b1b}
        .badge-pending{background:#fef3c7;color:#92400e}
        .badge-reviewed{background:#dcfce7;color:#166534}
        .badge-dismissed{background:#f1f5f9;color:#64748b}
        .badge-actioned{background:#dbeafe;color:#1e40af}
        .action-buttons{display:flex;gap:5px;flex-wrap:wrap}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal.active{display:flex}
        .modal-content{background:#fff;border-radius:15px;padding:30px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto}
        .modal-title{font-size:20px;font-weight:700;color:#1e293b;margin-bottom:20px}
        .form-group{margin-bottom:15px}
        .form-label{font-size:14px;font-weight:500;color:#475569;margin-bottom:6px;display:block}
        .form-input,.form-textarea,.form-select{width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px}
        .form-textarea{resize:vertical;min-height:80px}
        .modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:25px}
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">🚨 Suspicious Users</h1>
                <p class="page-subtitle">Review and manage suspicious activities</p>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div>❌ <?php echo htmlspecialchars($error); ?></div><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['total']); ?></div><div class="stat-label">Total Activities</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['pending']); ?></div><div class="stat-label">Pending Review</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['reviewed']); ?></div><div class="stat-label">Reviewed</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['actioned']); ?></div><div class="stat-label">Actioned</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['high_risk']); ?></div><div class="stat-label">High Risk</div></div>
            </div>
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label class="filter-label">Severity</label>
                    <select name="severity" class="filter-select">
                        <option value="">All Severities</option>
                        <option value="low" <?php if($filter_severity==='low')echo 'selected';?>>Low</option>
                        <option value="medium" <?php if($filter_severity==='medium')echo 'selected';?>>Medium</option>
                        <option value="high" <?php if($filter_severity==='high')echo 'selected';?>>High</option>
                        <option value="critical" <?php if($filter_severity==='critical')echo 'selected';?>>Critical</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-select">
                        <option value="pending" <?php if($filter_status==='pending')echo 'selected';?>>Pending</option>
                        <option value="reviewed" <?php if($filter_status==='reviewed')echo 'selected';?>>Reviewed</option>
                        <option value="dismissed" <?php if($filter_status==='dismissed')echo 'selected';?>>Dismissed</option>
                        <option value="actioned" <?php if($filter_status==='actioned')echo 'selected';?>>Actioned</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Activity Type</label>
                    <select name="activity_type" class="filter-select">
                        <option value="">All Types</option>
                        <option value="multiple_accounts" <?php if($filter_type==='multiple_accounts')echo 'selected';?>>Multiple Accounts</option>
                        <option value="rapid_tasks" <?php if($filter_type==='rapid_tasks')echo 'selected';?>>Rapid Tasks</option>
                        <option value="fake_reviews" <?php if($filter_type==='fake_reviews')echo 'selected';?>>Fake Reviews</option>
                        <option value="withdrawal_abuse" <?php if($filter_type==='withdrawal_abuse')echo 'selected';?>>Withdrawal Abuse</option>
                    </select>
                </div>
                <div class="filter-group" style="justify-content:flex-end;display:flex;gap:10px;align-items:flex-end">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="suspicious-users.php" class="btn btn-secondary">🔄 Reset</a>
                </div>
            </form>
            <div class="card">
                <h3 class="card-title">Suspicious Activities (<?php echo count($activities); ?>)</h3>
                <?php if (empty($activities)): ?>
                    <p style="text-align:center;color:#64748b;padding:40px">No suspicious activities found</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>User</th><th>Activity Type</th><th>Description</th><th>Severity</th><th>Status</th><th>Date</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong><br>
                                    <small style="color:#64748b">ID: <?php echo $activity['user_id']; ?></small><br>
                                    <small style="color:#64748b"><?php echo htmlspecialchars($activity['user_email']); ?></small>
                                </td>
                                <td><?php echo ucwords(str_replace('_', ' ', $activity['activity_type'])); ?></td>
                                <td><small><?php echo htmlspecialchars(substr($activity['description'], 0, 60)); ?><?php if(strlen($activity['description'])>60)echo '...';?></small></td>
                                <td><span class="badge badge-<?php echo $activity['severity']; ?>"><?php echo ucfirst($activity['severity']); ?></span></td>
                                <td><span class="badge badge-<?php echo $activity['status']; ?>"><?php echo ucfirst($activity['status']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($activity['created_at'])); ?></td>
                                <td>
                                    <?php if ($activity['status'] === 'pending'): ?>
                                    <div class="action-buttons">
                                        <button onclick="openReviewModal(<?php echo $activity['id']; ?>)" class="btn btn-success btn-small">✓ Review</button>
                                        <button onclick="openDismissModal(<?php echo $activity['id']; ?>)" class="btn btn-warning btn-small">✗ Dismiss</button>
                                        <button onclick="openPenaltyModal(<?php echo $activity['id']; ?>, <?php echo $activity['user_id']; ?>)" class="btn btn-danger btn-small">⚠️ Penalty</button>
                                    </div>
                                    <?php else: ?>
                                    <small style="color:#64748b">Processed</small>
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
            <form method="POST">
                <input type="hidden" name="activity_id" id="reviewActivityId">
                <input type="hidden" name="action" value="review">
                <div class="form-group">
                    <label class="form-label">Admin Note</label>
                    <textarea name="admin_note" class="form-textarea" placeholder="Add notes about this review..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('reviewModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-success">✓ Mark Reviewed</button>
                </div>
            </form>
        </div>
    </div>
    <div id="dismissModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Dismiss Activity</h3>
            <form method="POST">
                <input type="hidden" name="activity_id" id="dismissActivityId">
                <input type="hidden" name="action" value="dismiss">
                <div class="form-group">
                    <label class="form-label">Reason for Dismissal</label>
                    <textarea name="admin_note" class="form-textarea" placeholder="Why is this being dismissed?" required></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('dismissModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-warning">✗ Dismiss</button>
                </div>
            </form>
        </div>
    </div>
    <div id="penaltyModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Add Penalty</h3>
            <form method="POST">
                <input type="hidden" name="activity_id" id="penaltyActivityId">
                <input type="hidden" name="action" value="add_penalty">
                <div class="form-group">
                    <label class="form-label">Penalty Type *</label>
                    <select name="penalty_type" class="form-select" required>
                        <option value="">Select Type</option>
                        <option value="warning">Warning</option>
                        <option value="account_suspension">Account Suspension</option>
                        <option value="task_ban">Task Ban</option>
                        <option value="withdrawal_block">Withdrawal Block</option>
                        <option value="account_termination">Account Termination</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason *</label>
                    <textarea name="penalty_reason" class="form-textarea" placeholder="Explain the penalty reason..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Penalty Amount (if applicable)</label>
                    <input type="number" name="penalty_amount" class="form-input" placeholder="0.00" step="0.01" min="0">
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeModal('penaltyModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-danger">⚠️ Add Penalty</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function openReviewModal(id) {
            document.getElementById('reviewActivityId').value = id;
            document.getElementById('reviewModal').classList.add('active');
        }
        function openDismissModal(id) {
            document.getElementById('dismissActivityId').value = id;
            document.getElementById('dismissModal').classList.add('active');
        }
        function openPenaltyModal(activityId, userId) {
            document.getElementById('penaltyActivityId').value = activityId;
            document.getElementById('penaltyModal').classList.add('active');
        }
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
