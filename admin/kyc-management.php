<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL . '/index.php');
    exit;
}

header('Location: ' . ADMIN_URL . '/kyc-verification.php');
exit;
