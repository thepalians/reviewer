<?php
// This file should be included after setting $current_page variable
// Make sure to have fetched badge counts before including this

// Initialize badge counts with safe defaults
$pending_tasks_count = 0;
$unread_messages = 0;
$unread_announcements = 0;

// Get badge counts if not already set
if (!isset($pending_tasks_count)) {
    try {
        if (!isset($pdo)) {
            error_log("Sidebar: PDO connection not available");
            $pending_tasks_count = 0;
        } else {
            $user_id = $_SESSION['user_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND task_status = 'pending'");
            $stmt->execute([$user_id]);
            $pending_tasks_count = (int)$stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log("Sidebar pending tasks query error: " . $e->getMessage());
        $pending_tasks_count = 0;
    } catch (Exception $e) {
        error_log("Sidebar pending tasks unexpected error: " . $e->getMessage());
        $pending_tasks_count = 0;
    }
}

if (!isset($unread_messages)) {
    try {
        if (!isset($pdo)) {
            error_log("Sidebar: PDO connection not available");
            $unread_messages = 0;
        } else {
            $user_id = $_SESSION['user_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id = ? AND is_read = 0 AND sender = 'admin'");
            $stmt->execute([$user_id]);
            $unread_messages = (int)$stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log("Sidebar unread messages query error: " . $e->getMessage());
        $unread_messages = 0;
    } catch (Exception $e) {
        error_log("Sidebar unread messages unexpected error: " . $e->getMessage());
        $unread_messages = 0;
    }
}

if (!isset($unread_announcements)) {
    try {
        if (!isset($pdo)) {
            error_log("Sidebar: PDO connection not available");
            $unread_announcements = 0;
        } else {
            $user_id = $_SESSION['user_id'] ?? 0;
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM announcements a
                WHERE a.is_active = 1 
                AND (a.target_audience = 'all' OR a.target_audience = 'users')
                AND (a.start_date IS NULL OR a.start_date <= CURDATE())
                AND (a.end_date IS NULL OR a.end_date >= CURDATE())
                AND NOT EXISTS (
                    SELECT 1 FROM announcement_views av 
                    WHERE av.announcement_id = a.id AND av.user_id = ?
                )
            ");
            $stmt->execute([$user_id]);
            $unread_announcements = (int)$stmt->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log("Sidebar unread announcements query error: " . $e->getMessage());
        $unread_announcements = 0;
    } catch (Exception $e) {
        error_log("Sidebar unread announcements unexpected error: " . $e->getMessage());
        $unread_announcements = 0;
    }
}

// Set current page if not set
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
}
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h2>🏠 <?php echo htmlspecialchars(APP_NAME); ?></h2>
    </div>
    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li><a href="<?php echo APP_URL; ?>/user/dashboard.php" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">🏠 Dashboard</a></li>
        
        <!-- Tasks Section -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>📋 Tasks</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/tasks.php" class="<?= $current_page === 'tasks' ? 'active' : '' ?>">📋 My Tasks <?php if($pending_tasks_count > 0): ?><span class="badge"><?php echo $pending_tasks_count; ?></span><?php endif; ?></a></li>
        
        <!-- Wallet -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>💰 Finance</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/wallet.php" class="<?= $current_page === 'wallet' ? 'active' : '' ?>">💰 Wallet</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/transactions.php" class="<?= $current_page === 'transactions' ? 'active' : '' ?>">💳 Transactions</a></li>
        
        <!-- Referrals (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🔗 Referrals</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/referral.php" class="<?= in_array($current_page, ['referral', 'referrals'], true) ? 'active' : '' ?>">🔗 My Referrals</a></li>
        
        <!-- Rewards & Gamification (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🎮 Gamification</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/rewards.php" class="<?= $current_page === 'rewards' ? 'active' : '' ?>">🎮 Rewards & Points</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/spin-wheel.php" class="<?= $current_page === 'spin-wheel' ? 'active' : '' ?>">🎰 Daily Spin Wheel</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/leaderboard.php" class="<?= $current_page === 'leaderboard' ? 'active' : '' ?>">🏆 Leaderboard</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/competitions.php" class="<?= $current_page === 'competitions' ? 'active' : '' ?>">🏅 Competitions</a></li>
        
        <!-- Submit Proof (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>📸 Proofs</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/submit-proof.php" class="<?= $current_page === 'submit-proof' ? 'active' : '' ?>">📸 Submit Proof</a></li>
        
        <!-- Support (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>💬 Support</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/chat.php" class="<?= $current_page === 'chat' ? 'active' : '' ?>">💬 Support Chat <?php if($unread_messages > 0): ?><span class="badge"><?php echo $unread_messages; ?></span><?php endif; ?></a></li>
        
        <!-- Phase 4: Announcements -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>📢 Updates</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/announcements.php" class="<?= $current_page === 'announcements' ? 'active' : '' ?>">📢 Announcements <?php if($unread_announcements > 0): ?><span class="badge"><?php echo $unread_announcements; ?></span><?php endif; ?></a></li>
        
        <!-- Phase 3: Payment & Activity -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>💳 Payments</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/recharge-wallet.php" class="<?= $current_page === 'recharge-wallet' ? 'active' : '' ?>">💳 Recharge Wallet</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/payment-history.php" class="<?= $current_page === 'payment-history' ? 'active' : '' ?>">📜 Payment History</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/my-activity.php" class="<?= $current_page === 'my-activity' ? 'active' : '' ?>">📊 My Activity</a></li>
        
        <!-- KYC & Analytics (Phase 1) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🔐 Account</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/kyc.php" class="<?= $current_page === 'kyc' ? 'active' : '' ?>">🆔 KYC Verification</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/analytics.php" class="<?= $current_page === 'analytics' ? 'active' : '' ?>">📊 My Analytics</a></li>
        
        <!-- Profile & Settings -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>⚙️ Settings</span></li>
        <li><a href="<?php echo APP_URL; ?>/user/profile.php" class="<?= $current_page === 'profile' ? 'active' : '' ?>">👤 Profile</a></li>
        <li><a href="<?php echo APP_URL; ?>/user/notifications.php" class="<?= $current_page === 'notifications' ? 'active' : '' ?>">🔔 Notifications</a></li>
        
        <!-- Logout -->
        <div class="sidebar-divider"></div>
        <li><a href="<?php echo APP_URL; ?>/logout.php" class="logout">🚪 Logout</a></li>
    </ul>
</div>
