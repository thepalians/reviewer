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

// Get filters
$event_type = $_GET['event_type'] ?? 'all';
$user_type = $_GET['user_type'] ?? 'all';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

// Get audit logs
$logs = [];
$totalLogs = 0;

try {
    $query = "SELECT al.*, u.name as user_name, u.email as user_email 
              FROM audit_logs al 
              LEFT JOIN users u ON al.user_id = u.id 
              WHERE al.created_at >= :date_from AND al.created_at <= :date_to";
    
    $params = [
        ':date_from' => $date_from . ' 00:00:00',
        ':date_to' => $date_to . ' 23:59:59'
    ];
    
    if ($event_type !== 'all') {
        $query .= " AND al.event_type = :event_type";
        $params[':event_type'] = $event_type;
    }
    
    if ($user_type !== 'all') {
        $query .= " AND al.user_type = :user_type";
        $params[':user_type'] = $user_type;
    }
    
    if (!empty($search)) {
        $query .= " AND (al.event_data LIKE :search OR al.ip_address LIKE :search 
                    OR u.name LIKE :search OR u.email LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    // Get total count
    $countQuery = str_replace('al.*, u.name as user_name, u.email as user_email', 'COUNT(*)', $query);
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalLogs = (int)$stmt->fetchColumn();
    
    // Get paginated results
    $query .= " ORDER BY al.created_at DESC LIMIT :offset, :limit";
    $params[':offset'] = ($page - 1) * $perPage;
    $params[':limit'] = $perPage;
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    $totalPages = ceil($totalLogs / $perPage);
} catch (PDOException $e) {
    $error = 'Database error';
    error_log("Audit logs error: " . $e->getMessage());
}

// Get event type counts
$eventCounts = [];
try {
    $stmt = $pdo->query("
        SELECT event_type, COUNT(*) as count 
        FROM audit_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY event_type 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $eventCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Event counts error: " . $e->getMessage());
}

$csrf_token = generateCSRFToken();
$current_page = 'audit-logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .filters{background:#f8f9fa;padding:20px;border-radius:12px;margin-bottom:20px}
        .filters .row{row-gap:15px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        table th{background:#f8f9fa;padding:10px;text-align:left;font-weight:600;font-size:12px;border-bottom:2px solid #dee2e6;position:sticky;top:0}
        table td{padding:10px;border-bottom:1px solid #f0f0f0;vertical-align:top}
        table tr:hover{background:#f8f9fa}
        .badge{padding:4px 8px;border-radius:10px;font-size:11px;font-weight:600}
        .badge.create{background:#d1fae5;color:#065f46}
        .badge.update{background:#dbeafe;color:#1e40af}
        .badge.delete{background:#fee2e2;color:#991b1b}
        .badge.login{background:#e0e7ff;color:#4338ca}
        .badge.admin{background:#fef3c7;color:#92400e}
        .event-data{font-size:11px;color:#666;max-width:300px;overflow:hidden;text-overflow:ellipsis}
        .pagination{display:flex;gap:5px;justify-content:center;margin-top:20px}
        .pagination a,.pagination span{padding:8px 12px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333}
        .pagination a:hover{background:#f0f0f0}
        .pagination .active{background:#667eea;color:#fff;border-color:#667eea}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-journal-text"></i> Audit Logs</h3>
                <button class="btn btn-primary" onclick="exportLogs()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= escape($error) ?></div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Event Type</label>
                        <select name="event_type" class="form-select form-select-sm">
                            <option value="all">All Events</option>
                            <option value="login" <?= $event_type === 'login' ? 'selected' : '' ?>>Login</option>
                            <option value="logout" <?= $event_type === 'logout' ? 'selected' : '' ?>>Logout</option>
                            <option value="create" <?= $event_type === 'create' ? 'selected' : '' ?>>Create</option>
                            <option value="update" <?= $event_type === 'update' ? 'selected' : '' ?>>Update</option>
                            <option value="delete" <?= $event_type === 'delete' ? 'selected' : '' ?>>Delete</option>
                            <option value="admin_action" <?= $event_type === 'admin_action' ? 'selected' : '' ?>>Admin Action</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">User Type</label>
                        <select name="user_type" class="form-select form-select-sm">
                            <option value="all">All Types</option>
                            <option value="admin" <?= $user_type === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="seller" <?= $user_type === 'seller' ? 'selected' : '' ?>>Seller</option>
                            <option value="user" <?= $user_type === 'user' ? 'selected' : '' ?>>User</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" 
                               value="<?= escape($date_from) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" 
                               value="<?= escape($date_to) ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm" 
                               value="<?= escape($search) ?>" placeholder="IP, user, data...">
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6>Showing <?= number_format($totalLogs) ?> log entries</h6>
                    <?php if ($page > 1 || $totalPages > 1): ?>
                        <small class="text-muted">Page <?= $page ?> of <?= $totalPages ?></small>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($logs)): ?>
                    <p class="text-muted text-center py-4">No audit logs found</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:140px">Timestamp</th>
                                    <th style="width:100px">Event</th>
                                    <th>User</th>
                                    <th style="width:80px">Type</th>
                                    <th style="width:120px">IP Address</th>
                                    <th>Event Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): 
                                    $eventClass = strtolower(explode('_', $log['event_type'])[0]);
                                    $eventData = json_decode($log['event_data'], true) ?? [];
                                ?>
                                    <tr>
                                        <td>
                                            <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                                            <small class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?= $eventClass ?>">
                                                <?= escape(str_replace('_', ' ', $log['event_type'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($log['user_name']): ?>
                                                <strong><?= escape($log['user_name']) ?></strong><br>
                                                <small class="text-muted"><?= escape($log['user_email']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">System / Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= strtolower($log['user_type']) ?>">
                                                <?= escape($log['user_type']) ?>
                                            </span>
                                        </td>
                                        <td style="font-family:monospace;font-size:12px">
                                            <?= escape($log['ip_address']) ?>
                                        </td>
                                        <td>
                                            <div class="event-data">
                                                <?php if (!empty($eventData)): ?>
                                                    <?php foreach (array_slice($eventData, 0, 3) as $key => $value): ?>
                                                        <strong><?= escape($key) ?>:</strong> 
                                                        <?= escape(is_array($value) ? json_encode($value) : $value) ?><br>
                                                    <?php endforeach; ?>
                                                    <?php if (count($eventData) > 3): ?>
                                                        <a href="#" onclick="showFullData(<?= $log['id'] ?>);return false">
                                                            +<?= count($eventData) - 3 ?> more
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No data</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="bi bi-chevron-left"></i> Prev
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <?php if ($i === $page): ?>
                                    <span class="active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Event Statistics -->
            <?php if (!empty($eventCounts)): ?>
                <div class="card">
                    <h6 class="mb-3">Top Events (Last 7 Days)</h6>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px">
                        <?php foreach ($eventCounts as $event => $count): ?>
                            <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center">
                                <div style="font-size:20px;font-weight:700;color:#667eea"><?= $count ?></div>
                                <div style="font-size:11px;color:#666;text-transform:uppercase">
                                    <?= escape(str_replace('_', ' ', $event)) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'export-audit-logs.php?' + params.toString();
        }

        function showFullData(logId) {
            alert('Full data view - implement modal with complete JSON data');
        }
    </script>
</body>
</html>
