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

// Handle force logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        if ($_POST['action'] === 'force_logout') {
            $sessionId = sanitize($_POST['session_id']);
            try {
                // Delete from active_sessions table
                $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                
                // Log the action
                logAuditTrail($pdo, 'session_terminated', 'admin', 0, [
                    'session_id' => $sessionId,
                    'terminated_by' => $admin_name
                ]);
                
                $success = 'Session terminated successfully';
            } catch (PDOException $e) {
                $error = 'Failed to terminate session';
                error_log("Session termination error: " . $e->getMessage());
            }
        }
    }
}

// Get all active sessions
$activeSessions = [];
$stats = ['total' => 0, 'users' => 0, 'sellers' => 0, 'admins' => 0];

try {
    $stmt = $pdo->query("
        SELECT s.*, u.name, u.email, u.user_type,
               TIMESTAMPDIFF(MINUTE, s.last_activity, NOW()) as idle_minutes
        FROM active_sessions s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.expires_at > NOW()
        ORDER BY s.last_activity DESC
    ");
    $activeSessions = $stmt->fetchAll();
    
    $stats['total'] = count($activeSessions);
    foreach ($activeSessions as $session) {
        if ($session['user_type'] === 'admin') $stats['admins']++;
        elseif ($session['user_type'] === 'seller') $stats['sellers']++;
        else $stats['users']++;
    }
} catch (PDOException $e) {
    $error = 'Failed to load sessions';
    error_log("Session load error: " . $e->getMessage());
}

$csrf_token = generateCSRFToken();
$current_page = 'session-management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}
        .stat{background:#fff;padding:20px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .stat .val{font-size:32px;font-weight:700;color:#667eea}
        .stat .lbl{font-size:13px;color:#888;margin-top:5px}
        .stat.users .val{color:#3b82f6}
        .stat.sellers .val{color:#10b981}
        .stat.admins .val{color:#f59e0b}
        table{width:100%;border-collapse:collapse;font-size:13px}
        table th{background:#f8f9fa;padding:10px;text-align:left;font-weight:600;font-size:12px;border-bottom:2px solid #dee2e6}
        table td{padding:10px;border-bottom:1px solid #f0f0f0}
        table tr:hover{background:#f8f9fa}
        .badge{padding:4px 8px;border-radius:10px;font-size:11px;font-weight:600}
        .badge.online{background:#d1fae5;color:#065f46}
        .badge.idle{background:#fef3c7;color:#92400e}
        .badge.admin{background:#dbeafe;color:#1e40af}
        .badge.seller{background:#d1fae5;color:#065f46}
        .badge.user{background:#e0e7ff;color:#4338ca}
        .device-icon{font-size:20px;color:#667eea}
        .session-info{font-size:12px;color:#666}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.stats{grid-template-columns:repeat(2,1fr)}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-person-badge"></i> Active Sessions Management</h3>
                <button class="btn btn-primary" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat">
                    <div class="val"><?= $stats['total'] ?></div>
                    <div class="lbl">Total Sessions</div>
                </div>
                <div class="stat users">
                    <div class="val"><?= $stats['users'] ?></div>
                    <div class="lbl">Users</div>
                </div>
                <div class="stat sellers">
                    <div class="val"><?= $stats['sellers'] ?></div>
                    <div class="lbl">Sellers</div>
                </div>
                <div class="stat admins">
                    <div class="val"><?= $stats['admins'] ?></div>
                    <div class="lbl">Admins</div>
                </div>
            </div>

            <div class="card">
                <h5 class="mb-4">Active Sessions</h5>
                
                <?php if (empty($activeSessions)): ?>
                    <p class="text-muted text-center py-4">No active sessions</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Device</th>
                                    <th>IP Address</th>
                                    <th>Location</th>
                                    <th>Started</th>
                                    <th>Last Activity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeSessions as $session): 
                                    $isIdle = $session['idle_minutes'] > 15;
                                    $deviceIcon = 'bi-display';
                                    if (stripos($session['user_agent'], 'mobile') !== false) {
                                        $deviceIcon = 'bi-phone';
                                    } elseif (stripos($session['user_agent'], 'tablet') !== false) {
                                        $deviceIcon = 'bi-tablet';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= escape($session['name'] ?? 'Unknown') ?></strong><br>
                                            <small class="text-muted"><?= escape($session['email'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?= strtolower($session['user_type']) ?>">
                                                <?= ucfirst($session['user_type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="<?= $deviceIcon ?> device-icon"></i><br>
                                            <small class="session-info">
                                                <?= escape(substr($session['user_agent'] ?? '', 0, 30)) ?>...
                                            </small>
                                        </td>
                                        <td>
                                            <span style="font-family:monospace;font-weight:600">
                                                <?= escape($session['ip_address']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($session['location']): ?>
                                                <i class="bi bi-geo-alt"></i> 
                                                <?= escape($session['location']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, H:i', strtotime($session['created_at'])) ?></td>
                                        <td>
                                            <?= date('M d, H:i', strtotime($session['last_activity'])) ?><br>
                                            <small class="text-muted">
                                                <?= $session['idle_minutes'] ?> min ago
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($isIdle): ?>
                                                <span class="badge idle">Idle</span>
                                            <?php else: ?>
                                                <span class="badge online">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="forceLogout('<?= escape($session['session_id']) ?>')">
                                                <i class="bi bi-power"></i> Logout
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h6 class="mb-3"><i class="bi bi-info-circle"></i> Session Information</h6>
                <ul style="font-size:13px;color:#666;margin:0;padding-left:20px">
                    <li>Sessions automatically expire after 24 hours of inactivity</li>
                    <li>Idle status is shown for sessions inactive for more than 15 minutes</li>
                    <li>Force logout will immediately terminate the user's session</li>
                    <li>Location data is estimated based on IP address</li>
                </ul>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function forceLogout(sessionId) {
            if (!confirm('Are you sure you want to force logout this session?')) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="force_logout">
                <input type="hidden" name="session_id" value="${sessionId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Auto-refresh every 60 seconds
        setTimeout(() => location.reload(), 60000);
    </script>
</body>
</html>
