<?php
declare(strict_types=1);

// Start session FIRST - before ANY output or includes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/user/');
    exit;
}

$errors = [];
$success = false;
$login_field_value = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_field_value = $_POST['login_field'] ?? '';
    
    // Get form inputs
    $login_field = sanitizeInput($_POST['login_field'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($login_field)) {
        $errors[] = 'Email or Mobile number is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // Rate limiting check (simple version)
    $rate_key = 'login_' . md5($login_field . $_SERVER['REMOTE_ADDR']);
    $attempts = $_SESSION[$rate_key] ?? 0;
    $last_attempt = $_SESSION[$rate_key . '_time'] ?? 0;
    
    // Reset counter after 5 minutes
    if (time() - $last_attempt > 300) {
        $attempts = 0;
    }
    
    if ($attempts >= 5) {
        $errors[] = 'Too many login attempts. Please try again in 5 minutes.';
    }
    
    // Verify credentials if no errors
    if (empty($errors)) {
        try {
            // Check if login field is email or mobile
            $is_email = str_contains($login_field, '@');
            
            // Use separate queries to avoid dynamic column names in SQL
            if ($is_email) {
                $stmt = $pdo->prepare("
                    SELECT id, name, email, mobile, password, status, user_type
                    FROM users 
                    WHERE email = :login_field 
                    AND user_type = 'user'
                    AND status = 'active'
                    LIMIT 1
                ");
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, name, email, mobile, password, status, user_type
                    FROM users 
                    WHERE mobile = :login_field 
                    AND user_type = 'user'
                    AND status = 'active'
                    LIMIT 1
                ");
            }
            
            $stmt->execute([':login_field' => $login_field]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Successful login - reset rate limiting
                unset($_SESSION[$rate_key], $_SESSION[$rate_key . '_time']);
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Set session variables
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_mobile'] = $user['mobile'];
                $_SESSION['login_time'] = time();
                
                // Redirect to dashboard
                header('Location: ' . APP_URL . '/user/');
                exit;
                
            } else {
                // Failed login - increment rate limiting
                $_SESSION[$rate_key] = $attempts + 1;
                $_SESSION[$rate_key . '_time'] = time();
                
                $errors[] = 'Invalid credentials or account inactive';
                error_log("Failed login attempt for: $login_field from IP: " . $_SERVER['REMOTE_ADDR']);
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $errors[] = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>User Login - <?php echo escape(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        .login-card h2 {
            margin-bottom: 30px;
            color: #333;
            font-weight: 600;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .signup-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .signup-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #e74c3c;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2>🔐 User Login</h2>
            
            <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
                <div class="alert alert-success">
                    ✓ You have been logged out successfully.
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger">
                        ✗ <?php echo escape($error); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="login_field">Email or Mobile *</label>
                    <input type="text" class="form-control" id="login_field" name="login_field" 
                           value="<?php echo escape($login_field_value); ?>" 
                           placeholder="Enter email or 10-digit mobile" required>
                    <small style="color: #666;">Example: 8604261683 or aqidulm@gmail.com</small>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn-login">🔓 Login</button>
            </form>
            
            <div class="signup-link">
                New user? <a href="<?php echo APP_URL; ?>/user/signup.php">Create account here</a>
            </div>
        </div>
    </div>
</body>
</html>
