<?php
/**
 * ReviewFlow - Login & Landing Page
 * With referral code support
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';

// Check maintenance mode
if (isMaintenanceMode() && !isset($_SESSION['admin_name'])) {
    include __DIR__ . '/maintenance.php';
    exit;
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/user/');
    exit;
}

if (isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL . '/dashboard.php');
    exit;
}

// Get referral code from URL
$referral_code = sanitizeInput($_GET['ref'] ?? '');
if ($referral_code) {
    $_SESSION['referral_code'] = $referral_code;
}

$errors = [];
$success = '';
$show_form = $_GET['form'] ?? 'login'; // login or register

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Rate limiting
    if (!checkRateLimit('login_' . $email, 5, 15)) {
        $errors[] = "Too many login attempts. Please try again in 15 minutes.";
    } else {
        // Validation
        if (empty($email) || !isValidEmail($email)) {
            $errors[] = "Please enter a valid email address";
        }
        if (empty($password)) {
            $errors[] = "Please enter your password";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND user_type = 'user'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && verifyPassword($password, $user['password'])) {
                    // Check if account is active
                    if ($user['status'] !== 'active') {
                        $errors[] = "Your account is " . $user['status'] . ". Please contact support.";
                    } else {
                        // Clear rate limit
                        clearRateLimit('login_' . $email);
                        
                        // Set session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_mobile'] = $user['mobile'];
                        
                        // Log login
                        logLoginAttempt($user['id'], 'success');
                        
                        // Remember me
                        if ($remember) {
                            $token = generateRandomString(64);
                            $expires = time() + (30 * 24 * 60 * 60); // 30 days
                            
                            $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, remember_expires = FROM_UNIXTIME(?) WHERE id = ?");
                            $stmt->execute([$token, $expires, $user['id']]);
                            
                            setcookie('remember_token', $token, $expires, '/', '', true, true);
                            setcookie('remember_user', (string)$user['id'], $expires, '/', '', true, true);
                        }
                        
                        // Create wallet if not exists
                        createWallet($user['id']);
                        
                        // Update stats
                        updateUserStats($user['id']);
                        
                        // Redirect
                        header('Location: ' . APP_URL . '/user/');
                        exit;
                    }
                } else {
                    $errors[] = "Invalid email or password";
                    
                    // Log failed attempt if user exists
                    if ($user) {
                        logLoginAttempt($user['id'], 'failed');
                    }
                }
            } catch (PDOException $e) {
                error_log("Login Error: " . $e->getMessage());
                $errors[] = "An error occurred. Please try again.";
            }
        }
    }
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $mobile = sanitizeInput($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $referral_code = sanitizeInput($_POST['referral_code'] ?? $_SESSION['referral_code'] ?? '');
    $terms = isset($_POST['terms']);
    
    // Check if registration is enabled
    if (!isRegistrationEnabled()) {
        $errors[] = "Registration is currently closed. Please try again later.";
    }
    
    // Rate limiting
    if (!checkRateLimit('register_' . $_SERVER['REMOTE_ADDR'], 3, 60)) {
        $errors[] = "Too many registration attempts. Please try again later.";
    }
    
    // Validation
    if (empty($name) || strlen($name) < 3) {
        $errors[] = "Name must be at least 3 characters";
    }
    if (empty($email) || !isValidEmail($email)) {
        $errors[] = "Please enter a valid email address";
    }
    if (empty($mobile) || !isValidPhone($mobile)) {
        $errors[] = "Please enter a valid 10-digit mobile number";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    if (!$terms) {
        $errors[] = "You must agree to the terms and conditions";
    }
    
    // Check if email exists
    if (empty($errors) && userExists($email)) {
        $errors[] = "Email already registered. Please login or use a different email.";
    }
    
    // Check if mobile exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE mobile = ?");
            $stmt->execute([$mobile]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Mobile number already registered";
            }
        } catch (PDOException $e) {}
    }
    
    // Create user
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Hash password
            $hashed_password = hashPassword($password);
            
            // Generate referral code
            $user_referral_code = 'RF' . strtoupper(substr(md5(time() . $email . random_bytes(4)), 0, 6));
            
            // Insert user
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, mobile, password, referral_code, user_type, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'user', 'active', NOW())
            ");
            $stmt->execute([$name, $email, $mobile, $hashed_password, $user_referral_code]);
            $user_id = (int)$pdo->lastInsertId();
            
            // Create wallet
            createWallet($user_id);
            
            // Create user stats
            $stmt = $pdo->prepare("INSERT INTO user_stats (user_id) VALUES (?)");
            $stmt->execute([$user_id]);
            
            // Process referral
            if (!empty($referral_code)) {
                processReferral($user_id, $referral_code);
            }
            
            // Welcome notification
            createNotification($user_id, 'success', '🎉 Welcome to ' . APP_NAME . '!', 'Your account has been created successfully. Complete tasks to earn rewards!');
            
            // First task bonus notification
            $first_bonus = getSetting('first_task_bonus', 25);
            if ($first_bonus > 0) {
                createNotification($user_id, 'info', '💰 First Task Bonus', "Complete your first task to earn a bonus of ₹$first_bonus!");
            }
            
            // Send welcome email
            $emailBody = getEmailTemplate(
                '🎉 Welcome to ' . APP_NAME . '!',
                "Your account has been created successfully. You can now login and start completing tasks to earn rewards.",
                $name
            );
            sendEmail($email, 'Welcome to ' . APP_NAME, $emailBody, $user_id);
            
            // Log activity
            logActivity("New user registered: $email", null, $user_id);
            
            $pdo->commit();
            
            // Clear referral from session
            unset($_SESSION['referral_code']);
            
            // Auto login
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_mobile'] = $mobile;
            
            logLoginAttempt($user_id, 'success');
            
            header('Location: ' . APP_URL . '/user/?welcome=1');
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Registration Error: " . $e->getMessage());
            $errors[] = "Registration failed. Please try again.";
        }
    }
    
    $show_form = 'register';
}

// Handle Forgot Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = sanitizeInput($_POST['forgot_email'] ?? '');
    
    if (empty($email) || !isValidEmail($email)) {
        $errors[] = "Please enter a valid email address";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND user_type = 'user'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $token = generateRandomString(64);
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires, $user['id']]);
                
                // Send reset email
                $reset_link = APP_URL . "/reset-password.php?token=$token";
                $emailBody = getEmailTemplate(
                    '🔐 Password Reset Request',
                    "You requested to reset your password. Click the link below to reset:<br><br><a href='$reset_link' style='background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:12px 30px;text-decoration:none;border-radius:5px;display:inline-block'>Reset Password</a><br><br>This link expires in 1 hour. If you didn't request this, please ignore this email.",
                    $user['name']
                );
                sendEmail($email, 'Password Reset - ' . APP_NAME, $emailBody, $user['id']);
                
                $success = "Password reset link sent to your email!";
            } else {
                // Don't reveal if email exists or not
                $success = "If this email exists, you'll receive a reset link shortly.";
            }
        } catch (PDOException $e) {
            error_log("Forgot Password Error: " . $e->getMessage());
            $errors[] = "An error occurred. Please try again.";
        }
    }
}

// Check for remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token']) && isset($_COOKIE['remember_user'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND remember_token = ? AND remember_expires > NOW()");
        $stmt->execute([$_COOKIE['remember_user'], $_COOKIE['remember_token']]);
        $user = $stmt->fetch();
        
        if ($user && $user['status'] === 'active') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_mobile'] = $user['mobile'];
            
            logLoginAttempt($user['id'], 'success');
            
            header('Location: ' . APP_URL . '/user/');
            exit;
        }
    } catch (PDOException $e) {}
}

// Get stats for display
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'user'");
    $total_users = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'completed'");
    $total_tasks = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = 'completed'");
    $total_paid = (float)$stmt->fetchColumn();
} catch (PDOException $e) {
    $total_users = 1000;
    $total_tasks = 5000;
    $total_paid = 100000;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <meta name="description" content="<?php echo APP_NAME; ?> - Complete tasks, earn rewards, and withdraw money easily">
    <meta name="keywords" content="earn money, tasks, rewards, referral, work from home">
    <meta name="author" content="<?php echo APP_NAME; ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo APP_NAME; ?> - Earn Money Online">
    <meta property="og:description" content="Complete simple tasks and earn real money. Withdraw anytime!">
    <meta property="og:image" content="<?php echo APP_URL; ?>/assets/img/og-image.png">
    <meta property="og:url" content="<?php echo APP_URL; ?>">
    <meta property="og:type" content="website">
    
    <!-- PWA -->
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/img/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <title><?php echo APP_NAME; ?> - Earn Money Online</title>
    
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
        
        .container{width:100%;max-width:1100px;display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:center}
        
        /* Left Side - Info */
        .info-section{color:#fff;padding:20px}
        .logo{font-size:36px;font-weight:800;margin-bottom:20px;display:flex;align-items:center;gap:12px}
        .logo-icon{width:60px;height:60px;background:rgba(255,255,255,0.2);border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:30px}
        .tagline{font-size:24px;font-weight:600;margin-bottom:15px;line-height:1.4}
        .description{font-size:16px;opacity:0.9;line-height:1.7;margin-bottom:30px}
        
        .stats-row{display:flex;gap:20px;margin-bottom:30px}
        .stat-item{background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);padding:20px;border-radius:15px;text-align:center;flex:1}
        .stat-value{font-size:28px;font-weight:700;margin-bottom:5px}
        .stat-label{font-size:12px;opacity:0.8}
        
        .features{list-style:none}
        .features li{padding:12px 0;font-size:15px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .features li:last-child{border-bottom:none}
        .feature-icon{width:35px;height:35px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px}
        
        /* Right Side - Form */
        .form-section{background:#fff;border-radius:25px;padding:40px;box-shadow:0 25px 80px rgba(0,0,0,0.3)}
        
        .form-tabs{display:flex;gap:5px;margin-bottom:30px;background:#f5f5f5;padding:5px;border-radius:12px}
        .form-tab{flex:1;padding:12px;text-align:center;border-radius:10px;cursor:pointer;font-weight:600;font-size:14px;color:#666;transition:all 0.3s}
        .form-tab.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        
        .form-content{display:none}
        .form-content.active{display:block}
        
        .form-title{font-size:24px;font-weight:700;color:#333;margin-bottom:8px}
        .form-subtitle{font-size:14px;color:#888;margin-bottom:25px}
        
        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#333;font-size:14px}
        .form-group label span{color:#e74c3c}
        .form-control{width:100%;padding:14px 18px;border:2px solid #eee;border-radius:12px;font-size:14px;transition:all 0.3s}
        .form-control:focus{border-color:#667eea;outline:none;box-shadow:0 0 0 4px rgba(102,126,234,0.1)}
        .form-control.error{border-color:#e74c3c}
        
        .input-icon{position:relative}
        .input-icon .form-control{padding-left:50px}
        .input-icon .icon{position:absolute;left:18px;top:50%;transform:translateY(-50%);font-size:18px;opacity:0.5}
        
        .password-toggle{position:absolute;right:15px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:18px;opacity:0.5}
        .password-toggle:hover{opacity:1}
        
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:15px}
        
        .form-footer{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;font-size:13px}
        .form-footer a{color:#667eea;text-decoration:none;font-weight:500}
        .form-footer a:hover{text-decoration:underline}
        
        .checkbox-group{display:flex;align-items:center;gap:10px}
        .checkbox-group input{width:18px;height:18px;accent-color:#667eea}
        .checkbox-group label{font-size:13px;color:#666;cursor:pointer}
        
        .btn{padding:15px 30px;border:none;border-radius:12px;font-weight:600;cursor:pointer;font-size:15px;transition:all 0.3s;display:flex;align-items:center;justify-content:center;gap:10px;width:100%}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-primary:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(102,126,234,0.4)}
        .btn-google{background:#fff;border:2px solid #eee;color:#333}
        .btn-google:hover{border-color:#ddd;background:#fafafa}
        
        .divider{display:flex;align-items:center;gap:15px;margin:25px 0;color:#999;font-size:13px}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:#eee}
        
        .alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:13px;display:flex;align-items:center;gap:10px}
        .alert-danger{background:#fee;color:#c00;border:1px solid #fcc}
        .alert-success{background:#efe;color:#060;border:1px solid #cfc}
        .alert-info{background:#e8f4fd;color:#0066cc;border:1px solid #b8daff}
        
        .referral-banner{background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;padding:15px;border-radius:12px;margin-bottom:20px;text-align:center}
        .referral-banner .code{font-size:20px;font-weight:700;letter-spacing:2px}
        
        /* Forgot Password Modal */
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;padding:20px}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:20px;padding:35px;max-width:420px;width:100%}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .modal-title{font-size:20px;font-weight:700;color:#333}
        .modal-close{width:35px;height:35px;border-radius:50%;background:#f5f5f5;border:none;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center}
        
        /* Responsive */
        @media(max-width:992px){
            .container{grid-template-columns:1fr;max-width:500px}
            .info-section{display:none}
            body{align-items:flex-start;padding-top:40px}
        }
        @media(max-width:576px){
            .form-section{padding:30px 20px;border-radius:20px}
            .form-row{grid-template-columns:1fr}
            .stats-row{flex-wrap:wrap}
            .stat-item{min-width:calc(50% - 10px)}
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Left Side - Info -->
    <div class="info-section">
        <div class="logo">
            <div class="logo-icon">💼</div>
            <?php echo APP_NAME; ?>
        </div>
        <div class="tagline">Earn Money by Completing Simple Tasks</div>
        <div class="description">
            Join thousands of users who are earning money online. Complete tasks, 
            refer friends, and withdraw your earnings instantly!
        </div>
        
        <div class="stats-row">
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($total_users); ?>+</div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($total_tasks); ?>+</div>
                <div class="stat-label">Tasks Completed</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">₹<?php echo number_format($total_paid / 1000, 0); ?>K+</div>
                <div class="stat-label">Paid Out</div>
            </div>
        </div>
        
        <ul class="features">
            <li>
                <div class="feature-icon">✅</div>
                <span>Complete simple 4-step tasks</span>
            </li>
            <li>
                <div class="feature-icon">💰</div>
                <span>Earn commission on each task</span>
            </li>
            <li>
                <div class="feature-icon">🎁</div>
                <span>Refer friends & earn ₹<?php echo getSetting('referral_bonus', 50); ?> bonus</span>
            </li>
            <li>
                <div class="feature-icon">💳</div>
                <span>Instant withdrawals via UPI/Bank</span>
            </li>
            <li>
                <div class="feature-icon">📱</div>
                <span>Works on mobile & desktop</span>
            </li>
        </ul>
    </div>
    
    <!-- Right Side - Form -->
    <div class="form-section">
        <!-- Referral Banner -->
        <?php if (!empty($_SESSION['referral_code'])): ?>
            <div class="referral-banner">
                🎁 You've been referred! Code: <span class="code"><?php echo escape($_SESSION['referral_code']); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
        <?php endforeach; ?>
        
        <!-- Tabs -->
        <div class="form-tabs">
            <div class="form-tab <?php echo $show_form === 'login' ? 'active' : ''; ?>" onclick="showForm('login')">Login</div>
            <div class="form-tab <?php echo $show_form === 'register' ? 'active' : ''; ?>" onclick="showForm('register')">Register</div>
        </div>
        
        <!-- Login Form -->
        <div class="form-content <?php echo $show_form === 'login' ? 'active' : ''; ?>" id="loginForm">
            <div class="form-title">Welcome Back! 👋</div>
            <div class="form-subtitle">Login to continue earning</div>
            
            <form method="POST" autocomplete="on">
                <div class="form-group">
                    <label>Email Address <span>*</span></label>
                    <div class="input-icon">
                        <span class="icon">📧</span>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required autocomplete="email" value="<?php echo escape($_POST['email'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password <span>*</span></label>
                    <div class="input-icon">
                        <span class="icon">🔒</span>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password" id="loginPassword">
                        <span class="password-toggle" onclick="togglePassword('loginPassword')">👁️</span>
                    </div>
                </div>
                
                <div class="form-footer">
                    <div class="checkbox-group">
                        <input type="checkbox" name="remember" id="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" onclick="showForgotPassword()">Forgot Password?</a>
                </div>
                
                <button type="submit" name="login" class="btn btn-primary">
                    🔐 Login
                </button>
            </form>
            
            <div class="divider">or</div>
            
            <button class="btn btn-google" onclick="showForm('register')">
                📝 Create New Account
            </button>
        </div>
        
        <!-- Register Form -->
        <div class="form-content <?php echo $show_form === 'register' ? 'active' : ''; ?>" id="registerForm">
            <div class="form-title">Create Account 🚀</div>
            <div class="form-subtitle">Join and start earning today</div>
            
            <form method="POST" autocomplete="on" id="regForm">
                <div class="form-group">
                    <label>Full Name <span>*</span></label>
                    <div class="input-icon">
                        <span class="icon">👤</span>
                        <input type="text" name="name" class="form-control" placeholder="Enter your full name" required minlength="3" autocomplete="name" value="<?php echo escape($_POST['name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email <span>*</span></label>
                        <div class="input-icon">
                            <span class="icon">📧</span>
                            <input type="email" name="email" class="form-control" placeholder="Email address" required autocomplete="email" value="<?php echo escape($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Mobile <span>*</span></label>
                        <div class="input-icon">
                            <span class="icon">📱</span>
                            <input type="tel" name="mobile" class="form-control" placeholder="10-digit mobile" required pattern="[6-9][0-9]{9}" maxlength="10" autocomplete="tel" value="<?php echo escape($_POST['mobile'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span>*</span></label>
                        <div class="input-icon">
                            <span class="icon">🔒</span>
                            <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required minlength="8" autocomplete="new-password" id="regPassword">
                            <span class="password-toggle" onclick="togglePassword('regPassword')">👁️</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm <span>*</span></label>
                        <div class="input-icon">
                            <span class="icon">🔒</span>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required minlength="8" autocomplete="new-password" id="regConfirmPassword">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Referral Code (Optional)</label>
                    <div class="input-icon">
                        <span class="icon">🎁</span>
                        <input type="text" name="referral_code" class="form-control" placeholder="Enter referral code" value="<?php echo escape($_SESSION['referral_code'] ?? $_POST['referral_code'] ?? ''); ?>" style="text-transform:uppercase">
                    </div>
                </div>
                
                <div class="checkbox-group" style="margin-bottom:20px">
                    <input type="checkbox" name="terms" id="terms" required>
                    <label for="terms">I agree to the <a href="<?php echo APP_URL; ?>/terms.php" target="_blank">Terms & Conditions</a></label>
                </div>
                
                <button type="submit" name="register" class="btn btn-primary">
                    🚀 Create Account
                </button>
            </form>
            
            <div class="divider">or</div>
            
            <button class="btn btn-google" onclick="showForm('login')">
                🔐 Already have an account? Login
            </button>
        </div>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal" id="forgotModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">🔐 Reset Password</div>
            <button class="modal-close" onclick="hideForgotPassword()">×</button>
        </div>
        <p style="color:#666;font-size:14px;margin-bottom:20px">Enter your email address and we'll send you a link to reset your password.</p>
        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="forgot_email" class="form-control" placeholder="Enter your email" required>
            </div>
            <button type="submit" name="forgot_password" class="btn btn-primary">📧 Send Reset Link</button>
        </form>
    </div>
</div>

<script>
// Form switching
function showForm(form) {
    document.querySelectorAll('.form-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.form-content').forEach(content => content.classList.remove('active'));
    
    if (form === 'login') {
        document.querySelectorAll('.form-tab')[0].classList.add('active');
        document.getElementById('loginForm').classList.add('active');
    } else {
        document.querySelectorAll('.form-tab')[1].classList.add('active');
        document.getElementById('registerForm').classList.add('active');
    }
}

// Password toggle
function togglePassword(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Forgot password modal
function showForgotPassword() {
    document.getElementById('forgotModal').classList.add('show');
}

function hideForgotPassword() {
    document.getElementById('forgotModal').classList.remove('show');
}

// Close modal on outside click
document.getElementById('forgotModal').addEventListener('click', function(e) {
    if (e.target === this) hideForgotPassword();
});

// Password match validation
document.getElementById('regForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('regPassword').value;
    const confirm = document.getElementById('regConfirmPassword').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
    }
});

// Mobile number formatting
document.querySelector('input[name="mobile"]')?.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 10);
});

// Referral code uppercase
document.querySelector('input[name="referral_code"]')?.addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
});

// PWA Install
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?php echo APP_URL; ?>/sw.js').catch(() => {});
}
</script>
</body>
</html>
