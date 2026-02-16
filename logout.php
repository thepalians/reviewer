<?php
// Main logout.php - Handles both user and admin logout

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store info before destroying session
$is_admin = isset($_SESSION['admin_name']);
$user_name = $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'User';

// Clear all session data
$_SESSION = [];

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"] ?? '/',
        $params["domain"] ?? '',
        $params["secure"] ?? false,
        $params["httponly"] ?? true
    );
}

// Destroy the session
session_destroy();

// Load config for APP_URL (after session destroy is safe)
require_once __DIR__ . '/includes/config.php';

// Determine redirect URL
if ($is_admin) {
    $redirect_url = APP_URL . '/admin/?logout=success';
} else {
    $redirect_url = APP_URL . '/index.php?logout=success';
}

// Redirect using header (not redirectTo function which may have issues)
header('Location: ' . $redirect_url);
exit;
?>
