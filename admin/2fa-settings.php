<?php
/**
 * Admin 2FA Settings Management
 * Manage Two-Factor Authentication settings for the system
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/2fa-functions.php';

// Check admin authentication
if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$admin_id = $_SESSION['admin_id'] ?? 1;
$page_title = '2FA Settings';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'force_2fa_admins') {
        // Force 2FA for all admin accounts
        $conn->query("UPDATE users SET force_2fa = 1 WHERE user_type = 'admin'");
        $success_message = '2FA is now required for all admin accounts';
    } elseif ($action === 'disable_user_2fa') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            disable2FA($userId);
            $success_message = '2FA disabled for user';
        }
    }
}

// Get 2FA statistics
$stats = $conn->query("
    SELECT 
        COUNT(DISTINCT tfa.user_id) as enabled_users,
        COUNT(DISTINCT CASE WHEN u.user_type = 'admin' THEN tfa.user_id END) as enabled_admins,
        COUNT(DISTINCT td.user_id) as trusted_devices
    FROM two_factor_auth tfa
    LEFT JOIN users u ON tfa.user_id = u.id
    LEFT JOIN trusted_devices td ON tfa.user_id = td.user_id AND td.expires_at > NOW()
    WHERE tfa.is_enabled = 1
")->fetch_assoc();

// Get users with 2FA enabled
$users2FA = $conn->query("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.user_type,
        tfa.method,
        tfa.last_used_at,
        tfa.created_at
    FROM two_factor_auth tfa
    JOIN users u ON tfa.user_id = u.id
    WHERE tfa.is_enabled = 1
    ORDER BY u.user_type DESC, u.name
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ReviewFlow Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            text-align: center;
        }
        .stat-value { font-size: 36px; font-weight: bold; }
        .stat-label { margin-top: 5px; opacity: 0.9; }
        .settings-section {
            margin: 30px 0;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th { background: #f9fafb; font-weight: 600; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-admin { background: #fef3c7; color: #92400e; }
        .badge-user { background: #dbeafe; color: #1e40af; }
        .badge-totp { background: #d1fae5; color: #065f46; }
        .badge-sms { background: #fce7f3; color: #9f1239; }
        .alert {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $page_title; ?></h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['enabled_users'] ?? 0; ?></div>
                <div class="stat-label">Users with 2FA</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['enabled_admins'] ?? 0; ?></div>
                <div class="stat-label">Admins with 2FA</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['trusted_devices'] ?? 0; ?></div>
                <div class="stat-label">Trusted Devices</div>
            </div>
        </div>
        
        <!-- Settings -->
        <div class="settings-section">
            <h2>Security Settings</h2>
            <form method="POST">
                <input type="hidden" name="action" value="force_2fa_admins">
                <p>
                    <strong>Force 2FA for All Admins:</strong> 
                    Require all admin users to enable two-factor authentication.
                </p>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Force 2FA for all admins?')">
                    Enable Mandatory 2FA for Admins
                </button>
            </form>
        </div>
        
        <!-- Users with 2FA -->
        <h2>Users with 2FA Enabled</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Method</th>
                    <th>Last Used</th>
                    <th>Enabled Since</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users2FA)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No users have 2FA enabled yet</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users2FA as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $user['user_type']; ?>">
                                    <?php echo strtoupper($user['user_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $user['method']; ?>">
                                    <?php echo strtoupper($user['method']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['last_used_at'] ? date('M d, Y H:i', strtotime($user['last_used_at'])) : 'Never'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="disable_user_2fa">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Disable 2FA for this user?')">
                                        Disable 2FA
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
