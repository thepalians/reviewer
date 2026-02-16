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

// Get dashboard stats
try {
    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'user'");
    $total_users = (int)$stmt->fetchColumn();
    
    // Active Users (logged in last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'user' AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $active_users = (int)$stmt->fetchColumn();
    
    // New Users (this month)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'user' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
    $new_users_month = (int)$stmt->fetchColumn();
    
    // Total Tasks
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
    $total_tasks = (int)$stmt->fetchColumn();
    
    // Pending Tasks (Step 1 completed, waiting for refund)
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks = (int)$stmt->fetchColumn();
    
    // Completed Tasks
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'completed'");
    $completed_tasks = (int)$stmt->fetchColumn();
    
    // Total Wallet Balance (all users)
    $stmt = $pdo->query("SELECT COALESCE(SUM(balance), 0) FROM user_wallet");
    $total_wallet_balance = (float)$stmt->fetchColumn();
    
    // Total Paid Out
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = 'completed'");
    $total_paid = (float)$stmt->fetchColumn();
    
    // Pending Withdrawals
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    // Pending Wallet Recharge Requests
    $stmt = $pdo->query("SELECT COUNT(*) FROM wallet_recharge_requests WHERE status = 'pending'");
    $pending_wallet_recharges = (int)$stmt->fetchColumn();
    
    // Pending Withdrawal Amount
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawal_amount = (float)$stmt->fetchColumn();
    
    // Unread Messages
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND is_read = 0");
    $unread_messages = (int)$stmt->fetchColumn();
    
    // Unanswered Chatbot Questions
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_questions = (int)$stmt->fetchColumn();
    
    // Seller Stats
    $stmt = $pdo->query("SELECT COUNT(*) FROM sellers");
    $total_sellers = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM sellers WHERE status = 'active'");
    $active_sellers = (int)$stmt->fetchColumn();
    
    // Revenue from Sellers
    $stmt = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM review_requests WHERE payment_status = 'paid'");
    $total_seller_revenue = (float)$stmt->fetchColumn();
    
    // Pending Review Request Approvals
    $stmt = $pdo->query("SELECT COUNT(*) FROM review_requests WHERE admin_status = 'pending' AND payment_status = 'paid'");
    $pending_approvals = (int)$stmt->fetchColumn();
    
    // Revenue Stats (this month)
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN type IN ('credit', 'bonus', 'referral') THEN amount END), 0) as total_credited,
            COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount END), 0) as total_withdrawn
        FROM wallet_transactions 
        WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ");
    $month_stats = $stmt->fetch();
    
    // Daily registrations (last 7 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users 
        WHERE user_type = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $daily_registrations = $stmt->fetchAll();
    
    // Daily tasks (last 7 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM tasks 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $daily_tasks = $stmt->fetchAll();
    
    // Monthly revenue (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(CASE WHEN type IN ('credit', 'bonus', 'referral') THEN amount ELSE 0 END) as credited,
            SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as withdrawn
        FROM wallet_transactions
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_revenue = $stmt->fetchAll();
    
    // Recent Activities
    $stmt = $pdo->query("
        SELECT 'user' as type, name as title, 'New user registered' as description, created_at 
        FROM users WHERE user_type = 'user' 
        UNION ALL
        SELECT 'task' as type, CONCAT('Task #', id) as title, 'Task created' as description, created_at 
        FROM tasks
        UNION ALL
        SELECT 'withdrawal' as type, CONCAT('₹', amount) as title, CONCAT('Withdrawal ', status) as description, created_at 
        FROM withdrawal_requests
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll();
    
    // Recent Users
    $stmt = $pdo->query("SELECT id, name, email, mobile, created_at, last_login FROM users WHERE user_type = 'user' ORDER BY created_at DESC LIMIT 5");
    $recent_users = $stmt->fetchAll();
    
    // Pending Withdrawal Requests
    $stmt = $pdo->query("
        SELECT wr.*, u.name as user_name, u.email as user_email
        FROM withdrawal_requests wr
        JOIN users u ON wr.user_id = u.id
        WHERE wr.status = 'pending'
        ORDER BY wr.created_at ASC
        LIMIT 5
    ");
    $pending_withdrawal_list = $stmt->fetchAll();
    
    // Top Earners
    $stmt = $pdo->query("
        SELECT u.id, u.name, us.total_earnings, us.tasks_completed
        FROM user_stats us
        JOIN users u ON us.user_id = u.id
        ORDER BY us.total_earnings DESC
        LIMIT 5
    ");
    $top_earners = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $total_users = $active_users = $new_users_month = $total_tasks = $pending_tasks = $completed_tasks = 0;
    $total_wallet_balance = $total_paid = $pending_withdrawal_amount = 0;
    $pending_withdrawals = $unread_messages = $unanswered_questions = 0;
    $total_sellers = $active_sellers = $total_seller_revenue = $pending_approvals = 0;
    $month_stats = ['total_credited' => 0, 'total_withdrawn' => 0];
    $daily_registrations = $daily_tasks = $monthly_revenue = [];
    $recent_activities = $recent_users = $pending_withdrawal_list = $top_earners = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        
        /* Layout */
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
        .sidebar-menu a.logout:hover{background:rgba(231,76,60,0.1)}
        
        /* Main Content */
        .main-content{padding:25px;overflow-x:hidden}
        
        /* Header */
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:15px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .page-subtitle{color:#64748b;font-size:14px;margin-top:5px}
        .header-actions{display:flex;gap:10px}
        .header-btn{padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s;display:flex;align-items:center;gap:8px;text-decoration:none}
        .header-btn.primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .header-btn.secondary{background:#fff;color:#64748b;border:1px solid #e2e8f0}
        .header-btn:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(0,0,0,0.1)}
        
        /* Stats Grid */
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:15px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,0.04);position:relative;overflow:hidden;transition:transform 0.2s}
        .stat-card:hover{transform:translateY(-3px);box-shadow:0 5px 20px rgba(0,0,0,0.08)}
        .stat-card::after{content:'';position:absolute;top:0;right:0;width:100px;height:100px;border-radius:50%;opacity:0.1;transform:translate(30%,-30%)}
        .stat-card.blue::after{background:#3b82f6}
        .stat-card.green::after{background:#10b981}
        .stat-card.orange::after{background:#f59e0b}
        .stat-card.purple::after{background:#8b5cf6}
        .stat-card.red::after{background:#ef4444}
        .stat-card.cyan::after{background:#06b6d4}
        .stat-icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:15px}
        .stat-card.blue .stat-icon{background:#eff6ff;color:#3b82f6}
        .stat-card.green .stat-icon{background:#ecfdf5;color:#10b981}
        .stat-card.orange .stat-icon{background:#fffbeb;color:#f59e0b}
        .stat-card.purple .stat-icon{background:#f5f3ff;color:#8b5cf6}
        .stat-card.red .stat-icon{background:#fef2f2;color:#ef4444}
        .stat-card.cyan .stat-icon{background:#ecfeff;color:#06b6d4}
        .stat-value{font-size:28px;font-weight:700;color:#1e293b;margin-bottom:5px}
        .stat-label{font-size:13px;color:#64748b}
        .stat-change{font-size:12px;margin-top:10px;display:flex;align-items:center;gap:5px}
        .stat-change.up{color:#10b981}
        .stat-change.down{color:#ef4444}
        
        /* Alert Cards */
        .alert-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:25px}
        .alert-card{background:#fff;border-radius:12px;padding:18px;display:flex;align-items:center;gap:15px;box-shadow:0 2px 10px rgba(0,0,0,0.04);border-left:4px solid}
        .alert-card.warning{border-left-color:#f59e0b;background:#fffbeb}
        .alert-card.danger{border-left-color:#ef4444;background:#fef2f2}
        .alert-card.info{border-left-color:#3b82f6;background:#eff6ff}
        .alert-icon{font-size:24px}
        .alert-content{flex:1}
        .alert-title{font-weight:600;color:#1e293b;font-size:14px}
        .alert-desc{font-size:12px;color:#64748b;margin-top:2px}
        .alert-action{padding:8px 15px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;color:#fff}
        .alert-card.warning .alert-action{background:#f59e0b}
        .alert-card.danger .alert-action{background:#ef4444}
        .alert-card.info .alert-action{background:#3b82f6}
        
        /* Charts Row */
        .charts-row{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:25px}
        .chart-card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .chart-title{font-size:16px;font-weight:600;color:#1e293b;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
        .chart-title span{font-size:12px;color:#64748b;font-weight:400}
        
        /* Bar Chart */
        .bar-chart{display:flex;align-items:flex-end;gap:12px;height:200px;padding:10px 0}
        .bar-group{flex:1;display:flex;flex-direction:column;align-items:center}
        .bar-wrapper{display:flex;gap:4px;align-items:flex-end;height:160px}
        .bar{width:18px;border-radius:4px 4px 0 0;transition:height 0.5s}
        .bar.primary{background:linear-gradient(180deg,#667eea,#764ba2)}
        .bar.secondary{background:linear-gradient(180deg,#10b981,#059669)}
        .bar-label{font-size:11px;color:#64748b;margin-top:10px}
        
        /* Donut Chart */
        .donut-chart{display:flex;align-items:center;gap:30px}
        .donut{width:150px;height:150px;border-radius:50%;background:conic-gradient(#667eea 0deg calc(3.6deg * var(--completed)),#f59e0b calc(3.6deg * var(--completed)) calc(3.6deg * var(--completed) + 3.6deg * var(--pending)),#e2e8f0 calc(3.6deg * var(--completed) + 3.6deg * var(--pending)) 360deg);position:relative}
        .donut::after{content:'';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:100px;height:100px;background:#fff;border-radius:50%}
        .donut-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;z-index:1}
        .donut-value{font-size:28px;font-weight:700;color:#1e293b}
        .donut-label{font-size:11px;color:#64748b}
        .donut-legend{flex:1}
        .legend-item{display:flex;align-items:center;gap:10px;margin-bottom:12px}
        .legend-color{width:12px;height:12px;border-radius:3px}
        .legend-text{font-size:13px;color:#64748b}
        .legend-value{margin-left:auto;font-weight:600;color:#1e293b}
        
        /* Tables */
        .table-card{background:#fff;border-radius:15px;box-shadow:0 2px 10px rgba(0,0,0,0.04);overflow:hidden;margin-bottom:25px}
        .table-header{padding:20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center}
        .table-title{font-size:16px;font-weight:600;color:#1e293b}
        .table-action{font-size:13px;color:#667eea;text-decoration:none;font-weight:500}
        table{width:100%;border-collapse:collapse}
        th{background:#f8fafc;padding:12px 20px;text-align:left;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px}
        td{padding:15px 20px;border-bottom:1px solid #f1f5f9;font-size:14px;color:#334155}
        tr:last-child td{border-bottom:none}
        tr:hover{background:#f8fafc}
        
        .user-cell{display:flex;align-items:center;gap:12px}
        .user-avatar{width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:14px}
        .user-info{line-height:1.4}
        .user-name{font-weight:600;color:#1e293b}
        .user-email{font-size:12px;color:#64748b}
        
        .status-badge{padding:5px 12px;border-radius:20px;font-size:11px;font-weight:600}
        .status-badge.active{background:#ecfdf5;color:#059669}
        .status-badge.pending{background:#fffbeb;color:#d97706}
        .status-badge.completed{background:#eff6ff;color:#2563eb}
        
        .amount{font-weight:600}
        .amount.positive{color:#059669}
        .amount.negative{color:#dc2626}
        
        .action-btn{padding:6px 12px;border-radius:6px;font-size:12px;font-weight:500;border:none;cursor:pointer;margin-right:5px}
        .action-btn.approve{background:#ecfdf5;color:#059669}
        .action-btn.reject{background:#fef2f2;color:#dc2626}
        .action-btn.view{background:#f1f5f9;color:#475569}
        
        /* Activity Feed */
        .activity-feed{max-height:350px;overflow-y:auto;padding:0 20px 20px}
        .activity-item{display:flex;gap:15px;padding:15px 0;border-bottom:1px solid #f1f5f9}
        .activity-item:last-child{border-bottom:none}
        .activity-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
        .activity-icon.user{background:#eff6ff;color:#3b82f6}
        .activity-icon.task{background:#f5f3ff;color:#8b5cf6}
        .activity-icon.withdrawal{background:#ecfdf5;color:#10b981}
        .activity-content{flex:1;min-width:0}
        .activity-title{font-weight:600;color:#1e293b;font-size:14px}
        .activity-desc{font-size:12px;color:#64748b;margin-top:2px}
        .activity-time{font-size:11px;color:#94a3b8;margin-top:5px}
        
        /* Quick Actions */
        .quick-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}
        .quick-action{background:#fff;border-radius:12px;padding:20px;text-align:center;text-decoration:none;transition:all 0.2s;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .quick-action:hover{transform:translateY(-3px);box-shadow:0 5px 20px rgba(0,0,0,0.08)}
        .quick-action .icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 12px}
        .quick-action .label{font-size:13px;font-weight:600;color:#1e293b}
        
        /* Two Column Layout */
        .two-column{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        
        /* Responsive */
        @media(max-width:1200px){
            .stats-grid{grid-template-columns:repeat(3,1fr)}
            .charts-row{grid-template-columns:1fr}
            .two-column{grid-template-columns:1fr}
        }
        @media(max-width:992px){
            .admin-layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .stats-grid{grid-template-columns:repeat(2,1fr)}
            .alert-grid{grid-template-columns:1fr}
            .quick-actions{grid-template-columns:repeat(2,1fr)}
        }
        @media(max-width:576px){
            .stats-grid{grid-template-columns:1fr}
            .page-header{flex-direction:column;text-align:center}
            .header-actions{width:100%;justify-content:center}
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div>
                <div class="page-title">Dashboard</div>
                <div class="page-subtitle">Welcome back, <?php echo escape($admin_name); ?>! Here's what's happening.</div>
            </div>
            <div class="header-actions">
                <a href="<?php echo ADMIN_URL; ?>/assign-task.php" class="header-btn primary">➕ Assign Task</a>
                <a href="<?php echo ADMIN_URL; ?>/reports.php?export=pdf" class="header-btn secondary">📥 Export Report</a>
            </div>
        </div>
        
        <!-- Alert Cards -->
        <?php if ($pending_withdrawals > 0 || $pending_wallet_recharges > 0 || $unread_messages > 0 || $unanswered_questions > 0): ?>
        <div class="alert-grid">
            <?php if ($pending_withdrawals > 0): ?>
            <div class="alert-card warning">
                <div class="alert-icon">💸</div>
                <div class="alert-content">
                    <div class="alert-title"><?php echo $pending_withdrawals; ?> Pending Withdrawals</div>
                    <div class="alert-desc">₹<?php echo number_format($pending_withdrawal_amount, 0); ?> waiting to be processed</div>
                </div>
                <a href="<?php echo ADMIN_URL; ?>/withdrawals.php" class="alert-action">Process</a>
            </div>
            <?php endif; ?>
            
            <?php if ($pending_wallet_recharges > 0): ?>
            <div class="alert-card warning">
                <div class="alert-icon">💳</div>
                <div class="alert-content">
                    <div class="alert-title"><?php echo $pending_wallet_recharges; ?> Wallet Recharge Requests</div>
                    <div class="alert-desc">Seller wallet recharge requests pending approval</div>
                </div>
                <a href="<?php echo ADMIN_URL; ?>/wallet-requests.php" class="alert-action">Review</a>
            </div>
            <?php endif; ?>
            
            <?php if ($unread_messages > 0): ?>
            <div class="alert-card info">
                <div class="alert-icon">💬</div>
                <div class="alert-content">
                    <div class="alert-title"><?php echo $unread_messages; ?> Unread Messages</div>
                    <div class="alert-desc">Users are waiting for your response</div>
                </div>
                <a href="<?php echo ADMIN_URL; ?>/messages.php" class="alert-action">View</a>
            </div>
            <?php endif; ?>
            
            <?php if ($unanswered_questions > 0): ?>
            <div class="alert-card danger">
                <div class="alert-icon">❓</div>
                <div class="alert-content">
                    <div class="alert-title"><?php echo $unanswered_questions; ?> Unanswered Questions</div>
                    <div class="alert-desc">Train your AI chatbot with answers</div>
                </div>
                <a href="<?php echo ADMIN_URL; ?>/chatbot-unanswered.php" class="alert-action">Answer</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo number_format($total_users); ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-change up">↑ <?php echo $new_users_month; ?> this month</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?php echo number_format($total_tasks); ?></div>
                <div class="stat-label">Total Tasks</div>
                <div class="stat-change"><?php echo $completed_tasks; ?> completed</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon">💰</div>
                <div class="stat-value">₹<?php echo number_format($total_wallet_balance, 0); ?></div>
                <div class="stat-label">Total Wallet Balance</div>
                <div class="stat-change">All users combined</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon">💸</div>
                <div class="stat-value">₹<?php echo number_format($total_paid, 0); ?></div>
                <div class="stat-label">Total Paid Out</div>
                <div class="stat-change up">₹<?php echo number_format($month_stats['total_withdrawn'] ?? 0, 0); ?> this month</div>
            </div>
        </div>
        
        <!-- New Seller & Revenue Stats -->
        <div class="stats-grid">
            <div class="stat-card cyan">
                <div class="stat-icon">🏪</div>
                <div class="stat-value"><?php echo number_format($total_sellers); ?></div>
                <div class="stat-label">Total Sellers</div>
                <div class="stat-change"><?php echo $active_sellers; ?> active</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon">💵</div>
                <div class="stat-value">₹<?php echo number_format($total_seller_revenue, 0); ?></div>
                <div class="stat-label">Seller Revenue</div>
                <div class="stat-change">From paid review requests</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon">📝</div>
                <div class="stat-value"><?php echo number_format($pending_approvals); ?></div>
                <div class="stat-label">Pending Approvals</div>
                <div class="stat-change">Review requests to approve</div>
            </div>
            <div class="stat-card blue">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?php echo number_format($active_users); ?></div>
                <div class="stat-label">Active Users</div>
                <div class="stat-change">Last 7 days</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="<?php echo ADMIN_URL; ?>/assign-task.php" class="quick-action">
                <div class="icon" style="background:#eff6ff;color:#3b82f6">➕</div>
                <div class="label">Assign Task</div>
            </a>
            <a href="<?php echo ADMIN_URL; ?>/withdrawals.php" class="quick-action">
                <div class="icon" style="background:#ecfdf5;color:#10b981">💸</div>
                <div class="label">Process Withdrawals</div>
            </a>
            <a href="<?php echo ADMIN_URL; ?>/reviewers.php" class="quick-action">
                <div class="icon" style="background:#f5f3ff;color:#8b5cf6">👥</div>
                <div class="label">Manage Users</div>
            </a>
            <a href="<?php echo ADMIN_URL; ?>/reports.php" class="quick-action">
                <div class="icon" style="background:#fef3c7;color:#f59e0b">📊</div>
                <div class="label">View Reports</div>
            </a>
        </div>
        
        <!-- Charts Row -->
        <div class="charts-row">
            <!-- Revenue Chart -->
            <div class="chart-card">
                <div class="chart-title">
                    Monthly Revenue
                    <span>Last 6 months</span>
                </div>
                <div class="bar-chart">
                    <?php 
                    $max_val = 1;
                    foreach ($monthly_revenue as $m) {
                        $max_val = max($max_val, $m['credited'], $m['withdrawn']);
                    }
                    foreach ($monthly_revenue as $m): 
                        $credit_h = ($m['credited'] / $max_val) * 140;
                        $debit_h = ($m['withdrawn'] / $max_val) * 140;
                    ?>
                    <div class="bar-group">
                        <div class="bar-wrapper">
                            <div class="bar primary" style="height:<?php echo max(5, $credit_h); ?>px" title="Credited: ₹<?php echo number_format($m['credited'], 0); ?>"></div>
                            <div class="bar secondary" style="height:<?php echo max(5, $debit_h); ?>px" title="Withdrawn: ₹<?php echo number_format($m['withdrawn'], 0); ?>"></div>
                        </div>
                        <div class="bar-label"><?php echo date('M', strtotime($m['month'] . '-01')); ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($monthly_revenue)): ?>
                        <div style="flex:1;text-align:center;color:#94a3b8">No data available</div>
                    <?php endif; ?>
                </div>
                <div style="display:flex;justify-content:center;gap:25px;margin-top:15px;font-size:12px;color:#64748b">
                    <span><span style="display:inline-block;width:12px;height:12px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:3px;margin-right:6px"></span>Credited</span>
                    <span><span style="display:inline-block;width:12px;height:12px;background:linear-gradient(135deg,#10b981,#059669);border-radius:3px;margin-right:6px"></span>Withdrawn</span>
                </div>
            </div>
            
            <!-- Task Status Donut -->
            <div class="chart-card">
                <div class="chart-title">Task Status</div>
                <?php 
                $task_other = max(0, $total_tasks - $completed_tasks - $pending_tasks);
                $completed_pct = $total_tasks > 0 ? ($completed_tasks / $total_tasks) * 100 : 0;
                $pending_pct = $total_tasks > 0 ? ($pending_tasks / $total_tasks) * 100 : 0;
                ?>
                <div class="donut-chart">
                    <div class="donut" style="--completed:<?php echo $completed_pct; ?>;--pending:<?php echo $pending_pct; ?>">
                        <div class="donut-center">
                            <div class="donut-value"><?php echo $total_tasks; ?></div>
                            <div class="donut-label">Total</div>
                        </div>
                    </div>
                    <div class="donut-legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background:#667eea"></div>
                            <div class="legend-text">Completed</div>
                            <div class="legend-value"><?php echo $completed_tasks; ?></div>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background:#f59e0b"></div>
                            <div class="legend-text">Pending Refund</div>
                            <div class="legend-value"><?php echo $pending_tasks; ?></div>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background:#e2e8f0"></div>
                            <div class="legend-text">In Progress</div>
                            <div class="legend-value"><?php echo $task_other; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Two Column Section -->
        <div class="two-column">
            <!-- Pending Withdrawals -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">💸 Pending Withdrawals</div>
                    <a href="<?php echo ADMIN_URL; ?>/withdrawals.php" class="table-action">View All →</a>
                </div>
                <?php if (empty($pending_withdrawal_list)): ?>
                    <div style="padding:40px;text-align:center;color:#94a3b8">No pending withdrawals</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_withdrawal_list as $wr): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar"><?php echo strtoupper(substr($wr['user_name'], 0, 1)); ?></div>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo escape($wr['user_name']); ?></div>
                                        <div class="user-email"><?php echo escape($wr['user_email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="amount negative">₹<?php echo number_format($wr['amount'], 0); ?></span></td>
                            <td><?php echo ucfirst($wr['payment_method']); ?></td>
                            <td>
                                <a href="<?php echo ADMIN_URL; ?>/withdrawals.php?id=<?php echo $wr['id']; ?>" class="action-btn view">Process</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <!-- Recent Activity -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">📋 Recent Activity</div>
                </div>
                <div class="activity-feed">
                    <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo $activity['type']; ?>">
                            <?php echo $activity['type'] === 'user' ? '👤' : ($activity['type'] === 'task' ? '📋' : '💸'); ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title"><?php echo escape($activity['title']); ?></div>
                            <div class="activity-desc"><?php echo escape($activity['description']); ?></div>
                            <div class="activity-time"><?php echo date('d M Y, H:i', strtotime($activity['created_at'])); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($recent_activities)): ?>
                        <div style="padding:40px;text-align:center;color:#94a3b8">No recent activity</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Recent Users & Top Earners -->
        <div class="two-column">
            <!-- Recent Users -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">👥 Recent Users</div>
                    <a href="<?php echo ADMIN_URL; ?>/reviewers.php" class="table-action">View All →</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Mobile</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo escape($user['name']); ?></div>
                                        <div class="user-email"><?php echo escape($user['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo escape($user['mobile']); ?></td>
                            <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Top Earners -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">🏆 Top Earners</div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>User</th>
                            <th>Tasks</th>
                            <th>Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_earners as $index => $earner): ?>
                        <tr>
                            <td>
                                <?php if ($index === 0): ?>🥇
                                <?php elseif ($index === 1): ?>🥈
                                <?php elseif ($index === 2): ?>🥉
                                <?php else: ?>#<?php echo $index + 1; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo escape($earner['name']); ?></td>
                            <td><?php echo $earner['tasks_completed']; ?></td>
                            <td><span class="amount positive">₹<?php echo number_format($earner['total_earnings'], 0); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($top_earners)): ?>
                        <tr><td colspan="4" style="text-align:center;color:#94a3b8">No data yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Version Display -->
<?php require_once __DIR__ . '/../includes/version-display.php'; ?>

<!-- Include Theme CSS and JS -->
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/themes.css">
<script src="<?= APP_URL ?>/assets/js/theme.js"></script>

<!-- Include Chatbot Widget -->
<?php require_once __DIR__ . '/../includes/chatbot-widget.php'; ?>

<script>
// Auto refresh every 5 minutes
setTimeout(() => location.reload(), 300000);
</script>
</body>
</html>
