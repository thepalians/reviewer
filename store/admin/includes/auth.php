<?php
require_once dirname(__DIR__, 2) . '/includes/config.php';

function adminLogin(string $username, string $password): bool {
    $db = getStoreDB();
    $stmt = $db->prepare('SELECT * FROM store_admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id']        = $admin['id'];
        $_SESSION['admin_username']  = $admin['username'];
        $_SESSION['admin_logged_in'] = true;
        return true;
    }
    return false;
}

function requireAdminLogin(): void {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: ' . STORE_URL . '/admin/');
        exit;
    }
}

function adminLogout(): void {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . STORE_URL . '/admin/');
    exit;
}
