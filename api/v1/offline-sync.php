<?php
/**
 * API v1 - Offline Sync API
 * Handle offline data synchronization for PWA
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/api-functions.php';
require_once __DIR__ . '/../../includes/jwt-functions.php';
require_once __DIR__ . '/../../includes/rate-limit-functions.php';

// Handle CORS
handleCors();

// Database connection
$db = new Database();
$pdo = $db->connect();

// Get client IP for rate limiting
$client_ip = getClientIp();

// Get request method and path
$request_method = getRequestMethod();
$request_uri = $_SERVER['REQUEST_URI'];

// Route handling
if ($request_method === 'POST' && strpos($request_uri, '/offline-sync/queue') !== false) {
    handleAddToQueue($pdo, $client_ip);
} elseif ($request_method === 'GET' && strpos($request_uri, '/offline-sync/pending') !== false) {
    handleGetPending($pdo);
} elseif ($request_method === 'POST' && strpos($request_uri, '/offline-sync/complete') !== false) {
    handleMarkComplete($pdo);
} elseif ($request_method === 'GET' && strpos($request_uri, '/offline-sync/status') !== false) {
    handleGetStatus($pdo);
} else {
    sendErrorResponse('Endpoint not found', 404);
}

/**
 * Add items to sync queue
 */
function handleAddToQueue($pdo, $client_ip) {
    // Rate limiting
    $rate_limit = checkRateLimit($pdo, $client_ip, 'ip', 'offline_sync_queue', 50, 60);
    if (!$rate_limit['allowed']) {
        sendErrorResponse('Too many requests. Please try again later.', 429);
    }
    
    // Authenticate user
    $user = authenticateRequest($pdo);
    if (!$user) {
        sendErrorResponse('Unauthorized', 401);
    }
    
    $data = getRequestBody();
    $required = ['items'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    $items = $data['items'];
    
    if (!is_array($items) || empty($items)) {
        sendErrorResponse('Items must be a non-empty array', 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        $queued_items = [];
        
        foreach ($items as $item) {
            // Validate item structure
            if (!isset($item['entity_type']) || !isset($item['entity_id']) || !isset($item['action'])) {
                continue;
            }
            
            $entity_type = sanitizeInput($item['entity_type']);
            $entity_id = sanitizeInput($item['entity_id']);
            $action = sanitizeInput($item['action']);
            $data_json = json_encode($item['data'] ?? []);
            
            // Check if item already exists in queue
            $stmt = $pdo->prepare("
                SELECT id FROM offline_sync_queue 
                WHERE user_id = ? AND entity_type = ? AND entity_id = ? AND status = 'pending'
            ");
            $stmt->execute([$user['id'], $entity_type, $entity_id]);
            
            if ($stmt->fetch()) {
                // Update existing item
                $stmt = $pdo->prepare("
                    UPDATE offline_sync_queue 
                    SET action_type = ?, data = ?
                    WHERE user_id = ? AND entity_type = ? AND entity_id = ? AND status = 'pending'
                ");
                $stmt->execute([
                    $action,
                    $data_json,
                    $user['id'],
                    $entity_type,
                    $entity_id
                ]);
            } else {
                // Insert new item
                $stmt = $pdo->prepare("
                    INSERT INTO offline_sync_queue 
                    (user_id, entity_type, entity_id, action_type, data, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $user['id'],
                    $entity_type,
                    $entity_id,
                    $action,
                    $data_json
                ]);
            }
            
            $queued_items[] = [
                'entity_type' => $entity_type,
                'entity_id' => $entity_id,
                'action' => $action
            ];
        }
        
        $pdo->commit();
        
        sendSuccessResponse([
            'queued_count' => count($queued_items),
            'items' => $queued_items
        ], 'Items added to sync queue successfully');
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Sync queue error: ' . $e->getMessage());
        sendErrorResponse('Failed to add items to sync queue', 500);
    }
}

/**
 * Get pending sync items
 */
function handleGetPending($pdo) {
    // Authenticate user
    $user = authenticateRequest($pdo);
    if (!$user) {
        sendErrorResponse('Unauthorized', 401);
    }
    
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $entity_type = isset($_GET['entity_type']) ? sanitizeInput($_GET['entity_type']) : null;
    
    try {
        $where_conditions = ["user_id = ?", "status = 'pending'"];
        $params = [$user['id']];
        
        if ($entity_type) {
            $where_conditions[] = "entity_type = ?";
            $params[] = $entity_type;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $stmt = $pdo->prepare("
            SELECT 
                id,
                entity_type,
                entity_id,
                action_type as action,
                data,
                created_at
            FROM offline_sync_queue
            WHERE $where_clause
            ORDER BY created_at ASC
            LIMIT ?
        ");
        
        $params[] = $limit;
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse JSON data
        foreach ($items as &$item) {
            $item['data'] = json_decode($item['data'], true);
        }
        
        sendSuccessResponse([
            'items' => $items,
            'count' => count($items)
        ], 'Pending sync items retrieved successfully');
        
    } catch (PDOException $e) {
        error_log('Pending items fetch error: ' . $e->getMessage());
        sendErrorResponse('Failed to fetch pending items', 500);
    }
}

/**
 * Mark items as synced
 */
function handleMarkComplete($pdo) {
    // Authenticate user
    $user = authenticateRequest($pdo);
    if (!$user) {
        sendErrorResponse('Unauthorized', 401);
    }
    
    $data = getRequestBody();
    $required = ['items'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    $items = $data['items'];
    
    if (!is_array($items) || empty($items)) {
        sendErrorResponse('Items must be a non-empty array', 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        $completed_count = 0;
        $failed_count = 0;
        
        foreach ($items as $item) {
            if (!isset($item['id']) || !isset($item['status'])) {
                continue;
            }
            
            $item_id = (int)$item['id'];
            $status = sanitizeInput($item['status']);
            $error_message = isset($item['error']) ? sanitizeInput($item['error']) : null;
            
            if ($status === 'completed') {
                // Mark as completed
                $stmt = $pdo->prepare("
                    UPDATE offline_sync_queue 
                    SET status = 'synced', synced_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$item_id, $user['id']]);
                $completed_count += $stmt->rowCount();
                
            } elseif ($status === 'failed') {
                // Mark as failed
                $stmt = $pdo->prepare("
                    UPDATE offline_sync_queue 
                    SET status = 'failed'
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$item_id, $user['id']]);
                $failed_count += $stmt->rowCount();
            }
        }
        
        $pdo->commit();
        
        sendSuccessResponse([
            'completed' => $completed_count,
            'failed' => $failed_count
        ], 'Sync items updated successfully');
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Sync complete error: ' . $e->getMessage());
        sendErrorResponse('Failed to update sync items', 500);
    }
}

/**
 * Get sync status
 */
function handleGetStatus($pdo) {
    // Authenticate user
    $user = authenticateRequest($pdo);
    if (!$user) {
        sendErrorResponse('Unauthorized', 401);
    }
    
    try {
        // Get status summary
        $stmt = $pdo->prepare("
            SELECT 
                status,
                COUNT(*) as count
            FROM offline_sync_queue
            WHERE user_id = ?
            GROUP BY status
        ");
        $stmt->execute([$user['id']]);
        $status_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get failed items that need retry
        $stmt = $pdo->prepare("
            SELECT 
                id,
                entity_type,
                entity_id,
                action_type as action
            FROM offline_sync_queue
            WHERE user_id = ? AND status = 'failed'
            ORDER BY created_at ASC
        ");
        $stmt->execute([$user['id']]);
        $retry_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $totals = [
            'pending' => 0,
            'synced' => 0,
            'failed' => 0
        ];
        
        foreach ($status_summary as $status) {
            $totals[$status['status']] = (int)$status['count'];
        }
        
        // Check if sync is needed
        $needs_sync = $totals['pending'] > 0 || count($retry_items) > 0;
        
        sendSuccessResponse([
            'totals' => $totals,
            'needs_sync' => $needs_sync,
            'retry_items' => $retry_items,
            'status_summary' => $status_summary
        ], 'Sync status retrieved successfully');
        
    } catch (PDOException $e) {
        error_log('Sync status error: ' . $e->getMessage());
        sendErrorResponse('Failed to fetch sync status', 500);
    }
}

/**
 * Authenticate request using JWT
 */
function authenticateRequest($pdo) {
    $token = getBearerToken();
    
    if (!$token) {
        return false;
    }
    
    try {
        $decoded = verifyJWT($token);
        
        if (!$decoded || !isset($decoded->user_id)) {
            return false;
        }
        
        $stmt = $pdo->prepare("SELECT id, username, email, user_type FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$decoded->user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ?: false;
        
    } catch (Exception $e) {
        return false;
    }
}
