<?php
/**
 * 2FA Verification Page
 * Shown during login when user has 2FA enabled
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/2fa-functions.php';

// Check if user is in 2FA verification state
if (!isset($_SESSION['2fa_user_id']) || !isset($_SESSION['2fa_pending'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['2fa_user_id'];
$error_message = '';
$show_backup_code_form = false;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify_totp';
    
    if ($action === 'verify_totp') {
        $code = $_POST['code'] ?? '';
        $rememberDevice = isset($_POST['remember_device']);
        
        // Get user's 2FA settings
        $settings = get2FASettings($user_id);
        
        if ($settings && verifyTOTP($settings['secret_key'], $code)) {
            // Code is valid
            update2FALastUsed($user_id);
            
            // Remember device if requested
            if ($rememberDevice) {
                addTrustedDevice($user_id, 'Browser on ' . date('M d, Y'), 30);
            }
            
            // Complete login
            unset($_SESSION['2fa_pending']);
            unset($_SESSION['2fa_user_id']);
            
            // Set regular session variables
            $_SESSION['user_id'] = $user_id;
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $error_message = 'Invalid verification code. Please try again.';
        }
    } elseif ($action === 'verify_backup') {
        $backupCode = $_POST['backup_code'] ?? '';
        
        if (verifyBackupCode($user_id, $backupCode)) {
            // Backup code is valid
            update2FALastUsed($user_id);
            
            // Complete login
            unset($_SESSION['2fa_pending']);
            unset($_SESSION['2fa_user_id']);
            
            // Set regular session variables
            $_SESSION['user_id'] = $user_id;
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $error_message = 'Invalid backup code or code already used.';
        }
    } elseif ($action === 'show_backup') {
        $show_backup_code_form = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - ReviewFlow</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 90%;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 10px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 16px;
            text-align: center;
            letter-spacing: 4px;
            font-weight: bold;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
        }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
            margin-top: 10px;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }
        .link {
            text-align: center;
            margin-top: 20px;
            color: #667eea;
            cursor: pointer;
            text-decoration: underline;
        }
        .icon {
            text-align: center;
            font-size: 48px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔐</div>
        <h1>Two-Factor Authentication</h1>
        <p class="subtitle">Enter the verification code from your authenticator app</p>
        
        <?php if ($error_message): ?>
            <div class="alert"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if (!$show_backup_code_form): ?>
            <!-- TOTP Form -->
            <form method="POST">
                <input type="hidden" name="action" value="verify_totp">
                
                <div class="form-group">
                    <label for="code">Verification Code</label>
                    <input type="text" id="code" name="code" required 
                           maxlength="6" pattern="[0-9]{6}" 
                           placeholder="000000" 
                           autofocus>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="remember_device" name="remember_device" value="1">
                    <label for="remember_device">Remember this device for 30 days</label>
                </div>
                
                <button type="submit" class="btn btn-primary">Verify</button>
            </form>
            
            <form method="POST">
                <input type="hidden" name="action" value="show_backup">
                <button type="submit" class="btn btn-secondary">Use Backup Code</button>
            </form>
        <?php else: ?>
            <!-- Backup Code Form -->
            <form method="POST">
                <input type="hidden" name="action" value="verify_backup">
                
                <div class="form-group">
                    <label for="backup_code">Backup Code</label>
                    <input type="text" id="backup_code" name="backup_code" required 
                           maxlength="9" pattern="[0-9]{4}-[0-9]{4}" 
                           placeholder="0000-0000" 
                           autofocus>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Format: 0000-0000
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary">Verify Backup Code</button>
            </form>
            
            <form method="POST">
                <button type="submit" class="btn btn-secondary">Back to Authenticator Code</button>
            </form>
        <?php endif; ?>
        
        <div class="link" onclick="window.location.href='login.php'">
            Cancel and return to login
        </div>
    </div>
</body>
</html>
