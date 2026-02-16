<?php
/**
 * Admin Login as Seller - Version 2.0
 * Allows admin to impersonate seller accounts
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

// Check if user is admin
if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$seller_id = intval($_GET['seller_id'] ?? 0);

if ($seller_id <= 0) {
    $_SESSION['error'] = 'Invalid seller ID';
    header('Location: sellers.php');
    exit;
}

try {
    // Get seller details
    $stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$seller_id]);
    $seller = $stmt->fetch();
    
    if (!$seller) {
        $_SESSION['error'] = 'Seller not found';
        header('Location: sellers.php');
        exit;
    }
    
    // Store original admin session
    $_SESSION['original_admin_name'] = $_SESSION['admin_name'];
    $_SESSION['original_admin_session'] = session_id();
    $_SESSION['is_admin_impersonating'] = true;
    $_SESSION['impersonation_started'] = time();
    
    // Set seller session variables
    $_SESSION['seller_id'] = $seller['id'];
    $_SESSION['seller_name'] = $seller['name'];
    $_SESSION['seller_email'] = $seller['email'];
    
    // Log the impersonation
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_type, action, details, ip_address, created_at)
            VALUES (?, 'admin', 'admin_impersonation', ?, ?, NOW())
        ");
        $stmt->execute([
            0,
            'Admin ' . $_SESSION['original_admin_name'] . ' logged in as seller: ' . $seller['name'] . ' (ID: ' . $seller_id . ')',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log('Failed to log impersonation: ' . $e->getMessage());
    }
    
    // Redirect to seller dashboard
    header('Location: ' . SELLER_URL . '/dashboard.php');
    exit;
    
} catch (PDOException $e) {
    error_log('Admin impersonation error: ' . $e->getMessage());
    $_SESSION['error'] = 'Failed to login as seller. Please try again.';
    header('Location: sellers.php');
    exit;
}
?>
