<?php
session_start();
require_once __DIR__ . "/../includes/config.php";
require_once __DIR__ . "/../includes/security.php";
require_once __DIR__ . "/../includes/functions.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: " . APP_URL . "/index.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$task_id = intval($_GET["task_id"] ?? 0);
$errors = [];
$success = false;

if ($task_id <= 0) {
    die("Invalid task ID");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :task_id AND user_id = :user_id");
    $stmt->execute([":task_id" => $task_id, ":user_id" => $user_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        die("Task not found or unauthorized");
    }
    
    if ($task["refund_requested"]) {
        header("Location: " . APP_URL . "/user/task-detail.php?task_id=" . $task_id);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 1");
    $stmt->execute([":task_id" => $task_id]);
    $step_data = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    die("Database error");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCSRFToken($_POST["csrf_token"] ?? "")) {
        die("CSRF token validation failed");
    }
    
    $order_date = sanitizeInput($_POST["order_date"] ?? "");
    $order_name = sanitizeInput($_POST["order_name"] ?? "");
    $product_name = sanitizeInput($_POST["product_name"] ?? "");
    $order_number = sanitizeInput($_POST["order_number"] ?? "");
    $order_amount = floatval($_POST["order_amount"] ?? 0);
    
    if (empty($order_date)) $errors[] = "Order date is required";
    if (empty($order_name)) $errors[] = "Order name is required";
    if (empty($product_name)) $errors[] = "Product name is required";
    if (empty($order_number)) $errors[] = "Order number is required";
    if ($order_amount <= 0) $errors[] = "Order amount must be greater than 0";
    
    // Check for duplicate order number
    if (!empty($order_number)) {
        $stmt = $pdo->prepare("
            SELECT ts.task_id, t.user_id, u.name as user_name 
            FROM task_steps ts 
            JOIN tasks t ON ts.task_id = t.id 
            JOIN users u ON t.user_id = u.id
            WHERE ts.order_number = :order_number 
            AND ts.task_id != :current_task_id
        ");
        $stmt->execute([":order_number" => $order_number, ":current_task_id" => $task_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $errors[] = "This Order ID has already been used! Order IDs cannot be reused.";
        }
    }
    
    $order_screenshot = $step_data["order_screenshot"] ?? "";
    
    if (isset($_FILES["order_screenshot"]) && $_FILES["order_screenshot"]["error"] === UPLOAD_ERR_OK) {
        $max_size = 5 * 1024 * 1024;
        if ($_FILES["order_screenshot"]["size"] > $max_size) {
            $errors[] = "File size must be less than 5MB";
        }
        
        $allowed_types = ["image/jpeg", "image/jpg", "image/png", "image/gif", "image/webp"];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $_FILES["order_screenshot"]["tmp_name"]);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = "Only JPG, PNG, GIF, and WebP images are allowed";
        }
        
        if (empty($errors)) {
            $cfile = new CURLFile(
                $_FILES["order_screenshot"]["tmp_name"],
                $_FILES["order_screenshot"]["type"],
                $_FILES["order_screenshot"]["name"]
            );
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://palians.com/image-host/upload.php");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ["image" => $cfile]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($httpCode === 200 && !empty($response)) {
                $lines = explode("\n", trim($response));
                if (!empty($lines[0])) {
                    $order_screenshot = $lines[0];
                } else {
                    $errors[] = "Image uploaded but no URL returned";
                }
            } else {
                $errors[] = $curlError ? "Upload failed: " . $curlError : "Upload failed";
            }
        }
    } elseif (isset($_FILES["order_screenshot"]) && $_FILES["order_screenshot"]["error"] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = "File upload error";
    }
    
    if (empty($errors)) {
        try {
            if ($step_data) {
                $stmt = $pdo->prepare("
                    UPDATE task_steps 
                    SET order_date = :order_date, order_name = :order_name, product_name = :product_name,
                        order_number = :order_number, order_amount = :order_amount, 
                        order_screenshot = :order_screenshot, step_status = \"completed\",
                        submitted_by_user = true
                    WHERE task_id = :task_id AND step_number = 1
                ");
                
                $stmt->execute([
                    ":order_date" => $order_date,
                    ":order_name" => $order_name,
                    ":product_name" => $product_name,
                    ":order_number" => $order_number,
                    ":order_amount" => $order_amount,
                    ":order_screenshot" => $order_screenshot,
                    ":task_id" => $task_id
                ]);
                
                logActivity("User updated Step 1", $task_id, $user_id);
                
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO task_steps 
                    (task_id, step_number, step_name, step_status, order_date, order_name, product_name,
                     order_number, order_amount, order_screenshot, submitted_by_user)
                    VALUES (:task_id, 1, \"Order Placed\", \"completed\", :order_date, :order_name, :product_name,
                            :order_number, :order_amount, :order_screenshot, true)
                ");
                
                $stmt->execute([
                    ":task_id" => $task_id,
                    ":order_date" => $order_date,
                    ":order_name" => $order_name,
                    ":product_name" => $product_name,
                    ":order_number" => $order_number,
                    ":order_amount" => $order_amount,
                    ":order_screenshot" => $order_screenshot
                ]);
                
                logActivity("User submitted Step 1", $task_id, $user_id);
            }
            
            $success = true;
            
            $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 1");
            $stmt->execute([":task_id" => $task_id]);
            $step_data = $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Update error: " . $e->getMessage());
            $errors[] = "Failed to save data";
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 1: Submit Order - <?php echo APP_NAME; ?></title>
    <style>
        body{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px;font-family:-apple-system,sans-serif;margin:0}
        .form-container{max-width:600px;margin:0 auto;background:#fff;padding:30px;border-radius:15px;box-shadow:0 10px 40px rgba(0,0,0,0.2)}
        .form-title{font-size:24px;font-weight:600;margin-bottom:30px;color:#333}
        .form-group{margin-bottom:20px}
        .form-group label{font-weight:600;color:#333;margin-bottom:8px;display:block}
        .form-control{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box}
        .form-control:focus{border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,0.1);outline:none}
        .btn-submit{width:100%;padding:12px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:16px}
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(102,126,234,0.4)}
        .back-link{display:inline-block;margin-top:20px;color:#667eea;text-decoration:none;font-weight:600}
        .alert{margin-bottom:20px;padding:12px;border-radius:8px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .file-info{font-size:12px;color:#666;margin-top:5px}
        .current-image{margin-top:10px;padding:10px;background:#f8f9fa;border-radius:5px;border:1px solid #e9ecef}
        .current-image img{max-width:200px;max-height:150px;border-radius:5px;margin-top:5px;border:1px solid #dee2e6}
    </style>
</head>
<body>
    <div class="form-container">
        <h2 class="form-title">📦 Step 1: Submit Order</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✓ Order details saved successfully!
                <?php if (!empty($order_screenshot)): ?>
                    <br><small>Image uploaded: <a href="<?php echo escape($order_screenshot); ?>" target="_blank">View Screenshot</a></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">✗ <?php echo escape($error); ?></div>
        <?php endforeach; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Order Date *</label>
                <input type="date" name="order_date" class="form-control" value="<?php echo escape($step_data["order_date"] ?? date("Y-m-d")); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Order Name *</label>
                <input type="text" name="order_name" class="form-control" value="<?php echo escape($step_data["order_name"] ?? ""); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" name="product_name" class="form-control" value="<?php echo escape($step_data["product_name"] ?? ""); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Order Number (Order ID) *</label>
                <input type="text" name="order_number" class="form-control" value="<?php echo escape($step_data["order_number"] ?? ""); ?>" required placeholder="Enter unique Order ID">
                <div class="file-info">⚠️ Each Order ID can only be used once across all users</div>
            </div>
            
            <div class="form-group">
                <label>Order Amount (₹) *</label>
                <input type="number" name="order_amount" class="form-control" step="0.01" value="<?php echo escape($step_data["order_amount"] ?? ""); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Order Screenshot *</label>
                <input type="file" name="order_screenshot" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                <div class="file-info">Max size: 5MB | Allowed: JPG, PNG, GIF, WebP</div>
                
                <?php if ($step_data && $step_data["order_screenshot"]): ?>
                    <div class="current-image">
                        <strong>Current Screenshot:</strong><br>
                        <a href="<?php echo escape($step_data["order_screenshot"]); ?>" target="_blank">
                            <img src="<?php echo escape($step_data["order_screenshot"]); ?>" alt="Current Screenshot">
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" class="btn-submit">Submit Order Details</button>
            
            <a href="<?php echo APP_URL; ?>/user/" class="back-link">← Back to Dashboard</a>
        </form>
    </div>
</body>
</html>
