<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification-center-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$error = '';
$success = '';

// Handle POST actions with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $type = $_POST['type'] ?? 'info';
            $target = $_POST['target'] ?? 'all';
            
            if (empty($title) || empty($message)) {
                $error = 'Title and message are required';
            } else {
                try {
                    if ($target === 'all') {
                        // Send to all users
                        $stmt = $pdo->query("SELECT id FROM users WHERE user_type = 'user' AND status = 'active'");
                        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        $stmt = $pdo->prepare("INSERT INTO push_notifications (user_id, title, body, notification_type, created_at) VALUES (?, ?, ?, ?, NOW())");
                        foreach ($users as $uid) {
                            $stmt->execute([$uid, $title, $message, $type]);
                        }
                        $success = 'Notification sent to all users';
                    } elseif ($target === 'single' && $user_id > 0) {
                        $stmt = $pdo->prepare("INSERT INTO push_notifications (user_id, title, body, notification_type, created_at) VALUES (?, ?, ?, ?, NOW())");
                        $stmt->execute([$user_id, $title, $message, $type]);
                        $success = 'Notification sent successfully';
                    } else {
                        $error = 'Invalid target selection';
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to send notification';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM push_notifications WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Notification deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to delete notification';
                }
            }
        } elseif ($action === 'clear_old') {
            try {
                $stmt = $pdo->query("DELETE FROM push_notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_read = 1");
                $deleted = $stmt->rowCount();
                $success = "Cleared $deleted old notifications";
            } catch (PDOException $e) {
                $error = 'Failed to clear notifications';
            }
        }
    }
}

// Get filter
$user_filter = $_GET['user'] ?? '';
$type_filter = $_GET['type'] ?? 'all';

// Get notifications
$notifications = [];
$stats = ['total' => 0, 'unread' => 0, 'read' => 0, 'info' => 0, 'success' => 0, 'warning' => 0];
try {
    $query = "SELECT n.*, u.username, u.email as user_email
              FROM push_notifications n
              LEFT JOIN users u ON n.user_id = u.id
              WHERE 1=1";
    
    $params = [];
    if ($user_filter) {
        $query .= " AND u.username LIKE ?";
        $params[] = "%$user_filter%";
    }
    if ($type_filter !== 'all') {
        $query .= " AND n.notification_type = ?";
        $params[] = $type_filter;
    }
    
    $query .= " ORDER BY n.created_at DESC LIMIT 100";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    // Get stats
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as `read`,
        SUM(CASE WHEN notification_type = 'info' THEN 1 ELSE 0 END) as info,
        SUM(CASE WHEN notification_type = 'success' THEN 1 ELSE 0 END) as success,
        SUM(CASE WHEN notification_type = 'warning' THEN 1 ELSE 0 END) as warning
        FROM push_notifications
    ");
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Database error';
}

$csrf_token = generateCSRFToken();
$current_page = 'notification-manager';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Manager - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar h2{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);font-size:20px}
        .sidebar ul{list-style:none}
        .sidebar a{color:#bbb;text-decoration:none;padding:12px 15px;display:block;border-radius:8px;margin-bottom:5px;transition:all 0.3s}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.1);color:#fff}
        .sidebar .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px}
        .sidebar-divider{border-top:1px solid rgba(255,255,255,0.1);margin:15px 0}
        .menu-section-label{color:#888;font-size:11px;text-transform:uppercase;padding:10px 15px;font-weight:600}
        .content{padding:30px}
        .stats{display:grid;grid-template-columns:repeat(6,1fr);gap:15px;margin-bottom:25px}
        .stat{background:#fff;padding:20px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .stat .val{font-size:28px;font-weight:700}
        .stat .lbl{font-size:13px;color:#888;margin-top:5px}
        .stat.total .val{color:#667eea}.stat.unread .val{color:#e74c3c}.stat.read .val{color:#27ae60}
        .stat.info .val{color:#3498db}.stat.success .val{color:#27ae60}.stat.warning .val{color:#f39c12}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h4{margin:0;font-size:20px}
        .btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;transition:all 0.3s}
        .btn-primary{background:#667eea;color:#fff}.btn-primary:hover{background:#5568d3}
        .btn-success{background:#27ae60;color:#fff}.btn-success:hover{background:#229954}
        .btn-danger{background:#e74c3c;color:#fff}.btn-danger:hover{background:#c0392b}
        .btn-warning{background:#f39c12;color:#fff}.btn-warning:hover{background:#e67e22}
        .btn-secondary{background:#6c757d;color:#fff}.btn-secondary:hover{background:#5a6268}
        .btn-sm{padding:6px 12px;font-size:13px}
        table{width:100%;border-collapse:collapse}
        table th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;font-size:13px;color:#555;border-bottom:2px solid #dee2e6}
        table td{padding:12px;border-bottom:1px solid #f0f0f0;font-size:14px}
        table tr:hover{background:#f8f9fa}
        .badge{padding:5px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .badge.success{background:#d4edda;color:#155724}
        .badge.danger{background:#f8d7da;color:#721c24}
        .badge.warning{background:#fff3cd;color:#856404}
        .badge.info{background:#d1ecf1;color:#0c5460}
        .filter-bar{background:#fff;padding:15px;border-radius:10px;margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:12px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto}
        .modal-header{padding:20px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center}
        .modal-body{padding:20px}
        .modal-footer{padding:20px;border-top:1px solid #f0f0f0;display:flex;gap:10px;justify-content:flex-end}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:5px;font-weight:600;font-size:14px}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
        textarea.form-control{min-height:100px;resize:vertical}
        .close{background:none;border:none;font-size:24px;cursor:pointer;color:#999}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>🔔 Notification Manager</h4>
            <div>
                <button class="btn btn-primary" onclick="openSendModal()">📤 Send Notification</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Clear all read notifications older than 30 days?')">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="clear_old">
                    <button type="submit" class="btn btn-warning">🗑️ Clear Old</button>
                </form>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat total">
                <div class="val"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="lbl">Total</div>
            </div>
            <div class="stat unread">
                <div class="val"><?php echo number_format($stats['unread'] ?? 0); ?></div>
                <div class="lbl">Unread</div>
            </div>
            <div class="stat read">
                <div class="val"><?php echo number_format($stats['read'] ?? 0); ?></div>
                <div class="lbl">Read</div>
            </div>
            <div class="stat info">
                <div class="val"><?php echo number_format($stats['info'] ?? 0); ?></div>
                <div class="lbl">Info</div>
            </div>
            <div class="stat success">
                <div class="val"><?php echo number_format($stats['success'] ?? 0); ?></div>
                <div class="lbl">Success</div>
            </div>
            <div class="stat warning">
                <div class="val"><?php echo number_format($stats['warning'] ?? 0); ?></div>
                <div class="lbl">Warning</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <span><strong>Filter by:</strong></span>
            <input type="text" placeholder="Search username..." value="<?php echo htmlspecialchars($user_filter); ?>" 
                   onchange="location.href='?user='+this.value+'&type=<?php echo $type_filter; ?>'" 
                   style="padding:8px;border-radius:6px;border:1px solid #ddd">
            <select onchange="location.href='?user=<?php echo $user_filter; ?>&type='+this.value" 
                    style="padding:8px;border-radius:6px;border:1px solid #ddd">
                <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="info" <?php echo $type_filter === 'info' ? 'selected' : ''; ?>>Info</option>
                <option value="success" <?php echo $type_filter === 'success' ? 'selected' : ''; ?>>Success</option>
                <option value="warning" <?php echo $type_filter === 'warning' ? 'selected' : ''; ?>>Warning</option>
                <option value="error" <?php echo $type_filter === 'error' ? 'selected' : ''; ?>>Error</option>
            </select>
        </div>

        <!-- Notifications Table -->
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notifications)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:30px;color:#999">No notifications found</td></tr>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): ?>
                            <tr>
                                <td>#<?php echo $notif['id']; ?></td>
                                <td><?php echo htmlspecialchars($notif['username']); ?></td>
                                <td><?php echo htmlspecialchars($notif['title']); ?></td>
                                <td style="max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    <?php echo htmlspecialchars($notif['body']); ?>
                                </td>
                                <td>
                                    <?php
                                    $type_classes = [
                                        'info' => 'info',
                                        'success' => 'success',
                                        'warning' => 'warning',
                                        'error' => 'danger'
                                    ];
                                    $badge_class = $type_classes[$notif['notification_type']] ?? 'info';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($notif['notification_type']); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $notif['is_read'] ? 'success' : 'danger'; ?>">
                                        <?php echo $notif['is_read'] ? 'Read' : 'Unread'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?></td>
                                <td>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this notification?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $notif['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Send Notification Modal -->
<div id="sendModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5>Send Notification</h5>
            <button class="close" onclick="closeModal('sendModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="send">
            <div class="modal-body">
                <div class="form-group">
                    <label>Target</label>
                    <select name="target" id="target" class="form-control" onchange="toggleUserSelect()">
                        <option value="all">All Users</option>
                        <option value="single">Single User</option>
                    </select>
                </div>
                <div class="form-group" id="userGroup" style="display:none">
                    <label>User ID</label>
                    <input type="number" name="user_id" id="user_id" class="form-control">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="info">Info</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" class="form-control" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('sendModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Notification</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSendModal() {
    document.getElementById('sendModal').classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

function toggleUserSelect() {
    const target = document.getElementById('target').value;
    const userGroup = document.getElementById('userGroup');
    const userId = document.getElementById('user_id');
    
    if (target === 'single') {
        userGroup.style.display = 'block';
        userId.required = true;
    } else {
        userGroup.style.display = 'none';
        userId.required = false;
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}
</script>
</body>
</html>
