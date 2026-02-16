<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/advanced-security-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'update_settings') {
                $settings = [
                    'email_alerts' => (int)($_POST['email_alerts'] ?? 0),
                    'alert_email' => sanitize($_POST['alert_email'] ?? ''),
                    'failed_attempts_threshold' => (int)($_POST['failed_attempts_threshold'] ?? 5),
                    'suspicious_ip_threshold' => (int)($_POST['suspicious_ip_threshold'] ?? 10),
                    'new_device_alert' => (int)($_POST['new_device_alert'] ?? 1),
                    'location_change_alert' => (int)($_POST['location_change_alert'] ?? 1)
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value)
                        VALUES (?, ?)
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->execute(["login_alert_$key", $value, $value]);
                }
                
                $success = 'Settings updated successfully!';
            } elseif ($action === 'mark_read') {
                $alertId = (int)($_POST['alert_id'] ?? 0);
                $stmt = $pdo->prepare("UPDATE login_alerts SET is_read = 1 WHERE id = ?");
                $stmt->execute([$alertId]);
                $success = 'Alert marked as read';
            } elseif ($action === 'mark_all_read') {
                $pdo->exec("UPDATE login_alerts SET is_read = 1 WHERE is_read = 0");
                $success = 'All alerts marked as read';
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
            error_log("Login alerts error: " . $e->getMessage());
        }
    }
}

// Get login alerts
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$alerts = [];
$totalAlerts = 0;

try {
    $baseQuery = "SELECT la.*, u.name, u.email 
                  FROM login_alerts la 
                  LEFT JOIN users u ON la.user_id = u.id";
    
    $whereClause = "";
    if ($filter === 'unread') {
        $whereClause = " WHERE la.is_read = 0";
    } elseif ($filter === 'suspicious') {
        $whereClause = " WHERE la.alert_type IN ('suspicious_ip', 'multiple_failures', 'location_change')";
    }
    
    // Count query using prepared statement
    $countQuery = "SELECT COUNT(*) FROM login_alerts la" . $whereClause;
    $totalAlerts = (int)$pdo->query($countQuery)->fetchColumn();
    
    // Main query with safe pagination using prepared statement
    $offset = ($page - 1) * $perPage;
    $query = $baseQuery . $whereClause . " ORDER BY la.created_at DESC LIMIT :offset, :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $alerts = $stmt->fetchAll();
    
    $totalPages = ceil($totalAlerts / $perPage);
} catch (PDOException $e) {
    $error = 'Failed to load alerts';
    error_log("Alert load error: " . $e->getMessage());
}

// Get alert counts
$alertCounts = ['total' => 0, 'unread' => 0, 'suspicious' => 0];
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
            SUM(CASE WHEN alert_type IN ('suspicious_ip', 'multiple_failures', 'location_change') THEN 1 ELSE 0 END) as suspicious
        FROM login_alerts
    ");
    $alertCounts = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Alert counts error: " . $e->getMessage());
}

// Get current settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'login_alert_%'");
    while ($row = $stmt->fetch()) {
        $key = str_replace('login_alert_', '', $row['setting_key']);
        $settings[$key] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Settings load error: " . $e->getMessage());
}

$csrf_token = generateCSRFToken();
$current_page = 'login-alerts';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Alerts - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:25px}
        .stat{background:#fff;padding:20px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .stat .val{font-size:32px;font-weight:700;color:#667eea}
        .stat .lbl{font-size:13px;color:#888;margin-top:5px}
        .stat.unread .val{color:#f59e0b}
        .stat.suspicious .val{color:#ef4444}
        .alert-item{padding:15px;border-radius:10px;margin-bottom:15px;border-left:4px solid #667eea;background:#f8f9fa}
        .alert-item.unread{background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.05)}
        .alert-item.suspicious{border-left-color:#ef4444}
        .alert-item.warning{border-left-color:#f59e0b}
        .alert-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px}
        .alert-type{font-size:14px;font-weight:600;color:#333}
        .alert-time{font-size:12px;color:#999}
        .alert-details{font-size:13px;color:#666;line-height:1.6}
        .alert-details strong{color:#333}
        .badge{padding:4px 10px;border-radius:10px;font-size:11px;font-weight:600}
        .badge.new-device{background:#dbeafe;color:#1e40af}
        .badge.suspicious-ip{background:#fee2e2;color:#991b1b}
        .badge.multiple-failures{background:#fef3c7;color:#92400e}
        .badge.location-change{background:#e0e7ff;color:#4338ca}
        .filter-tabs{display:flex;gap:10px;margin-bottom:20px}
        .filter-tabs a{padding:10px 20px;border-radius:8px;text-decoration:none;color:#666;background:#f8f9fa;transition:all 0.3s}
        .filter-tabs a:hover,.filter-tabs a.active{background:#667eea;color:#fff}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.stats{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-bell"></i> Login Alerts</h3>
                <div>
                    <button class="btn btn-outline-primary me-2" onclick="markAllRead()">
                        <i class="bi bi-check-all"></i> Mark All Read
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#settingsModal">
                        <i class="bi bi-gear"></i> Settings
                    </button>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat">
                    <div class="val"><?= $alertCounts['total'] ?></div>
                    <div class="lbl">Total Alerts</div>
                </div>
                <div class="stat unread">
                    <div class="val"><?= $alertCounts['unread'] ?></div>
                    <div class="lbl">Unread</div>
                </div>
                <div class="stat suspicious">
                    <div class="val"><?= $alertCounts['suspicious'] ?></div>
                    <div class="lbl">Suspicious</div>
                </div>
            </div>

            <div class="filter-tabs">
                <a href="?filter=all" class="<?= $filter === 'all' ? 'active' : '' ?>">All Alerts</a>
                <a href="?filter=unread" class="<?= $filter === 'unread' ? 'active' : '' ?>">Unread</a>
                <a href="?filter=suspicious" class="<?= $filter === 'suspicious' ? 'active' : '' ?>">Suspicious</a>
            </div>

            <div class="card">
                <?php if (empty($alerts)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash" style="font-size:64px;color:#ccc"></i>
                        <h5 class="mt-3">No Alerts</h5>
                        <p class="text-muted">You're all caught up!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($alerts as $alert): 
                        $alertClass = $alert['is_read'] ? '' : 'unread';
                        if (in_array($alert['alert_type'], ['suspicious_ip', 'multiple_failures'])) {
                            $alertClass .= ' suspicious';
                        }
                        $details = json_decode($alert['details'], true) ?? [];
                    ?>
                        <div class="alert-item <?= $alertClass ?>">
                            <div class="alert-header">
                                <div>
                                    <span class="badge <?= str_replace('_', '-', $alert['alert_type']) ?>">
                                        <?= escape(str_replace('_', ' ', ucwords($alert['alert_type']))) ?>
                                    </span>
                                    <span class="alert-type ms-2">
                                        <?= escape($alert['name'] ?? 'Unknown User') ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="alert-time">
                                        <?= date('M d, Y H:i', strtotime($alert['created_at'])) ?>
                                    </span>
                                    <?php if (!$alert['is_read']): ?>
                                        <button class="btn btn-sm btn-link" onclick="markRead(<?= $alert['id'] ?>)">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="alert-details">
                                <?php if ($alert['alert_type'] === 'new_device'): ?>
                                    New login from <strong><?= escape($details['device'] ?? 'unknown device') ?></strong>
                                    from IP <strong><?= escape($details['ip_address'] ?? '') ?></strong>
                                <?php elseif ($alert['alert_type'] === 'suspicious_ip'): ?>
                                    Suspicious login attempt from IP <strong><?= escape($details['ip_address'] ?? '') ?></strong>
                                    - This IP has <strong><?= $details['violation_count'] ?? 0 ?> violations</strong>
                                <?php elseif ($alert['alert_type'] === 'multiple_failures'): ?>
                                    Multiple failed login attempts detected
                                    - <strong><?= $details['attempt_count'] ?? 0 ?> attempts</strong> in the last hour
                                <?php elseif ($alert['alert_type'] === 'location_change'): ?>
                                    Login from new location: <strong><?= escape($details['location'] ?? 'Unknown') ?></strong>
                                    (Previous: <?= escape($details['previous_location'] ?? 'Unknown') ?>)
                                <?php else: ?>
                                    <?= escape($alert['message'] ?? 'Security alert detected') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($totalPages > 1): ?>
                        <div class="d-flex justify-content-center mt-4">
                            <nav>
                                <ul class="pagination">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?filter=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Login Alert Settings</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="email_alerts" value="1" class="form-check-input" 
                                   id="email_alerts" <?= ($settings['email_alerts'] ?? 0) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="email_alerts">Send email alerts</label>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Alert Email</label>
                            <input type="email" name="alert_email" class="form-control" 
                                   value="<?= escape($settings['alert_email'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Failed Attempts Threshold</label>
                            <input type="number" name="failed_attempts_threshold" class="form-control" 
                                   value="<?= $settings['failed_attempts_threshold'] ?? 5 ?>">
                            <small class="text-muted">Alert after this many failed attempts</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Suspicious IP Threshold</label>
                            <input type="number" name="suspicious_ip_threshold" class="form-control" 
                                   value="<?= $settings['suspicious_ip_threshold'] ?? 10 ?>">
                            <small class="text-muted">Mark IP as suspicious after this many violations</small>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="new_device_alert" value="1" class="form-check-input" 
                                   id="new_device" <?= ($settings['new_device_alert'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="new_device">Alert on new device login</label>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="location_change_alert" value="1" class="form-check-input" 
                                   id="location_change" <?= ($settings['location_change_alert'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="location_change">Alert on location change</label>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markRead(alertId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="alert_id" value="${alertId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function markAllRead() {
            if (!confirm('Mark all alerts as read?')) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="mark_all_read">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
