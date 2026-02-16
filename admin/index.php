<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

// Admin authentication using environment variables
$admin_user = env('ADMIN_EMAIL', 'aqidulmumtaz');
$admin_pass = env('ADMIN_PASSWORD', 'Malik@241123');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // For security, check against hashed password if stored
    // For initial setup, allow direct comparison but log warning
    $isValidPassword = false;
    
    // Check if admin_pass is a hash
    if (strpos($admin_pass, '$2y$') === 0) {
        // It's a bcrypt hash
        $isValidPassword = password_verify($password, $admin_pass);
    } else {
        // Plain text password (only for development/initial setup)
        $isValidPassword = ($password === $admin_pass);
        error_log("WARNING: Admin password is not hashed. Please hash the password in .env file.");
    }
    
    if ($username === $admin_user && $isValidPassword) {
        $_SESSION['admin_name'] = $username;
        $_SESSION['admin_login_time'] = time();
        header('Location: ' . ADMIN_URL . '/dashboard.php');
        exit;
    } else {
        $error = 'Invalid admin credentials';
    }
}

// Check if already logged in
if (isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL . '/dashboard.php');
    exit;
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(102,126,234,0.3), transparent);
            top: -100px;
            right: -100px;
            border-radius: 50%;
        }
        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(118,75,162,0.2), transparent);
            bottom: -150px;
            left: -150px;
            border-radius: 50%;
        }
        .login-card {
            width: 100%;
            max-width: 440px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.4);
            padding: 48px 40px;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .admin-logo {
            text-align: center;
            margin-bottom: 35px;
        }
        .admin-logo .logo-icon {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(102,126,234,0.35);
        }
        .admin-logo h1 {
            font-size: 26px;
            font-weight: 800;
            color: #1a1a2e;
            margin: 0;
        }
        .admin-logo p {
            color: #888;
            font-size: 13px;
            margin: 6px 0 0;
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 22px;
        }
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 4px rgba(102,126,234,0.12);
            background: #fff;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 8px;
            letter-spacing: 0.5px;
        }
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(102,126,234,0.4);
        }
        .btn-login:active { transform: translateY(-1px); }
        .alert {
            margin-bottom: 20px;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: none;
        }
        .back-link {
            text-align: center;
            margin-top: 24px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="admin-logo">
            <div class="logo-icon">🔐</div>
            <h1>Admin Panel</h1>
            <p><?php echo APP_NAME; ?></p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Admin Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Enter admin email" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Admin Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required>
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" class="btn-login">🔓 Sign In</button>
        </form>
        
        <div class="back-link">
            <a href="<?php echo APP_URL; ?>/">← Back to Homepage</a>
        </div>
    </div>
</body>
</html>
