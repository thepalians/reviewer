<?php
/**
 * TOTP Verification API
 * Verify TOTP codes via AJAX
 */

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/2fa-functions.php';

// Check authentication
if (!isset($_SESSION['user_id']) && !isset($_SESSION['2fa_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Fallback to POST data
    $input = $_POST;
}

$code = $input['code'] ?? '';
$userId = $_SESSION['user_id'] ?? $_SESSION['2fa_user_id'] ?? 0;

if (empty($code) || $userId === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Get user's 2FA settings
$settings = get2FASettings($userId);

if (!$settings || !$settings['is_enabled']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '2FA not enabled']);
    exit;
}

// Verify the code
$isValid = verifyTOTP($settings['secret_key'], $code);

if ($isValid) {
    // Update last used time
    update2FALastUsed($userId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Code verified successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid verification code'
    ]);
}
