<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/kyc-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$task_id = intval($_GET['task_id'] ?? 0);
$errors = [];
$success = false;

// Create QR uploads directory
$qr_upload_dir = __DIR__ . '/../uploads/qr/';
if (!is_dir($qr_upload_dir)) {
    mkdir($qr_upload_dir, 0755, true);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :task_id AND user_id = :user_id");
    $stmt->execute([':task_id' => $task_id, ':user_id' => $user_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        header('Location: ' . APP_URL . '/user/');
        exit;
    }
    
    if ($task['refund_requested']) {
        header('Location: ' . APP_URL . '/user/task-detail.php?task_id=' . $task_id);
        exit;
    }
    
    // Step 2 must be completed (Step 3 is optional)
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 2 AND step_status = 'completed'");
    $stmt->execute([':task_id' => $task_id]);
    if ($stmt->rowCount() === 0) {
        header('Location: ' . APP_URL . '/user/task-detail.php?task_id=' . $task_id);
        exit;
    }
    
    // Check if user has verified KYC
    if (!isKYCApproved($pdo, $user_id)) {
        $_SESSION['kyc_required_message'] = 'KYC verification is required to complete Step 4 (Refund Request). Please complete your KYC first.';
        header('Location: ' . APP_URL . '/user/kyc.php');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 4");
    $stmt->execute([':task_id' => $task_id]);
    $step_data = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
    die('Database error');
}

// Upload to image-host (for review screenshot)
function uploadToImageHost($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File too large'];
    }
    
    $cfile = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://palians.com/image-host/upload.php',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['image' => $cfile],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 120,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($response)) {
        $lines = explode("\n", trim($response));
        if (!empty($lines[0]) && strpos($lines[0], 'http') === 0) {
            return ['success' => true, 'url' => $lines[0]];
        }
    }
    return ['success' => false, 'error' => 'Upload failed'];
}

// Upload QR locally (direct image for preview)
function uploadQRLocally($file, $upload_dir) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error'];
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File too large (max 5MB)'];
    }
    
    // Validate image
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }
    
    // Check if actually an image
    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) {
        return ['success' => false, 'error' => 'Invalid image'];
    }
    
    // Generate unique filename
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $ext = 'jpg';
    }
    $filename = 'qr_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true, 
            'url' => APP_URL . '/uploads/qr/' . $filename,
            'filename' => $filename
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to save file'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_ss = $step_data['review_live_screenshot'] ?? '';
    $qr_code = $step_data['payment_qr_code'] ?? '';
    $rating = intval($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please select a star rating (1-5)';
    }
    
    // Validate review text
    if (empty($review_text)) {
        $errors[] = 'Please provide your product feedback';
    }
    
    // Upload Review Screenshot to image-host
    if (isset($_FILES['review_screenshot']) && $_FILES['review_screenshot']['error'] === UPLOAD_ERR_OK) {
        $result = uploadToImageHost($_FILES['review_screenshot']);
        if ($result['success']) {
            $review_ss = $result['url'];
        } else {
            $errors[] = 'Review: ' . $result['error'];
        }
    } elseif (empty($review_ss)) {
        $errors[] = 'Review screenshot required';
    }
    
    // Upload QR Code locally
    if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] === UPLOAD_ERR_OK) {
        $result = uploadQRLocally($_FILES['qr_code'], $qr_upload_dir);
        if ($result['success']) {
            // Delete old QR if exists
            if (!empty($step_data['payment_qr_code'])) {
                $old_file = str_replace(APP_URL . '/uploads/qr/', '', $step_data['payment_qr_code']);
                $old_path = $qr_upload_dir . $old_file;
                if (file_exists($old_path)) {
                    @unlink($old_path);
                }
            }
            $qr_code = $result['url'];
        } else {
            $errors[] = 'QR Code: ' . $result['error'];
        }
    } elseif (empty($qr_code)) {
        $errors[] = 'QR code required';
    }
    
    if (empty($errors) && !empty($review_ss) && !empty($qr_code)) {
        try {
            $pdo->beginTransaction();
            
            if ($step_data) {
                $stmt = $pdo->prepare("
                    UPDATE task_steps SET 
                        review_live_screenshot = :review_ss,
                        payment_qr_code = :qr_code,
                        rating = :rating,
                        review_text = :review_text,
                        step_status = 'pending',
                        submitted_by_user = 1,
                        updated_at = NOW()
                    WHERE task_id = :task_id AND step_number = 4
                ");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO task_steps (task_id, step_number, step_name, review_live_screenshot, payment_qr_code, rating, review_text, step_status, submitted_by_user, created_at)
                    VALUES (:task_id, 4, 'Refund Request', :review_ss, :qr_code, :rating, :review_text, 'pending', 1, NOW())
                ");
            }
            $stmt->execute([':task_id' => $task_id, ':review_ss' => $review_ss, ':qr_code' => $qr_code, ':rating' => $rating, ':review_text' => $review_text]);
            
            $stmt = $pdo->prepare("UPDATE tasks SET refund_requested = 1 WHERE id = :task_id");
            $stmt->execute([':task_id' => $task_id]);
            
            $pdo->commit();
            $success = true;
            
            $stmt = $pdo->prepare("SELECT * FROM task_steps WHERE task_id = :task_id AND step_number = 4");
            $stmt->execute([':task_id' => $task_id]);
            $step_data = $stmt->fetch();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Step 4: Product Feedback & Refund - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:linear-gradient(135deg,#667eea,#764ba2);min-height:100vh;padding:15px;font-family:-apple-system,sans-serif}
        .container{max-width:550px;margin:0 auto;background:#fff;padding:25px;border-radius:18px;box-shadow:0 10px 40px rgba(0,0,0,0.2)}
        h2{font-size:22px;text-align:center;margin-bottom:20px;color:#333}
        .info-box{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:15px;border-radius:12px;text-align:center;margin-bottom:20px}
        .warning{background:#fff3cd;border-left:4px solid #ffc107;padding:12px;border-radius:8px;margin-bottom:20px;color:#856404;font-size:13px}
        .honest-note{background:#f0fff4;border-left:4px solid #27ae60;padding:12px;border-radius:8px;margin-bottom:20px;color:#155724;font-size:13px}
        .alert{padding:12px;border-radius:8px;margin-bottom:15px;font-size:14px}
        .alert-success{background:#d4edda;color:#155724}
        .alert-danger{background:#f8d7da;color:#721c24}
        .upload-box{border:2px dashed #ddd;border-radius:12px;padding:20px;margin-bottom:20px;background:#fafafa}
        .upload-box.qr{background:#fffbeb;border-color:#f59e0b}
        .upload-box.feedback{background:#f0f4ff;border-color:#667eea}
        .upload-title{font-weight:600;margin-bottom:8px;font-size:15px}
        .upload-desc{font-size:12px;color:#666;margin-bottom:12px}
        .upload-desc strong{color:#d97706}
        input[type="file"]{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:16px}
        textarea{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:14px;resize:vertical;min-height:100px}
        .file-hint{font-size:11px;color:#999;margin-top:6px}
        .preview{margin-top:12px;text-align:center}
        .preview img{max-width:180px;border-radius:10px;border:3px solid #27ae60}
        .btn{width:100%;padding:15px;border:none;border-radius:12px;font-size:16px;font-weight:600;cursor:pointer;background:#27ae60;color:#fff}
        .btn:disabled{background:#ccc;cursor:not-allowed}
        .success-box{background:#d4edda;border:2px solid #27ae60;border-radius:15px;padding:30px;text-align:center;margin-bottom:20px}
        .success-box h3{color:#155724;margin:10px 0}
        .nav-btn{display:block;padding:14px;text-align:center;border-radius:10px;text-decoration:none;font-weight:600;margin-top:12px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .links{text-align:center;margin-top:25px}
        .links a{color:#667eea;text-decoration:none;font-weight:600;margin:0 15px}
        .loading{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;justify-content:center;align-items:center;flex-direction:column}
        .loading.show{display:flex}
        .spinner{width:50px;height:50px;border:4px solid #fff;border-top-color:#667eea;border-radius:50%;animation:spin 1s linear infinite}
        .loading-text{color:#fff;margin-top:15px}
        .star-rating{display:flex;gap:8px;margin-bottom:10px}
        .star-rating input[type=radio]{position:absolute;opacity:0;width:0;height:0}
        .star-rating label{font-size:30px;cursor:pointer;color:#ddd;transition:color 0.2s;outline:none}
        .star-rating label:focus-visible{outline:2px solid #667eea;border-radius:4px}
        .star-rating input[type=radio]:checked ~ label,.star-rating label:hover,.star-rating label:hover ~ label{color:#f59e0b;text-shadow:0 0 3px rgba(245,158,11,0.4)}
        .star-rating input[type=radio]:checked + label{font-weight:bold}
        .star-rating{flex-direction:row-reverse;justify-content:flex-end}
        @keyframes spin{to{transform:rotate(360deg)}}
    </style>
</head>
<body>
    <div class="loading" id="loader"><div class="spinner"></div><div class="loading-text">Uploading...</div></div>
    <div class="container">
        <h2>⭐ Step 4: Product Feedback & Refund</h2>
        
        <?php if ($success): ?>
            <div class="success-box">
                <div style="font-size:50px">🎉</div>
                <h3>Feedback Submitted!</h3>
                <p>Thank you for your honest feedback! Admin will send refund to your QR code.</p>
            </div>
            <a href="<?php echo APP_URL; ?>/user/task-detail.php?task_id=<?php echo $task_id; ?>" class="nav-btn">📋 View Task</a>
            <a href="<?php echo APP_URL; ?>/user/" class="nav-btn" style="background:#27ae60">← Dashboard</a>
        <?php else: ?>
            <div class="info-box"><strong>📌 Task #<?php echo $task_id; ?></strong><br><small>Share your feedback and upload your QR code</small></div>
            <div class="honest-note">💡 Please provide your honest feedback about the product. Your review can be positive or negative — we value genuine opinions.</div>
            <div class="warning">⚠️ Admin will send refund to the QR code you provide. Make sure it's clear!</div>
            
            <?php foreach ($errors as $e): ?><div class="alert alert-danger">✗ <?php echo escape($e); ?></div><?php endforeach; ?>
            
            <form method="POST" enctype="multipart/form-data" id="form">
                <!-- Star Rating -->
                <div class="upload-box feedback">
                    <div class="upload-title">⭐ Product Rating *</div>
                    <div class="upload-desc">Rate the product quality (1 = Poor, 5 = Excellent)</div>
                    <div class="star-rating">
                        <?php $existing_rating = intval($step_data['rating'] ?? 0); ?>
                        <input type="radio" id="star5" name="rating" value="5" <?php echo $existing_rating === 5 ? 'checked' : ''; ?> required aria-label="5 stars - Excellent">
                        <label for="star5" title="5 stars - Excellent">★</label>
                        <input type="radio" id="star4" name="rating" value="4" <?php echo $existing_rating === 4 ? 'checked' : ''; ?> aria-label="4 stars - Good">
                        <label for="star4" title="4 stars - Good">★</label>
                        <input type="radio" id="star3" name="rating" value="3" <?php echo $existing_rating === 3 ? 'checked' : ''; ?> aria-label="3 stars - Average">
                        <label for="star3" title="3 stars - Average">★</label>
                        <input type="radio" id="star2" name="rating" value="2" <?php echo $existing_rating === 2 ? 'checked' : ''; ?> aria-label="2 stars - Poor">
                        <label for="star2" title="2 stars - Poor">★</label>
                        <input type="radio" id="star1" name="rating" value="1" <?php echo $existing_rating === 1 ? 'checked' : ''; ?> aria-label="1 star - Very Poor">
                        <label for="star1" title="1 star - Very Poor">★</label>
                    </div>
                </div>
                
                <!-- Written Review -->
                <div class="upload-box feedback">
                    <div class="upload-title">📝 Product Feedback *</div>
                    <div class="upload-desc">Share your honest experience with the product</div>
                    <textarea name="review_text" placeholder="Write your genuine feedback here (positive or negative)..." required><?php echo escape($step_data['review_text'] ?? ''); ?></textarea>
                </div>
                
                <div class="upload-box">
                    <div class="upload-title">📸 Review Live Screenshot *</div>
                    <div class="upload-desc">Screenshot showing your review is live</div>
                    <input type="file" name="review_screenshot" accept="image/*" <?php echo empty($step_data['review_live_screenshot']) ? 'required' : ''; ?>>
                    <div class="file-hint">Max 5MB</div>
                    <?php if (!empty($step_data['review_live_screenshot'])): ?>
                        <div class="preview"><small>✓ Uploaded</small><br><a href="<?php echo escape($step_data['review_live_screenshot']); ?>" target="_blank">View Screenshot →</a></div>
                    <?php endif; ?>
                </div>
                
                <div class="upload-box qr">
                    <div class="upload-title">📱 Your Payment QR Code *</div>
                    <div class="upload-desc"><strong>UPI/GPay/PhonePe QR</strong> - Admin will scan to send refund</div>
                    <input type="file" name="qr_code" accept="image/*" <?php echo empty($step_data['payment_qr_code']) ? 'required' : ''; ?>>
                    <div class="file-hint">Max 5MB</div>
                    <?php if (!empty($step_data['payment_qr_code'])): ?>
                        <div class="preview"><small>✓ Your QR</small><br><img src="<?php echo escape($step_data['payment_qr_code']); ?>" alt="QR"></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn" id="btn">🔒 Submit Feedback & Request Refund</button>
            </form>
            <div class="links">
                <a href="<?php echo APP_URL; ?>/user/">← Dashboard</a>
                <a href="<?php echo APP_URL; ?>/user/task-detail.php?task_id=<?php echo $task_id; ?>">Task Details →</a>
            </div>
        <?php endif; ?>
    </div>
    <script>
        document.getElementById('form')?.addEventListener('submit',function(){
            document.getElementById('loader').classList.add('show');
            document.getElementById('btn').disabled=true;
        });
    </script>
</body>
</html>
