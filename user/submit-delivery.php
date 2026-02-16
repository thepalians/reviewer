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
$errors = [];
$success = false;

try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :task_id AND user_id = :user_id");
    $stmt->execute([':task_id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch();
    
    if (!$task || $task['refund_requested']) {
        header('Location: ' . APP_URL . '/user/');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 1 AND step_status = 'completed'");
    $stmt->execute([':task_id' => $task_id]);
    if ($stmt->rowCount() === 0) {
        header('Location: ' . APP_URL . '/user/task-detail.php?task_id=' . $task_id);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 2");
    $stmt->execute([':task_id' => $task_id]);
    $step_data = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_screenshot = $step_data['delivery_screenshot'] ?? '';
    
    if (isset($_FILES['delivery_screenshot']) && $_FILES['delivery_screenshot']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['delivery_screenshot']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'File too large (max 5MB)';
        } else {
            $cfile = new CURLFile($_FILES['delivery_screenshot']['tmp_name'], $_FILES['delivery_screenshot']['type'], $_FILES['delivery_screenshot']['name']);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://palians.com/image-host/upload.php',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['image' => $cfile],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 60
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && !empty($response)) {
                $lines = explode("\n", trim($response));
                if (!empty($lines[0])) $delivery_screenshot = $lines[0];
                else $errors[] = 'Upload failed';
            } else {
                $errors[] = 'Upload failed';
            }
        }
    } elseif (empty($delivery_screenshot)) {
        $errors[] = 'Screenshot required';
    }
    
    if (empty($errors) && !empty($delivery_screenshot)) {
        try {
            if ($step_data) {
                $stmt = $pdo->prepare("UPDATE task_steps SET delivery_screenshot = :ss, step_status = 'completed', submitted_by_user = true, updated_at = NOW() WHERE task_id = :tid AND step_number = 2");
            } else {
                $stmt = $pdo->prepare("INSERT INTO task_steps (task_id, step_number, step_name, delivery_screenshot, step_status, submitted_by_user) VALUES (:tid, 2, 'Order Delivered', :ss, 'completed', true)");
            }
            $stmt->execute([':tid' => $task_id, ':ss' => $delivery_screenshot]);
            $success = true;
            
            $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 2");
            $stmt->execute([':task_id' => $task_id]);
            $step_data = $stmt->fetch();
        } catch (PDOException $e) {
            $errors[] = 'Save failed';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Step 2: Delivery - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box}body{background:linear-gradient(135deg,#667eea,#764ba2);min-height:100vh;padding:15px;margin:0;font-family:-apple-system,sans-serif}
        .form-box{max-width:500px;margin:0 auto;background:#fff;padding:25px;border-radius:15px;box-shadow:0 10px 40px rgba(0,0,0,0.2)}
        h2{font-size:20px;margin-bottom:20px;color:#333;text-align:center}
        .info-box{background:#f8f9fa;padding:12px;border-radius:8px;margin-bottom:15px;border-left:4px solid #667eea;font-size:14px}
        .alert{padding:12px;border-radius:8px;margin-bottom:15px;font-size:14px}
        .alert-success{background:#d4edda;color:#155724}
        .alert-danger{background:#f8d7da;color:#721c24}
        .form-group{margin-bottom:20px}
        label{display:block;font-weight:600;margin-bottom:8px;color:#333}
        input[type="file"]{width:100%;padding:12px;border:2px dashed #ddd;border-radius:10px;font-size:16px;background:#fafafa}
        .file-info{font-size:11px;color:#888;margin-top:5px}
        .preview{margin-top:12px;text-align:center}
        .preview img{max-width:200px;border-radius:8px;border:2px solid #27ae60}
        .btn{width:100%;padding:14px;border:none;border-radius:10px;font-size:16px;font-weight:600;cursor:pointer;margin-top:10px}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-success{background:#27ae60;color:#fff}
        .links{text-align:center;margin-top:20px}
        .links a{color:#667eea;text-decoration:none;margin:0 10px;font-weight:600}
    </style>
</head>
<body>
<div class="form-box">
    <h2>🚚 Step 2: Order Delivered</h2>
    <div class="info-box">📌 Task #<?php echo $task_id; ?><br><small>Upload delivery screenshot</small></div>
    
    <?php if ($success): ?>
        <div class="alert alert-success">✓ Saved! <a href="<?php echo APP_URL; ?>/user/submit-review.php?task_id=<?php echo $task_id; ?>">Continue to Step 3 →</a></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger">✗ <?php echo escape($e); ?></div><?php endforeach; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Delivery Screenshot *</label>
            <input type="file" name="delivery_screenshot" accept="image/*" <?php echo empty($step_data['delivery_screenshot']) ? 'required' : ''; ?>>
            <div class="file-info">Max 5MB • JPG, PNG, WebP</div>
            <?php if (!empty($step_data['delivery_screenshot'])): ?>
                <div class="preview"><img src="<?php echo escape($step_data['delivery_screenshot']); ?>" alt="Preview"></div>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">Submit Step 2</button>
    </form>
    <div class="links">
        <a href="<?php echo APP_URL; ?>/user/">← Dashboard</a>
        <a href="<?php echo APP_URL; ?>/user/task-detail.php?task_id=<?php echo $task_id; ?>">Task Details</a>
    </div>
</div>
</body>
</html>
