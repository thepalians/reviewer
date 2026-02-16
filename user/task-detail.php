<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$task_id = intval($_GET['task_id'] ?? 0);

if ($task_id <= 0) {
    header('Location: ' . APP_URL . '/user/');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.name
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = :task_id AND t.user_id = :user_id
    ");
    $stmt->execute([':task_id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        header('Location: ' . APP_URL . '/user/');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id ORDER BY step_number");
    $stmt->execute([':task_id' => $task_id]);
    $steps = $stmt->fetchAll();
    $steps_by_number = [];
    foreach ($steps as $s) {
        $steps_by_number[$s['step_number']] = $s;
    }
    
    $step1 = $steps_by_number[1] ?? null;
    $step2 = $steps_by_number[2] ?? null;
    $step3 = $steps_by_number[3] ?? null;
    $step4 = $steps_by_number[4] ?? null;
    
    $step1_done = $step1 && $step1['step_status'] === 'completed';
    $step2_done = $step2 && $step2['step_status'] === 'completed';
    $step3_done = $step3 && $step3['step_status'] === 'completed';
    $step4_done = $step4 && $step4['step_status'] === 'completed';
    $step4_pending = $step4 && $step4['step_status'] === 'pending_admin';
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Database error');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#667eea">
    <title>Task #<?php echo $task_id; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:15px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
        .container{max-width:800px;margin:0 auto}
        .card{background:#fff;border-radius:15px;padding:20px;margin-bottom:15px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .task-header h1{font-size:22px;color:#2c3e50;margin-bottom:15px}
        .meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .meta-item{background:#f8f9fa;padding:12px;border-radius:8px}
        .meta-label{font-size:11px;color:#666;text-transform:uppercase}
        .meta-value{font-weight:600;color:#2c3e50;margin-top:3px}
        .status-badge{display:inline-block;padding:5px 12px;border-radius:15px;font-size:12px;font-weight:600}
        .status-pending{background:#ffeaa7;color:#d63031}
        .status-completed{background:#55efc4;color:#00b894}
        .status-processing{background:#74b9ff;color:#0984e3}
        .btn-group{display:flex;gap:10px;margin-top:15px;flex-wrap:wrap}
        .btn{padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;display:inline-block;text-align:center;border:none;cursor:pointer}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-success{background:#27ae60;color:#fff}
        .btn-secondary{background:#95a5a6;color:#fff}
        
        .step-card{border-left:4px solid #e74c3c;transition:all 0.3s}
        .step-card.done{border-left-color:#27ae60;background:#f0fff4}
        .step-card.pending{border-left-color:#f39c12;background:#fffbeb}
        .step-card.locked{opacity:0.7;background:#f5f5f5}
        .step-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;padding-bottom:12px;border-bottom:1px solid #eee}
        .step-title{font-size:16px;font-weight:600;color:#2c3e50;display:flex;align-items:center;gap:8px}
        .step-content{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px}
        .field{margin-bottom:8px}
        .field-label{font-size:11px;color:#888;text-transform:uppercase}
        .field-value{color:#333;font-weight:500;word-break:break-word}
        .screenshot-link{color:#3498db;text-decoration:none;font-weight:600}
        .screenshot-link:hover{text-decoration:underline}
        
        .action-btn{display:block;width:100%;padding:12px;background:#3498db;color:#fff;text-align:center;border-radius:8px;text-decoration:none;font-weight:600;margin-top:12px}
        .action-btn:hover{background:#2980b9;color:#fff}
        .action-btn.locked{background:#bdc3c7;cursor:not-allowed}
        
        .refund-info{background:linear-gradient(135deg,#d4edda,#c3e6cb);border:2px solid #27ae60;border-radius:12px;padding:20px;margin-top:15px}
        .refund-info h4{color:#155724;margin-bottom:12px}
        .refund-amount{font-size:28px;font-weight:700;color:#27ae60}
        
        .qr-preview{text-align:center;margin:15px 0}
        .qr-preview img{max-width:150px;border:3px solid #27ae60;border-radius:10px;padding:5px;background:#fff}
        
        @media(max-width:600px){
            body{padding:10px}
            .card{padding:15px}
            .meta-grid{grid-template-columns:1fr}
            .step-content{grid-template-columns:1fr}
            .btn-group{flex-direction:column}
            .btn{width:100%}
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Task Header -->
    <div class="card task-header">
        <h1>📋 Task #<?php echo $task_id; ?></h1>
        <div class="meta-grid">
            <div class="meta-item">
                <div class="meta-label">Status</div>
                <div class="meta-value">
                    <?php if ($task['task_status'] === 'completed'): ?>
                        <span class="status-badge status-completed">✓ Completed</span>
                    <?php elseif ($task['refund_requested']): ?>
                        <span class="status-badge status-processing">Processing</span>
                    <?php else: ?>
                        <span class="status-badge status-pending">Pending</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Product Link</div>
                <div class="meta-value">
                    <a href="<?php echo escape($task['product_link']); ?>" target="_blank" class="screenshot-link">Visit →</a>
                </div>
            </div>
        </div>
        <div class="btn-group">
            <a href="<?php echo APP_URL; ?>/user/" class="btn btn-secondary">← Dashboard</a>
            <?php if (!$task['refund_requested']): ?>
                <a href="<?php echo APP_URL; ?>/user/submit-order.php?task_id=<?php echo $task_id; ?>" class="btn btn-primary">✎ Edit Task</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- STEP 1 -->
    <div class="card step-card <?php echo $step1_done ? 'done' : ''; ?>">
        <div class="step-header">
            <div class="step-title">📦 Step 1: Order Placed</div>
            <span class="status-badge <?php echo $step1_done ? 'status-completed' : 'status-pending'; ?>">
                <?php echo $step1_done ? '✓ Done' : 'Pending'; ?>
            </span>
        </div>
        <?php if ($step1): ?>
            <div class="step-content">
                <div class="field"><div class="field-label">Order Date</div><div class="field-value"><?php echo escape($step1['order_date'] ?? '-'); ?></div></div>
                <div class="field"><div class="field-label">Order Name</div><div class="field-value"><?php echo escape($step1['order_name'] ?? '-'); ?></div></div>
                <div class="field"><div class="field-label">Product</div><div class="field-value"><?php echo escape($step1['product_name'] ?? '-'); ?></div></div>
                <div class="field"><div class="field-label">Order ID</div><div class="field-value"><strong><?php echo escape($step1['order_number'] ?? '-'); ?></strong></div></div>
                <div class="field"><div class="field-label">Amount</div><div class="field-value">₹<?php echo number_format($step1['order_amount'] ?? 0, 2); ?></div></div>
                <div class="field"><div class="field-label">Screenshot</div><div class="field-value"><?php echo !empty($step1['order_screenshot']) ? '<a href="'.escape($step1['order_screenshot']).'" target="_blank" class="screenshot-link">View →</a>' : '-'; ?></div></div>
            </div>
        <?php else: ?>
            <p style="color:#666">Not submitted yet</p>
        <?php endif; ?>
        <?php if (!$task['refund_requested']): ?>
            <a href="<?php echo APP_URL; ?>/user/submit-order.php?task_id=<?php echo $task_id; ?>" class="action-btn"><?php echo $step1 ? '✎ Edit' : 'Submit'; ?> Step 1</a>
        <?php endif; ?>
    </div>
    
    <!-- STEP 2 -->
    <div class="card step-card <?php echo $step2_done ? 'done' : ($step1_done ? '' : 'locked'); ?>">
        <div class="step-header">
            <div class="step-title">🚚 Step 2: Order Delivered</div>
            <span class="status-badge <?php echo $step2_done ? 'status-completed' : ($step1_done ? 'status-pending' : 'status-pending'); ?>">
                <?php echo $step2_done ? '✓ Done' : ($step1_done ? 'Ready' : '🔒 Locked'); ?>
            </span>
        </div>
        <?php if ($step2 && !empty($step2['delivered_screenshot'])): ?>
            <div class="field"><div class="field-label">Screenshot</div><div class="field-value"><a href="<?php echo escape($step2['delivered_screenshot']); ?>" target="_blank" class="screenshot-link">View Delivery Proof →</a></div></div>
        <?php elseif ($step1_done): ?>
            <p style="color:#666">Upload delivery screenshot to complete this step</p>
        <?php else: ?>
            <p style="color:#999">Complete Step 1 first</p>
        <?php endif; ?>
        <?php if ($step1_done && !$task['refund_requested']): ?>
            <a href="<?php echo APP_URL; ?>/user/submit-delivery.php?task_id=<?php echo $task_id; ?>" class="action-btn"><?php echo $step2 ? '✎ Edit' : 'Submit'; ?> Step 2</a>
        <?php elseif (!$step1_done): ?>
            <span class="action-btn locked">🔒 Complete Step 1 First</span>
        <?php endif; ?>
    </div>
    
    <!-- STEP 3 -->
    <div class="card step-card <?php echo $step3_done ? 'done' : ($step2_done ? '' : 'locked'); ?>">
        <div class="step-header">
            <div class="step-title">⭐ Step 3: Review Submitted</div>
            <span class="status-badge <?php echo $step3_done ? 'status-completed' : ($step2_done ? 'status-pending' : 'status-pending'); ?>">
                <?php echo $step3_done ? '✓ Done' : ($step2_done ? 'Ready' : '🔒 Locked'); ?>
            </span>
        </div>
        <?php if ($step3 && !empty($step3['review_submitted_screenshot'])): ?>
            <div class="field"><div class="field-label">Screenshot</div><div class="field-value"><a href="<?php echo escape($step3['review_submitted_screenshot']); ?>" target="_blank" class="screenshot-link">View Review Proof →</a></div></div>
        <?php elseif ($step2_done): ?>
            <p style="color:#666">Upload review screenshot to complete this step</p>
        <?php else: ?>
            <p style="color:#999">Complete Step 2 first</p>
        <?php endif; ?>
        <?php if ($step2_done && !$task['refund_requested']): ?>
            <a href="<?php echo APP_URL; ?>/user/submit-review.php?task_id=<?php echo $task_id; ?>" class="action-btn"><?php echo $step3 ? '✎ Edit' : 'Submit'; ?> Step 3</a>
        <?php elseif (!$step2_done): ?>
            <span class="action-btn locked">🔒 Complete Step 2 First</span>
        <?php endif; ?>
    </div>
    
    <!-- STEP 4 -->
    <div class="card step-card <?php echo $step4_done ? 'done' : ($step4_pending ? 'pending' : ($step3_done ? '' : 'locked')); ?>">
        <div class="step-header">
            <div class="step-title">💰 Step 4: Refund Request</div>
            <span class="status-badge <?php echo $step4_done ? 'status-completed' : ($step4_pending ? 'status-processing' : ($step3_done ? 'status-pending' : 'status-pending')); ?>">
                <?php echo $step4_done ? '✓ Completed' : ($step4_pending ? '⏳ Processing' : ($step3_done ? 'Ready' : '🔒 Locked')); ?>
            </span>
        </div>
        
        <?php if ($step4): ?>
            <?php if (!empty($step4['review_live_screenshot'])): ?>
                <div class="field"><div class="field-label">Review Live Screenshot</div><div class="field-value"><a href="<?php echo escape($step4['review_live_screenshot']); ?>" target="_blank" class="screenshot-link">View →</a></div></div>
            <?php endif; ?>
            
            <?php if (!empty($step4['payment_qr_code'])): ?>
                <div class="qr-preview">
                    <div class="field-label">Your QR Code</div>
                    <img src="<?php echo escape($step4['payment_qr_code']); ?>" alt="QR Code">
                </div>
            <?php endif; ?>
            
            <!-- Admin Refund Info -->
            <?php if ($step4_done && !empty($step4['refund_amount'])): ?>
                <div class="refund-info">
                    <h4>✅ Refund Processed!</h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                        <div>
                            <div class="field-label">Refund Amount</div>
                            <div class="refund-amount">₹<?php echo number_format($step4['refund_amount'], 2); ?></div>
                        </div>
                        <div>
                            <div class="field-label">Payment Proof</div>
                            <div class="field-value">
                                <?php if (!empty($step4['admin_payment_screenshot'])): ?>
                                    <a href="<?php echo escape($step4['admin_payment_screenshot']); ?>" target="_blank" class="screenshot-link">View Payment →</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <div class="field-label">Processed By</div>
                            <div class="field-value"><?php echo escape($step4['refund_processed_by'] ?? 'Admin'); ?></div>
                        </div>
                        <div>
                            <div class="field-label">Processed On</div>
                            <div class="field-value"><?php echo $step4['refund_processed_at'] ? date('d M Y, h:i A', strtotime($step4['refund_processed_at'])) : '-'; ?></div>
                        </div>
                    </div>
                </div>
            <?php elseif ($step4_pending): ?>
                <div style="background:#fff3cd;padding:15px;border-radius:8px;margin-top:15px;text-align:center">
                    <strong>⏳ Waiting for Admin</strong><br>
                    <small>Admin will verify and process your refund soon</small>
                </div>
            <?php endif; ?>
            
        <?php elseif ($step3_done): ?>
            <p style="color:#666">Submit review live screenshot and your QR code to request refund</p>
        <?php else: ?>
            <p style="color:#999">Complete Step 3 first</p>
        <?php endif; ?>
        
        <?php if ($step3_done && !$task['refund_requested']): ?>
            <a href="<?php echo APP_URL; ?>/user/submit-refund.php?task_id=<?php echo $task_id; ?>" class="action-btn" style="background:#27ae60">💰 Request Refund</a>
        <?php elseif (!$step3_done && !$step4): ?>
            <span class="action-btn locked">🔒 Complete Step 3 First</span>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
