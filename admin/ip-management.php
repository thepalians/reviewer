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
            if ($action === 'add_blacklist') {
                $ipAddress = sanitize($_POST['ip_address']);
                $reason = sanitize($_POST['reason']);
                $isPermanent = (int)($_POST['is_permanent'] ?? 0);
                $expiresAt = $isPermanent ? null : ($_POST['expires_at'] ?? null);
                
                $stmt = $pdo->prepare("
                    INSERT INTO ip_blacklist (ip_address, reason, is_permanent, expires_at, created_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$ipAddress, $reason, $isPermanent, $expiresAt, $admin_name]);
                $success = 'IP added to blacklist';
            } elseif ($action === 'add_whitelist') {
                $ipAddress = sanitize($_POST['ip_address']);
                $description = sanitize($_POST['description']);
                $expiresAt = $_POST['expires_at'] ?? null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO ip_whitelist (ip_address, description, expires_at, created_by)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$ipAddress, $description, $expiresAt, $admin_name]);
                $success = 'IP added to whitelist';
            } elseif ($action === 'remove_blacklist') {
                $stmt = $pdo->prepare("DELETE FROM ip_blacklist WHERE id = ?");
                $stmt->execute([(int)$_POST['ip_id']]);
                $success = 'IP removed from blacklist';
            } elseif ($action === 'remove_whitelist') {
                $stmt = $pdo->prepare("DELETE FROM ip_whitelist WHERE id = ?");
                $stmt->execute([(int)$_POST['ip_id']]);
                $success = 'IP removed from whitelist';
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
            error_log("IP Management Error: " . $e->getMessage());
        }
    }
}

// Get blacklisted IPs
$blacklistedIPs = [];
try {
    $stmt = $pdo->query("
        SELECT *, 
               (SELECT COUNT(*) FROM security_logs WHERE ip_address = ip_blacklist.ip_address) as violation_count
        FROM ip_blacklist 
        ORDER BY created_at DESC
    ");
    $blacklistedIPs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching blacklist: " . $e->getMessage());
}

// Get whitelisted IPs
$whitelistedIPs = [];
try {
    $stmt = $pdo->query("
        SELECT *, 
               (SELECT COUNT(*) FROM users WHERE last_login_ip = ip_whitelist.ip_address) as user_count
        FROM ip_whitelist 
        WHERE is_active = 1
        ORDER BY created_at DESC
    ");
    $whitelistedIPs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching whitelist: " . $e->getMessage());
}

// Get IP statistics
$stats = ['blacklist_total' => count($blacklistedIPs), 'whitelist_total' => count($whitelistedIPs)];

$csrf_token = generateCSRFToken();
$current_page = 'ip-management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .stats{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;margin-bottom:25px}
        .stat{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:25px;border-radius:12px;text-align:center}
        .stat.danger{background:linear-gradient(135deg,#ef4444,#dc2626)}
        .stat.success{background:linear-gradient(135deg,#10b981,#059669)}
        .stat .val{font-size:36px;font-weight:700;margin-bottom:5px}
        .stat .lbl{font-size:14px;opacity:0.9}
        table{width:100%;border-collapse:collapse;font-size:14px}
        table th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;border-bottom:2px solid #dee2e6}
        table td{padding:12px;border-bottom:1px solid #f0f0f0}
        table tr:hover{background:#f8f9fa}
        .badge{padding:4px 10px;border-radius:10px;font-size:11px;font-weight:600}
        .badge.active{background:#d1fae5;color:#065f46}
        .badge.expired{background:#fee2e2;color:#991b1b}
        .badge.permanent{background:#dbeafe;color:#1e40af}
        .ip-address{font-family:monospace;font-weight:600;color:#667eea}
        .btn-sm{padding:6px 12px;font-size:13px;border-radius:6px}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.stats{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-shield-lock"></i> IP Management</h3>
                <div>
                    <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#addBlacklistModal">
                        <i class="bi bi-slash-circle"></i> Add to Blacklist
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addWhitelistModal">
                        <i class="bi bi-check-circle"></i> Add to Whitelist
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
                <div class="stat danger">
                    <div class="val"><?= $stats['blacklist_total'] ?></div>
                    <div class="lbl">Blacklisted IPs</div>
                </div>
                <div class="stat success">
                    <div class="val"><?= $stats['whitelist_total'] ?></div>
                    <div class="lbl">Whitelisted IPs</div>
                </div>
            </div>

            <!-- Blacklisted IPs -->
            <div class="card">
                <h5 class="mb-4"><i class="bi bi-slash-circle text-danger"></i> Blacklisted IP Addresses</h5>
                
                <?php if (empty($blacklistedIPs)): ?>
                    <p class="text-muted text-center py-4">No blacklisted IPs</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Reason</th>
                                    <th>Type</th>
                                    <th>Violations</th>
                                    <th>Expires</th>
                                    <th>Added By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blacklistedIPs as $ip): 
                                    $isExpired = !$ip['is_permanent'] && $ip['expires_at'] && strtotime($ip['expires_at']) < time();
                                ?>
                                    <tr>
                                        <td><span class="ip-address"><?= escape($ip['ip_address']) ?></span></td>
                                        <td><?= escape($ip['reason']) ?></td>
                                        <td>
                                            <?php if ($ip['is_permanent']): ?>
                                                <span class="badge permanent">Permanent</span>
                                            <?php elseif ($isExpired): ?>
                                                <span class="badge expired">Expired</span>
                                            <?php else: ?>
                                                <span class="badge active">Temporary</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $ip['violation_count'] ?></td>
                                        <td><?= $ip['expires_at'] ? date('M d, Y H:i', strtotime($ip['expires_at'])) : 'Never' ?></td>
                                        <td><?= escape($ip['created_by']) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="action" value="remove_blacklist">
                                                <input type="hidden" name="ip_id" value="<?= $ip['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success" 
                                                        onclick="return confirm('Remove this IP from blacklist?')">
                                                    <i class="bi bi-check"></i> Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Whitelisted IPs -->
            <div class="card">
                <h5 class="mb-4"><i class="bi bi-check-circle text-success"></i> Whitelisted IP Addresses</h5>
                
                <?php if (empty($whitelistedIPs)): ?>
                    <p class="text-muted text-center py-4">No whitelisted IPs</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Users</th>
                                    <th>Expires</th>
                                    <th>Added By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($whitelistedIPs as $ip): 
                                    $isExpired = $ip['expires_at'] && strtotime($ip['expires_at']) < time();
                                ?>
                                    <tr>
                                        <td><span class="ip-address"><?= escape($ip['ip_address']) ?></span></td>
                                        <td><?= escape($ip['description']) ?></td>
                                        <td>
                                            <?php if ($isExpired): ?>
                                                <span class="badge expired">Expired</span>
                                            <?php else: ?>
                                                <span class="badge active">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $ip['user_count'] ?></td>
                                        <td><?= $ip['expires_at'] ? date('M d, Y H:i', strtotime($ip['expires_at'])) : 'Never' ?></td>
                                        <td><?= escape($ip['created_by']) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="action" value="remove_whitelist">
                                                <input type="hidden" name="ip_id" value="<?= $ip['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                        onclick="return confirm('Remove this IP from whitelist?')">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Blacklist Modal -->
    <div class="modal fade" id="addBlacklistModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="add_blacklist">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Add IP to Blacklist</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">IP Address</label>
                            <input type="text" name="ip_address" class="form-control" 
                                   placeholder="e.g., 192.168.1.1" required 
                                   pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_permanent" value="1" 
                                   class="form-check-input" id="permanent" onchange="toggleExpiry()">
                            <label class="form-check-label" for="permanent">Permanent Ban</label>
                        </div>
                        
                        <div class="mb-3" id="expiryField">
                            <label class="form-label">Expires At</label>
                            <input type="datetime-local" name="expires_at" class="form-control">
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Add to Blacklist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Whitelist Modal -->
    <div class="modal fade" id="addWhitelistModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="add_whitelist">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Add IP to Whitelist</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">IP Address</label>
                            <input type="text" name="ip_address" class="form-control" 
                                   placeholder="e.g., 192.168.1.1" required 
                                   pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" 
                                   placeholder="e.g., Office network, VPN server">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Expires At (Optional)</label>
                            <input type="datetime-local" name="expires_at" class="form-control">
                            <small class="text-muted">Leave empty for permanent whitelist</small>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add to Whitelist</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleExpiry() {
            const permanent = document.getElementById('permanent').checked;
            document.getElementById('expiryField').style.display = permanent ? 'none' : 'block';
        }
    </script>
</body>
</html>
