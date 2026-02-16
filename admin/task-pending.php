<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$filter_order = $_GET['order_id'] ?? '';
$filter_user = $_GET['user'] ?? '';

try {
    // Pending = refund_requested=1 BUT step 4 NOT completed by admin
    $query = "
        SELECT t.id, t.created_at, u.name as user_name, u.email, u.mobile,
            ts1.order_number, ts1.order_amount, ts1.step_status as step1_status,
            ts2.step_status as step2_status, ts3.step_status as step3_status,
            ts4.step_status as step4_status, ts4.payment_qr_code
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN task_steps ts1 ON t.id = ts1.task_id AND ts1.step_number = 1
        LEFT JOIN task_steps ts2 ON t.id = ts2.task_id AND ts2.step_number = 2
        LEFT JOIN task_steps ts3 ON t.id = ts3.task_id AND ts3.step_number = 3
        LEFT JOIN task_steps ts4 ON t.id = ts4.task_id AND ts4.step_number = 4
        WHERE ts1.step_status = 'completed' 
        AND (ts4.step_status IS NULL OR ts4.step_status != 'completed')
        AND t.task_status != 'rejected'
    ";
    
    $params = [];
    if (!empty($filter_order)) {
        $query .= " AND ts1.order_number LIKE :order_id";
        $params[':order_id'] = "%$filter_order%";
    }
    if (!empty($filter_user)) {
        $query .= " AND (u.name LIKE :user OR u.email LIKE :user2)";
        $params[':user'] = "%$filter_user%";
        $params[':user2'] = "%$filter_user%";
    }
    $query .= " ORDER BY t.id DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $tasks = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Tasks - Admin</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#f5f5f5;font-family:-apple-system,sans-serif}
        .wrapper{display:grid;grid-template-columns:220px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px}
        .sidebar h3{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar ul{list-style:none}
        .sidebar a{color:#bbb;text-decoration:none;padding:12px 15px;display:block;border-radius:8px;margin-bottom:8px}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.1);color:#fff}
        .content{padding:25px}
        .page-title{font-size:24px;color:#2c3e50;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
        .badge{background:#3498db;color:#fff;padding:5px 15px;border-radius:15px;font-size:14px}
        .filter-box{background:#fff;padding:20px;border-radius:12px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
        .filter-box h4{margin-bottom:15px;color:#2c3e50}
        .filter-form{display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end}
        .filter-group{flex:1;min-width:200px}
        .filter-group label{display:block;font-size:12px;color:#666;margin-bottom:5px}
        .filter-group input{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px}
        .filter-btn{padding:10px 20px;background:#3498db;color:#fff;border:none;border-radius:8px;cursor:pointer}
        .task-card{background:#fff;border-radius:12px;padding:20px;margin-bottom:15px;box-shadow:0 2px 10px rgba(0,0,0,0.1);border-left:4px solid #f39c12}
        .task-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
        .task-id{font-size:18px;font-weight:600;color:#2c3e50}
        .task-user{color:#666;font-size:13px;margin-top:5px}
        .task-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:15px;padding:15px;background:#f8f9fa;border-radius:8px}
        .info-item{font-size:13px}
        .info-label{color:#888}
        .info-value{font-weight:600;color:#2c3e50}
        .status-badge{padding:5px 12px;border-radius:15px;font-size:12px;font-weight:600}
        .status-pending{background:#fff3cd;color:#856404}
        .status-done{background:#d4edda;color:#155724}
        .btn{padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block}
        .btn-primary{background:#3498db;color:#fff}
        .empty{text-align:center;padding:60px;background:#fff;border-radius:12px;color:#666}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content">
        <div class="page-title">
            <span>📋 Pending Tasks</span>
            <div style="display:flex;gap:10px;align-items:center">
                <a href="task-pending-brandwise.php" class="btn btn-secondary" style="font-size:14px;padding:8px 16px;text-decoration:none">
                    📁 Brand View
                </a>
                <span class="badge"><?php echo count($tasks); ?> Task(s)</span>
            </div>
        </div>
        
        <div class="filter-box">
            <h4>🔍 Filter Tasks</h4>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Order ID</label>
                    <input type="text" name="order_id" value="<?php echo escape($filter_order); ?>" placeholder="Search...">
                </div>
                <div class="filter-group">
                    <label>Reviewer (Name/Email)</label>
                    <input type="text" name="user" value="<?php echo escape($filter_user); ?>" placeholder="Search...">
                </div>
                <button type="submit" class="filter-btn">🔍 Search</button>
            </form>
        </div>
        
        <?php if (empty($tasks)): ?>
            <div class="empty">
                <h3>📭 No pending tasks found</h3>
                <p>All tasks are completed or no refund requests yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task): ?>
                <div class="task-card">
                    <div class="task-header">
                        <div>
                            <div class="task-id">Task #<?php echo $task['id']; ?></div>
                            <div class="task-user">👤 <?php echo escape($task['user_name']); ?> | 📧 <?php echo escape($task['email']); ?></div>
                        </div>
                        <span class="status-badge status-pending">⏳ Awaiting Refund</span>
                    </div>
                    <div class="task-info">
                        <div class="info-item"><div class="info-label">Order ID</div><div class="info-value"><?php echo escape($task['order_number'] ?? '-'); ?></div></div>
                        <div class="info-item"><div class="info-label">Amount</div><div class="info-value">₹<?php echo number_format($task['order_amount'] ?? 0, 2); ?></div></div>
                        <div class="info-item"><div class="info-label">Step 1</div><div class="info-value"><?php echo $task['step1_status'] === 'completed' ? '✓' : '○'; ?></div></div>
                        <div class="info-item"><div class="info-label">Step 2</div><div class="info-value"><?php echo $task['step2_status'] === 'completed' ? '✓' : '○'; ?></div></div>
                        <div class="info-item"><div class="info-label">Step 3</div><div class="info-value"><?php echo $task['step3_status'] === 'completed' ? '✓' : '○'; ?></div></div>
                        <div class="info-item"><div class="info-label">Step 4</div><div class="info-value"><?php echo $task['step4_status'] === 'completed' ? '✓' : '⏳'; ?></div></div>
                    </div>
                    <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>" class="btn btn-primary">Process Refund →</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
