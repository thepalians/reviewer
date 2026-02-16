<?php
// user/logout.php - User-specific logout

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Store user name for message
$user_name = $_SESSION['user_name'] ?? 'User';

// Destroy user session completely
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

// Redirect to login page with success message
header('Location: ../index.php?logout=success&user=' . urlencode($user_name));
exit();
?>
