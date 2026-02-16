<?php
/**
 * API Helper Functions
 * Common functions for API endpoints
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Send JSON response
 */
function sendJsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send error response
 */
function sendErrorResponse($message, $status_code = 400, $errors = []) {
    sendJsonResponse([
        'success' => false,
        'message' => $message,
        'errors' => $errors
    ], $status_code);
}

/**
 * Send success response
 */
function sendSuccessResponse($data = [], $message = 'Success') {
    sendJsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ], 200);
}

/**
 * Get request headers
 */
function getRequestHeaders() {
    if (function_exists('getallheaders')) {
        return getallheaders();
    }
    
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) === 'HTTP_') {
            $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$header] = $value;
        }
    }
    return $headers;
}

/**
 * Get authorization header
 */
function getAuthorizationHeader() {
    $headers = getRequestHeaders();
    
    if (isset($headers['Authorization'])) {
        return $headers['Authorization'];
    }
    
    if (isset($headers['authorization'])) {
        return $headers['authorization'];
    }
    
    return null;
}

/**
 * Get bearer token from header
 */
function getBearerToken() {
    $auth_header = getAuthorizationHeader();
    
    if ($auth_header && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * Get client IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Validate required fields
 */
function validateRequiredFields($data, $required_fields) {
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    return $missing_fields;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 */
function validatePhone($phone) {
    return preg_match('/^[0-9]{10,15}$/', $phone);
}

/**
 * Paginate results
 */
function paginateResults($pdo, $query, $params = [], $page = 1, $per_page = 20) {
    try {
        // Get total count
        $count_query = "SELECT COUNT(*) FROM ($query) as count_table";
        $stmt = $pdo->prepare($count_query);
        $stmt->execute($params);
        $total_count = $stmt->fetchColumn();
        
        // Calculate pagination
        $total_pages = ceil($total_count / $per_page);
        $offset = ($page - 1) * $per_page;
        
        // Get paginated results
        $paginated_query = "$query LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($paginated_query);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$per_page,
                'total_count' => (int)$total_count,
                'total_pages' => (int)$total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ];
    } catch (PDOException $e) {
        error_log("Error paginating results: " . $e->getMessage());
        return [
            'data' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => $per_page,
                'total_count' => 0,
                'total_pages' => 0,
                'has_next' => false,
                'has_prev' => false
            ]
        ];
    }
}

/**
 * Format user data for API response
 */
function formatUserForApi($user) {
    return [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'user_type' => $user['user_type'],
        'phone' => $user['phone'] ?? null,
        'created_at' => $user['created_at'],
        'is_active' => (bool)($user['is_active'] ?? true)
    ];
}

/**
 * Format task data for API response
 */
function formatTaskForApi($task) {
    return [
        'id' => (int)$task['id'],
        'task_name' => $task['task_name'],
        'platform' => $task['platform'],
        'task_status' => $task['task_status'],
        'commission_amount' => (float)$task['commission_amount'],
        'current_step' => (int)$task['current_step'],
        'product_link' => $task['product_link'] ?? null,
        'order_placed_at' => $task['order_placed_at'] ?? null,
        'delivery_received_at' => $task['delivery_received_at'] ?? null,
        'review_submitted_at' => $task['review_submitted_at'] ?? null,
        'refund_requested_at' => $task['refund_requested_at'] ?? null,
        'created_at' => $task['created_at']
    ];
}

/**
 * Format wallet transaction for API response
 */
function formatTransactionForApi($transaction) {
    return [
        'id' => (int)$transaction['id'],
        'type' => $transaction['type'],
        'amount' => (float)$transaction['amount'],
        'description' => $transaction['description'],
        'balance_after' => (float)$transaction['balance_after'],
        'created_at' => $transaction['created_at']
    ];
}

/**
 * Format notification for API response
 */
function formatNotificationForApi($notification) {
    return [
        'id' => (int)$notification['id'],
        'title' => $notification['title'],
        'message' => $notification['message'],
        'is_read' => (bool)$notification['is_read'],
        'link' => $notification['link'] ?? null,
        'created_at' => $notification['created_at']
    ];
}

/**
 * Check API version
 */
function checkApiVersion($required_version = 'v1') {
    $requested_version = $_SERVER['REQUEST_URI'] ?? '';
    
    if (strpos($requested_version, "/api/$required_version/") === false) {
        sendErrorResponse('Invalid API version', 400);
    }
}

/**
 * Handle CORS
 */
function handleCors() {
    // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
    
    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        }
        
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        }
        
        exit(0);
    }
}

/**
 * Get request method
 */
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Get request body
 */
function getRequestBody() {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?? [];
}

/**
 * Require request method
 */
function requireRequestMethod($method) {
    if (getRequestMethod() !== strtoupper($method)) {
        sendErrorResponse('Method not allowed', 405);
    }
}

/**
 * Generate API documentation
 */
function generateApiDocumentation() {
    return [
        'version' => 'v1',
        'endpoints' => [
            [
                'path' => '/api/v1/auth/login',
                'method' => 'POST',
                'description' => 'User login',
                'parameters' => ['email', 'password']
            ],
            [
                'path' => '/api/v1/auth/register',
                'method' => 'POST',
                'description' => 'User registration',
                'parameters' => ['username', 'email', 'password', 'phone']
            ],
            [
                'path' => '/api/v1/tasks',
                'method' => 'GET',
                'description' => 'Get user tasks',
                'auth_required' => true
            ],
            [
                'path' => '/api/v1/wallet/balance',
                'method' => 'GET',
                'description' => 'Get wallet balance',
                'auth_required' => true
            ],
            [
                'path' => '/api/v1/profile',
                'method' => 'GET',
                'description' => 'Get user profile',
                'auth_required' => true
            ]
        ]
    ];
}
