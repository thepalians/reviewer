<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/export-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$error = '';
$success = '';
$preview_data = null;

// Handle POST actions with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'preview' || $action === 'export') {
            $report_type = $_POST['report_type'] ?? 'tasks';
            $date_from = $_POST['date_from'] ?? date('Y-m-01');
            $date_to = $_POST['date_to'] ?? date('Y-m-t');
            $format = $_POST['format'] ?? 'csv';
            $status_filter = $_POST['status_filter'] ?? 'all';
            $seller_filter = (int)($_POST['seller_filter'] ?? 0);
            
            try {
                if ($report_type === 'tasks') {
                    $query = "SELECT t.*, u.name as user_name, u.email as user_email, s.name as seller_name 
                              FROM tasks t 
                              LEFT JOIN users u ON t.user_id = u.id 
                              LEFT JOIN sellers s ON t.seller_id = s.seller_id 
                              WHERE t.created_at >= :date_from AND t.created_at <= :date_to";
                    
                    $params = [
                        ':date_from' => $date_from . ' 00:00:00',
                        ':date_to' => $date_to . ' 23:59:59'
                    ];
                    
                    if ($status_filter !== 'all') {
                        $query .= " AND t.task_status = :status";
                        $params[':status'] = $status_filter;
                    }
                    
                    if ($seller_filter > 0) {
                        $query .= " AND t.seller_id = :seller_id";
                        $params[':seller_id'] = $seller_filter;
                    }
                    
                    $query .= " ORDER BY t.created_at DESC";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $data = $stmt->fetchAll();
                    
                    if ($action === 'preview') {
                        $preview_data = array_slice($data, 0, 10);
                    } else {
                        exportToCSV($data, 'tasks_report_' . date('Y-m-d') . '.csv', [
                            'id' => 'Task ID',
                            'brand_name' => 'Brand',
                            'task_link' => 'Link',
                            'user_name' => 'User',
                            'seller_name' => 'Seller',
                            'task_status' => 'Status',
                            'price' => 'Price',
                            'created_at' => 'Created',
                            'completed_at' => 'Completed'
                        ]);
                    }
                    
                } elseif ($report_type === 'users') {
                    $query = "SELECT u.*, 
                              COUNT(DISTINCT t.id) as total_tasks,
                              SUM(CASE WHEN t.task_status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                              (SELECT balance FROM user_wallet WHERE user_id = u.id LIMIT 1) as wallet_balance
                              FROM users u 
                              LEFT JOIN tasks t ON u.id = t.user_id 
                              WHERE u.created_at >= :date_from AND u.created_at <= :date_to
                              GROUP BY u.id 
                              ORDER BY u.created_at DESC";
                    
                    $params = [
                        ':date_from' => $date_from . ' 00:00:00',
                        ':date_to' => $date_to . ' 23:59:59'
                    ];
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $data = $stmt->fetchAll();
                    
                    if ($action === 'preview') {
                        $preview_data = array_slice($data, 0, 10);
                    } else {
                        exportToCSV($data, 'users_report_' . date('Y-m-d') . '.csv', [
                            'id' => 'User ID',
                            'name' => 'Name',
                            'email' => 'Email',
                            'mobile' => 'Mobile',
                            'status' => 'Status',
                            'total_tasks' => 'Total Tasks',
                            'completed_tasks' => 'Completed Tasks',
                            'wallet_balance' => 'Wallet Balance',
                            'created_at' => 'Registered'
                        ]);
                    }
                    
                } elseif ($report_type === 'financial') {
                    $query = "SELECT 
                              DATE(t.created_at) as date,
                              COUNT(*) as total_tasks,
                              SUM(CASE WHEN t.task_status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                              SUM(CASE WHEN t.task_status = 'completed' THEN t.price ELSE 0 END) as total_amount,
                              SUM(CASE WHEN t.task_status = 'completed' THEN t.admin_commission ELSE 0 END) as admin_commission,
                              COUNT(DISTINCT t.user_id) as unique_users,
                              COUNT(DISTINCT t.seller_id) as unique_sellers
                              FROM tasks t 
                              WHERE t.created_at >= :date_from AND t.created_at <= :date_to
                              GROUP BY DATE(t.created_at) 
                              ORDER BY date DESC";
                    
                    $params = [
                        ':date_from' => $date_from . ' 00:00:00',
                        ':date_to' => $date_to . ' 23:59:59'
                    ];
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $data = $stmt->fetchAll();
                    
                    if ($action === 'preview') {
                        $preview_data = array_slice($data, 0, 10);
                    } else {
                        exportToCSV($data, 'financial_report_' . date('Y-m-d') . '.csv', [
                            'date' => 'Date',
                            'total_tasks' => 'Total Tasks',
                            'completed_tasks' => 'Completed',
                            'total_amount' => 'Total Amount',
                            'admin_commission' => 'Commission',
                            'unique_users' => 'Unique Users',
                            'unique_sellers' => 'Unique Sellers'
                        ]);
                    }
                }
                
                if ($action === 'preview') {
                    $success = 'Preview generated successfully';
                }
                
            } catch (PDOException $e) {
                $error = 'Failed to generate report';
            }
        } elseif ($action === 'quick_export') {
            $type = $_POST['quick_type'] ?? 'this_month';
            
            try {
                if ($type === 'this_month') {
                    $date_from = date('Y-m-01');
                    $date_to = date('Y-m-t');
                } elseif ($type === 'last_month') {
                    $date_from = date('Y-m-01', strtotime('first day of last month'));
                    $date_to = date('Y-m-t', strtotime('last day of last month'));
                } elseif ($type === 'this_year') {
                    $date_from = date('Y-01-01');
                    $date_to = date('Y-12-31');
                } else {
                    $date_from = date('Y-m-01');
                    $date_to = date('Y-m-t');
                }
                
                $stmt = $pdo->prepare("SELECT t.*, u.name as user_name, u.email as user_email, s.name as seller_name 
                                       FROM tasks t 
                                       LEFT JOIN users u ON t.user_id = u.id 
                                       LEFT JOIN sellers s ON t.seller_id = s.seller_id 
                                       WHERE t.created_at >= :date_from AND t.created_at <= :date_to
                                       ORDER BY t.created_at DESC");
                $stmt->execute([
                    ':date_from' => $date_from . ' 00:00:00',
                    ':date_to' => $date_to . ' 23:59:59'
                ]);
                $data = $stmt->fetchAll();
                
                exportToCSV($data, 'quick_export_' . $type . '_' . date('Y-m-d') . '.csv', [
                    'id' => 'Task ID',
                    'brand_name' => 'Brand',
                    'task_link' => 'Link',
                    'user_name' => 'User',
                    'seller_name' => 'Seller',
                    'task_status' => 'Status',
                    'price' => 'Price',
                    'created_at' => 'Created'
                ]);
                
            } catch (PDOException $e) {
                $error = 'Failed to export data';
            }
        }
    }
}

// Get sellers for filter
$sellers = [];
try {
    $stmt = $pdo->query("SELECT seller_id, name FROM sellers WHERE status = 'active' ORDER BY name");
    $sellers = $stmt->fetchAll();
} catch (PDOException $e) {
    // Ignore error
}

$csrf_token = generateCSRFToken();
$current_page = 'export-reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Reports - Admin Panel</title>
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
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h4{margin:0;font-size:20px}
        .btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;transition:all 0.3s}
        .btn-primary{background:#667eea;color:#fff}.btn-primary:hover{background:#5568d3}
        .btn-success{background:#27ae60;color:#fff}.btn-success:hover{background:#229954}
        .btn-warning{background:#f39c12;color:#fff}.btn-warning:hover{background:#e67e22}
        .btn-sm{padding:8px 16px;font-size:13px}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:5px;font-weight:600;font-size:14px}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .quick-exports{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:25px}
        .quick-export{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px;border-radius:12px;text-align:center;cursor:pointer;transition:transform 0.3s}
        .quick-export:hover{transform:translateY(-3px)}
        .quick-export h5{margin:0 0 10px 0}
        .quick-export p{margin:0;font-size:13px;opacity:0.9}
        table{width:100%;border-collapse:collapse;font-size:13px;margin-top:20px}
        table th{background:#f8f9fa;padding:10px;text-align:left;font-weight:600;font-size:12px;color:#555;border-bottom:2px solid #dee2e6}
        table td{padding:10px;border-bottom:1px solid #f0f0f0}
        table tr:hover{background:#f8f9fa}
        .badge{padding:5px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .badge.success{background:#d4edda;color:#155724}
        .badge.warning{background:#fff3cd;color:#856404}
        .badge.danger{background:#f8d7da;color:#721c24}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>📊 Export Reports</h4>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo escape($success); ?></div>
        <?php endif; ?>
        
        <h5 style="margin-bottom:15px">Quick Exports</h5>
        <div class="quick-exports">
            <form method="POST" style="margin:0">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="quick_export">
                <input type="hidden" name="quick_type" value="this_month">
                <button type="submit" class="quick-export" style="width:100%;border:none">
                    <h5>📅 This Month</h5>
                    <p>Export all tasks from current month</p>
                </button>
            </form>
            
            <form method="POST" style="margin:0">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="quick_export">
                <input type="hidden" name="quick_type" value="last_month">
                <button type="submit" class="quick-export" style="width:100%;border:none">
                    <h5>📆 Last Month</h5>
                    <p>Export all tasks from previous month</p>
                </button>
            </form>
            
            <form method="POST" style="margin:0">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="quick_export">
                <input type="hidden" name="quick_type" value="this_year">
                <button type="submit" class="quick-export" style="width:100%;border:none">
                    <h5>📊 This Year</h5>
                    <p>Export all tasks from current year</p>
                </button>
            </form>
        </div>
        
        <div class="card">
            <h5>Custom Export</h5>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label>Report Type *</label>
                    <select name="report_type" id="reportType" class="form-control" onchange="toggleFilters()">
                        <option value="tasks">Tasks Report</option>
                        <option value="users">Users Report</option>
                        <option value="financial">Financial Summary</option>
                    </select>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label>Date From *</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo date('Y-m-01'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Date To *</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo date('Y-m-t'); ?>" required>
                    </div>
                </div>
                
                <div id="taskFilters" style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label>Status Filter</label>
                        <select name="status_filter" class="form-control">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="completed">Completed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Seller Filter</label>
                        <select name="seller_filter" class="form-control">
                            <option value="0">All Sellers</option>
                            <?php foreach ($sellers as $seller): ?>
                                <option value="<?php echo $seller['seller_id']; ?>"><?php echo escape($seller['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Export Format</label>
                    <select name="format" class="form-control">
                        <option value="csv">CSV (Excel Compatible)</option>
                        <option value="pdf" disabled>PDF (Coming Soon)</option>
                    </select>
                </div>
                
                <div style="display:flex;gap:10px">
                    <button type="submit" name="action" value="preview" class="btn btn-warning">Preview Data</button>
                    <button type="submit" name="action" value="export" class="btn btn-success">Export Now</button>
                </div>
            </form>
        </div>
        
        <?php if ($preview_data): ?>
            <div class="card">
                <h5>Preview (First 10 rows)</h5>
                <table>
                    <thead>
                        <tr>
                            <?php if (!empty($preview_data)): ?>
                                <?php foreach (array_keys($preview_data[0]) as $column): ?>
                                    <th><?php echo escape(ucwords(str_replace('_', ' ', $column))); ?></th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_data as $row): ?>
                            <tr>
                                <?php foreach ($row as $value): ?>
                                    <td><?php echo escape($value); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleFilters() {
    const reportType = document.getElementById('reportType').value;
    const taskFilters = document.getElementById('taskFilters');
    taskFilters.style.display = reportType === 'tasks' ? 'grid' : 'none';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
