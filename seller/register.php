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
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $name = trim($_POST['name'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $company_name = trim($_POST['company_name'] ?? '');
    $gst_number = strtoupper(trim($_POST['gst_number'] ?? ''));
    $billing_address = trim($_POST['billing_address'] ?? '');
    
    // Store form data for repopulation
    $form_data = compact('name', 'email', 'mobile', 'company_name', 'gst_number', 'billing_address');
    
    // Validation
    if (empty($name) || empty($email) || empty($mobile) || empty($password) || empty($billing_address)) {
        $error = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($mobile) !== 10) {
        $error = 'Please enter a valid 10-digit mobile number.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM sellers WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email address is already registered.';
            } else {
                // Check if mobile already exists
                $stmt = $pdo->prepare("SELECT id FROM sellers WHERE mobile = ?");
                $stmt->execute([$mobile]);
                if ($stmt->fetch()) {
                    $error = 'Mobile number is already registered.';
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS);
                    
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    // Insert seller
                    $stmt = $pdo->prepare("
                        INSERT INTO sellers (name, email, mobile, password, company_name, gst_number, billing_address, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([$name, $email, $mobile, $hashed_password, $company_name, $gst_number ?: null, $billing_address]);
                    $seller_id = $pdo->lastInsertId();
                    
                    // Create wallet entry
                    $stmt = $pdo->prepare("
                        INSERT INTO seller_wallet (seller_id, balance, total_spent)
                        VALUES (?, 0, 0)
                    ");
                    $stmt->execute([$seller_id]);
                    
                    $pdo->commit();
                    
                    // Redirect to login
                    header('Location: index.php?registered=1');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Seller registration error: ' . $e->getMessage());
            $error = 'An error occurred during registration. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Registration - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        
        .register-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            margin: 0 auto;
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }
        
        .register-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.75rem;
        }
        
        .register-body {
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
        
        .btn-register {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            color: white;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4);
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="register-header">
                <i class="bi bi-shop" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                <h2>Seller Registration</h2>
                <p class="mb-0 mt-2" style="opacity: 0.9;">Join <?= APP_NAME ?> as a Seller</p>
            </div>
            
            <div class="register-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="bi bi-person-circle"></i> Personal Information
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="Enter your full name" 
                                   value="<?= htmlspecialchars($form_data['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="seller@example.com" 
                                       value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                <input type="tel" name="mobile" class="form-control" placeholder="9876543210" 
                                       value="<?= htmlspecialchars($form_data['mobile'] ?? '') ?>" 
                                       pattern="[0-9]{10}" maxlength="10" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" 
                                       placeholder="Min. 6 characters" minlength="6" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="Re-enter password" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Business Information -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="bi bi-building"></i> Business Information
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" 
                                   placeholder="Your company or business name" 
                                   value="<?= htmlspecialchars($form_data['company_name'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">GST Number <small class="text-muted">(Optional)</small></label>
                            <input type="text" name="gst_number" class="form-control" 
                                   placeholder="22AAAAA0000A1Z5" maxlength="15"
                                   value="<?= htmlspecialchars($form_data['gst_number'] ?? '') ?>">
                            <small class="text-muted">Enter 15 digit GSTIN if registered</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Billing Address <span class="text-danger">*</span></label>
                            <textarea name="billing_address" class="form-control" rows="3" 
                                      placeholder="Enter complete billing address" required><?= htmlspecialchars($form_data['billing_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#" target="_blank">Terms & Conditions</a> and 
                            <a href="#" target="_blank">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-register">
                        <i class="bi bi-person-plus"></i> Create Seller Account
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <p class="text-muted mb-2">Already have an account?</p>
                    <a href="index.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
