<?php
// This file should be included after setting $current_page variable
// Make sure to have fetched badge counts before including this

// Get badge counts if not already set
if (!isset($pending_tasks)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
        $pending_tasks = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $pending_tasks = 0;
    }
}

if (!isset($pending_withdrawals)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
        $pending_withdrawals = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $pending_withdrawals = 0;
    }
}

if (!isset($pending_wallet_recharges)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM wallet_recharge_requests WHERE status = 'pending'");
        $pending_wallet_recharges = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $pending_wallet_recharges = 0;
    }
}

if (!isset($unanswered_questions)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
        $unanswered_questions = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $unanswered_questions = 0;
    }
}

if (!isset($pending_review_requests)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM review_requests WHERE admin_status = 'pending'");
        $pending_review_requests = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $pending_review_requests = 0;
    }
}

if (!isset($pending_kyc)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM user_kyc WHERE status = 'pending'");
        $pending_kyc = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $pending_kyc = 0;
    }
}

if (!isset($pending_proofs)) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM task_proofs WHERE status = 'pending'");
        $pending_proofs = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $pending_proofs = 0;
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
        <h2>⚙️ <?php echo htmlspecialchars(APP_NAME); ?></h2>
    </div>
    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li><a href="<?php echo ADMIN_URL; ?>/dashboard.php" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a></li>
        
        <!-- Users Section -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>👥 Users</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/reviewers.php" class="<?= $current_page === 'reviewers' ? 'active' : '' ?>">All Reviewers</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/kyc-management.php" class="<?= in_array($current_page, ['kyc-management', 'kyc-verification', 'kyc-view']) ? 'active' : '' ?>">🔐 KYC Management <?php if($pending_kyc > 0): ?><span class="badge"><?php echo $pending_kyc; ?></span><?php endif; ?></a></li>
        
        <!-- Tasks Section -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>📋 Tasks</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/assign-task.php" class="<?= $current_page === 'assign-task' ? 'active' : '' ?>">➕ Assign Task</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/bulk-upload.php" class="<?= $current_page === 'bulk-upload' ? 'active' : '' ?>">📤 Bulk Upload</a></li>
        <li>
            <a href="<?php echo ADMIN_URL; ?>/task-pending.php" class="<?= in_array($current_page, ['task-pending', 'task-pending-brandwise']) ? 'active' : '' ?>">
                ⏳ Pending Tasks <?php if($pending_tasks > 0): ?><span class="badge"><?php echo $pending_tasks; ?></span><?php endif; ?>
            </a>
            <ul class="submenu" style="list-style:none;padding-left:20px;margin:5px 0">
                <li><a href="<?php echo ADMIN_URL; ?>/task-pending-brandwise.php" class="<?= $current_page === 'task-pending-brandwise' ? 'active' : '' ?>" style="font-size:13px;padding:8px 15px">📁 Brand View</a></li>
            </ul>
        </li>
        <li>
            <a href="<?php echo ADMIN_URL; ?>/task-completed.php" class="<?= in_array($current_page, ['task-completed', 'task-completed-brandwise']) ? 'active' : '' ?>">
                ✅ Completed Tasks
            </a>
            <ul class="submenu" style="list-style:none;padding-left:20px;margin:5px 0">
                <li><a href="<?php echo ADMIN_URL; ?>/task-completed-brandwise.php" class="<?= $current_page === 'task-completed-brandwise' ? 'active' : '' ?>" style="font-size:13px;padding:8px 15px">📁 Brand View</a></li>
            </ul>
        </li>
        <li><a href="<?php echo ADMIN_URL; ?>/task-rejected.php" class="<?= $current_page === 'task-rejected' ? 'active' : '' ?>">❌ Rejected Tasks</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/verify-proofs.php" class="<?= in_array($current_page, ['verify-proofs', 'proof-view']) ? 'active' : '' ?>">✅ Verify Proofs <?php if($pending_proofs > 0): ?><span class="badge"><?php echo $pending_proofs; ?></span><?php endif; ?></a></li>
        
        <!-- Finance Section -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>💰 Finance</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/withdrawals.php" class="<?= $current_page === 'withdrawals' ? 'active' : '' ?>">💸 Withdrawals <?php if($pending_withdrawals > 0): ?><span class="badge"><?php echo $pending_withdrawals; ?></span><?php endif; ?></a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/wallet-requests.php" class="<?= $current_page === 'wallet-requests' ? 'active' : '' ?>">💳 Wallet Recharges <?php if($pending_wallet_recharges > 0): ?><span class="badge"><?php echo $pending_wallet_recharges; ?></span><?php endif; ?></a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/seller-wallet-manage.php" class="<?= $current_page === 'seller-wallet-manage' ? 'active' : '' ?>">🏦 Manage Seller Wallet</a></li>
        
        <!-- Sellers Section -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🏪 Sellers</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/sellers.php" class="<?= $current_page === 'sellers' ? 'active' : '' ?>">All Sellers</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/review-requests.php" class="<?= $current_page === 'review-requests' ? 'active' : '' ?>">📝 Seller Requests <?php if($pending_review_requests > 0): ?><span class="badge"><?php echo $pending_review_requests; ?></span><?php endif; ?></a></li>
        
        <!-- Referrals (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🔗 Referrals</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/referral-settings.php" class="<?= $current_page === 'referral-settings' ? 'active' : '' ?>">⚙️ Referral Settings</a></li>
        
        <!-- Gamification (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🎮 Gamification</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/gamification-settings.php" class="<?= $current_page === 'gamification-settings' ? 'active' : '' ?>">⚙️ Gamification Settings</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/leaderboard.php" class="<?= $current_page === 'leaderboard' ? 'active' : '' ?>">🏆 Leaderboard</a></li>
        
        <!-- Support (Phase 2) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>💬 Support</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/support-chat.php" class="<?= $current_page === 'support-chat' ? 'active' : '' ?>">💬 Support Chat</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/faq-manager.php" class="<?= $current_page === 'faq-manager' ? 'active' : '' ?>">❓ Chatbot FAQ</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/chatbot-unanswered.php" class="<?= $current_page === 'chatbot-unanswered' ? 'active' : '' ?>">📝 Unanswered Questions <?php if($unanswered_questions > 0): ?><span class="badge"><?php echo $unanswered_questions; ?></span><?php endif; ?></a></li>
        
        <!-- Phase 4: Communication & Announcements -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>📢 Communication</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/announcements.php" class="<?= $current_page === 'announcements' ? 'active' : '' ?>">📢 Announcements</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/broadcast.php" class="<?= $current_page === 'broadcast' ? 'active' : '' ?>">📡 Broadcast Messages</a></li>
        
        <!-- Phase 4: Task Management -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🏷️ Task Management</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/task-categories.php" class="<?= $current_page === 'task-categories' ? 'active' : '' ?>">🏷️ Task Categories</a></li>
        
        <!-- Analytics (Phase 1) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>📊 Analytics</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/analytics.php" class="<?= $current_page === 'analytics' ? 'active' : '' ?>">📈 Analytics Dashboard</a></li>
        
        <!-- Reports & Export Section -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>📊 Reports & Export</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/reports.php" class="<?= $current_page === 'reports' ? 'active' : '' ?>">📈 Reports</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/export-data.php" class="<?= $current_page === 'export-data' ? 'active' : '' ?>">📥 Export Review Data</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/export-reports.php" class="<?= $current_page === 'export-reports' ? 'active' : '' ?>">📊 Export Reports</a></li>
        
        <!-- Notifications (Phase 1) -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>📧 Notifications</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/notification-templates.php" class="<?= $current_page === 'notification-templates' ? 'active' : '' ?>">📧 Notification Templates</a></li>
        
        <!-- Phase 4: Security & Audit -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>🔒 Security</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/security-logs.php" class="<?= $current_page === 'security-logs' ? 'active' : '' ?>">🔒 Security Logs</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/suspicious-users.php" class="<?= $current_page === 'suspicious-users' ? 'active' : '' ?>">🚨 Suspicious Users</a></li>
        
        <!-- Settings Section -->
        <div class="sidebar-divider"></div>
        <li class="menu-section-label"><span>⚙️ Settings</span></li>
        <li><a href="<?php echo ADMIN_URL; ?>/settings.php" class="<?= $current_page === 'settings' ? 'active' : '' ?>">⚙️ General Settings</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/gst-settings.php" class="<?= $current_page === 'gst-settings' ? 'active' : '' ?>">💰 GST Settings</a></li>
        <li><a href="<?php echo ADMIN_URL; ?>/features.php" class="<?= $current_page === 'features' ? 'active' : '' ?>">✨ Features</a></li>
        
        <!-- Logout -->
        <div class="sidebar-divider"></div>
        <li><a href="<?php echo APP_URL; ?>/logout.php" class="logout">🚪 Logout</a></li>
    </ul>
</div>
