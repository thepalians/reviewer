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

// Get filters
$severity_filter = $_GET['severity'] ?? 'all';
$event_filter = $_GET['event'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');

// Get security logs with try-catch
$logs = [];
$stats = ['total' => 0, 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
try {
    $query = "SELECT sl.*, u.name as user_name, u.email as user_email 
              FROM security_logs sl 
              LEFT JOIN users u ON sl.user_id = u.id 
              WHERE sl.created_at >= :date_from AND sl.created_at <= :date_to";
    
    $params = [
        ':date_from' => $date_from . ' 00:00:00',
        ':date_to' => $date_to . ' 23:59:59'
    ];
    
    if ($severity_filter !== 'all') {
        $query .= " AND sl.severity = :severity";
        $params[':severity'] = $severity_filter;
    }
    
    if ($event_filter !== 'all') {
        $query .= " AND sl.event_type = :event";
        $params[':event'] = $event_filter;
    }
    
    if (!empty($search)) {
        $query .= " AND (sl.ip_address LIKE :search OR sl.details LIKE :search OR u.name LIKE :search OR u.email LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    $query .= " ORDER BY sl.created_at DESC LIMIT 500";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Get stats for last 30 days
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low
        FROM security_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $stats_row = $stmt->fetch();
    $stats['total'] = (int)$stats_row['total'];
    $stats['critical'] = (int)$stats_row['critical'];
    $stats['high'] = (int)$stats_row['high'];
    $stats['medium'] = (int)$stats_row['medium'];
    $stats['low'] = (int)$stats_row['low'];
} catch (PDOException $e) {
    $error = 'Database error';
}

$csrf_token = generateCSRFToken();
$current_page = 'security-logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Logs - Admin Panel</title>
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
        .stats{display:grid;grid-template-columns:repeat(5,1fr);gap:15px;margin-bottom:25px}
        .stat{background:#fff;padding:20px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .stat .val{font-size:28px;font-weight:700}
        .stat .lbl{font-size:13px;color:#888;margin-top:5px}
        .stat.p .val{color:#667eea}.stat.cr .val{color:#e74c3c}.stat.h .val{color:#f39c12}.stat.m .val{color:#f1c40f}.stat.l .val{color:#27ae60}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h4{margin:0;font-size:20px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        table th{background:#f8f9fa;padding:10px;text-align:left;font-weight:600;font-size:12px;color:#555;border-bottom:2px solid #dee2e6}
        table td{padding:10px;border-bottom:1px solid #f0f0f0}
        table tr:hover{background:#f8f9fa}
        .badge{padding:4px 8px;border-radius:10px;font-size:11px;font-weight:600}
        .badge.critical{background:#e74c3c;color:#fff}
        .badge.high{background:#f39c12;color:#fff}
        .badge.medium{background:#f1c40f;color:#333}
        .badge.low{background:#27ae60;color:#fff}
        .badge.info{background:#3498db;color:#fff}
        .filter-bar{background:#fff;padding:15px;border-radius:10px;margin-bottom:20px}
        .filter-bar form{display:grid;grid-template-columns:1fr 1fr 1fr 1fr 150px;gap:10px;align-items:end}
        .form-group{margin-bottom:0}
        .form-group label{display:block;margin-bottom:5px;font-weight:600;font-size:12px}
        .form-control{width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;font-size:13px}
        .btn{padding:8px 16px;border-radius:6px;border:none;cursor:pointer;font-weight:600;transition:all 0.3s;font-size:13px}
        .btn-primary{background:#667eea;color:#fff}.btn-primary:hover{background:#5568d3}
        .btn-warning{background:#f39c12;color:#fff}.btn-warning:hover{background:#e67e22}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .alert-warning{background:#fff3cd;color:#856404;border:1px solid #ffeaa7;margin-bottom:15px}
        .text-truncate{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>🔒 Security Logs</h4>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>
        
        <?php if ($stats['critical'] > 0 || $stats['high'] > 0): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Security Alert!</strong> 
                <?php if ($stats['critical'] > 0): ?>
                    <?php echo $stats['critical']; ?> critical event(s) detected in the last 30 days. 
                <?php endif; ?>
                <?php if ($stats['high'] > 0): ?>
                    <?php echo $stats['high']; ?> high severity event(s) detected.
                <?php endif; ?>
                Please review immediately.
            </div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat p">
                <div class="val"><?php echo number_format($stats['total']); ?></div>
                <div class="lbl">Total Events (30d)</div>
            </div>
            <div class="stat cr">
                <div class="val"><?php echo $stats['critical']; ?></div>
                <div class="lbl">Critical</div>
            </div>
            <div class="stat h">
                <div class="val"><?php echo $stats['high']; ?></div>
                <div class="lbl">High</div>
            </div>
            <div class="stat m">
                <div class="val"><?php echo $stats['medium']; ?></div>
                <div class="lbl">Medium</div>
            </div>
            <div class="stat l">
                <div class="val"><?php echo $stats['low']; ?></div>
                <div class="lbl">Low</div>
            </div>
        </div>
        
        <div class="filter-bar">
            <form method="GET">
                <div class="form-group">
                    <label>Severity</label>
                    <select name="severity" class="form-control">
                        <option value="all" <?php echo $severity_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        <option value="high" <?php echo $severity_filter === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $severity_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $severity_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Event Type</label>
                    <select name="event" class="form-control">
                        <option value="all" <?php echo $event_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="login_failed" <?php echo $event_filter === 'login_failed' ? 'selected' : ''; ?>>Failed Login</option>
                        <option value="suspicious_activity" <?php echo $event_filter === 'suspicious_activity' ? 'selected' : ''; ?>>Suspicious Activity</option>
                        <option value="unauthorized_access" <?php echo $event_filter === 'unauthorized_access' ? 'selected' : ''; ?>>Unauthorized Access</option>
                        <option value="data_breach" <?php echo $event_filter === 'data_breach' ? 'selected' : ''; ?>>Data Breach</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo escape($date_from); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo escape($date_to); ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width:100%">Filter</button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Severity</th>
                        <th>Event Type</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" style="text-align:center;color:#999">No security logs found</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="white-space:nowrap"><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <span class="badge <?php echo escape($log['severity']); ?>">
                                        <?php echo strtoupper(escape($log['severity'])); ?>
                                    </span>
                                </td>
                                <td><span class="badge info"><?php echo escape($log['event_type']); ?></span></td>
                                <td>
                                    <?php if ($log['user_name']): ?>
                                        <?php echo escape($log['user_name']); ?>
                                        <br><small style="color:#888"><?php echo escape($log['user_email']); ?></small>
                                    <?php else: ?>
                                        <span style="color:#999">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><code style="font-size:11px"><?php echo escape($log['ip_address']); ?></code></td>
                                <td><div class="text-truncate" title="<?php echo escape($log['details']); ?>"><?php echo escape($log['details']); ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
