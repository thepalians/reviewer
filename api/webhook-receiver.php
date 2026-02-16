<?php
/**
 * Webhook Receiver API Endpoint
 * Phase 7: Advanced Automation & Intelligence Features
 * 
 * This endpoint receives incoming webhooks from external services
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/webhook-functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    // Get database connection
    $database = new Database();
    $pdo = $database->connect();
    
    // Get request headers
    $headers = getallheaders();
    
    // Get request body
    $body = file_get_contents('php://input');
    
    if (empty($body)) {
        throw new Exception('Empty request body');
    }
    
    // Process the webhook
    $result = processIncomingWebhook($pdo, $headers, $body);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Webhook received successfully'
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    // Log error
    error_log("Webhook receiver error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => DEBUG ? $e->getMessage() : 'An error occurred'
    ]);
}
