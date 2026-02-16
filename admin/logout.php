<?php
// admin/logout.php - Admin-specific logout

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Store admin name for message
$admin_name = $_SESSION['user_name'] ?? 'Admin';

// Destroy admin session completely
$_SESSION = array();

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to admin login page with success message
header('Location: ../index.php?logout=admin&user=' . urlencode($admin_name));
exit();
?>
