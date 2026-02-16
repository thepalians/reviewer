<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

try {
    // Completed = Step 4 status = completed (admin processed refund)
    $stmt = $pdo->query("
        SELECT t.id, t.created_at, u.name as user_name, u.email,
            ts1.order_number, ts1.order_amount,
            ts4.refund_amount, ts4.refund_processed_at, ts4.refund_processed_by
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN task_steps ts1 ON t.id = ts1.task_id AND ts1.step_number = 1
        LEFT JOIN task_steps ts4 ON t.id = ts4.task_id AND ts4.step_number = 4
        WHERE ts4.step_status = 'completed'
        ORDER BY ts4.refund_processed_at DESC
    ");
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
    <title>Completed Tasks - Admin</title>
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
        .page-title{font-size:24px;color:#2c3e50;margin-bottom:20px}
        .task-card{background:#fff;border-radius:12px;padding:20px;margin-bottom:15px;box-shadow:0 2px 10px rgba(0,0,0,0.1);border-left:4px solid #27ae60}
        .task-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
        .task-id{font-size:18px;font-weight:600;color:#2c3e50}
        .task-user{color:#666;font-size:13px;margin-top:5px}
        .task-info{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:15px;padding:15px;background:#f8f9fa;border-radius:8px}
        .info-item{font-size:13px}
        .info-label{color:#888}
        .info-value{font-weight:600;color:#2c3e50}
        .refund-amount{color:#27ae60;font-size:18px}
        .status-badge{padding:5px 12px;border-radius:15px;font-size:12px;font-weight:600;background:#d4edda;color:#155724}
        .btn{padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;background:#3498db;color:#fff}
        .empty{text-align:center;padding:60px;background:#fff;border-radius:12px;color:#666}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content">
        <div class="page-title">✓ Completed Tasks (Refunds Sent)</div>
        
        <?php if (empty($tasks)): ?>
            <div class="empty">
                <h3>📭 No completed tasks yet</h3>
                <p>Tasks will appear here after you process refunds</p>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $task): ?>
                <div class="task-card">
                    <div class="task-header">
                        <div>
                            <div class="task-id">Task #<?php echo $task['id']; ?></div>
                            <div class="task-user">👤 <?php echo escape($task['user_name']); ?> | 📧 <?php echo escape($task['email']); ?></div>
                        </div>
                        <span class="status-badge">✓ COMPLETED</span>
                    </div>
                    <div class="task-info">
                        <div class="info-item"><div class="info-label">Order ID</div><div class="info-value"><?php echo escape($task['order_number'] ?? '-'); ?></div></div>
                        <div class="info-item"><div class="info-label">Order Amount</div><div class="info-value">₹<?php echo number_format($task['order_amount'] ?? 0, 2); ?></div></div>
                        <div class="info-item"><div class="info-label">Refund Sent</div><div class="info-value refund-amount">₹<?php echo number_format($task['refund_amount'] ?? 0, 2); ?></div></div>
                        <div class="info-item"><div class="info-label">Processed By</div><div class="info-value"><?php echo escape($task['refund_processed_by'] ?? '-'); ?></div></div>
                        <div class="info-item"><div class="info-label">Completed On</div><div class="info-value"><?php echo $task['refund_processed_at'] ? date('d M Y, h:i A', strtotime($task['refund_processed_at'])) : '-'; ?></div></div>
                    </div>
                    <a href="<?php echo ADMIN_URL; ?>/task-detail.php?task_id=<?php echo $task['id']; ?>" class="btn">View Details</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
