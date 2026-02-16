<?php
/**
 * Admin Impersonation Banner
 * Shows when admin is logged in as seller or user
 * Include this at the top of seller/user dashboards
 */

// Check if admin is impersonating
$is_impersonating = isset($_SESSION['is_admin_impersonating']) && $_SESSION['is_admin_impersonating'] === true;
$is_admin_as_user = isset($_SESSION['admin_logged_in_as_user']) && $_SESSION['admin_logged_in_as_user'] === true;

if ($is_impersonating || $is_admin_as_user):
    $admin_name = $_SESSION['original_admin_name'] ?? 'Admin';
    $impersonation_time = isset($_SESSION['impersonation_started']) 
        ? (time() - $_SESSION['impersonation_started']) 
        : 0;
    $minutes = floor($impersonation_time / 60);
?>
<!-- Admin Impersonation Banner -->
<div class="admin-impersonation-banner">
    <div class="impersonation-content">
        <div class="impersonation-icon">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div class="impersonation-text">
            <strong>Admin Mode Active</strong>
            <span>Logged in as <?php echo $is_impersonating ? 'seller' : 'user'; ?> by <?php echo htmlspecialchars($admin_name); ?></span>
            <?php if ($minutes > 0): ?>
                <span class="impersonation-time">(<?php echo $minutes; ?> min<?php echo $minutes != 1 ? 's' : ''; ?>)</span>
            <?php endif; ?>
        </div>
    </div>
    <a href="<?php echo APP_URL; ?>/admin-return.php" class="impersonation-return-btn">
        <i class="bi bi-arrow-left-circle-fill"></i>
        Return to Admin
    </a>
</div>

<style>
.admin-impersonation-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 10000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.impersonation-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.impersonation-icon {
    font-size: 24px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.05);
    }
}

.impersonation-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.impersonation-text strong {
    font-size: 14px;
    font-weight: 700;
}

.impersonation-text span {
    font-size: 12px;
    opacity: 0.9;
}

.impersonation-time {
    font-size: 11px;
    opacity: 0.8;
    font-style: italic;
}

.impersonation-return-btn {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.impersonation-return-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    color: white;
}

/* Adjust page content to account for banner */
body {
    padding-top: 54px;
}

@media (max-width: 768px) {
    .admin-impersonation-banner {
        flex-direction: column;
        gap: 10px;
        padding: 10px 15px;
    }
    
    .impersonation-content {
        width: 100%;
        justify-content: center;
    }
    
    .impersonation-return-btn {
        width: 100%;
        justify-content: center;
    }
    
    body {
        padding-top: 100px;
    }
}
</style>

<?php endif; ?>
