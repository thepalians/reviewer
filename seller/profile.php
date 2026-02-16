<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

$error = '';
$success = '';

// Get seller profile
$stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
$stmt->execute([$seller_id]);
$profile = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $gst_number = strtoupper(trim($_POST['gst_number'] ?? ''));
    $billing_address = trim($_POST['billing_address'] ?? '');
    
    if (empty($name) || empty($mobile) || empty($billing_address)) {
        $error = 'Please fill all required fields.';
    } elseif (strlen($mobile) !== 10) {
        $error = 'Please enter a valid 10-digit mobile number.';
    } else {
        try {
            // Check if mobile is used by another seller
            $stmt = $pdo->prepare("SELECT id FROM sellers WHERE mobile = ? AND id != ?");
            $stmt->execute([$mobile, $seller_id]);
            if ($stmt->fetch()) {
                $error = 'Mobile number is already used by another account.';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE sellers 
                    SET name = ?, mobile = ?, company_name = ?, gst_number = ?, billing_address = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $mobile, $company_name, $gst_number ?: null, $billing_address, $seller_id]);
                
                $success = 'Profile updated successfully!';
                
                // Refresh profile data
                $stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
                $stmt->execute([$seller_id]);
                $profile = $stmt->fetch();
            }
        } catch (PDOException $e) {
            error_log('Profile update error: ' . $e->getMessage());
            $error = 'Failed to update profile. Please try again.';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Please fill all password fields.';
    } elseif (!password_verify($current_password, $profile['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS);
            $stmt = $pdo->prepare("UPDATE sellers SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $seller_id]);
            
            $success = 'Password changed successfully!';
        } catch (PDOException $e) {
            error_log('Password change error: ' . $e->getMessage());
            $error = 'Failed to change password. Please try again.';
        }
    }
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Profile</li>
                </ol>
            </nav>
            <h3 class="mb-0">Seller Profile</h3>
            <p class="text-muted">Manage your account information and settings</p>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i>
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Profile Information -->
            <div class="card mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-person-circle"></i> Profile Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?= htmlspecialchars($profile['name']) ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" 
                                       value="<?= htmlspecialchars($profile['email']) ?>" disabled>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <input type="tel" name="mobile" class="form-control" 
                                   value="<?= htmlspecialchars($profile['mobile']) ?>" 
                                   pattern="[0-9]{10}" maxlength="10" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" 
                                   value="<?= htmlspecialchars($profile['company_name'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">GST Number</label>
                            <input type="text" name="gst_number" class="form-control" 
                                   value="<?= htmlspecialchars($profile['gst_number'] ?? '') ?>" 
                                   maxlength="15" placeholder="22AAAAA0000A1Z5">
                            <small class="text-muted">Optional - 15 digit GSTIN</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Billing Address <span class="text-danger">*</span></label>
                            <textarea name="billing_address" class="form-control" rows="3" required><?= htmlspecialchars($profile['billing_address']) ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" 
                                   minlength="6" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="bi bi-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Account Status -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3"><i class="bi bi-info-circle"></i> Account Status</h6>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Account Status:</span>
                            <span class="badge bg-<?= $profile['status'] === 'active' ? 'success' : 'danger' ?>">
                                <?= ucfirst($profile['status']) ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Email Verified:</span>
                            <span class="badge bg-<?= $profile['email_verified'] ? 'success' : 'warning' ?>">
                                <?= $profile['email_verified'] ? 'Yes' : 'No' ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Member Since:</span>
                            <span><?= date('M Y', strtotime($profile['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Help & Support -->
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3"><i class="bi bi-question-circle"></i> Help & Support</h6>
                    
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-chat-dots"></i> Contact Support
                        </a>
                        <a href="#" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-book"></i> View Documentation
                        </a>
                        <a href="#" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash"></i> Delete Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
