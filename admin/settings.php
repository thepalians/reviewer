<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$errors = [];
$success = '';
$active_tab = $_GET['tab'] ?? 'general';

// Get all settings
try {
    $stmt = $pdo->query("SELECT * FROM system_settings ORDER BY setting_key ASC");
    $settings_raw = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_raw as $s) {
        $settings[$s['setting_key']] = $s['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $update_settings = [];
    
    // General Settings
    if (isset($_POST['site_name'])) {
        $update_settings['site_name'] = sanitizeInput($_POST['site_name']);
    }
    if (isset($_POST['support_email'])) {
        $update_settings['support_email'] = sanitizeInput($_POST['support_email']);
    }
    if (isset($_POST['support_whatsapp'])) {
        $update_settings['support_whatsapp'] = sanitizeInput($_POST['support_whatsapp']);
    }
    
    // Wallet Settings
    if (isset($_POST['min_withdrawal'])) {
        $update_settings['min_withdrawal'] = max(1, intval($_POST['min_withdrawal']));
    }
    if (isset($_POST['referral_bonus'])) {
        $update_settings['referral_bonus'] = max(0, intval($_POST['referral_bonus']));
    }
    if (isset($_POST['first_task_bonus'])) {
        $update_settings['first_task_bonus'] = max(0, intval($_POST['first_task_bonus']));
    }
    if (isset($_POST['task_commission'])) {
        $update_settings['task_commission'] = max(0, floatval($_POST['task_commission']));
    }
    
    // Notification Settings
    if (isset($_POST['active_tab']) && $_POST['active_tab'] === 'notifications') { $update_settings['email_enabled'] = isset($_POST['email_enabled']) ? '1' : '0'; }
    if (isset($_POST['active_tab']) && $_POST['active_tab'] === 'notifications') { $update_settings['sms_enabled'] = isset($_POST['sms_enabled']) ? '1' : '0'; }
    if (isset($_POST['active_tab']) && $_POST['active_tab'] === 'notifications') { $update_settings['whatsapp_enabled'] = isset($_POST['whatsapp_enabled']) ? '1' : '0'; }
    if (isset($_POST['active_tab']) && $_POST['active_tab'] === 'notifications') { $update_settings['push_enabled'] = isset($_POST['push_enabled']) ? '1' : '0'; }
    
    // Task Settings
    if (isset($_POST['default_deadline_days'])) {
        $update_settings['default_deadline_days'] = max(1, intval($_POST['default_deadline_days']));
    }
    if (isset($_POST['max_pending_tasks'])) {
        $update_settings['max_pending_tasks'] = max(1, intval($_POST['max_pending_tasks']));
    }
    
    // Security Settings
    if (isset($_POST['max_login_attempts'])) {
        $update_settings['max_login_attempts'] = max(3, intval($_POST['max_login_attempts']));
    }
    if (isset($_POST['session_timeout'])) {
        $update_settings['session_timeout'] = max(300, intval($_POST['session_timeout']));
    }
    if (isset($_POST['active_tab']) && $_POST['active_tab'] === 'security') { $update_settings['registration_enabled'] = isset($_POST['registration_enabled']) ? '1' : '0'; $update_settings['maintenance_mode'] = isset($_POST['maintenance_mode']) ? '1' : '0'; }
    
    // Payment Settings
    if (isset($_POST['razorpay_key_id'])) {
        $update_settings['razorpay_key_id'] = sanitizeInput($_POST['razorpay_key_id']);
    }
    if (isset($_POST['razorpay_key_secret'])) {
        $update_settings['razorpay_key_secret'] = sanitizeInput($_POST['razorpay_key_secret']);
    }
    if (isset($_POST['active_tab']) && $_POST['active_tab'] === 'payment') { $update_settings['razorpay_enabled'] = isset($_POST['razorpay_enabled']) ? '1' : '0'; }
    if (isset($_POST['active_tab']) && $_POST['active_tab'] === 'payment') { $update_settings['razorpay_test_mode'] = isset($_POST['razorpay_test_mode']) ? '1' : '0'; }
    
    if (isset($_POST['payumoney_merchant_key'])) {
        $update_settings['payumoney_merchant_key'] = sanitizeInput($_POST['payumoney_merchant_key']);
    }
    if (isset($_POST['payumoney_merchant_salt'])) {
        $update_settings['payumoney_merchant_salt'] = sanitizeInput($_POST['payumoney_merchant_salt']);
    }
    if (isset($_POST['active_tab']) && $_POST['active_tab'] === 'payment') { $update_settings['payumoney_enabled'] = isset($_POST['payumoney_enabled']) ? '1' : '0'; }
    if (isset($_POST['active_tab']) && $_POST['active_tab'] === 'payment') { $update_settings['payumoney_test_mode'] = isset($_POST['payumoney_test_mode']) ? '1' : '0'; }
    
    if (isset($_POST['admin_commission_per_review'])) {
        $update_settings['admin_commission_per_review'] = max(0, floatval($_POST['admin_commission_per_review']));
    }
    
    // Legal Pages
    if (isset($_POST['terms_content'])) {
        $update_settings['terms_content'] = $_POST['terms_content'];
    }
    if (isset($_POST['privacy_content'])) {
        $update_settings['privacy_content'] = $_POST['privacy_content'];
    }
    if (isset($_POST['refund_content'])) {
        $update_settings['refund_content'] = $_POST['refund_content'];
    }
    if (isset($_POST['disclaimer_content'])) {
        $update_settings['disclaimer_content'] = $_POST['disclaimer_content'];
    }
    
    // Save settings
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        
        foreach ($update_settings as $key => $value) {
            $stmt->execute([$key, $value]);
            $settings[$key] = $value;
        }
        
        $success = "Settings saved successfully!";
        $active_tab = $_POST['active_tab'] ?? 'general';
        
        // Log activity
        logActivity("Settings updated by admin");
        
    } catch (PDOException $e) {
        $errors[] = "Failed to save settings";
        error_log("Settings Error: " . $e->getMessage());
    }
}

// Handle admin password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_admin_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Admin credentials from environment
    $admin_user = env('ADMIN_EMAIL', 'admin@reviewflow.com');
    $admin_pass = env('ADMIN_PASSWORD', '');
    
    // Verify current password
    $isCurrentPasswordValid = false;
    if (strpos($admin_pass, '$2y$') === 0) {
        $isCurrentPasswordValid = password_verify($current_password, $admin_pass);
    } else {
        $isCurrentPasswordValid = ($current_password === $admin_pass);
    }
    
    if (!$isCurrentPasswordValid) {
        $errors[] = "Current password is incorrect";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New passwords do not match";
    } else {
        // Note: In production, store admin credentials in database or update .env file
        $errors[] = "Admin password change requires manual update in config file for security";
    }
    $active_tab = 'security';
}

// Handle clear cache
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cache'])) {
    // Clear session data except admin login
    $admin_session = $_SESSION['admin_name'];
    session_unset();
    $_SESSION['admin_name'] = $admin_session;
    
    $success = "Cache cleared successfully!";
    $active_tab = 'maintenance';
}

// Handle clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    try {
        // Clear old logs (older than 30 days)
        $stmt = $pdo->prepare("DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $deleted_logs = $stmt->rowCount();
        
        $stmt = $pdo->prepare("DELETE FROM login_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $deleted_logins = $stmt->rowCount();
        
        $stmt = $pdo->prepare("DELETE FROM notification_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        
        $success = "Cleared $deleted_logs activity logs and $deleted_logins login records (older than 30 days)";
    } catch (PDOException $e) {
        $errors[] = "Failed to clear logs";
    }
    $active_tab = 'maintenance';
}

// Handle clear old notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_notifications'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        $success = "Cleared $deleted read notifications (older than 7 days)";
    } catch (PDOException $e) {
        $errors[] = "Failed to clear notifications";
    }
    $active_tab = 'maintenance';
}

// Get system stats
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'user'");
    $total_users = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
    $total_tasks = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM wallet_transactions");
    $total_transactions = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications");
    $total_notifications = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin_logs");
    $total_logs = (int)$stmt->fetchColumn();
    
} catch (PDOException $e) {
    $total_users = $total_tasks = $total_transactions = $total_notifications = $total_logs = 0;
}

// Get sidebar badge counts
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND is_read = 0");
    $unread_messages = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_questions = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_withdrawals = $unread_messages = $unanswered_questions = $pending_tasks = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        
        /* Sidebar */
        .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
        .sidebar-menu{list-style:none;padding:15px 0}
        .sidebar-menu li{margin-bottom:5px}
        .sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
        .sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
        .sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
        .sidebar-menu a.logout{color:#e74c3c}
        
        .main-content{padding:25px}
        
        /* Header */
        .page-header{margin-bottom:25px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .page-subtitle{color:#64748b;font-size:14px;margin-top:5px}
        
        /* Alerts */
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        .alert-warning{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
        
        /* Settings Layout */
        .settings-layout{display:grid;grid-template-columns:220px 1fr;gap:25px}
        
        /* Settings Nav */
        .settings-nav{background:#fff;border-radius:12px;padding:15px;box-shadow:0 2px 10px rgba(0,0,0,0.04);height:fit-content;position:sticky;top:25px}
        .nav-item{display:flex;align-items:center;gap:12px;padding:12px 15px;border-radius:8px;text-decoration:none;color:#64748b;font-weight:500;font-size:14px;transition:all 0.2s;margin-bottom:5px}
        .nav-item:hover{background:#f8fafc;color:#1e293b}
        .nav-item.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .nav-item .icon{font-size:18px}
        
        /* Settings Card */
        .settings-card{background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 10px rgba(0,0,0,0.04);display:none}
        .settings-card.active{display:block}
        .card-title{font-size:20px;font-weight:600;color:#1e293b;margin-bottom:8px}
        .card-subtitle{color:#64748b;font-size:14px;margin-bottom:25px;padding-bottom:20px;border-bottom:1px solid #f1f5f9}
        
        /* Form */
        .form-section{margin-bottom:30px}
        .section-title{font-size:16px;font-weight:600;color:#1e293b;margin-bottom:15px;display:flex;align-items:center;gap:8px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#374151;font-size:14px}
        .form-group label span{color:#ef4444}
        .form-control{width:100%;padding:12px 15px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;transition:all 0.2s}
        .form-control:focus{border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,0.1)}
        .form-control:disabled{background:#f8fafc;cursor:not-allowed}
        .form-hint{font-size:12px;color:#94a3b8;margin-top:5px}
        
        select.form-control{cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");background-position:right 12px center;background-repeat:no-repeat;background-size:16px;padding-right:40px}
        
        textarea.form-control{min-height:100px;resize:vertical;font-family:inherit}
        
        /* Toggle Switch */
        .toggle-group{display:flex;align-items:center;justify-content:space-between;padding:15px;background:#f8fafc;border-radius:10px;margin-bottom:12px}
        .toggle-info{flex:1}
        .toggle-label{font-weight:600;color:#1e293b;font-size:14px}
        .toggle-desc{font-size:12px;color:#64748b;margin-top:2px}
        .toggle-switch{position:relative;width:50px;height:28px}
        .toggle-switch input{opacity:0;width:0;height:0}
        .toggle-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#cbd5e1;border-radius:28px;transition:0.3s}
        .toggle-slider:before{position:absolute;content:"";height:22px;width:22px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 2px 5px rgba(0,0,0,0.2)}
        .toggle-switch input:checked + .toggle-slider{background:linear-gradient(135deg,#667eea,#764ba2)}
        .toggle-switch input:checked + .toggle-slider:before{transform:translateX(22px)}
        
        /* Buttons */
        .btn{padding:12px 25px;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px;transition:all 0.2s;display:inline-flex;align-items:center;gap:8px}
        .btn:hover{transform:translateY(-2px)}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-primary:hover{box-shadow:0 5px 20px rgba(102,126,234,0.4)}
        .btn-secondary{background:#f1f5f9;color:#475569}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-warning{background:#f59e0b;color:#fff}
        .btn-success{background:#10b981;color:#fff}
        .btn-block{width:100%}
        
        .btn-group{display:flex;gap:10px;margin-top:25px;padding-top:20px;border-top:1px solid #f1f5f9}
        
        /* Stats Grid */
        .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:25px}
        .stat-item{background:#f8fafc;border-radius:10px;padding:20px;text-align:center}
        .stat-value{font-size:28px;font-weight:700;color:#1e293b}
        .stat-label{font-size:12px;color:#64748b;margin-top:5px}
        
        /* Action Cards */
        .action-cards{display:grid;grid-template-columns:repeat(2,1fr);gap:15px}
        .action-card{background:#f8fafc;border-radius:12px;padding:20px;border:1px solid #e2e8f0}
        .action-card h4{font-size:15px;color:#1e293b;margin-bottom:8px;display:flex;align-items:center;gap:8px}
        .action-card p{font-size:13px;color:#64748b;margin-bottom:15px}
        .action-card .btn{width:100%}
        
        /* Info Box */
        .info-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:15px;margin-bottom:20px}
        .info-box p{font-size:13px;color:#1e40af;display:flex;align-items:flex-start;gap:10px}
        
        /* Version Info */
        .version-info{background:#f8fafc;border-radius:10px;padding:20px;margin-top:20px}
        .version-info h4{font-size:14px;color:#64748b;margin-bottom:10px}
        .version-info table{width:100%;font-size:13px}
        .version-info td{padding:8px 0;border-bottom:1px solid #e2e8f0}
        .version-info td:first-child{color:#64748b}
        .version-info td:last-child{color:#1e293b;font-weight:500;text-align:right}
        .version-info tr:last-child td{border-bottom:none}
        
        /* Responsive */
        @media(max-width:992px){
            .admin-layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .settings-layout{grid-template-columns:1fr}
            .settings-nav{display:flex;overflow-x:auto;gap:10px;padding:10px;position:static}
            .nav-item{white-space:nowrap;flex-shrink:0}
        }
        @media(max-width:768px){
            .form-row{grid-template-columns:1fr}
            .stats-grid{grid-template-columns:repeat(2,1fr)}
            .action-cards{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div class="page-title">⚙️ System Settings</div>
            <div class="page-subtitle">Configure your application settings</div>
        </div>
        
        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
        <?php endforeach; ?>
        
        <div class="settings-layout">
            <!-- Settings Navigation -->
            <div class="settings-nav">
                <a href="?tab=general" class="nav-item <?php echo $active_tab === 'general' ? 'active' : ''; ?>">
                    <span class="icon">🏠</span> General
                </a>
                <a href="?tab=wallet" class="nav-item <?php echo $active_tab === 'wallet' ? 'active' : ''; ?>">
                    <span class="icon">💰</span> Wallet & Payments
                </a>
                <a href="?tab=notifications" class="nav-item <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>">
                    <span class="icon">🔔</span> Notifications
                </a>
                <a href="?tab=tasks" class="nav-item <?php echo $active_tab === 'tasks' ? 'active' : ''; ?>">
                    <span class="icon">📋</span> Tasks
                </a>
                <a href="?tab=security" class="nav-item <?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                    <span class="icon">🔒</span> Security
                </a>
                <a href="?tab=maintenance" class="nav-item <?php echo $active_tab === 'maintenance' ? 'active' : ''; ?>">
                    <span class="icon">🛠️</span> Maintenance
                </a>
                <a href="?tab=payment" class="nav-item <?php echo $active_tab === 'payment' ? 'active' : ''; ?>">
                    <span class="icon">💳</span> Payment Settings
                </a>
                <a href="?tab=legal" class="nav-item <?php echo $active_tab === 'legal' ? 'active' : ''; ?>">
                    <span class="icon">📄</span> Legal Pages
                </a>
                <a href="?tab=about" class="nav-item <?php echo $active_tab === 'about' ? 'active' : ''; ?>">
                    <span class="icon">ℹ️</span> About
                </a>
            </div>
            
            <!-- Settings Content -->
            <div class="settings-content">
                <!-- General Settings -->
                <div class="settings-card <?php echo $active_tab === 'general' ? 'active' : ''; ?>" id="general">
                    <div class="card-title">🏠 General Settings</div>
                    <div class="card-subtitle">Basic application configuration</div>
                    
                    <form method="POST">
                        <input type="hidden" name="active_tab" value="general">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Site Name</label>
                                <input type="text" name="site_name" class="form-control" value="<?php echo escape($settings['site_name'] ?? APP_NAME); ?>">
                            </div>
                            <div class="form-group">
                                <label>Support Email</label>
                                <input type="email" name="support_email" class="form-control" value="<?php echo escape($settings['support_email'] ?? ''); ?>" placeholder="support@example.com">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Support WhatsApp Number</label>
                                <input type="text" name="support_whatsapp" class="form-control" value="<?php echo escape($settings['support_whatsapp'] ?? ''); ?>" placeholder="919876543210">
                                <div class="form-hint">Include country code without + (e.g., 919876543210)</div>
                            </div>
                            <div class="form-group">
                                <label>App URL</label>
                                <input type="text" class="form-control" value="<?php echo APP_URL; ?>" disabled>
                                <div class="form-hint">Edit in config.php</div>
                            </div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="save_settings" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Wallet Settings -->
                <div class="settings-card <?php echo $active_tab === 'wallet' ? 'active' : ''; ?>" id="wallet">
                    <div class="card-title">💰 Wallet & Payment Settings</div>
                    <div class="card-subtitle">Configure wallet and payment options</div>
                    
                    <form method="POST">
                        <input type="hidden" name="active_tab" value="wallet">
                        
                        <div class="form-section">
                            <div class="section-title">💳 Withdrawal Settings</div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Minimum Withdrawal Amount (₹)</label>
                                    <input type="number" name="min_withdrawal" class="form-control" value="<?php echo escape($settings['min_withdrawal'] ?? 100); ?>" min="1">
                                </div>
                                <div class="form-group">
                                    <label>Default Task Commission (₹)</label>
                                    <input type="number" name="task_commission" class="form-control" value="<?php echo escape($settings['task_commission'] ?? 10); ?>" min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-title">🎁 Bonus Settings</div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Referral Bonus Amount (₹)</label>
                                    <input type="number" name="referral_bonus" class="form-control" value="<?php echo escape($settings['referral_bonus'] ?? 50); ?>" min="0">
                                    <div class="form-hint">Bonus given when referred user completes first task</div>
                                </div>
                                <div class="form-group">
                                    <label>First Task Bonus (₹)</label>
                                    <input type="number" name="first_task_bonus" class="form-control" value="<?php echo escape($settings['first_task_bonus'] ?? 25); ?>" min="0">
                                    <div class="form-hint">Bonus for user completing their first task</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="save_settings" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Notification Settings -->
                <div class="settings-card <?php echo $active_tab === 'notifications' ? 'active' : ''; ?>" id="notifications">
                    <div class="card-title">🔔 Notification Settings</div>
                    <div class="card-subtitle">Configure notification channels</div>
                    
                    <form method="POST">
                        <input type="hidden" name="active_tab" value="notifications">
                        
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <div class="toggle-label">📧 Email Notifications</div>
                                <div class="toggle-desc">Send email notifications for important events</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_enabled" <?php echo ($settings['email_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <div class="toggle-label">📱 SMS Notifications</div>
                                <div class="toggle-desc">Send SMS for critical alerts (requires SMS gateway)</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="sms_enabled" <?php echo ($settings['sms_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <div class="toggle-label">💬 WhatsApp Notifications</div>
                                <div class="toggle-desc">Send WhatsApp messages for updates</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="whatsapp_enabled" <?php echo ($settings['whatsapp_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <div class="toggle-label">🔔 Push Notifications</div>
                                <div class="toggle-desc">Browser push notifications (PWA)</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="push_enabled" <?php echo ($settings['push_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="save_settings" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Task Settings -->
                <div class="settings-card <?php echo $active_tab === 'tasks' ? 'active' : ''; ?>" id="tasks">
                    <div class="card-title">📋 Task Settings</div>
                    <div class="card-subtitle">Configure task-related options</div>
                    
                    <form method="POST">
                        <input type="hidden" name="active_tab" value="tasks">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Default Task Deadline (Days)</label>
                                <input type="number" name="default_deadline_days" class="form-control" value="<?php echo escape($settings['default_deadline_days'] ?? 7); ?>" min="1" max="30">
                                <div class="form-hint">Default number of days for task completion</div>
                            </div>
                            <div class="form-group">
                                <label>Max Pending Tasks Per User</label>
                                <input type="number" name="max_pending_tasks" class="form-control" value="<?php echo escape($settings['max_pending_tasks'] ?? 5); ?>" min="1" max="20">
                                <div class="form-hint">Maximum incomplete tasks a user can have</div>
                            </div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="save_settings" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Security Settings -->
                <div class="settings-card <?php echo $active_tab === 'security' ? 'active' : ''; ?>" id="security">
                    <div class="card-title">🔒 Security Settings</div>
                    <div class="card-subtitle">Configure security and access options</div>
                    
                    <form method="POST">
                        <input type="hidden" name="active_tab" value="security">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Max Login Attempts</label>
                                <input type="number" name="max_login_attempts" class="form-control" value="<?php echo escape($settings['max_login_attempts'] ?? 5); ?>" min="3" max="10">
                                <div class="form-hint">Before temporary lockout</div>
                            </div>
                            <div class="form-group">
                                <label>Session Timeout (Seconds)</label>
                                <input type="number" name="session_timeout" class="form-control" value="<?php echo escape($settings['session_timeout'] ?? 3600); ?>" min="300" step="60">
                                <div class="form-hint">3600 = 1 hour</div>
                            </div>
                        </div>
                        
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <div class="toggle-label">👤 User Registration</div>
                                <div class="toggle-desc">Allow new users to register</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="registration_enabled" <?php echo ($settings['registration_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-group">
                            <div class="toggle-info">
                                <div class="toggle-label">🚧 Maintenance Mode</div>
                                <div class="toggle-desc">Disable site access for users (admin still works)</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="maintenance_mode" <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="save_settings" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                    
                    <hr style="margin:30px 0;border:none;border-top:1px solid #e2e8f0">
                    
                    <div class="card-title" style="font-size:16px">🔑 Change Admin Password</div>
                    <div class="info-box">
                        <p>⚠️ For security reasons, admin password is stored in config file and requires manual update.</p>
                    </div>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control" required minlength="8">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="8">
                            </div>
                        </div>
                        <button type="submit" name="change_admin_password" class="btn btn-warning">🔐 Change Password</button>
                    </form>
                </div>
                
                <!-- Maintenance -->
                <div class="settings-card <?php echo $active_tab === 'maintenance' ? 'active' : ''; ?>" id="maintenance">
                    <div class="card-title">🛠️ Maintenance</div>
                    <div class="card-subtitle">System maintenance and cleanup tools</div>
                    
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($total_users); ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($total_tasks); ?></div>
                            <div class="stat-label">Total Tasks</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($total_transactions); ?></div>
                            <div class="stat-label">Transactions</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($total_notifications); ?></div>
                            <div class="stat-label">Notifications</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($total_logs); ?></div>
                            <div class="stat-label">Activity Logs</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</div>
                            <div class="stat-label">Memory Usage</div>
                        </div>
                    </div>
                    
                    <div class="action-cards">
                        <div class="action-card">
                            <h4>🗑️ Clear Cache</h4>
                            <p>Clear session cache and temporary data</p>
                            <form method="POST" onsubmit="return confirm('Clear cache?')">
                                <button type="submit" name="clear_cache" class="btn btn-secondary">Clear Cache</button>
                            </form>
                        </div>
                        
                        <div class="action-card">
                            <h4>📋 Clear Old Logs</h4>
                            <p>Remove activity logs older than 30 days</p>
                            <form method="POST" onsubmit="return confirm('Clear old logs?')">
                                <button type="submit" name="clear_logs" class="btn btn-warning">Clear Logs</button>
                            </form>
                        </div>
                        
                        <div class="action-card">
                            <h4>🔔 Clear Notifications</h4>
                            <p>Remove read notifications older than 7 days</p>
                            <form method="POST" onsubmit="return confirm('Clear old notifications?')">
                                <button type="submit" name="clear_notifications" class="btn btn-secondary">Clear Notifications</button>
                            </form>
                        </div>
                        
                        <div class="action-card">
                            <h4>📥 Backup Database</h4>
                            <p>Download database backup (via phpMyAdmin)</p>
                            <a href="https://palians.com/phpmyadmin" target="_blank" class="btn btn-success">Open phpMyAdmin</a>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Settings -->
                <div class="settings-card <?php echo $active_tab === 'payment' ? 'active' : ''; ?>" id="payment">
                    <div class="card-title">💳 Payment Settings</div>
                    <div class="card-subtitle">Configure payment gateways and commission</div>
                    
                    <form method="POST">
                        <input type="hidden" name="active_tab" value="payment">
                        
                        <div class="form-section">
                            <div class="section-title">💳 Razorpay Settings</div>
                            
                            <div class="toggle-group">
                                <div class="toggle-info">
                                    <div class="toggle-label">Enable Razorpay</div>
                                    <div class="toggle-desc">Accept payments via Razorpay gateway</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="razorpay_enabled" value="1" <?php echo ($settings['razorpay_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="toggle-group">
                                <div class="toggle-info">
                                    <div class="toggle-label">Test Mode</div>
                                    <div class="toggle-desc">Use test credentials for development</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="razorpay_test_mode" value="1" <?php echo ($settings['razorpay_test_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Key ID</label>
                                    <input type="text" name="razorpay_key_id" class="form-control" value="<?php echo escape($settings['razorpay_key_id'] ?? ''); ?>" placeholder="rzp_test_xxxxx or rzp_live_xxxxx">
                                </div>
                                <div class="form-group">
                                    <label>Key Secret</label>
                                    <input type="password" name="razorpay_key_secret" class="form-control" value="<?php echo escape($settings['razorpay_key_secret'] ?? ''); ?>" placeholder="Enter secret key">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-title">💰 PayU Money Settings</div>
                            
                            <div class="toggle-group">
                                <div class="toggle-info">
                                    <div class="toggle-label">Enable PayU Money</div>
                                    <div class="toggle-desc">Accept payments via PayU Money gateway</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="payumoney_enabled" value="1" <?php echo ($settings['payumoney_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="toggle-group">
                                <div class="toggle-info">
                                    <div class="toggle-label">Test Mode</div>
                                    <div class="toggle-desc">Use test credentials for development</div>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="payumoney_test_mode" value="1" <?php echo ($settings['payumoney_test_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Merchant Key</label>
                                    <input type="text" name="payumoney_merchant_key" class="form-control" value="<?php echo escape($settings['payumoney_merchant_key'] ?? ''); ?>" placeholder="Enter merchant key">
                                </div>
                                <div class="form-group">
                                    <label>Merchant Salt</label>
                                    <input type="password" name="payumoney_merchant_salt" class="form-control" value="<?php echo escape($settings['payumoney_merchant_salt'] ?? ''); ?>" placeholder="Enter merchant salt">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-title">💵 Commission Settings</div>
                            
                            <div class="form-group">
                                <label>Admin Commission Per Review (₹)</label>
                                <input type="number" name="admin_commission_per_review" class="form-control" value="<?php echo escape($settings['admin_commission_per_review'] ?? DEFAULT_ADMIN_COMMISSION_PER_REVIEW); ?>" step="0.01" min="0" placeholder="<?php echo DEFAULT_ADMIN_COMMISSION_PER_REVIEW; ?>.00">
                                <div class="form-hint">Amount earned by admin per review completed</div>
                            </div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="save_settings" class="btn btn-primary">💾 Save Payment Settings</button>
                        </div>
                    </form>
                </div>
                
                <!-- Legal Pages -->
                <div class="settings-card <?php echo $active_tab === 'legal' ? 'active' : ''; ?>" id="legal">
                    <div class="card-title">📄 Legal Pages</div>
                    <div class="card-subtitle">Manage terms, privacy policy, and other legal content</div>
                    
                    <form method="POST">
                        <input type="hidden" name="active_tab" value="legal">
                        
                        <div class="info-box">
                            <p>💡 These pages will be displayed to users. Use HTML for formatting.</p>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-title">📋 Terms & Conditions</div>
                            <div class="form-group">
                                <textarea name="terms_content" class="form-control" style="min-height:200px" placeholder="Enter terms and conditions..."><?php echo escape($settings['terms_content'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-title">🔒 Privacy Policy</div>
                            <div class="form-group">
                                <textarea name="privacy_content" class="form-control" style="min-height:200px" placeholder="Enter privacy policy..."><?php echo escape($settings['privacy_content'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-title">💰 Refund Policy</div>
                            <div class="form-group">
                                <textarea name="refund_content" class="form-control" style="min-height:200px" placeholder="Enter refund policy..."><?php echo escape($settings['refund_content'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="section-title">⚠️ Disclaimer</div>
                            <div class="form-group">
                                <textarea name="disclaimer_content" class="form-control" style="min-height:200px" placeholder="Enter disclaimer..."><?php echo escape($settings['disclaimer_content'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="save_settings" class="btn btn-primary">💾 Save Legal Pages</button>
                        </div>
                    </form>
                </div>
                
                <!-- About -->
                <div class="settings-card <?php echo $active_tab === 'about' ? 'active' : ''; ?>" id="about">
                    <div class="card-title">ℹ️ About</div>
                    <div class="card-subtitle">System information</div>
                    
                    <div class="version-info">
                        <h4>System Information</h4>
                        <table>
                            <tr>
                                <td>Application Name</td>
                                <td><?php echo APP_NAME; ?></td>
                            </tr>
                            <tr>
                                <td>Version</td>
                                <td><?php echo defined('APP_VERSION') ? APP_VERSION : '2.0.0'; ?></td>
                            </tr>
                            <tr>
                                <td>PHP Version</td>
                                <td><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <td>MySQL Version</td>
                                <td><?php echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td>Server</td>
                                <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                            </tr>
                            <tr>
                                <td>Domain</td>
                                <td><?php echo $_SERVER['HTTP_HOST'] ?? 'Unknown'; ?></td>
                            </tr>
                            <tr>
                                <td>Server Time</td>
                                <td><?php echo date('Y-m-d H:i:s'); ?></td>
                            </tr>
                            <tr>
                                <td>Timezone</td>
                                <td><?php echo date_default_timezone_get(); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="version-info" style="margin-top:20px">
                        <h4>Features Enabled</h4>
                        <table>
                            <tr>
                                <td>Wallet System</td>
                                <td>✅ Active</td>
                            </tr>
                            <tr>
                                <td>Referral System</td>
                                <td>✅ Active</td>
                            </tr>
                            <tr>
                                <td>AI Chatbot</td>
                                <td>✅ Active</td>
                            </tr>
                            <tr>
                                <td>Email Notifications</td>
                                <td><?php echo ($settings['email_enabled'] ?? '1') === '1' ? '✅ Active' : '❌ Disabled'; ?></td>
                            </tr>
                            <tr>
                                <td>WhatsApp Integration</td>
                                <td><?php echo ($settings['whatsapp_enabled'] ?? '1') === '1' ? '✅ Active' : '❌ Disabled'; ?></td>
                            </tr>
                            <tr>
                                <td>PWA Support</td>
                                <td>✅ Active</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab navigation via URL
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', function(e) {
        // Let the link work normally to update URL
    });
});
</script>
</body>
</html>
