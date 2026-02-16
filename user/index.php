<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gamification-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Check if admin is logged in as user
$admin_viewing = false;
$original_admin = '';
if (isset($_SESSION['admin_logged_in_as_user']) && $_SESSION['admin_logged_in_as_user'] === true) {
    $admin_viewing = true;
    $original_admin = $_SESSION['original_admin_name'] ?? 'Admin';
}
$user_name = escape($_SESSION['user_name'] ?? '');

// Create wallet if not exists
createWallet($user_id);

// Get user data
$wallet_balance = (float)getWalletBalance($user_id);
$notification_count = getUnreadNotificationCount($user_id);
$notifications = getNotifications($user_id, 5);
$user_stats = getUserStats($user_id);
$referral_code = getReferralCode($user_id);

// Update stats
updateUserStats($user_id);

// Fetch tasks
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
            COUNT(CASE WHEN ts.step_status = 'completed' THEN 1 END) as completed_steps,
            DATEDIFF(t.deadline, CURDATE()) as days_left
        FROM tasks t
        LEFT JOIN task_steps ts ON t.id = ts.task_id
        WHERE t.user_id = :user_id
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    $tasks = [];
}

// Get recent transactions
try {
    $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $transactions = [];
}

// Get unread messages count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_type = 'user' AND receiver_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_messages = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $unread_messages = 0;
}

// Type cast all stats values
$tasks_completed = (int)($user_stats['tasks_completed'] ?? 0);
$tasks_pending = (int)($user_stats['tasks_pending'] ?? 0);
$total_earnings = (float)($user_stats['total_earnings'] ?? 0);
$user_rating = (float)($user_stats['rating'] ?? 5.0);
$user_level = (int)($user_stats['level'] ?? 1);
$streak_days = (int)($user_stats['streak_days'] ?? 0);

// Leaderboard summary
try {
    $user_points = getUserPoints($pdo, $user_id);
    $user_rank = (int)getUserRank($pdo, $user_id);
    $leaderboard_preview = getLeaderboard($pdo, 'all_time', 5);
} catch (PDOException $e) {
    $user_points = ['points' => 0];
    $user_rank = 0;
    $leaderboard_preview = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="<?php echo APP_URL; ?>/manifest.json">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>/assets/img/icon-192.png">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding-bottom:80px}
        
        .dashboard{max-width:1200px;margin:0 auto;padding:20px}
        
        /* Header */
        .header{background:#fff;border-radius:15px;padding:20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .user-info h1{font-size:22px;color:#333}
        .user-info p{color:#666;font-size:13px;margin-top:3px}
        .user-level{display:inline-block;background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;margin-left:8px}
        .header-actions{display:flex;gap:10px;align-items:center}
        .wallet-badge{background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff;padding:10px 18px;border-radius:25px;font-weight:600;display:flex;align-items:center;gap:8px;text-decoration:none;transition:transform 0.2s}
        .wallet-badge:hover{transform:scale(1.05)}
        .icon-btn{position:relative;width:42px;height:42px;background:#f5f5f5;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;font-size:18px;transition:background 0.2s;text-decoration:none}
        .icon-btn:hover{background:#eee}
        .icon-btn .badge{position:absolute;top:-3px;right:-3px;background:#e74c3c;color:#fff;min-width:18px;height:18px;border-radius:9px;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:600}
        .logout-btn{padding:10px 18px;background:#e74c3c;color:#fff;border:none;border-radius:8px;cursor:pointer;text-decoration:none;font-weight:600;font-size:13px}
        
        /* Stats Grid */
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px}
        .stat-card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 3px 15px rgba(0,0,0,0.08);position:relative;overflow:hidden}
        .stat-card::after{content:'';position:absolute;top:0;right:0;width:80px;height:80px;background:linear-gradient(135deg,rgba(102,126,234,0.1),rgba(118,75,162,0.1));border-radius:0 0 0 80px}
        .stat-card.wallet{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .stat-card.wallet::after{background:rgba(255,255,255,0.1)}
        .stat-card.earnings{background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff}
        .stat-card.earnings::after{background:rgba(255,255,255,0.1)}
        .stat-card.tasks{background:linear-gradient(135deg,#3498db,#2980b9);color:#fff}
        .stat-card.tasks::after{background:rgba(255,255,255,0.1)}
        .stat-card.referral{background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff}
        .stat-card.referral::after{background:rgba(255,255,255,0.1)}
        .stat-icon{font-size:28px;margin-bottom:8px}
        .stat-value{font-size:26px;font-weight:700;line-height:1.2}
        .stat-label{font-size:12px;opacity:0.9;margin-top:3px}
        .stat-action{margin-top:12px}
        .stat-action a{color:inherit;font-size:12px;opacity:0.9;text-decoration:none;border-bottom:1px dashed currentColor}
        .stat-action a:hover{opacity:1}
        
        /* Quick Actions */
        .quick-actions{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:20px}
        .action-btn{background:#fff;border-radius:12px;padding:15px 10px;text-align:center;text-decoration:none;color:#333;transition:all 0.2s;box-shadow:0 3px 10px rgba(0,0,0,0.08)}
        .action-btn:hover{transform:translateY(-3px);box-shadow:0 5px 20px rgba(0,0,0,0.12)}
        .action-btn .icon{font-size:24px;margin-bottom:6px}
        .action-btn .label{font-size:11px;font-weight:600;color:#555}
        .action-btn .badge{background:#e74c3c;color:#fff;font-size:10px;padding:2px 6px;border-radius:10px;margin-left:3px}
        
        /* Main Content Grid */
        .content-grid{display:grid;grid-template-columns:1fr 380px;gap:20px}
        
        /* Section Card */
        .section-card{background:#fff;border-radius:15px;padding:20px;box-shadow:0 5px 20px rgba(0,0,0,0.08);margin-bottom:20px}
        .section-title{font-size:17px;font-weight:600;color:#333;margin-bottom:15px;display:flex;justify-content:space-between;align-items:center}
        .section-title a{font-size:13px;color:#667eea;text-decoration:none;font-weight:500}
        
        /* Task Cards */
        .task-card{border:1px solid #eee;border-radius:12px;padding:15px;margin-bottom:12px;transition:all 0.2s;position:relative}
        .task-card:hover{border-color:#667eea;box-shadow:0 3px 15px rgba(102,126,234,0.15)}
        .task-card.urgent{border-left:4px solid #e74c3c}
        .task-card.warning{border-left:4px solid #f39c12}
        .task-card.completed{border-left:4px solid #27ae60;opacity:0.8}
        .task-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
        .task-id{font-weight:600;color:#333;font-size:15px}
        .task-commission{font-size:13px;color:#27ae60;font-weight:600}
        .task-badge{padding:4px 10px;border-radius:12px;font-size:10px;font-weight:600;text-transform:uppercase}
        .badge-pending{background:#ffeaa7;color:#d63031}
        .badge-completed{background:#55efc4;color:#00b894}
        .badge-urgent{background:#fab1a0;color:#d63031}
        .badge-warning{background:#ffeaa7;color:#fdcb6e}
        .progress-bar{height:6px;background:#eee;border-radius:3px;overflow:hidden;margin:10px 0}
        .progress-fill{height:100%;background:linear-gradient(90deg,#667eea,#764ba2);border-radius:3px;transition:width 0.3s}
        .task-meta{display:flex;gap:12px;font-size:11px;color:#888;flex-wrap:wrap}
        .task-meta span{display:flex;align-items:center;gap:3px}
        .task-actions{margin-top:12px;display:flex;gap:8px}
        .task-actions a{padding:8px 14px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;transition:all 0.2s}
        .task-actions a:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(102,126,234,0.3)}
        .task-actions a.secondary{background:#f5f5f5;color:#666}
        
        /* Empty State */
        .empty-state{text-align:center;padding:40px 20px;color:#999}
        .empty-state .icon{font-size:50px;margin-bottom:15px;opacity:0.5}
        .empty-state h3{color:#666;margin-bottom:8px;font-size:16px}
        .empty-state p{font-size:13px}
        
        /* Widget */
        .widget{background:#fff;border-radius:15px;padding:20px;box-shadow:0 5px 20px rgba(0,0,0,0.08);margin-bottom:20px}
        .widget-title{font-size:15px;font-weight:600;color:#333;margin-bottom:15px;display:flex;align-items:center;gap:8px}
        
        /* Referral Box */
        .referral-box{background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;border-radius:12px;padding:20px;text-align:center}
        .referral-box p{font-size:13px;opacity:0.9;margin-bottom:10px}
        .referral-code{background:rgba(255,255,255,0.2);padding:12px 20px;border-radius:8px;font-size:22px;font-weight:700;letter-spacing:3px;margin:10px 0}
        .copy-btn{background:#fff;color:#f39c12;border:none;padding:10px 25px;border-radius:20px;font-weight:600;cursor:pointer;font-size:13px;transition:transform 0.2s}
        .copy-btn:hover{transform:scale(1.05)}
        .share-btns{display:flex;gap:10px;justify-content:center;margin-top:15px}
        .share-btn{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;color:#fff;font-size:18px;transition:transform 0.2s}
        .share-btn:hover{transform:scale(1.1)}
        .share-btn.whatsapp{background:#25d366}
        .share-btn.telegram{background:#0088cc}
        .share-btn.twitter{background:#1da1f2}
        
        /* Transactions */
        .transaction-item{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #f5f5f5}
        .transaction-item:last-child{border-bottom:none}
        .txn-info{flex:1;min-width:0}
        .txn-type{font-size:13px;font-weight:600;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .txn-date{font-size:11px;color:#999;margin-top:2px}
        .txn-amount{font-weight:600;font-size:14px;white-space:nowrap;margin-left:10px}
        .txn-amount.credit{color:#27ae60}
        .txn-amount.debit{color:#e74c3c}
        
        /* Notification Dropdown */
        .notification-wrapper{position:relative}
        .notification-dropdown{position:absolute;top:50px;right:0;width:340px;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);display:none;z-index:1000;overflow:hidden}
        .notification-dropdown.show{display:block}
        .notification-header{padding:15px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
        .notification-header h4{font-size:14px;color:#333}
        .notification-header a{font-size:12px;color:#667eea;text-decoration:none}
        .notification-list{max-height:350px;overflow-y:auto}
        .notification-item{padding:12px 15px;border-bottom:1px solid #f5f5f5;cursor:pointer;transition:background 0.2s}
        .notification-item:hover{background:#f9f9f9}
        .notification-item.unread{background:#f0f7ff}
        .notification-item .title{font-weight:600;font-size:13px;color:#333}
        .notification-item .message{font-size:12px;color:#666;margin-top:3px;line-height:1.4}
        .notification-item .time{font-size:10px;color:#999;margin-top:5px}
        .notification-empty{padding:30px;text-align:center;color:#999;font-size:13px}
        
        /* Chat Button & Window */
        .chat-toggle{position:fixed;bottom:25px;right:25px;width:60px;height:60px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:50%;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;font-size:28px;box-shadow:0 5px 20px rgba(102,126,234,0.4);transition:transform 0.2s;z-index:999}
        .chat-toggle:hover{transform:scale(1.1)}
        
        .chat-window{position:fixed;bottom:100px;right:25px;width:380px;max-width:calc(100vw - 50px);height:500px;max-height:calc(100vh - 150px);background:#fff;border-radius:20px;box-shadow:0 10px 50px rgba(0,0,0,0.2);display:none;flex-direction:column;z-index:1000;overflow:hidden}
        .chat-window.show{display:flex}
        .chat-header{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:18px 20px;display:flex;justify-content:space-between;align-items:center}
        .chat-header h4{margin:0;font-size:16px;font-weight:600}
        .chat-close{background:none;border:none;color:#fff;font-size:28px;cursor:pointer;line-height:1;opacity:0.8}
        .chat-close:hover{opacity:1}
        .chat-messages{flex:1;padding:20px;overflow-y:auto;background:#f8f9fa}
        .chat-message{margin-bottom:12px;display:flex}
        .chat-message.user{justify-content:flex-end}
        .chat-message.bot{justify-content:flex-start}
        .chat-bubble{max-width:80%;padding:12px 16px;border-radius:18px;font-size:14px;line-height:1.4}
        .chat-message.bot .chat-bubble{background:#fff;color:#333;border-bottom-left-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.05)}
        .chat-message.user .chat-bubble{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-bottom-right-radius:5px}
        .chat-input-area{padding:15px;background:#fff;border-top:1px solid #eee;display:flex;gap:10px}
        .chat-input-area input{flex:1;padding:12px 16px;border:1px solid #ddd;border-radius:25px;font-size:14px;outline:none}
        .chat-input-area input:focus{border-color:#667eea}
        .chat-input-area button{width:45px;height:45px;background:linear-gradient(135deg,#667eea,#764ba2);border:none;border-radius:50%;color:#fff;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center}
        .typing-indicator{display:flex;gap:4px;padding:12px 16px;background:#fff;border-radius:18px;border-bottom-left-radius:5px;width:fit-content;box-shadow:0 2px 5px rgba(0,0,0,0.05)}
        .typing-indicator span{width:8px;height:8px;background:#667eea;border-radius:50%;animation:typing 1s infinite}
        .typing-indicator span:nth-child(2){animation-delay:0.2s}
        .typing-indicator span:nth-child(3){animation-delay:0.4s}
        @keyframes typing{0%,100%{opacity:0.3;transform:scale(0.8)}50%{opacity:1;transform:scale(1)}}
        
        /* Responsive */
        @media(max-width:1024px){
            .content-grid{grid-template-columns:1fr}
            .stats-grid{grid-template-columns:repeat(2,1fr)}
            .quick-actions{grid-template-columns:repeat(3,1fr)}
        }
        @media(max-width:768px){
            .dashboard{padding:15px}
            .header{flex-direction:column;text-align:center}
            .header-actions{width:100%;justify-content:center;flex-wrap:wrap}
            .stats-grid{grid-template-columns:1fr 1fr}
            .stat-value{font-size:22px}
            .quick-actions{grid-template-columns:repeat(3,1fr)}
            .notification-dropdown{width:calc(100vw - 30px);right:-60px}
            .chat-window{right:15px;bottom:90px;width:calc(100vw - 30px);height:calc(100vh - 120px)}
            .chat-toggle{right:15px;bottom:15px;width:55px;height:55px}
        }
        @media(max-width:480px){
            .header-actions{gap:8px}
            .wallet-badge{padding:8px 12px;font-size:13px}
            .icon-btn{width:38px;height:38px;font-size:16px}
            .logout-btn{padding:8px 12px;font-size:12px}
            .quick-actions{grid-template-columns:repeat(3,1fr);gap:8px}
            .action-btn{padding:12px 8px}
            .action-btn .icon{font-size:20px}
            .action-btn .label{font-size:10px}
        }
    </style>
</head>
<body>
<?php if ($admin_viewing): ?>
<div style="background:linear-gradient(135deg,#e74c3c,#c0392b);color:#fff;padding:12px 20px;text-align:center;position:fixed;top:0;left:0;right:0;z-index:9999;box-shadow:0 2px 10px rgba(0,0,0,0.3);">
    ⚠️ Viewing as <strong><?php echo escape($user_name); ?></strong> (Admin: <?php echo escape($original_admin); ?>)
    <a href="<?php echo APP_URL; ?>/admin-return.php" style="background:#fff;color:#e74c3c;padding:6px 15px;border-radius:5px;margin-left:15px;text-decoration:none;font-weight:600;">← Return to Admin</a>
</div>
<style>.dashboard{padding-top:55px !important}</style>
<?php endif; ?>
<div class="dashboard">
    <!-- Header -->
    <div class="header">
        <div class="user-info">
            <h1>👋 Welcome, <?php echo $user_name; ?>!<span class="user-level">Lvl <?php echo $user_level; ?></span></h1>
            <p>📊 <?php echo $tasks_completed; ?> tasks completed • ⭐ <?php echo number_format($user_rating, 1); ?> rating</p>
        </div>
        <div class="header-actions">
            <a href="<?php echo APP_URL; ?>/user/wallet.php" class="wallet-badge">
                💰 ₹<?php echo number_format($wallet_balance, 2); ?>
            </a>
            <div class="notification-wrapper">
                <button class="icon-btn" onclick="toggleNotifications()" title="Notifications">
                    🔔
                    <?php if ($notification_count > 0): ?>
                        <span class="badge"><?php echo $notification_count > 9 ? '9+' : $notification_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h4>🔔 Notifications</h4>
                        <a href="<?php echo APP_URL; ?>/user/notifications.php">View All</a>
                    </div>
                    <div class="notification-list">
                        <?php if (empty($notifications)): ?>
                            <div class="notification-empty">No notifications yet</div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" onclick="window.location='<?php echo APP_URL; ?>/user/notifications.php'">
                                    <div class="title"><?php echo escape($notif['title']); ?></div>
                                    <div class="message"><?php echo escape(substr($notif['message'], 0, 60)); ?>...</div>
                                    <div class="time"><?php echo date('d M, H:i', strtotime($notif['created_at'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <a href="<?php echo APP_URL; ?>/user/messages.php" class="icon-btn" title="Messages">
                💬
                <?php if ($unread_messages > 0): ?>
                    <span class="badge"><?php echo $unread_messages > 9 ? '9+' : $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo APP_URL; ?>/user/profile.php" class="icon-btn" title="Profile">👤</a>
            <a href="<?php echo APP_URL; ?>/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card wallet">
            <div class="stat-icon">💰</div>
            <div class="stat-value">₹<?php echo number_format($wallet_balance, 2); ?></div>
            <div class="stat-label">Wallet Balance</div>
            <div class="stat-action"><a href="<?php echo APP_URL; ?>/user/wallet.php">Withdraw →</a></div>
        </div>
        <div class="stat-card earnings">
            <div class="stat-icon">📈</div>
            <div class="stat-value">₹<?php echo number_format($total_earnings, 2); ?></div>
            <div class="stat-label">Total Earnings</div>
            <div class="stat-action"><a href="<?php echo APP_URL; ?>/user/transactions.php">View History →</a></div>
        </div>
        <div class="stat-card tasks">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $tasks_completed; ?></div>
            <div class="stat-label">Tasks Completed</div>
            <div class="stat-action"><a href="#tasks">View Tasks →</a></div>
        </div>
        <div class="stat-card referral">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo $referral_code; ?></div>
            <div class="stat-label">Referral Code</div>
            <div class="stat-action"><a href="<?php echo APP_URL; ?>/user/referral.php">Share & Earn →</a></div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="<?php echo APP_URL; ?>/user/wallet.php" class="action-btn">
            <div class="icon">💳</div>
            <div class="label">Wallet</div>
        </a>
        <a href="<?php echo APP_URL; ?>/user/referral.php" class="action-btn">
            <div class="icon">🎁</div>
            <div class="label">Refer & Earn</div>
        </a>
        <a href="<?php echo APP_URL; ?>/user/profile.php" class="action-btn">
            <div class="icon">👤</div>
            <div class="label">Profile</div>
        </a>
        <a href="<?php echo APP_URL; ?>/user/messages.php" class="action-btn">
            <div class="icon">💬</div>
            <div class="label">Messages<?php if($unread_messages > 0): ?><span class="badge"><?php echo $unread_messages; ?></span><?php endif; ?></div>
        </a>
        <a href="<?php echo APP_URL; ?>/user/transactions.php" class="action-btn">
            <div class="icon">📊</div>
            <div class="label">History</div>
        </a>
        <a href="<?php echo APP_URL; ?>/help.php" class="action-btn">
            <div class="icon">❓</div>
            <div class="label">Help</div>
        </a>
    </div>
    
    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Left Column - Tasks -->
        <div class="main-column">
            <div class="section-card" id="tasks">
                <div class="section-title">
                    <span>📋 My Tasks</span>
                    <span style="font-size:13px;color:#888"><?php echo count($tasks); ?> total</span>
                </div>
                
                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <h3>No Tasks Assigned Yet</h3>
                        <p>Admin will assign tasks to you soon. Check back later!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): 
                        $completed_steps = (int)($task['completed_steps'] ?? 0);
                        $progress = ($completed_steps / 4) * 100;
                        $is_completed = ($task['task_status'] ?? '') === 'completed';
                        $days_left = $task['days_left'];
                        $is_urgent = !$is_completed && $days_left !== null && $days_left >= 0 && $days_left <= 1;
                        $is_warning = !$is_completed && $days_left !== null && $days_left > 1 && $days_left <= 3;
                        $is_overdue = !$is_completed && $days_left !== null && $days_left < 0;
                        $commission = (float)($task['commission'] ?? 0);
                    ?>
                        <div class="task-card <?php echo $is_completed ? 'completed' : ($is_overdue || $is_urgent ? 'urgent' : ($is_warning ? 'warning' : '')); ?>">
                            <div class="task-header">
                                <div>
                                    <span class="task-id">Task #<?php echo $task['id']; ?></span>
                                    <?php if ($commission > 0): ?>
                                        <span class="task-commission">💰 ₹<?php echo number_format($commission, 0); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_completed): ?>
                                    <span class="task-badge badge-completed">✓ Completed</span>
                                <?php elseif ($is_overdue): ?>
                                    <span class="task-badge badge-urgent">⚠️ Overdue</span>
                                <?php elseif ($is_urgent): ?>
                                    <span class="task-badge badge-urgent">🔥 Due Today</span>
                                <?php elseif ($is_warning): ?>
                                    <span class="task-badge badge-warning">⏰ Due Soon</span>
                                <?php else: ?>
                                    <span class="task-badge badge-pending">In Progress</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="progress-bar">
                                <div class="progress-fill" style="width:<?php echo $progress; ?>%"></div>
                            </div>
                            
                            <div class="task-meta">
                                <span>📊 <?php echo $completed_steps; ?>/4 Steps</span>
                                <span>📅 <?php echo date('d M Y', strtotime($task['created_at'])); ?></span>
                                <?php if (!empty($task['deadline'])): ?>
                                    <span>⏰ Due: <?php echo date('d M', strtotime($task['deadline'])); ?>
                                        <?php if (!$is_completed && $days_left !== null): ?>
                                            (<?php echo $is_overdue ? abs($days_left) . 'd overdue' : $days_left . 'd left'; ?>)
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($task['priority']) && $task['priority'] !== 'medium'): ?>
                                    <span>🎯 <?php echo ucfirst($task['priority']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="task-actions">
                                <?php if (!$is_completed): ?>
                                    <?php if ($completed_steps == 0): ?>
                                        <a href="<?php echo APP_URL; ?>/user/submit-order.php?task_id=<?php echo $task['id']; ?>">Start Task →</a>
                                    <?php elseif ($completed_steps == 1): ?>
                                        <a href="<?php echo APP_URL; ?>/user/submit-delivery.php?task_id=<?php echo $task['id']; ?>">Continue Step 2 →</a>
                                    <?php elseif ($completed_steps == 2): ?>
                                        <a href="<?php echo APP_URL; ?>/user/submit-review.php?task_id=<?php echo $task['id']; ?>">Continue Step 3 →</a>
                                    <?php elseif ($completed_steps == 3): ?>
                                        <a href="<?php echo APP_URL; ?>/user/submit-refund.php?task_id=<?php echo $task['id']; ?>">Final Step →</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <a href="<?php echo APP_URL; ?>/user/task-detail.php?task_id=<?php echo $task['id']; ?>" class="secondary">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Right Column - Widgets -->
        <div class="sidebar-column">
            <!-- Leaderboard Widget -->
            <div class="widget">
                <div class="widget-title">🏆 Leaderboard</div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <div style="font-size:13px;color:#666">Your Rank</div>
                    <div style="font-size:18px;font-weight:700;color:#667eea">#<?php echo $user_rank ?: '-'; ?></div>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                    <div style="font-size:13px;color:#666">Your Points</div>
                    <div style="font-size:16px;font-weight:700;color:#27ae60"><?php echo number_format((float)($user_points['points'] ?? 0)); ?></div>
                </div>
                <?php if (empty($leaderboard_preview)): ?>
                    <div class="empty-state" style="padding:16px">
                        <p>No leaderboard data yet</p>
                    </div>
                <?php else: ?>
                    <div style="display:grid;gap:8px">
                        <?php foreach ($leaderboard_preview as $index => $leader): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;background:#f8f9fa;padding:10px;border-radius:8px">
                                <div style="font-size:13px;font-weight:600;color:#333">
                                    #<?php echo $index + 1; ?> <?php echo escape($leader['username']); ?>
                                </div>
                                <div style="font-size:12px;color:#666">
                                    <?php echo number_format((float)$leader['points']); ?> pts
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a href="<?php echo APP_URL; ?>/user/leaderboard.php" style="display:block;text-align:center;margin-top:12px;color:#667eea;font-size:13px;text-decoration:none">View Full Leaderboard →</a>
            </div>
            <!-- Referral Widget -->
            <div class="widget">
                <div class="widget-title">🎁 Refer & Earn ₹<?php echo getSetting('referral_bonus', 50); ?></div>
                <div class="referral-box">
                    <p>Share your code with friends and earn bonus!</p>
                    <div class="referral-code" id="referralCode"><?php echo $referral_code; ?></div>
                    <button class="copy-btn" onclick="copyReferralCode()">📋 Copy Code</button>
                    <div class="share-btns">
                        <a href="https://wa.me/?text=<?php echo urlencode("🎁 Join " . APP_NAME . " and earn money!\n\nUse my referral code: " . $referral_code . "\n\n👉 " . APP_URL); ?>" target="_blank" class="share-btn whatsapp">📱</a>
                        <a href="https://t.me/share/url?url=<?php echo urlencode(APP_URL); ?>&text=<?php echo urlencode("Join " . APP_NAME . "! Use code: " . $referral_code); ?>" target="_blank" class="share-btn telegram">✈️</a>
                        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode("Join " . APP_NAME . " and earn! Use my code: " . $referral_code . " " . APP_URL); ?>" target="_blank" class="share-btn twitter">🐦</a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Transactions -->
            <div class="widget">
                <div class="widget-title">💳 Recent Transactions</div>
                <?php if (empty($transactions)): ?>
                    <div class="empty-state" style="padding:20px">
                        <p>No transactions yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($transactions as $txn): ?>
                        <div class="transaction-item">
                            <div class="txn-info">
                                <div class="txn-type"><?php echo escape($txn['description'] ?? 'Transaction'); ?></div>
                                <div class="txn-date"><?php echo date('d M, H:i', strtotime($txn['created_at'])); ?></div>
                            </div>
                            <div class="txn-amount <?php echo in_array($txn['type'], ['credit', 'bonus', 'referral']) ? 'credit' : 'debit'; ?>">
                                <?php echo in_array($txn['type'], ['credit', 'bonus', 'referral']) ? '+' : '-'; ?>₹<?php echo number_format((float)($txn['amount'] ?? 0), 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="<?php echo APP_URL; ?>/user/transactions.php" style="display:block;text-align:center;margin-top:15px;color:#667eea;font-size:13px;text-decoration:none">View All Transactions →</a>
                <?php endif; ?>
            </div>
            
            <!-- Quick Stats Widget -->
            <div class="widget">
                <div class="widget-title">📊 Your Stats</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center">
                        <div style="font-size:20px;font-weight:700;color:#667eea"><?php echo $tasks_completed; ?></div>
                        <div style="font-size:11px;color:#666">Completed</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center">
                        <div style="font-size:20px;font-weight:700;color:#f39c12"><?php echo $tasks_pending; ?></div>
                        <div style="font-size:11px;color:#666">Pending</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center">
                        <div style="font-size:20px;font-weight:700;color:#27ae60">⭐ <?php echo number_format($user_rating, 1); ?></div>
                        <div style="font-size:11px;color:#666">Rating</div>
                    </div>
                    <div style="background:#f8f9fa;padding:12px;border-radius:8px;text-align:center">
                        <div style="font-size:20px;font-weight:700;color:#e74c3c"><?php echo $streak_days; ?></div>
                        <div style="font-size:11px;color:#666">Day Streak</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chat Toggle Button -->
<button class="chat-toggle" onclick="toggleChat()" title="AI Support Chat">💬</button>

<!-- Chat Window -->
<div class="chat-window" id="chatWindow">
    <div class="chat-header">
        <h4>🤖 AI Support</h4>
        <button class="chat-close" onclick="toggleChat()">&times;</button>
    </div>
    <div class="chat-messages" id="chatMessages">
        <div class="chat-message bot">
            <div class="chat-bubble">Hi <?php echo $user_name; ?>! 👋 How can I help you today?</div>
        </div>
    </div>
    <div class="chat-input-area">
        <input type="text" id="chatInput" placeholder="Type your message..." autocomplete="off">
        <button onclick="sendChatMessage()">➤</button>
    </div>
</div>

<script>
// Notification Toggle
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.notification-wrapper')) {
        document.getElementById('notificationDropdown').classList.remove('show');
    }
});

// Copy Referral Code
function copyReferralCode() {
    const code = document.getElementById('referralCode').innerText;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(code).then(() => {
            alert('✓ Referral code copied: ' + code);
        });
    } else {
        const input = document.createElement('input');
        input.value = code;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        alert('✓ Referral code copied: ' + code);
    }
}

// Chat Toggle
function toggleChat() {
    const chat = document.getElementById('chatWindow');
    chat.classList.toggle('show');
    if (chat.classList.contains('show')) {
        document.getElementById('chatInput').focus();
    }
}

// Send Chat Message
function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message) return;
    
    const messages = document.getElementById('chatMessages');
    
    messages.innerHTML += `<div class="chat-message user"><div class="chat-bubble">${escapeHtml(message)}</div></div>`;
    input.value = '';
    messages.scrollTop = messages.scrollHeight;
    
    const typingId = 'typing-' + Date.now();
    messages.innerHTML += `<div class="chat-message bot" id="${typingId}"><div class="typing-indicator"><span></span><span></span><span></span></div></div>`;
    messages.scrollTop = messages.scrollHeight;
    
    fetch('<?php echo APP_URL; ?>/chatbot/api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({message: message})
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById(typingId)?.remove();
        const response = data.response || 'Sorry, something went wrong.';
        messages.innerHTML += `<div class="chat-message bot"><div class="chat-bubble">${response.replace(/\n/g, '<br>')}</div></div>`;
        messages.scrollTop = messages.scrollHeight;
    })
    .catch(err => {
        document.getElementById(typingId)?.remove();
        messages.innerHTML += `<div class="chat-message bot"><div class="chat-bubble">Connection error. Please try again.</div></div>`;
        messages.scrollTop = messages.scrollHeight;
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('chatInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') sendChatMessage();
});

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?php echo APP_URL; ?>/sw.js').catch(() => {});
}
</script>
</body>
</html>
