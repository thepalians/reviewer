<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirectTo(APP_URL . '/user/');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Verification
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
    
    // Rate limiting
    if (!checkRateLimit('signup_attempt')) {
        $errors[] = 'Too many signup attempts. Please try again later.';
    }
    
    // Sanitize inputs
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $mobile = sanitizeInput($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validate inputs
    if (empty($name) || strlen($name) < 3) {
        $errors[] = 'Name must be at least 3 characters';
    }
    
    if (empty($email) || !validateEmail($email)) {
        $errors[] = 'Invalid email address';
    }
    
    if (empty($mobile) || !validateMobile($mobile)) {
        $errors[] = 'Invalid mobile number (10 digits required)';
    }
    
    if (empty($password) || strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match';
    }
    
    // Check if email/mobile exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE (email = :email OR mobile = :mobile) AND user_type = "user" LIMIT 1');
            $stmt->execute([
                ':email' => $email,
                ':mobile' => $mobile
            ]);
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Email or Mobile number already registered';
            }
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());
            $errors[] = 'Database error occurred';
        }
    }
    
    // Create account if no errors
    if (empty($errors)) {
        try {
            $hashed_password = hashPassword($password);
            
            // FIX: Match actual database schema
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, mobile, password, user_type, status)
                VALUES (:name, :email, :mobile, :password, 'user', 'active')
            ");
            
            $result = $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':mobile' => $mobile,
                ':password' => $hashed_password
            ]);
            
            if ($result) {
                $user_id = (int)$pdo->lastInsertId();
                
                // Log activity
                logActivity('User Signup', null, $user_id);
                
                $success = true;
                $_SESSION['success_message'] = 'Registration successful! Please login.';
                
                // Redirect to login after 2 seconds
                header('Refresh: 2; url=' . APP_URL . '/index.php');
            }
            
        } catch (PDOException $e) {
            error_log('Signup error: ' . $e->getMessage());
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Sign Up - <?php echo escape(APP_NAME); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .signup-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .signup-card {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        .signup-card h2 {
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
        .btn-signup {
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
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .login-link a {
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
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #27ae60;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="signup-card">
            <h2>✨ Create Account</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <strong>Success!</strong> Registration complete. Redirecting to login...
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger" role="alert">
                        <strong>Error:</strong> <?php echo escape($error); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo escape($_POST['name'] ?? ''); ?>" 
                           minlength="3" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo escape($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="mobile">Mobile Number *</label>
                    <input type="tel" class="form-control" id="mobile" name="mobile" 
                           placeholder="10 digit number (e.g., 9876543210)" 
                           value="<?php echo escape($_POST['mobile'] ?? ''); ?>" 
                           pattern="[6-9][0-9]{9}" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="At least 8 characters" minlength="8" required>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirm Password *</label>
                    <input type="password" class="form-control" id="password_confirm" 
                           name="password_confirm" minlength="8" required>
                </div>
                
                <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
                
                <button type="submit" class="btn-signup">Create Account</button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="<?php echo APP_URL; ?>/index.php">Login here</a>
            </div>
        </div>
    </div>
</body>
</html>
