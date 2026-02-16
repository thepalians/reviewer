<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api-functions.php';

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
        
        if ($action === 'create') {
            $key_name = trim($_POST['key_name'] ?? '');
            $permissions = $_POST['permissions'] ?? [];
            $rate_limit = (int)($_POST['rate_limit'] ?? 100);
            $expires_at = $_POST['expires_at'] ?? null;
            
            if (empty($key_name)) {
                $error = 'Key name is required';
            } else {
                try {
                    // Generate API key
                    $api_key = 'rvw_' . bin2hex(random_bytes(32));
                    $secret_key = bin2hex(random_bytes(32));
                    $permissions_json = json_encode($permissions);
                    
                    $stmt = $pdo->prepare("INSERT INTO api_keys (name, api_key, secret_key, permissions, rate_limit, expires_at, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$key_name, $api_key, $secret_key, $permissions_json, $rate_limit, $expires_at, $admin_id]);
                    
                    $success = "API Key created: $api_key (Save this, it won't be shown again!)";
                } catch (PDOException $e) {
                    $error = 'Failed to create API key';
                }
            }
        } elseif ($action === 'revoke') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE api_keys SET is_active = 0, revoked_at = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'API key revoked successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to revoke key';
                }
            }
        } elseif ($action === 'activate') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE api_keys SET is_active = 1, revoked_at = NULL WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'API key activated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to activate key';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'API key deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to delete key';
                }
            }
        } elseif ($action === 'update_rate_limit') {
            $id = (int)($_POST['id'] ?? 0);
            $rate_limit = (int)($_POST['rate_limit'] ?? 100);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE api_keys SET rate_limit = ? WHERE id = ?");
                    $stmt->execute([$rate_limit, $id]);
                    $success = 'Rate limit updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update rate limit';
                }
            }
        }
    }
}

// Get API keys
$api_keys = [];
$stats = ['total' => 0, 'active' => 0, 'revoked' => 0, 'expired' => 0];
try {
    $stmt = $pdo->query("
        SELECT k.*, u.username as creator_name,
               (SELECT COUNT(*) FROM api_usage_logs WHERE api_key_id = k.id) as request_count,
               (SELECT MAX(created_at) FROM api_usage_logs WHERE api_key_id = k.id) as last_used
        FROM api_keys k
        LEFT JOIN users u ON k.user_id = u.id
        ORDER BY k.created_at DESC
    ");
    $api_keys = $stmt->fetchAll();
    
    // Get stats
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as revoked,
        SUM(CASE WHEN expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 ELSE 0 END) as expired
        FROM api_keys
    ");
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Database error';
}

// Get API usage stats
$api_stats = ['today' => 0, 'week' => 0, 'month' => 0];
try {
    $stmt = $pdo->query("SELECT 
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as month
        FROM api_usage_logs
    ");
    $api_stats = $stmt->fetch();
} catch (PDOException $e) {
    // Silent fail
}

$csrf_token = generateCSRFToken();
$current_page = 'api-settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Settings - Admin Panel</title>
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
        .stat.total .val{color:#667eea}.stat.active .val{color:#27ae60}.stat.revoked .val{color:#e74c3c}.stat.expired .val{color:#f39c12}
        .usage-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:25px}
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
        .close{background:none;border:none;font-size:24px;cursor:pointer;color:#999}
        .api-key{font-family:monospace;background:#f8f9fa;padding:8px;border-radius:4px;font-size:12px;word-break:break-all}
        .checkbox-group{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
        .info-box{background:#e3f2fd;padding:15px;border-radius:8px;margin-bottom:20px;border-left:4px solid #2196f3}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>🔑 API Key Management</h4>
            <button class="btn btn-primary" onclick="openCreateModal()">+ Create API Key</button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- API Keys Stats -->
        <div class="stats">
            <div class="stat total">
                <div class="val"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="lbl">Total Keys</div>
            </div>
            <div class="stat active">
                <div class="val"><?php echo number_format($stats['active'] ?? 0); ?></div>
                <div class="lbl">Active</div>
            </div>
            <div class="stat revoked">
                <div class="val"><?php echo number_format($stats['revoked'] ?? 0); ?></div>
                <div class="lbl">Revoked</div>
            </div>
            <div class="stat expired">
                <div class="val"><?php echo number_format($stats['expired'] ?? 0); ?></div>
                <div class="lbl">Expired</div>
            </div>
        </div>

        <!-- API Usage Stats -->
        <div class="card">
            <h5>API Usage Statistics</h5>
            <hr>
            <div class="usage-stats">
                <div class="stat">
                    <div class="val"><?php echo number_format($api_stats['today'] ?? 0); ?></div>
                    <div class="lbl">Requests Today</div>
                </div>
                <div class="stat">
                    <div class="val"><?php echo number_format($api_stats['week'] ?? 0); ?></div>
                    <div class="lbl">Last 7 Days</div>
                </div>
                <div class="stat">
                    <div class="val"><?php echo number_format($api_stats['month'] ?? 0); ?></div>
                    <div class="lbl">Last 30 Days</div>
                </div>
            </div>
        </div>

        <!-- API Keys Table -->
        <div class="card">
            <h5>API Keys</h5>
            <hr>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>API Key</th>
                        <th>Status</th>
                        <th>Rate Limit</th>
                        <th>Requests</th>
                        <th>Last Used</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($api_keys)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:30px;color:#999">No API keys found</td></tr>
                    <?php else: ?>
                        <?php foreach ($api_keys as $key): ?>
                            <?php
                            $is_expired = $key['expires_at'] && strtotime($key['expires_at']) < time();
                            $is_active = $key['is_active'] && !$is_expired;
                            ?>
                            <tr>
                                <td>#<?php echo $key['id']; ?></td>
                                <td><?php echo htmlspecialchars($key['name']); ?></td>
                                <td>
                                    <div class="api-key">
                                        <?php echo substr($key['api_key'], 0, 20); ?>...
                                        <button onclick="copyKey('<?php echo $key['api_key']; ?>')" class="btn btn-sm" style="padding:2px 6px;font-size:11px">Copy</button>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($is_active): ?>
                                        <span class="badge success">Active</span>
                                    <?php elseif ($is_expired): ?>
                                        <span class="badge warning">Expired</span>
                                    <?php else: ?>
                                        <span class="badge danger">Revoked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="update_rate_limit">
                                        <input type="hidden" name="id" value="<?php echo $key['id']; ?>">
                                        <input type="number" name="rate_limit" value="<?php echo $key['rate_limit']; ?>" 
                                               style="width:70px;padding:4px;border:1px solid #ddd;border-radius:4px" 
                                               onchange="this.form.submit()">
                                    </form>/hr
                                </td>
                                <td><?php echo number_format($key['request_count']); ?></td>
                                <td><?php echo $key['last_used'] ? date('M j, g:i A', strtotime($key['last_used'])) : 'Never'; ?></td>
                                <td><?php echo date('M j, Y', strtotime($key['created_at'])); ?></td>
                                <td>
                                    <?php if ($is_active): ?>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Revoke this API key?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="id" value="<?php echo $key['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm">Revoke</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="id" value="<?php echo $key['id']; ?>">
                                            <button type="submit" class="btn btn-success btn-sm">Activate</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this API key?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $key['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- API Documentation -->
        <div class="card">
            <h5>API Documentation</h5>
            <hr>
            <div class="info-box">
                <strong>API Endpoint:</strong> <?php echo APP_URL; ?>/api/v1/
            </div>
            <p><strong>Authentication:</strong> Include API key in header: <code>Authorization: Bearer YOUR_API_KEY</code></p>
            <p><strong>Available Endpoints:</strong></p>
            <ul>
                <li><code>GET /tasks</code> - List tasks</li>
                <li><code>GET /tasks/{id}</code> - Get task details</li>
                <li><code>POST /tasks</code> - Create task</li>
                <li><code>GET /users</code> - List users</li>
                <li><code>GET /stats</code> - Get statistics</li>
            </ul>
        </div>
    </div>
</div>

<!-- Create API Key Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5>Create API Key</h5>
            <button class="close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label>Key Name *</label>
                    <input type="text" name="key_name" class="form-control" required 
                           placeholder="e.g., Mobile App, Integration Service">
                </div>

                <div class="form-group">
                    <label>Permissions</label>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="permissions[]" value="read" checked> Read</label>
                        <label><input type="checkbox" name="permissions[]" value="write"> Write</label>
                        <label><input type="checkbox" name="permissions[]" value="delete"> Delete</label>
                        <label><input type="checkbox" name="permissions[]" value="admin"> Admin</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Rate Limit (requests/hour)</label>
                    <input type="number" name="rate_limit" class="form-control" value="100" min="1">
                </div>

                <div class="form-group">
                    <label>Expires At (Optional)</label>
                    <input type="datetime-local" name="expires_at" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create API Key</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

function copyKey(key) {
    navigator.clipboard.writeText(key).then(() => {
        alert('API key copied to clipboard!');
    });
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}
</script>
</body>
</html>
