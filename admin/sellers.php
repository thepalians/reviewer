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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $seller_id = intval($_POST['seller_id'] ?? 0);
    
    if ($seller_id > 0) {
        try {
            if ($action === 'suspend') {
                $stmt = $pdo->prepare("UPDATE sellers SET status = 'suspended', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$seller_id]);
                $success = "Seller suspended successfully!";
                
            } elseif ($action === 'activate') {
                $stmt = $pdo->prepare("UPDATE sellers SET status = 'active', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$seller_id]);
                $success = "Seller activated successfully!";
                
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM review_requests WHERE seller_id = ?");
                $stmt->execute([$seller_id]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "Cannot delete seller with existing review requests";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM sellers WHERE id = ?");
                    $stmt->execute([$seller_id]);
                    $success = "Seller deleted successfully!";
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Action failed: " . $e->getMessage();
        }
    }
}

// Filters
$filter_status = $_GET['status'] ?? '';
$filter_search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if ($filter_status) {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
}

if ($filter_search) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR mobile LIKE ? OR company_name LIKE ?)";
    $search_param = "%$filter_search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get sellers
try {
    $stmt = $pdo->prepare("SELECT * FROM sellers $where_sql ORDER BY created_at DESC LIMIT 100");
    $stmt->execute($params);
    $sellers = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended
        FROM sellers
    ");
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $sellers = [];
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0];
    error_log("Sellers Error: " . $e->getMessage());
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
    <title>Sellers - Admin - <?php echo APP_NAME; ?></title>
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
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:15px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .stat-value{font-size:28px;font-weight:700;color:#1e293b}
        .stat-label{font-size:13px;color:#64748b;margin-top:5px}
        .card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:25px}
        .card-title{font-size:18px;font-weight:600;color:#1e293b;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f1f5f9}
        .filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:25px;background:#fff;padding:20px;border-radius:12px}
        .filter-group{display:flex;flex-direction:column}
        .filter-label{font-size:13px;font-weight:500;color:#475569;margin-bottom:6px}
        .filter-input,.filter-select{padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px}
        .btn{padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;display:inline-block;text-decoration:none;text-align:center}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-secondary{background:#64748b;color:#fff}
        .btn-warning{background:#f59e0b;color:#fff}
        .btn-success{background:#10b981;color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-small{padding:6px 12px;font-size:12px}
        .data-table{width:100%;border-collapse:collapse}
        .data-table th{background:#f8fafc;padding:12px 15px;text-align:left;font-size:13px;font-weight:600;color:#475569;border-bottom:2px solid #e2e8f0}
        .data-table td{padding:15px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#1e293b}
        .data-table tr:hover{background:#f8fafc}
        .badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}
        .badge-active{background:#dcfce7;color:#166534}
        .badge-inactive{background:#f1f5f9;color:#64748b}
        .badge-suspended{background:#fee2e2;color:#991b1b}
        .action-buttons{display:flex;gap:5px;flex-wrap:wrap}
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">🏪 Sellers Management</h1>
                <p class="page-subtitle">Manage seller accounts and activities</p>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div>❌ <?php echo htmlspecialchars($error); ?></div><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['total']); ?></div><div class="stat-label">Total Sellers</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['active']); ?></div><div class="stat-label">Active</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['inactive']); ?></div><div class="stat-label">Inactive</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['suspended']); ?></div><div class="stat-label">Suspended</div></div>
            </div>
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input type="text" name="search" class="filter-input" placeholder="Name, email, mobile, company" value="<?php echo htmlspecialchars($filter_search); ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="active" <?php if($filter_status==='active')echo 'selected';?>>Active</option>
                        <option value="inactive" <?php if($filter_status==='inactive')echo 'selected';?>>Inactive</option>
                        <option value="suspended" <?php if($filter_status==='suspended')echo 'selected';?>>Suspended</option>
                    </select>
                </div>
                <div class="filter-group" style="justify-content:flex-end;display:flex;gap:10px;align-items:flex-end">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="sellers.php" class="btn btn-secondary">🔄 Reset</a>
                </div>
            </form>
            <div class="card">
                <h3 class="card-title">All Sellers (<?php echo count($sellers); ?>)</h3>
                <?php if (empty($sellers)): ?>
                    <p style="text-align:center;color:#64748b;padding:40px">No sellers found</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Company</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sellers as $seller): ?>
                            <tr>
                                <td><strong>#<?php echo $seller['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($seller['name']); ?></td>
                                <td><?php echo htmlspecialchars($seller['email']); ?></td>
                                <td><?php echo htmlspecialchars($seller['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($seller['company_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $badge_classes = ['active'=>'badge-active','inactive'=>'badge-inactive','suspended'=>'badge-suspended'];
                                    echo '<span class="badge '.$badge_classes[$seller['status']].'">'.ucfirst($seller['status']).'</span>';
                                    ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($seller['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($seller['status'] === 'active'): ?>
                                            <a href="login-as-seller.php?seller_id=<?php echo $seller['id']; ?>" 
                                               class="btn btn-primary btn-small" 
                                               title="Login as this seller">
                                                🔐 Login as Seller
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($seller['status'] === 'suspended'): ?>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-success btn-small">✓ Activate</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <button type="submit" class="btn btn-warning btn-small" onclick="return confirm('Suspend this seller?')">⛔ Suspend</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-danger btn-small" onclick="return confirm('Delete this seller? This action cannot be undone.')">🗑️ Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
