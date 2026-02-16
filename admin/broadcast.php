<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security-functions.php';

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
        
        if ($action === 'create_broadcast') {
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $channel = $_POST['channel'] ?? 'in_app';
            $target_type = $_POST['target_type'] ?? 'all';
            $target_role = $_POST['target_role'] ?? '';
            $schedule_at = $_POST['schedule_at'] ?? null;
            
            if (empty($title) || empty($message)) {
                $error = 'Title and message are required';
            } else {
                try {
                    // Create broadcast
                    $status = empty($schedule_at) ? 'sent' : 'scheduled';
                    $sent_at = empty($schedule_at) ? date('Y-m-d H:i:s') : null;
                    
                    $stmt = $pdo->prepare("INSERT INTO broadcast_messages (subject, message, channel, target_users, status, scheduled_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$title, $message, $channel, $target_type, $status, $schedule_at, $admin_id]);
                    
                    $broadcast_id = $pdo->lastInsertId();
                    
                    // Get target users
                    $target_users = [];
                    if ($target_type === 'all') {
                        $stmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
                        $target_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        $stmt = $pdo->query("SELECT seller_id FROM sellers WHERE status = 'active'");
                        $target_users = array_merge($target_users, $stmt->fetchAll(PDO::FETCH_COLUMN));
                    } elseif ($target_type === 'role' && !empty($target_role)) {
                        if ($target_role === 'users') {
                            $stmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
                            $target_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        } elseif ($target_role === 'sellers') {
                            $stmt = $pdo->query("SELECT seller_id FROM sellers WHERE status = 'active'");
                            $target_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        }
                    }
                    
                    // Send notifications if immediate
                    if (empty($schedule_at) && !empty($target_users)) {
                        foreach ($target_users as $user_id) {
                            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, 'broadcast', NOW())");
                            $stmt->execute([$user_id, $title, $message]);
                        }
                        
                        // Update broadcast stats
                        $stmt = $pdo->prepare("UPDATE broadcasts SET sent_count = ?, status = 'sent' WHERE id = ?");
                        $stmt->execute([count($target_users), $broadcast_id]);
                    }
                    
                    $success = empty($schedule_at) ? 'Broadcast sent successfully' : 'Broadcast scheduled successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to create broadcast';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM broadcast_messages WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Broadcast deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to delete broadcast';
                }
            }
        }
    }
}

// Get broadcasts with try-catch
$broadcasts = [];
$stats = ['total' => 0, 'sent' => 0, 'scheduled' => 0, 'failed' => 0];
try {
    $stmt = $pdo->query("SELECT * FROM broadcast_messages ORDER BY created_at DESC LIMIT 50");
    $broadcasts = $stmt->fetchAll();
    
    // Get stats
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent, SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed FROM broadcast_messages");
    $stats_row = $stmt->fetch();
    $stats['total'] = (int)$stats_row['total'];
    $stats['sent'] = (int)$stats_row['sent'];
    $stats['scheduled'] = (int)$stats_row['scheduled'];
    $stats['failed'] = (int)$stats_row['failed'];
} catch (PDOException $e) {
    $error = 'Database error';
}

$csrf_token = generateCSRFToken();
$current_page = 'broadcast';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadcast Messages - Admin Panel</title>
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
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}
        .stat{background:#fff;padding:20px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .stat .val{font-size:28px;font-weight:700}
        .stat .lbl{font-size:13px;color:#888;margin-top:5px}
        .stat.p .val{color:#667eea}.stat.s .val{color:#27ae60}.stat.w .val{color:#f39c12}.stat.d .val{color:#e74c3c}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h4{margin:0;font-size:20px}
        .btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;transition:all 0.3s}
        .btn-primary{background:#667eea;color:#fff}.btn-primary:hover{background:#5568d3}
        .btn-success{background:#27ae60;color:#fff}.btn-success:hover{background:#229954}
        .btn-danger{background:#e74c3c;color:#fff}.btn-danger:hover{background:#c0392b}
        .btn-warning{background:#f39c12;color:#fff}.btn-warning:hover{background:#e67e22}
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
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:5px;font-weight:600;font-size:14px}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
        textarea.form-control{min-height:100px;resize:vertical}
        .text-truncate{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>📡 Broadcast Messages</h4>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo escape($success); ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat p">
                <div class="val"><?php echo $stats['total']; ?></div>
                <div class="lbl">Total Broadcasts</div>
            </div>
            <div class="stat s">
                <div class="val"><?php echo $stats['sent']; ?></div>
                <div class="lbl">Sent</div>
            </div>
            <div class="stat w">
                <div class="val"><?php echo $stats['scheduled']; ?></div>
                <div class="lbl">Scheduled</div>
            </div>
            <div class="stat d">
                <div class="val"><?php echo $stats['failed']; ?></div>
                <div class="lbl">Failed</div>
            </div>
        </div>
        
        <div class="card">
            <h5>Create New Broadcast</h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="create_broadcast">
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" class="form-control" required></textarea>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label>Channel</label>
                        <select name="channel" class="form-control">
                            <option value="in_app">In-App Notification</option>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                            <option value="push">Push Notification</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Target</label>
                        <select name="target_type" id="targetType" class="form-control" onchange="toggleTargetRole()">
                            <option value="all">All Users</option>
                            <option value="role">By Role</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" id="targetRoleGroup" style="display:none">
                    <label>Select Role</label>
                    <select name="target_role" class="form-control">
                        <option value="">Select...</option>
                        <option value="users">Users Only</option>
                        <option value="sellers">Sellers Only</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Schedule (Optional - Leave empty to send immediately)</label>
                    <input type="datetime-local" name="schedule_at" class="form-control">
                </div>
                
                <button type="submit" class="btn btn-primary">Send Broadcast</button>
            </form>
        </div>
        
        <div class="card">
            <h5>Broadcast History</h5>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Channel</th>
                        <th>Target</th>
                        <th>Sent/Scheduled</th>
                        <th>Recipients</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($broadcasts)): ?>
                        <tr><td colspan="8" style="text-align:center;color:#999">No broadcasts found</td></tr>
                    <?php else: ?>
                        <?php foreach ($broadcasts as $bc): ?>
                            <tr>
                                <td><strong><?php echo escape($bc['title']); ?></strong></td>
                                <td><div class="text-truncate"><?php echo escape($bc['message']); ?></div></td>
                                <td><span class="badge info"><?php echo escape($bc['channel']); ?></span></td>
                                <td><?php echo escape($bc['target_users'] ?? 'All'); ?></td>
                                <td><?php echo $bc['sent_at'] ? date('M d, Y H:i', strtotime($bc['sent_at'])) : ($bc['scheduled_at'] ? date('M d, Y H:i', strtotime($bc['scheduled_at'])) : 'N/A'); ?></td>
                                <td><?php echo number_format($bc['sent_count']); ?> sent<?php if($bc['failed_count'] > 0): ?>, <?php echo $bc['failed_count']; ?> failed<?php endif; ?></td>
                                <td>
                                    <?php if ($bc['status'] === 'sent'): ?>
                                        <span class="badge success">Sent</span>
                                    <?php elseif ($bc['status'] === 'scheduled'): ?>
                                        <span class="badge warning">Scheduled</span>
                                    <?php elseif ($bc['status'] === 'failed'): ?>
                                        <span class="badge danger">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this broadcast?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $bc['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
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

<script>
function toggleTargetRole() {
    const targetType = document.getElementById('targetType').value;
    const roleGroup = document.getElementById('targetRoleGroup');
    roleGroup.style.display = targetType === 'role' ? 'block' : 'none';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
