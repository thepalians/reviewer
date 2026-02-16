<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success = '';
$active_tab = $_GET['tab'] ?? 'profile';

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
    
    // Get user stats
    $user_stats = getUserStats($user_id);
    
    // Get login history
    $stmt = $pdo->prepare("SELECT * FROM login_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $login_history = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Profile Error: " . $e->getMessage());
    $user = [];
    $user_stats = [];
    $login_history = [];
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $mobile = sanitizeInput($_POST['mobile'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $state = sanitizeInput($_POST['state'] ?? '');
    $pincode = sanitizeInput($_POST['pincode'] ?? '');
    
    // Validation
    if (empty($name) || strlen($name) < 3) {
        $errors[] = "Name must be at least 3 characters";
    }
    if (empty($mobile) || !preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $errors[] = "Invalid mobile number";
    }
    if (!empty($pincode) && !preg_match('/^\d{6}$/', $pincode)) {
        $errors[] = "Invalid pincode";
    }
    
    // Check if mobile exists for another user
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? AND id != ?");
            $stmt->execute([$mobile, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = "Mobile number already registered with another account";
            }
        } catch (PDOException $e) {}
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    name = ?, mobile = ?, address = ?, city = ?, state = ?, pincode = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $mobile, $address, $city, $state, $pincode, $user_id]);
            
            $_SESSION['user_name'] = $name;
            $_SESSION['user_mobile'] = $mobile;
            
            $success = "Profile updated successfully!";
            $user['name'] = $name;
            $user['mobile'] = $mobile;
            $user['address'] = $address;
            $user['city'] = $city;
            $user['state'] = $state;
            $user['pincode'] = $pincode;
            $active_tab = 'profile';
            
        } catch (PDOException $e) {
            $errors[] = "Failed to update profile";
            error_log("Profile Update Error: " . $e->getMessage());
        }
    }
}

// Handle payment details update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $upi_id = sanitizeInput($_POST['upi_id'] ?? '');
    $bank_name = sanitizeInput($_POST['bank_name'] ?? '');
    $bank_account = sanitizeInput($_POST['bank_account'] ?? '');
    $bank_ifsc = strtoupper(sanitizeInput($_POST['bank_ifsc'] ?? ''));
    
    // Validation
    if (!empty($upi_id) && !preg_match('/^[\w.-]+@[\w.-]+$/', $upi_id)) {
        $errors[] = "Invalid UPI ID format";
    }
    if (!empty($bank_ifsc) && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $bank_ifsc)) {
        $errors[] = "Invalid IFSC code format";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    upi_id = ?, bank_name = ?, bank_account = ?, bank_ifsc = ?
                WHERE id = ?
            ");
            $stmt->execute([$upi_id, $bank_name, $bank_account, $bank_ifsc, $user_id]);
            
            $success = "Payment details updated successfully!";
            $user['upi_id'] = $upi_id;
            $user['bank_name'] = $bank_name;
            $user['bank_account'] = $bank_account;
            $user['bank_ifsc'] = $bank_ifsc;
            $active_tab = 'payment';
            
        } catch (PDOException $e) {
            $errors[] = "Failed to update payment details";
            error_log("Payment Update Error: " . $e->getMessage());
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password)) {
        $errors[] = "Current password is required";
    }
    if (empty($new_password) || strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters";
    }
    if ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    }
    
    // Verify current password
    if (empty($errors)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $success = "Password changed successfully!";
            $active_tab = 'security';
            
            // Create notification
            createNotification($user_id, 'warning', '🔒 Password Changed', 'Your account password was changed. If this wasn\'t you, please contact support immediately.');
            
        } catch (PDOException $e) {
            $errors[] = "Failed to change password";
            error_log("Password Change Error: " . $e->getMessage());
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        
        // Validation
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed_types)) {
            $errors[] = "Invalid file type. Allowed: JPG, PNG, GIF, WebP";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "File size must be less than 2MB";
        } else {
            // Upload
            $upload_dir = UPLOAD_DIR . 'profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Delete old profile picture
                if (!empty($user['profile_picture'])) {
                    $old_file = UPLOAD_DIR . 'profiles/' . basename($user['profile_picture']);
                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                }
                
                // Update database
                $picture_url = APP_URL . '/uploads/profiles/' . $filename;
                try {
                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt->execute([$picture_url, $user_id]);
                    
                    $user['profile_picture'] = $picture_url;
                    $success = "Profile picture updated!";
                    $active_tab = 'profile';
                    
                } catch (PDOException $e) {
                    $errors[] = "Failed to save profile picture";
                }
            } else {
                $errors[] = "Failed to upload file";
            }
        }
    } else {
        $errors[] = "Please select an image to upload";
    }
}

// Handle account deletion request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirm_email = sanitizeInput($_POST['confirm_email'] ?? '');
    $delete_password = $_POST['delete_password'] ?? '';
    
    if ($confirm_email !== $user['email']) {
        $errors[] = "Email confirmation does not match";
    }
    if (!password_verify($delete_password, $user['password'])) {
        $errors[] = "Incorrect password";
    }
    
    if (empty($errors)) {
        // For now, just mark as inactive instead of deleting
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = 'deleted' WHERE id = ?");
            $stmt->execute([$user_id]);
            
            session_destroy();
            header('Location: ' . APP_URL . '/index.php?deleted=1');
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Failed to delete account";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px}
        
        .container{max-width:900px;margin:0 auto}
        
        .back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fff;color:#333;text-decoration:none;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:14px;transition:transform 0.2s;box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        .back-btn:hover{transform:translateY(-2px)}
        
        /* Profile Header */
        .profile-header{background:#fff;border-radius:20px;padding:30px;margin-bottom:25px;display:flex;align-items:center;gap:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .avatar-section{position:relative}
        .avatar{width:120px;height:120px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:48px;font-weight:700;overflow:hidden;border:4px solid #fff;box-shadow:0 5px 20px rgba(0,0,0,0.2)}
        .avatar img{width:100%;height:100%;object-fit:cover}
        .avatar-edit{position:absolute;bottom:5px;right:5px;width:35px;height:35px;background:#667eea;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:3px solid #fff;font-size:14px}
        .avatar-edit:hover{background:#764ba2}
        .profile-info h1{font-size:26px;color:#333;margin-bottom:5px}
        .profile-info p{color:#666;font-size:14px;margin-bottom:3px}
        .profile-badges{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
        .badge{padding:5px 12px;border-radius:15px;font-size:11px;font-weight:600}
        .badge-level{background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff}
        .badge-verified{background:#d4edda;color:#155724}
        .badge-member{background:#e3f2fd;color:#1565c0}
        
        /* Stats Mini */
        .stats-mini{margin-left:auto;display:grid;grid-template-columns:repeat(3,1fr);gap:15px;text-align:center}
        .stat-mini{padding:10px 20px}
        .stat-mini .value{font-size:22px;font-weight:700;color:#667eea}
        .stat-mini .label{font-size:11px;color:#888}
        
        /* Alerts */
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        
        /* Tab Navigation */
        .tabs{display:flex;gap:5px;background:#fff;padding:8px;border-radius:12px;margin-bottom:20px;box-shadow:0 3px 15px rgba(0,0,0,0.08);overflow-x:auto}
        .tab{padding:12px 20px;background:transparent;border:none;border-radius:8px;cursor:pointer;font-weight:600;font-size:13px;color:#666;transition:all 0.2s;white-space:nowrap}
        .tab:hover{background:#f5f5f5}
        .tab.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        
        /* Cards */
        .card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1);margin-bottom:20px;display:none}
        .card.active{display:block}
        .card-title{font-size:18px;font-weight:600;color:#333;margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid #eee;display:flex;align-items:center;gap:10px}
        
        /* Form Elements */
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#333;font-size:14px}
        .form-group label span{color:#e74c3c}
        .form-control{width:100%;padding:12px 15px;border:2px solid #eee;border-radius:10px;font-size:14px;transition:border-color 0.2s}
        .form-control:focus{border-color:#667eea;outline:none}
        .form-control:disabled{background:#f5f5f5;cursor:not-allowed}
        .form-hint{font-size:12px;color:#888;margin-top:5px}
        
        /* Buttons */
        .btn{padding:12px 25px;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px;transition:all 0.2s;display:inline-flex;align-items:center;justify-content:center;gap:8px}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(102,126,234,0.4)}
        .btn-success{background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff}
        .btn-danger{background:#e74c3c;color:#fff}
        .btn-secondary{background:#f5f5f5;color:#666}
        .btn-block{width:100%}
        
        /* Login History */
        .login-item{display:flex;align-items:center;padding:15px 0;border-bottom:1px solid #f5f5f5}
        .login-item:last-child{border-bottom:none}
        .login-icon{width:45px;height:45px;background:#f5f5f5;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;margin-right:15px}
        .login-icon.success{background:#d4edda;color:#27ae60}
        .login-icon.failed{background:#f8d7da;color:#e74c3c}
        .login-info{flex:1}
        .login-device{font-weight:600;color:#333;font-size:14px}
        .login-details{font-size:12px;color:#888;margin-top:2px}
        .login-time{font-size:12px;color:#888;text-align:right}
        
        /* Danger Zone */
        .danger-zone{background:#fff5f5;border:2px solid #fee;border-radius:12px;padding:20px;margin-top:20px}
        .danger-zone h4{color:#e74c3c;margin-bottom:10px;font-size:16px}
        .danger-zone p{font-size:13px;color:#666;margin-bottom:15px}
        
        /* Modal */
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;padding:20px}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:15px;padding:30px;max-width:450px;width:100%;max-height:90vh;overflow-y:auto}
        .modal-title{font-size:20px;font-weight:600;margin-bottom:20px;color:#333}
        .modal-close{float:right;font-size:24px;cursor:pointer;color:#999;line-height:1}
        .modal-close:hover{color:#333}
        
        /* Responsive */
        @media(max-width:768px){
            .profile-header{flex-direction:column;text-align:center}
            .stats-mini{margin-left:0;margin-top:20px;width:100%}
            .form-row{grid-template-columns:1fr}
            .tabs{flex-wrap:nowrap;overflow-x:auto;-webkit-overflow-scrolling:touch}
            .tab{flex-shrink:0}
        }
    </style>
</head>
<body>
<div class="container">
    <a href="<?php echo APP_URL; ?>/user/" class="back-btn">← Back to Dashboard</a>
    
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="avatar-section">
            <div class="avatar">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?php echo escape($user['profile_picture']); ?>" alt="Profile">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <label for="avatarInput" class="avatar-edit" title="Change Photo">📷</label>
            <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display:none">
                <input type="file" name="profile_picture" id="avatarInput" accept="image/*" onchange="document.getElementById('avatarForm').submit()">
                <input type="hidden" name="upload_picture" value="1">
            </form>
        </div>
        <div class="profile-info">
            <h1><?php echo escape($user['name']); ?></h1>
            <p>📧 <?php echo escape($user['email']); ?></p>
            <p>📱 <?php echo escape($user['mobile']); ?></p>
            <div class="profile-badges">
                <span class="badge badge-level">⭐ Level <?php echo $user_stats['level'] ?? 1; ?></span>
                <?php if ($user['status'] === 'active'): ?>
                    <span class="badge badge-verified">✓ Verified</span>
                <?php endif; ?>
                <span class="badge badge-member">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
            </div>
        </div>
        <div class="stats-mini">
            <div class="stat-mini">
                <div class="value"><?php echo $user_stats['tasks_completed'] ?? 0; ?></div>
                <div class="label">Tasks</div>
            </div>
            <div class="stat-mini">
                <div class="value">⭐<?php echo number_format($user_stats['rating'] ?? 5, 1); ?></div>
                <div class="label">Rating</div>
            </div>
            <div class="stat-mini">
                <div class="value"><?php echo $user['login_count'] ?? 0; ?></div>
                <div class="label">Logins</div>
            </div>
        </div>
    </div>
    
    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
    <?php endforeach; ?>
    
    <!-- Tabs -->
    <div class="tabs">
        <button class="tab <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" onclick="showTab('profile')">👤 Profile</button>
        <button class="tab <?php echo $active_tab === 'payment' ? 'active' : ''; ?>" onclick="showTab('payment')">💳 Payment</button>
        <button class="tab <?php echo $active_tab === 'security' ? 'active' : ''; ?>" onclick="showTab('security')">🔒 Security</button>
        <button class="tab <?php echo $active_tab === 'activity' ? 'active' : ''; ?>" onclick="showTab('activity')">📊 Activity</button>
    </div>
    
    <!-- Profile Tab -->
    <div class="card <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" id="profileTab">
        <div class="card-title">👤 Personal Information</div>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name <span>*</span></label>
                    <input type="text" name="name" class="form-control" value="<?php echo escape($user['name']); ?>" required minlength="3">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-control" value="<?php echo escape($user['email']); ?>" disabled>
                    <div class="form-hint">Email cannot be changed</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Mobile Number <span>*</span></label>
                    <input type="text" name="mobile" class="form-control" value="<?php echo escape($user['mobile']); ?>" required pattern="[6-9]\d{9}" maxlength="10">
                    <div class="form-hint">10-digit mobile number</div>
                </div>
                <div class="form-group">
                    <label>Referral Code</label>
                    <input type="text" class="form-control" value="<?php echo escape($user['referral_code'] ?? getReferralCode($user_id)); ?>" disabled>
                </div>
            </div>
            
            <div class="card-title" style="margin-top:30px">📍 Address Details</div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" class="form-control" value="<?php echo escape($user['address'] ?? ''); ?>" placeholder="Street address, building, apartment">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" class="form-control" value="<?php echo escape($user['city'] ?? ''); ?>" placeholder="City">
                </div>
                <div class="form-group">
                    <label>State</label>
                    <select name="state" class="form-control">
                        <option value="">Select State</option>
                        <?php
                        $states = ['Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Delhi', 'Jammu and Kashmir', 'Ladakh'];
                        foreach ($states as $state): ?>
                            <option value="<?php echo $state; ?>" <?php echo ($user['state'] ?? '') === $state ? 'selected' : ''; ?>><?php echo $state; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?php echo escape($user['pincode'] ?? ''); ?>" placeholder="6-digit pincode" maxlength="6" pattern="\d{6}">
                </div>
                <div class="form-group"></div>
            </div>
            
            <button type="submit" name="update_profile" class="btn btn-primary">💾 Save Changes</button>
        </form>
    </div>
    
    <!-- Payment Tab -->
    <div class="card <?php echo $active_tab === 'payment' ? 'active' : ''; ?>" id="paymentTab">
        <div class="card-title">💳 Payment Details</div>
        <p style="color:#666;font-size:13px;margin-bottom:20px">Save your payment details for faster withdrawals. This information is encrypted and secure.</p>
        
        <form method="POST">
            <div class="card-title" style="font-size:16px;border:none;padding:0;margin-bottom:15px">📱 UPI Details</div>
            <div class="form-group">
                <label>UPI ID</label>
                <input type="text" name="upi_id" class="form-control" value="<?php echo escape($user['upi_id'] ?? ''); ?>" placeholder="example@paytm, example@upi">
                <div class="form-hint">Enter your UPI ID for instant payments</div>
            </div>
            
            <div class="card-title" style="font-size:16px;border:none;padding:0;margin:30px 0 15px">🏦 Bank Details</div>
            <div class="form-group">
                <label>Bank Name</label>
                <input type="text" name="bank_name" class="form-control" value="<?php echo escape($user['bank_name'] ?? ''); ?>" placeholder="e.g., State Bank of India">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="bank_account" class="form-control" value="<?php echo escape($user['bank_account'] ?? ''); ?>" placeholder="Enter account number">
                </div>
                <div class="form-group">
                    <label>IFSC Code</label>
                    <input type="text" name="bank_ifsc" class="form-control" value="<?php echo escape($user['bank_ifsc'] ?? ''); ?>" placeholder="e.g., SBIN0001234" style="text-transform:uppercase" maxlength="11">
                    <div class="form-hint">11-character IFSC code</div>
                </div>
            </div>
            
            <button type="submit" name="update_payment" class="btn btn-success">💾 Save Payment Details</button>
        </form>
    </div>
    
    <!-- Security Tab -->
    <div class="card <?php echo $active_tab === 'security' ? 'active' : ''; ?>" id="securityTab">
        <div class="card-title">🔒 Security Settings</div>
        
        <!-- Change Password -->
        <form method="POST">
            <h4 style="font-size:16px;color:#333;margin-bottom:15px">Change Password</h4>
            <div class="form-group">
                <label>Current Password <span>*</span></label>
                <input type="password" name="current_password" class="form-control" required placeholder="Enter current password">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>New Password <span>*</span></label>
                    <input type="password" name="new_password" class="form-control" required minlength="8" placeholder="Enter new password">
                    <div class="form-hint">Minimum 8 characters</div>
                </div>
                <div class="form-group">
                    <label>Confirm New Password <span>*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="8" placeholder="Confirm new password">
                </div>
            </div>
            <button type="submit" name="change_password" class="btn btn-primary">🔐 Change Password</button>
        </form>
        
        <!-- Login History -->
        <div class="card-title" style="margin-top:40px">📜 Recent Login Activity</div>
        <?php if (empty($login_history)): ?>
            <p style="color:#888;text-align:center;padding:20px">No login history available</p>
        <?php else: ?>
            <?php foreach ($login_history as $login): ?>
                <div class="login-item">
                    <div class="login-icon <?php echo $login['status']; ?>">
                        <?php echo $login['device_type'] === 'mobile' ? '📱' : ($login['device_type'] === 'tablet' ? '📱' : '💻'); ?>
                    </div>
                    <div class="login-info">
                        <div class="login-device"><?php echo ucfirst($login['device_type']); ?> - <?php echo $login['status'] === 'success' ? 'Successful Login' : 'Failed Attempt'; ?></div>
                        <div class="login-details">IP: <?php echo escape($login['ip_address']); ?></div>
                    </div>
                    <div class="login-time"><?php echo date('d M Y, H:i', strtotime($login['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Danger Zone -->
        <div class="danger-zone">
            <h4>⚠️ Danger Zone</h4>
            <p>Once you delete your account, there is no going back. Please be certain.</p>
            <button class="btn btn-danger" onclick="showDeleteModal()">🗑️ Delete Account</button>
        </div>
    </div>
    
    <!-- Activity Tab -->
    <div class="card <?php echo $active_tab === 'activity' ? 'active' : ''; ?>" id="activityTab">
        <div class="card-title">📊 Account Activity</div>
        
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:15px;margin-bottom:25px">
            <div style="background:#f8f9fa;padding:20px;border-radius:12px;text-align:center">
                <div style="font-size:32px;font-weight:700;color:#667eea"><?php echo $user_stats['tasks_completed'] ?? 0; ?></div>
                <div style="font-size:13px;color:#666">Tasks Completed</div>
            </div>
            <div style="background:#f8f9fa;padding:20px;border-radius:12px;text-align:center">
                <div style="font-size:32px;font-weight:700;color:#f39c12"><?php echo $user_stats['tasks_pending'] ?? 0; ?></div>
                <div style="font-size:13px;color:#666">Tasks Pending</div>
            </div>
            <div style="background:#f8f9fa;padding:20px;border-radius:12px;text-align:center">
                <div style="font-size:32px;font-weight:700;color:#27ae60">₹<?php echo number_format($user_stats['total_earnings'] ?? 0, 0); ?></div>
                <div style="font-size:13px;color:#666">Total Earnings</div>
            </div>
            <div style="background:#f8f9fa;padding:20px;border-radius:12px;text-align:center">
                <div style="font-size:32px;font-weight:700;color:#e74c3c"><?php echo $user_stats['streak_days'] ?? 0; ?></div>
                <div style="font-size:13px;color:#666">Day Streak</div>
            </div>
        </div>
        
        <div class="card-title">📅 Account Details</div>
        <div style="font-size:14px;color:#666;line-height:2">
            <p><strong>Account Created:</strong> <?php echo date('d M Y, H:i', strtotime($user['created_at'])); ?></p>
            <p><strong>Last Login:</strong> <?php echo $user['last_login'] ? date('d M Y, H:i', strtotime($user['last_login'])) : 'N/A'; ?></p>
            <p><strong>Total Logins:</strong> <?php echo $user['login_count'] ?? 0; ?></p>
            <p><strong>Account Status:</strong> <span style="color:#27ae60;font-weight:600"><?php echo ucfirst($user['status']); ?></span></p>
            <p><strong>User ID:</strong> #<?php echo $user_id; ?></p>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <span class="modal-close" onclick="hideDeleteModal()">&times;</span>
        <div class="modal-title">⚠️ Delete Account</div>
        <p style="color:#666;margin-bottom:20px;font-size:14px">This action cannot be undone. This will permanently delete your account, tasks, and all associated data.</p>
        
        <form method="POST">
            <div class="form-group">
                <label>Type your email to confirm</label>
                <input type="email" name="confirm_email" class="form-control" placeholder="<?php echo escape($user['email']); ?>" required>
            </div>
            <div class="form-group">
                <label>Enter your password</label>
                <input type="password" name="delete_password" class="form-control" placeholder="Your password" required>
            </div>
            <button type="submit" name="delete_account" class="btn btn-danger btn-block">🗑️ Permanently Delete My Account</button>
        </form>
    </div>
</div>

<script>
// Tab switching
function showTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.card').forEach(c => c.classList.remove('active'));
    
    document.querySelector(`.tab:nth-child(${['profile','payment','security','activity'].indexOf(tab)+1})`).classList.add('active');
    document.getElementById(tab + 'Tab').classList.add('active');
    
    // Update URL
    history.replaceState(null, '', '?tab=' + tab);
}

// Delete modal
function showDeleteModal() {
    document.getElementById('deleteModal').classList.add('show');
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
}

// Close modal on outside click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) hideDeleteModal();
});

// Avatar preview
document.getElementById('avatarInput')?.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const file = this.files[0];
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            this.value = '';
            return;
        }
        // Submit form
        if (confirm('Upload this profile picture?')) {
            document.getElementById('avatarForm').submit();
        } else {
            this.value = '';
        }
    }
});
</script>
</body>
</html>
