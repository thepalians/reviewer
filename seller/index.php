<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['seller_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if (isset($_GET['registered'])) {
    $success = 'Registration successful! Please login to continue.';
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'account_inactive') {
        $error = 'Your account is inactive. Please contact support.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM sellers WHERE email = ?");
            $stmt->execute([$email]);
            $seller = $stmt->fetch();
            
            if ($seller && password_verify($password, $seller['password'])) {
                if ($seller['status'] !== 'active') {
                    $error = 'Your account is ' . htmlspecialchars($seller['status']) . '. Please contact support.';
                } else {
                    // Set session
                    $_SESSION['seller_id'] = $seller['id'];
                    $_SESSION['seller_name'] = $seller['name'];
                    $_SESSION['seller_email'] = $seller['email'];
                    $_SESSION['login_time'] = time();
                    
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log('Seller login error: ' . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        
        .login-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.75rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
        }
        
        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            color: white;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
        }
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e2e8f0;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
            position: relative;
            color: #64748b;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-shop" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
            <h2>Seller Login</h2>
            <p class="mb-0 mt-2" style="opacity: 0.9;">Welcome to <?= APP_NAME ?></p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="seller@example.com" 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
            
            <div class="divider">
                <span>OR</span>
            </div>
            
            <div class="text-center">
                <p class="text-muted mb-2">Don't have an account?</p>
                <a href="register.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-person-plus"></i> Register as Seller
                </a>
            </div>
            
            <div class="text-center mt-3">
                <a href="../index.php" class="text-muted text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
