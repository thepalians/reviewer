<?php
/**
 * ReviewFlow - Password Reset Page
 * Handles password reset via email token
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/user/');
    exit;
}

$errors = [];
$success = '';
$valid_token = false;
$user = null;

// Get token from URL
$token = sanitizeInput($_GET['token'] ?? '');

if (empty($token)) {
    $errors[] = "Invalid or missing reset token.";
} else {
    // Validate token
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, email, reset_token, reset_expires 
            FROM users 
            WHERE reset_token = ? AND reset_expires > NOW() AND user_type = 'user'
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $valid_token = true;
        } else {
            $errors[] = "This reset link has expired or is invalid. Please request a new one.";
        }
    } catch (PDOException $e) {
        error_log("Reset Token Error: " . $e->getMessage());
        $errors[] = "An error occurred. Please try again.";
    }
}

// Handle password reset form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = sanitizeInput($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate token again
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, email, reset_token, reset_expires 
            FROM users 
            WHERE reset_token = ? AND reset_expires > NOW() AND user_type = 'user'
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $errors[] = "This reset link has expired. Please request a new one.";
            $valid_token = false;
        }
    } catch (PDOException $e) {
        $errors[] = "An error occurred. Please try again.";
    }
    
    // Validate password
    if (empty($errors)) {
        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        } elseif ($password !== $confirm_password) {
            $errors[] = "Passwords do not match";
        }
    }
    
    // Update password
    if (empty($errors) && $user) {
        try {
            $pdo->beginTransaction();
            
            // Hash new password
            $hashed_password = hashPassword($password);
            
            // Update user
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, reset_token = NULL, reset_expires = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$hashed_password, $user['id']]);
            
            // Create notification
            createNotification(
                $user['id'],
                'success',
                '🔐 Password Reset Successful',
                'Your password has been changed successfully. You can now login with your new password.'
            );
            
            // Log activity
            logActivity("Password reset for user: {$user['email']}", null, $user['id']);
            
            // Send confirmation email
            $emailBody = getEmailTemplate(
                '🔐 Password Changed Successfully',
                "Your password has been changed successfully. If you didn't make this change, please contact support immediately.",
                $user['name']
            );
            sendEmail($user['email'], 'Password Changed - ' . APP_NAME, $emailBody, $user['id']);
            
            $pdo->commit();
            
            $success = "Password reset successful! You can now login with your new password.";
            $valid_token = false; // Hide form after success
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Password Reset Error: " . $e->getMessage());
            $errors[] = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#667eea">
    <meta name="robots" content="noindex, nofollow">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 35px;
            margin: 0 auto 15px;
        }
        
        .logo h1 {
            font-size: 24px;
            color: #333;
            font-weight: 700;
        }
        
        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: #333;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            font-size: 14px;
            color: #666;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .alert {
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-info {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }
        
        .form-group label span {
            color: #e74c3c;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            opacity: 0.5;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 18px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
            opacity: 0.5;
            user-select: none;
        }
        
        .password-toggle:hover {
            opacity: 1;
        }
        
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-bar {
            height: 5px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
            border-radius: 3px;
        }
        
        .strength-fill.weak { width: 33%; background: #ef4444; }
        .strength-fill.medium { width: 66%; background: #f59e0b; }
        .strength-fill.strong { width: 100%; background: #10b981; }
        
        .strength-text {
            font-size: 12px;
            color: #666;
        }
        
        .password-requirements {
            margin-top: 10px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 12px;
        }
        
        .password-requirements ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .password-requirements li {
            padding: 4px 0;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .password-requirements li.valid {
            color: #10b981;
        }
        
        .password-requirements li.invalid {
            color: #94a3b8;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            margin-top: 15px;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        /* Success animation */
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
            animation: scaleIn 0.5s ease;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        /* Expired state */
        .expired-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
        }
        
        @media (max-width: 480px) {
            .reset-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
<div class="reset-container">
    <div class="logo">
        <div class="logo-icon">🔐</div>
        <h1><?php echo APP_NAME; ?></h1>
    </div>
    
    <?php if ($success): ?>
        <!-- Success State -->
        <div class="success-icon">✓</div>
        <div class="page-title">Password Reset Complete!</div>
        <div class="page-subtitle"><?php echo $success; ?></div>
        <a href="<?php echo APP_URL; ?>/index.php" class="btn btn-primary">
            🔐 Login Now
        </a>
    <?php elseif ($valid_token && $user): ?>
        <!-- Reset Form -->
        <div class="page-title">Create New Password</div>
        <div class="page-subtitle">Enter a new password for <strong><?php echo escape($user['email']); ?></strong></div>
        
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
        <?php endforeach; ?>
        
        <form method="POST" id="resetForm">
            <input type="hidden" name="token" value="<?php echo escape($token); ?>">
            
            <div class="form-group">
                <label>New Password <span>*</span></label>
                <div class="input-wrapper">
                    <span class="icon">🔒</span>
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="Enter new password" required minlength="8" autocomplete="new-password">
                    <span class="password-toggle" onclick="togglePassword('password')">👁️</span>
                </div>
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Enter a password</div>
                </div>
                <div class="password-requirements">
                    <ul>
                        <li id="req-length" class="invalid">✗ At least 8 characters</li>
                        <li id="req-upper" class="invalid">✗ One uppercase letter</li>
                        <li id="req-lower" class="invalid">✗ One lowercase letter</li>
                        <li id="req-number" class="invalid">✗ One number</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label>Confirm Password <span>*</span></label>
                <div class="input-wrapper">
                    <span class="icon">🔒</span>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                           placeholder="Confirm new password" required minlength="8" autocomplete="new-password">
                    <span class="password-toggle" onclick="togglePassword('confirm_password')">👁️</span>
                </div>
                <div id="matchMessage" style="font-size:12px;margin-top:8px;"></div>
            </div>
            
            <button type="submit" name="reset_password" class="btn btn-primary" id="submitBtn" disabled>
                🔐 Reset Password
            </button>
        </form>
        
        <a href="<?php echo APP_URL; ?>/index.php" class="back-link">← Back to Login</a>
    <?php else: ?>
        <!-- Expired/Invalid State -->
        <div class="expired-icon">⏰</div>
        <div class="page-title">Link Expired</div>
        <div class="page-subtitle">This password reset link has expired or is invalid.</div>
        
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
        <?php endforeach; ?>
        
        <div class="alert alert-info">
            💡 Password reset links expire after 1 hour for security reasons. Please request a new link.
        </div>
        
        <a href="<?php echo APP_URL; ?>/index.php" class="btn btn-primary">
            🔐 Back to Login
        </a>
        
        <button onclick="showForgotForm()" class="btn btn-secondary">
            📧 Request New Reset Link
        </button>
        
        <!-- Forgot Password Form (Hidden) -->
        <div id="forgotForm" style="display:none;margin-top:20px;padding-top:20px;border-top:1px solid #e2e8f0;">
            <form method="POST" action="<?php echo APP_URL; ?>/index.php">
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <span class="icon">📧</span>
                        <input type="email" name="forgot_email" class="form-control" placeholder="Enter your email" required>
                    </div>
                </div>
                <button type="submit" name="forgot_password" class="btn btn-primary">
                    📧 Send Reset Link
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
// Toggle password visibility
function togglePassword(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Show forgot form
function showForgotForm() {
    document.getElementById('forgotForm').style.display = 'block';
}

// Password validation
const password = document.getElementById('password');
const confirmPassword = document.getElementById('confirm_password');
const submitBtn = document.getElementById('submitBtn');
const strengthFill = document.getElementById('strengthFill');
const strengthText = document.getElementById('strengthText');
const matchMessage = document.getElementById('matchMessage');

if (password) {
    password.addEventListener('input', function() {
        const value = this.value;
        let strength = 0;
        let requirements = {
            length: value.length >= 8,
            upper: /[A-Z]/.test(value),
            lower: /[a-z]/.test(value),
            number: /[0-9]/.test(value)
        };
        
        // Update requirement indicators
        document.getElementById('req-length').className = requirements.length ? 'valid' : 'invalid';
        document.getElementById('req-length').innerHTML = (requirements.length ? '✓' : '✗') + ' At least 8 characters';
        
        document.getElementById('req-upper').className = requirements.upper ? 'valid' : 'invalid';
        document.getElementById('req-upper').innerHTML = (requirements.upper ? '✓' : '✗') + ' One uppercase letter';
        
        document.getElementById('req-lower').className = requirements.lower ? 'valid' : 'invalid';
        document.getElementById('req-lower').innerHTML = (requirements.lower ? '✓' : '✗') + ' One lowercase letter';
        
        document.getElementById('req-number').className = requirements.number ? 'valid' : 'invalid';
        document.getElementById('req-number').innerHTML = (requirements.number ? '✓' : '✗') + ' One number';
        
        // Calculate strength
        if (requirements.length) strength++;
        if (requirements.upper) strength++;
        if (requirements.lower) strength++;
        if (requirements.number) strength++;
        if (value.length >= 12) strength++;
        if (/[!@#$%^&*(),.?":{}|<>]/.test(value)) strength++;
        
        // Update strength bar
        strengthFill.className = 'strength-fill';
        if (strength <= 2) {
            strengthFill.classList.add('weak');
            strengthText.textContent = 'Weak password';
            strengthText.style.color = '#ef4444';
        } else if (strength <= 4) {
            strengthFill.classList.add('medium');
            strengthText.textContent = 'Medium password';
            strengthText.style.color = '#f59e0b';
        } else {
            strengthFill.classList.add('strong');
            strengthText.textContent = 'Strong password';
            strengthText.style.color = '#10b981';
        }
        
        validateForm();
    });
    
    confirmPassword.addEventListener('input', function() {
        validateForm();
    });
}

function validateForm() {
    const pass = password.value;
    const confirm = confirmPassword.value;
    
    // Check password requirements
    const isValid = pass.length >= 8;
    
    // Check match
    if (confirm.length > 0) {
        if (pass === confirm) {
            matchMessage.textContent = '✓ Passwords match';
            matchMessage.style.color = '#10b981';
        } else {
            matchMessage.textContent = '✗ Passwords do not match';
            matchMessage.style.color = '#ef4444';
        }
    } else {
        matchMessage.textContent = '';
    }
    
    // Enable/disable submit button
    submitBtn.disabled = !(isValid && pass === confirm && confirm.length > 0);
}

// Form submit validation
document.getElementById('resetForm')?.addEventListener('submit', function(e) {
    const pass = password.value;
    const confirm = confirmPassword.value;
    
    if (pass.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters');
        return;
    }
    
    if (pass !== confirm) {
        e.preventDefault();
        alert('Passwords do not match');
        return;
    }
    
    // Disable button to prevent double submit
    submitBtn.disabled = true;
    submitBtn.innerHTML = '⏳ Resetting...';
});
</script>
</body>
</html>
