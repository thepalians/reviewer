<?php
/**
 * User Security Settings - 2FA Setup
 * User page to enable and configure two-factor authentication
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/2fa-functions.php';

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['email'] ?? '';

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'enable_2fa') {
        // Generate secret and enable 2FA
        $secret = generate2FASecret();
        $method = $_POST['method'] ?? 'totp';
        $phoneNumber = $_POST['phone_number'] ?? null;
        
        // Verify the code before enabling
        $code = $_POST['verification_code'] ?? '';
        if (verifyTOTP($secret, $code)) {
            enable2FA($user_id, $secret, $method, $phoneNumber);
            $_SESSION['2fa_setup_complete'] = true;
            $success_message = '2FA enabled successfully! Please save your backup codes.';
        } else {
            $error_message = 'Invalid verification code. Please try again.';
        }
    } elseif ($action === 'disable_2fa') {
        disable2FA($user_id);
        $success_message = '2FA has been disabled.';
    } elseif ($action === 'generate_secret') {
        // Generate new secret for setup
        $_SESSION['2fa_temp_secret'] = generate2FASecret();
    } elseif ($action === 'remove_device') {
        $deviceId = (int)($_POST['device_id'] ?? 0);
        if ($deviceId > 0) {
            removeTrustedDevice($deviceId, $user_id);
            $success_message = 'Trusted device removed.';
        }
    }
}

// Get current 2FA status
$is2FAEnabled = is2FAEnabled($user_id);
$settings2FA = get2FASettings($user_id);
$trustedDevices = getTrustedDevices($user_id);

// Get or generate temporary secret for setup
if (!$is2FAEnabled && !isset($_SESSION['2fa_temp_secret'])) {
    $_SESSION['2fa_temp_secret'] = generate2FASecret();
}
$tempSecret = $_SESSION['2fa_temp_secret'] ?? '';
$qrCodeUrl = !empty($tempSecret) ? get2FAQRCodeUrl($tempSecret, $user_email) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - ReviewFlow</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .status-enabled {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .status-disabled {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #ef4444;
        }
        .setup-section {
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            margin: 20px 0;
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .backup-codes {
            background: #fef3c7;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .code {
            font-family: monospace;
            background: white;
            padding: 10px;
            border-radius: 4px;
            margin: 5px 0;
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        input[type="text"],
        input[type="tel"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px 5px 5px 0;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }
        .device-list {
            margin: 20px 0;
        }
        .device-item {
            padding: 15px;
            background: #f9fafb;
            border-radius: 8px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Security Settings</h1>
        <p>Enhance your account security with two-factor authentication (2FA)</p>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <!-- 2FA Status -->
        <div class="status <?php echo $is2FAEnabled ? 'status-enabled' : 'status-disabled'; ?>">
            <strong>Two-Factor Authentication:</strong>
            <?php echo $is2FAEnabled ? 'Enabled ✓' : 'Disabled'; ?>
        </div>
        
        <?php if (!$is2FAEnabled): ?>
            <!-- Setup 2FA -->
            <div class="setup-section">
                <h2>Enable Two-Factor Authentication</h2>
                <p>Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>
                
                <?php if ($qrCodeUrl): ?>
                    <div class="qr-code">
                        <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="QR Code">
                    </div>
                    <p style="text-align: center;">
                        <strong>Secret Key:</strong> 
                        <span class="code"><?php echo htmlspecialchars($tempSecret); ?></span>
                    </p>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="enable_2fa">
                    
                    <div class="form-group">
                        <label>Verification Code</label>
                        <input type="text" name="verification_code" required placeholder="Enter 6-digit code from app">
                    </div>
                    
                    <div class="form-group">
                        <label>Method</label>
                        <select name="method">
                            <option value="totp">Authenticator App (TOTP)</option>
                            <option value="sms">SMS (Coming Soon)</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Enable 2FA</button>
                    <button type="button" class="btn btn-secondary" onclick="location.reload()">Generate New Secret</button>
                </form>
            </div>
        <?php else: ?>
            <!-- 2FA Enabled -->
            <div class="setup-section">
                <h2>2FA is Active</h2>
                <p>Your account is protected with two-factor authentication.</p>
                
                <p><strong>Method:</strong> <?php echo strtoupper($settings2FA['method'] ?? 'TOTP'); ?></p>
                <p><strong>Enabled on:</strong> <?php echo date('M d, Y', strtotime($settings2FA['created_at'])); ?></p>
                
                <?php if (!empty($settings2FA['backup_codes'])): ?>
                    <div class="backup-codes">
                        <h3>Backup Codes</h3>
                        <p>Save these codes in a safe place. You can use them to access your account if you lose your device.</p>
                        <?php foreach ($settings2FA['backup_codes'] as $backupCode): ?>
                            <?php if (!$backupCode['used']): ?>
                                <div class="code"><?php echo htmlspecialchars($backupCode['code']); ?></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" onsubmit="return confirm('Are you sure you want to disable 2FA?');">
                    <input type="hidden" name="action" value="disable_2fa">
                    <button type="submit" class="btn btn-danger">Disable 2FA</button>
                </form>
            </div>
            
            <!-- Trusted Devices -->
            <div class="setup-section">
                <h2>Trusted Devices</h2>
                <?php if (empty($trustedDevices)): ?>
                    <p>No trusted devices configured.</p>
                <?php else: ?>
                    <div class="device-list">
                        <?php foreach ($trustedDevices as $device): ?>
                            <div class="device-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($device['device_name'] ?? 'Unknown Device'); ?></strong><br>
                                    <small>Added: <?php echo date('M d, Y', strtotime($device['created_at'])); ?></small><br>
                                    <small>Expires: <?php echo date('M d, Y', strtotime($device['expires_at'])); ?></small>
                                </div>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="remove_device">
                                    <input type="hidden" name="device_id" value="<?php echo $device['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Remove</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
