<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gamification-functions.php';
require_once __DIR__ . '/../includes/referral-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$task_id = intval($_GET['task_id'] ?? 0);
$errors = [];
$success = '';

if ($task_id <= 0) {
    header('Location: ' . ADMIN_URL . '/task-pending.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT t.*, u.name as user_name, u.email, u.mobile FROM tasks t JOIN users u ON t.user_id = u.id WHERE t.id = :id");
    $stmt->execute([':id' => $task_id]);
    $task = $stmt->fetch();
    
    if (!$task) { header('Location: ' . ADMIN_URL . '/task-pending.php'); exit; }
    
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :id ORDER BY step_number");
    $stmt->execute([':id' => $task_id]);
    $steps = $stmt->fetchAll();
    $step = [];
    foreach ($steps as $s) $step[$s['step_number']] = $s;
} catch (PDOException $e) {
    die('Database Error: ' . $e->getMessage());
}

// Handle refund processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
    $refund_amount = floatval($_POST['refund_amount'] ?? 0);
    $payment_ss = '';
    
    if ($refund_amount <= 0) {
        $errors[] = 'Enter valid refund amount';
    }
    
    if (isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] === UPLOAD_ERR_OK) {
        $cfile = new CURLFile($_FILES['payment_screenshot']['tmp_name'], $_FILES['payment_screenshot']['type'], $_FILES['payment_screenshot']['name']);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://palians.com/image-host/upload.php',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['image' => $cfile],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 120
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && !empty($response)) {
            $lines = explode("\n", trim($response));
            if (!empty($lines[0]) && strpos($lines[0], 'http') === 0) {
                $payment_ss = $lines[0];
            }
        }
        if (empty($payment_ss)) $errors[] = 'Payment screenshot upload failed';
    } else {
        $errors[] = 'Payment screenshot required';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update step 4
            $stmt = $pdo->prepare("
                UPDATE task_steps SET 
                    refund_amount = :amount,
                    admin_payment_screenshot = :screenshot,
                    step_status = 'completed',
                    refund_processed_at = NOW(),
                    refund_processed_by = :admin
                WHERE task_id = :task_id AND step_number = 4
            ");
            $stmt->execute([
                ':amount' => $refund_amount,
                ':screenshot' => $payment_ss,
                ':admin' => $admin_name,
                ':task_id' => $task_id
            ]);
            
            // Add commission to wallet
            $commission = floatval($task['commission'] ?? 0);
            if ($commission > 0) {
                // Check if user_wallet row exists
                $walletCheck = $pdo->prepare("SELECT id FROM user_wallet WHERE user_id = :uid");
                $walletCheck->execute([':uid' => $task['user_id']]);
                if ($walletCheck->fetch()) {
                    $stmt = $pdo->prepare("UPDATE user_wallet SET balance = balance + :amount WHERE user_id = :user_id");
                    $stmt->execute([':amount' => $commission, ':user_id' => $task['user_id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO user_wallet (user_id, balance) VALUES (:user_id, :amount)");
                    $stmt->execute([':user_id' => $task['user_id'], ':amount' => $commission]);
                }
                
                $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, type, amount, description, created_at) VALUES (:user_id, 'credit', :amount, :desc, NOW())");
                $stmt->execute([':user_id' => $task['user_id'], ':amount' => $commission, ':desc' => 'Commission for Task #' . $task_id]);
            }
            
            // Mark task completed
            $stmt = $pdo->prepare("UPDATE tasks SET task_status = 'completed' WHERE id = :id");
            $stmt->execute([':id' => $task_id]);
            
            $pdo->commit();
            
            // ✅ Gamification hook (OUTSIDE transaction - safe)
            try {
                if (function_exists('awardTaskCompletionPoints')) {
                    $pointsCheck = $pdo->prepare("SELECT 1 FROM point_transactions WHERE user_id = ? AND type = 'task_completion' AND reference_id = ?");
                    $pointsCheck->execute([$task['user_id'], $task_id]);
                    if (!$pointsCheck->fetchColumn()) {
                        awardTaskCompletionPoints($pdo, $task['user_id'], $task_id);
                    }
                }
            } catch (Exception $ge) {
                error_log("Gamification error (non-critical): " . $ge->getMessage());
            }

            // ✅ Referral commission hook (OUTSIDE transaction - safe)
            try {
                if (function_exists('creditReferralCommission')) {
                    $refCheck = $pdo->prepare("SELECT 1 FROM referral_earnings WHERE task_id = ? AND from_user_id = ? LIMIT 1");
                    $refCheck->execute([$task_id, $task['user_id']]);
                    if (!$refCheck->fetchColumn()) {
                        $task_amount = $refund_amount > 0 ? $refund_amount : floatval($step[1]['order_amount'] ?? 0);
                        creditReferralCommission($pdo, $task['user_id'], $task_id, $task_amount);
                    }
                }
            } catch (Exception $re) {
                error_log("Referral commission error (non-critical): " . $re->getMessage());
            }
            
            // ✅ Notification (safe)
            try {
                if (function_exists('createNotification')) {
                    createNotification($task['user_id'], 'success', 'Refund Processed', 'Your refund of ₹' . number_format($refund_amount, 2) . ' has been sent for Task #' . $task_id);
                }
            } catch (Exception $ne) {
                error_log("Notification error (non-critical): " . $ne->getMessage());
            }
            
            $success = 'Refund processed successfully!';
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :id ORDER BY step_number");
            $stmt->execute([':id' => $task_id]);
            $steps = $stmt->fetchAll();
            $step = [];
            foreach ($steps as $s) $step[$s['step_number']] = $s;
            
            $stmt = $pdo->prepare("SELECT t.*, u.name as user_name, u.email, u.mobile FROM tasks t JOIN users u ON t.user_id = u.id WHERE t.id = :id");
            $stmt->execute([':id' => $task_id]);
            $task = $stmt->fetch();
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle refund rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_refund'])) {
    $reject_reason = $_POST['reject_reason'] ?? '';
    $custom_reason = trim($_POST['custom_reason'] ?? '');
    
    if (empty($reject_reason)) {
        $errors[] = 'Please select rejection reason';
    } elseif ($reject_reason === 'other' && empty($custom_reason)) {
        $errors[] = 'Please enter custom rejection reason';
    } else {
        $final_reason = ($reject_reason === 'other') ? $custom_reason : $reject_reason;
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE task_steps SET 
                    step_status = 'rejected',
                    rejection_reason = :reason,
                    rejected_at = NOW(),
                    rejected_by = :admin
                WHERE task_id = :task_id AND step_number = 4
            ");
            $stmt->execute([
                ':reason' => $final_reason,
                ':admin' => $admin_name,
                ':task_id' => $task_id
            ]);
            
            $stmt = $pdo->prepare("UPDATE tasks SET task_status = 'rejected', refund_requested = 0 WHERE id = :id");
            $stmt->execute([':id' => $task_id]);
            
            // Insert into task_rejections table
            try {
                $stmt = $pdo->prepare("INSERT INTO task_rejections (task_id, user_id, rejected_by, rejection_reason, rejection_type, can_resubmit, created_at) VALUES (:task_id, :user_id, :rejected_by, :reason, :type, 1, NOW())");
                $stmt->execute([
                    ':task_id' => $task_id,
                    ':user_id' => $task['user_id'],
                    ':rejected_by' => $admin_name,
                    ':reason' => $final_reason,
                    ':type' => 'other'
                ]);
            } catch (PDOException $rje) {
                error_log("Task rejection log error (non-critical): " . $rje->getMessage());
            }
            
            $pdo->commit();
            
            // Notification (safe, outside transaction)
            try {
                if (function_exists('createNotification')) {
                    createNotification($task['user_id'], 'warning', 'Refund Rejected', 'Your refund request for Task #' . $task_id . ' was rejected. Reason: ' . $final_reason);
                }
            } catch (Exception $ne) {
                error_log("Notification error: " . $ne->getMessage());
            }
            
            $success = 'Refund request rejected!';
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :id ORDER BY step_number");
            $stmt->execute([':id' => $task_id]);
            $steps = $stmt->fetchAll();
            $step = [];
            foreach ($steps as $s) $step[$s['step_number']] = $s;
            
            $stmt = $pdo->prepare("SELECT t.*, u.name as user_name, u.email, u.mobile FROM tasks t JOIN users u ON t.user_id = u.id WHERE t.id = :id");
            $stmt->execute([':id' => $task_id]);
            $task = $stmt->fetch();
            
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$step4 = $step[4] ?? null;
$refund_done = $step4 && ($step4['step_status'] ?? '') === 'completed';
$refund_rejected = $step4 && ($step4['step_status'] ?? '') === 'rejected';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task #<?php echo $task_id; ?> - Admin</title>
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
        .back-btn{display:inline-block;padding:10px 20px;background:#6c757d;color:#fff;text-decoration:none;border-radius:8px;margin-bottom:20px}
        .card{background:#fff;border-radius:12px;padding:20px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
        .card-title{font-size:18px;font-weight:600;color:#2c3e50;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
        .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px}
        .info-item{padding:10px;background:#f8f9fa;border-radius:8px}
        .info-label{font-size:11px;color:#888;text-transform:uppercase}
        .info-value{font-weight:600;color:#2c3e50;margin-top:3px}
        .info-value a{color:#3498db;text-decoration:none}
        .step-card{border-left:4px solid #e74c3c}
        .step-card.done{border-left-color:#27ae60}
        .step-card.pending{border-left-color:#f39c12}
        .step-card.rejected{border-left-color:#e74c3c}
        .status-badge{padding:5px 12px;border-radius:15px;font-size:12px;font-weight:600}
        .status-done{background:#d4edda;color:#155724}
        .status-pending{background:#fff3cd;color:#856404}
        .status-rejected{background:#f8d7da;color:#721c24}
        .screenshot-link{color:#3498db;text-decoration:none;font-weight:600}
        .qr-section{background:linear-gradient(135deg,#fff8e1,#ffecb3);border:2px solid #ffc107;border-radius:12px;padding:25px;text-align:center;margin:20px 0}
        .qr-section h4{color:#ff8f00;margin-bottom:15px}
        .qr-section img{max-width:280px;border:4px solid #27ae60;border-radius:12px;padding:10px;background:#fff}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px}
        .refund-form{background:#e8f5e9;border:2px solid #4caf50;border-radius:12px;padding:25px}
        .refund-form h4{color:#2e7d32;margin-bottom:20px}
        .reject-form{background:#ffebee;border:2px solid #f44336;border-radius:12px;padding:25px}
        .reject-form h4{color:#c62828;margin-bottom:20px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#333}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px}
        .form-group textarea{min-height:80px;resize:vertical}
        .btn-submit{padding:15px 30px;background:#27ae60;color:#fff;border:none;border-radius:8px;font-weight:600;font-size:16px;cursor:pointer;width:100%}
        .btn-submit:hover{background:#219a52}
        .btn-reject{padding:15px 30px;background:#e74c3c;color:#fff;border:none;border-radius:8px;font-weight:600;font-size:16px;cursor:pointer;width:100%}
        .btn-reject:hover{background:#c0392b}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724}
        .alert-danger{background:#f8d7da;color:#721c24}
        .refund-done{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border:2px solid #4caf50;border-radius:12px;padding:25px;margin-top:20px}
        .refund-done h4{color:#2e7d32;margin-bottom:15px}
        .refund-amount{font-size:32px;font-weight:700;color:#27ae60}
        .rejection-box{background:#ffebee;border:2px solid #f44336;border-radius:12px;padding:20px;margin-top:20px}
        .rejection-box h4{color:#c62828;margin-bottom:15px}
        #customReasonBox{display:none}
        .no-data{color:#999;font-style:italic}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.form-row,.two-col{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="content">
        <a href="<?php echo ADMIN_URL; ?>/task-pending.php" class="back-btn">← Back</a>
        
        <?php if ($success): ?><div class="alert alert-success">✓ <?php echo $success; ?></div><?php endif; ?>
        <?php foreach ($errors as $e): ?><div class="alert alert-danger">✗ <?php echo escape($e); ?></div><?php endforeach; ?>
        
        <!-- Task Header -->
        <div class="card">
            <div class="card-title">📋 Task #<?php echo $task_id; ?></div>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Reviewer</div><div class="info-value"><?php echo escape($task['user_name']); ?></div></div>
                <div class="info-item"><div class="info-label">Email</div><div class="info-value"><?php echo escape($task['email']); ?></div></div>
                <div class="info-item"><div class="info-label">Mobile</div><div class="info-value"><?php echo escape($task['mobile']); ?></div></div>
                <div class="info-item"><div class="info-label">Commission</div><div class="info-value">₹<?php echo number_format($task['commission'] ?? 0, 2); ?></div></div>
                <div class="info-item"><div class="info-label">Status</div><div class="info-value"><?php echo $refund_done ? '✓ Completed' : ($refund_rejected ? '❌ Rejected' : '⏳ Pending'); ?></div></div>
                <div class="info-item"><div class="info-label">Product</div><div class="info-value"><a href="<?php echo escape($task['product_link'] ?? '#'); ?>" target="_blank">View →</a></div></div>
            </div>
        </div>
        
        <!-- Step 1 -->
        <?php $s1 = $step[1] ?? null; $s1_done = $s1 && ($s1['step_status'] ?? '') === 'completed'; ?>
        <div class="card step-card <?php echo $s1_done ? 'done' : ''; ?>">
            <div class="card-title">
                <span>📦 Step 1: Order Placed</span>
                <span class="status-badge <?php echo $s1_done ? 'status-done' : 'status-pending'; ?>"><?php echo $s1_done ? '✓ Completed' : 'Pending'; ?></span>
            </div>
            <?php if ($s1_done): ?>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Order Number</div><div class="info-value"><?php echo escape($s1['order_number'] ?? '-'); ?></div></div>
                <div class="info-item"><div class="info-label">Order Name</div><div class="info-value"><?php echo escape($s1['order_name'] ?? '-'); ?></div></div>
                <div class="info-item"><div class="info-label">Product</div><div class="info-value"><?php echo escape($s1['product_name'] ?? '-'); ?></div></div>
                <div class="info-item"><div class="info-label">Amount</div><div class="info-value">₹<?php echo number_format($s1['order_amount'] ?? 0, 2); ?></div></div>
                <div class="info-item"><div class="info-label">Date</div><div class="info-value"><?php echo ($s1['order_date'] ?? '') ? date('d M Y', strtotime($s1['order_date'])) : '-'; ?></div></div>
                <div class="info-item"><div class="info-label">Screenshot</div><div class="info-value"><?php echo !empty($s1['order_screenshot']) ? '<a href="'.escape($s1['order_screenshot']).'" target="_blank" class="screenshot-link">View Screenshot →</a>' : '<span class="no-data">Not uploaded</span>'; ?></div></div>
            </div>
            <?php else: ?><p class="no-data" style="padding:10px 0">Not submitted yet.</p><?php endif; ?>
        </div>
        
        <!-- Step 2 -->
        <?php $s2 = $step[2] ?? null; $s2_done = $s2 && ($s2['step_status'] ?? '') === 'completed'; ?>
        <div class="card step-card <?php echo $s2_done ? 'done' : ''; ?>">
            <div class="card-title">
                <span>🚚 Step 2: Order Delivered</span>
                <span class="status-badge <?php echo $s2_done ? 'status-done' : 'status-pending'; ?>"><?php echo $s2_done ? '✓ Completed' : 'Pending'; ?></span>
            </div>
            <?php if ($s2_done): ?>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Delivery Date</div><div class="info-value"><?php echo ($s2['completed_at'] ?? '') ? date('d M Y', strtotime($s2['completed_at'])) : '-'; ?></div></div>
                <div class="info-item"><div class="info-label">Delivery Screenshot</div><div class="info-value"><?php echo !empty($s2['delivery_screenshot']) ? '<a href="'.escape($s2['delivery_screenshot']).'" target="_blank" class="screenshot-link">View Screenshot →</a>' : '<span class="no-data">Not uploaded</span>'; ?></div></div>
            </div>
            <?php else: ?><p class="no-data" style="padding:10px 0">Not submitted yet.</p><?php endif; ?>
        </div>
        
        <!-- Step 3 -->
        <?php $s3 = $step[3] ?? null; $s3_done = $s3 && ($s3['step_status'] ?? '') === 'completed'; ?>
        <div class="card step-card <?php echo $s3_done ? 'done' : ''; ?>">
            <div class="card-title">
                <span>⭐ Step 3: Review Submitted</span>
                <span class="status-badge <?php echo $s3_done ? 'status-done' : 'status-pending'; ?>"><?php echo $s3_done ? '✓ Completed' : 'Pending'; ?></span>
            </div>
            <?php if ($s3_done): ?>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Review Date</div><div class="info-value"><?php echo ($s3['completed_at'] ?? '') ? date('d M Y', strtotime($s3['completed_at'])) : '-'; ?></div></div>
                <div class="info-item"><div class="info-label">Review Screenshot</div><div class="info-value"><?php echo !empty($s3['review_screenshot']) ? '<a href="'.escape($s3['review_screenshot']).'" target="_blank" class="screenshot-link">View Screenshot →</a>' : '<span class="no-data">Not uploaded</span>'; ?></div></div>
            </div>
            <?php else: ?><p class="no-data" style="padding:10px 0">Not submitted yet.</p><?php endif; ?>
        </div>
        
        <!-- Step 4 -->
        <div class="card step-card <?php echo $refund_done ? 'done' : ($refund_rejected ? 'rejected' : 'pending'); ?>">
            <div class="card-title">
                <span>💰 Step 4: Refund Request</span>
                <span class="status-badge <?php echo $refund_done ? 'status-done' : ($refund_rejected ? 'status-rejected' : 'status-pending'); ?>"><?php echo $refund_done ? '✓ Refund Sent' : ($refund_rejected ? '❌ Rejected' : '⏳ Pending'); ?></span>
            </div>
            
            <?php if ($step4): ?>
            <div class="info-grid" style="margin-bottom:15px">
                <div class="info-item"><div class="info-label">Review Live Screenshot</div><div class="info-value"><?php echo !empty($step4['review_live_screenshot']) ? '<a href="'.escape($step4['review_live_screenshot']).'" target="_blank" class="screenshot-link">View Screenshot →</a>' : '<span class="no-data">Not uploaded</span>'; ?></div></div>
                <div class="info-item"><div class="info-label">Payment QR Code</div><div class="info-value"><?php echo !empty($step4['payment_qr_code']) ? '<a href="'.escape($step4['payment_qr_code']).'" target="_blank" class="screenshot-link">View QR →</a>' : '<span class="no-data">Not uploaded</span>'; ?></div></div>
            </div>
            <?php endif; ?>
            
            <?php if ($step4 && !empty($step4['payment_qr_code']) && !$refund_done && !$refund_rejected): ?>
                <div class="qr-section">
                    <h4>📱 User's Payment QR Code (Scan to Send Refund)</h4>
                    <img src="<?php echo escape($step4['payment_qr_code']); ?>" alt="Payment QR Code">
                    <p style="margin-top:10px"><a href="<?php echo escape($step4['payment_qr_code']); ?>" target="_blank" class="screenshot-link">Open Full Size →</a></p>
                </div>
            <?php endif; ?>
            
            <?php if ($refund_done): ?>
                <div class="refund-done">
                    <h4>✅ Refund Processed</h4>
                    <div class="info-grid">
                        <div class="info-item"><div class="info-label">Refund Amount</div><div class="refund-amount">₹<?php echo number_format($step4['refund_amount'] ?? 0, 2); ?></div></div>
                        <div class="info-item"><div class="info-label">Payment Proof</div><div class="info-value"><?php echo !empty($step4['admin_payment_screenshot']) ? '<a href="'.escape($step4['admin_payment_screenshot']).'" target="_blank" class="screenshot-link">View →</a>' : '-'; ?></div></div>
                        <div class="info-item"><div class="info-label">Processed By</div><div class="info-value"><?php echo escape($step4['refund_processed_by'] ?? '-'); ?></div></div>
                        <div class="info-item"><div class="info-label">Processed On</div><div class="info-value"><?php echo ($step4['refund_processed_at'] ?? '') ? date('d M Y, h:i A', strtotime($step4['refund_processed_at'])) : '-'; ?></div></div>
                    </div>
                </div>
            <?php elseif ($refund_rejected): ?>
                <div class="rejection-box">
                    <h4>❌ Rejected</h4>
                    <div class="info-grid">
                        <div class="info-item"><div class="info-label">Reason</div><div class="info-value"><?php echo escape($step4['rejection_reason'] ?? 'N/A'); ?></div></div>
                        <div class="info-item"><div class="info-label">By</div><div class="info-value"><?php echo escape($step4['rejected_by'] ?? 'N/A'); ?></div></div>
                        <div class="info-item"><div class="info-label">On</div><div class="info-value"><?php echo ($step4['rejected_at'] ?? '') ? date('d M Y, h:i A', strtotime($step4['rejected_at'])) : 'N/A'; ?></div></div>
                    </div>
                </div>
            <?php elseif ($step4): ?>
                <div class="two-col">
                    <div class="refund-form">
                        <h4>✅ Approve & Process Refund</h4>
                        <p style="color:#666;margin-bottom:20px">Scan the QR code above, send the refund, then fill details below.</p>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Refund Amount (₹) *</label>
                                <input type="number" name="refund_amount" step="0.01" min="1" required value="<?php echo $step[1]['order_amount'] ?? ''; ?>" placeholder="Enter amount">
                            </div>
                            <div class="form-group">
                                <label>Payment Screenshot (Proof) *</label>
                                <input type="file" name="payment_screenshot" accept="image/*" required>
                            </div>
                            <button type="submit" name="process_refund" class="btn-submit">✓ Mark Refund as Completed</button>
                        </form>
                    </div>
                    <div class="reject-form">
                        <h4>❌ Reject Refund Request</h4>
                        <form method="POST">
                            <div class="form-group">
                                <label>Rejection Reason *</label>
                                <select name="reject_reason" required onchange="document.getElementById('customReasonBox').style.display=this.value==='other'?'block':'none'">
                                    <option value="">-- Select Reason --</option>
                                    <option value="Your filled order data does not belong to your task">Your filled order data does not belong to your task</option>
                                    <option value="The required information has not been entered correctly">The required information has not been entered correctly</option>
                                    <option value="other">Others (Custom Reason)</option>
                                </select>
                            </div>
                            <div class="form-group" id="customReasonBox">
                                <label>Custom Reason *</label>
                                <textarea name="custom_reason" placeholder="Enter custom rejection reason..."></textarea>
                            </div>
                            <button type="submit" name="reject_refund" class="btn-reject">✗ Reject Refund</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <p class="no-data" style="padding:15px 0">User has not requested refund yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
