<?php
/**
 * Push Notification Subscription API
 * Handle push notification subscription requests
 */

header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/pwa-functions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

// Only accept POST and DELETE requests
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Subscribe to push notifications
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['subscription'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    $subscription = $input['subscription'];
    
    // Validate subscription data
    if (empty($subscription['endpoint']) || 
        empty($subscription['keys']['p256dh']) || 
        empty($subscription['keys']['auth'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid subscription data']);
        exit;
    }
    
    // Save subscription
    $success = savePushSubscription($userId, $subscription);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Subscription saved successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save subscription'
        ]);
    }
    
} elseif ($method === 'DELETE') {
    // Unsubscribe from push notifications
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['endpoint'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    $endpoint = $input['endpoint'];
    
    // Delete subscription
    $success = deletePushSubscription($userId, $endpoint);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Subscription removed successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to remove subscription'
        ]);
    }
    
} elseif ($method === 'GET') {
    // Get subscription status
    
    $subscriptions = getUserPushSubscriptions($userId);
    
    echo json_encode([
        'success' => true,
        'subscribed' => !empty($subscriptions),
        'count' => count($subscriptions)
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
