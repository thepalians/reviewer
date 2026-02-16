<?php
/**
 * API v1 - Tasks Endpoints
 * Handles task listing, details, and submissions
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

// Require authentication
$user = requireJwtAuth($pdo);

// Get request method
$request_method = getRequestMethod();
$request_uri = $_SERVER['REQUEST_URI'];

// Route handling
if ($request_method === 'GET' && preg_match('/\/tasks\/(\d+)$/', $request_uri, $matches)) {
    getTaskDetail($pdo, $user, $matches[1]);
} elseif ($request_method === 'GET') {
    getTasks($pdo, $user);
} elseif ($request_method === 'POST' && strpos($request_uri, '/tasks/submit-order') !== false) {
    submitOrder($pdo, $user);
} elseif ($request_method === 'POST' && strpos($request_uri, '/tasks/submit-delivery') !== false) {
    submitDelivery($pdo, $user);
} elseif ($request_method === 'POST' && strpos($request_uri, '/tasks/submit-review') !== false) {
    submitReview($pdo, $user);
} elseif ($request_method === 'POST' && strpos($request_uri, '/tasks/submit-refund') !== false) {
    submitRefund($pdo, $user);
} else {
    sendErrorResponse('Endpoint not found', 404);
}

/**
 * Get user tasks
 */
function getTasks($pdo, $user) {
    try {
        $status = $_GET['status'] ?? 'all';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? min((int)$_GET['per_page'], 50) : 20;
        
        $query = "
            SELECT t.*, s.username as seller_name
            FROM tasks t
            LEFT JOIN users s ON t.seller_id = s.id
            WHERE t.user_id = ?
        ";
        
        $params = [$user['id']];
        
        if ($status !== 'all') {
            $query .= " AND t.task_status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY t.created_at DESC";
        
        $result = paginateResults($pdo, $query, $params, $page, $per_page);
        
        // Format tasks
        $tasks = array_map('formatTaskForApi', $result['data']);
        
        sendSuccessResponse([
            'tasks' => $tasks,
            'pagination' => $result['pagination']
        ]);
    } catch (PDOException $e) {
        error_log("Get tasks error: " . $e->getMessage());
        sendErrorResponse('Failed to fetch tasks', 500);
    }
}

/**
 * Get task detail
 */
function getTaskDetail($pdo, $user, $task_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, s.username as seller_name, s.email as seller_email
            FROM tasks t
            LEFT JOIN users s ON t.seller_id = s.id
            WHERE t.id = ? AND t.user_id = ?
        ");
        $stmt->execute([$task_id, $user['id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            sendErrorResponse('Task not found', 404);
        }
        
        // Get proofs
        $stmt = $pdo->prepare("
            SELECT * FROM task_proofs
            WHERE task_id = ?
            ORDER BY step_number ASC
        ");
        $stmt->execute([$task_id]);
        $proofs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendSuccessResponse([
            'task' => formatTaskForApi($task),
            'proofs' => $proofs
        ]);
    } catch (PDOException $e) {
        error_log("Get task detail error: " . $e->getMessage());
        sendErrorResponse('Failed to fetch task details', 500);
    }
}

/**
 * Submit order placed proof
 */
function submitOrder($pdo, $user) {
    $data = getRequestBody();
    
    $required = ['task_id', 'order_id', 'proof_url'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    try {
        // Verify task ownership
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['task_id'], $user['id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            sendErrorResponse('Task not found', 404);
        }
        
        if ($task['current_step'] != 0) {
            sendErrorResponse('Order already submitted', 400);
        }
        
        // Insert proof
        $stmt = $pdo->prepare("
            INSERT INTO task_proofs (task_id, step_number, proof_url, proof_text)
            VALUES (?, 1, ?, ?)
        ");
        $stmt->execute([$data['task_id'], $data['proof_url'], $data['order_id']]);
        
        // Update task
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET current_step = 1, order_placed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$data['task_id']]);
        
        sendSuccessResponse([], 'Order submitted successfully');
    } catch (PDOException $e) {
        error_log("Submit order error: " . $e->getMessage());
        sendErrorResponse('Failed to submit order', 500);
    }
}

/**
 * Submit delivery received proof
 */
function submitDelivery($pdo, $user) {
    $data = getRequestBody();
    
    $required = ['task_id', 'proof_url'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['task_id'], $user['id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            sendErrorResponse('Task not found', 404);
        }
        
        if ($task['current_step'] != 1) {
            sendErrorResponse('Invalid step', 400);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO task_proofs (task_id, step_number, proof_url)
            VALUES (?, 2, ?)
        ");
        $stmt->execute([$data['task_id'], $data['proof_url']]);
        
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET current_step = 2, delivery_received_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$data['task_id']]);
        
        sendSuccessResponse([], 'Delivery proof submitted successfully');
    } catch (PDOException $e) {
        error_log("Submit delivery error: " . $e->getMessage());
        sendErrorResponse('Failed to submit delivery proof', 500);
    }
}

/**
 * Submit review proof
 */
function submitReview($pdo, $user) {
    $data = getRequestBody();
    
    $required = ['task_id', 'proof_url'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['task_id'], $user['id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            sendErrorResponse('Task not found', 404);
        }
        
        if ($task['current_step'] != 2) {
            sendErrorResponse('Invalid step', 400);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO task_proofs (task_id, step_number, proof_url)
            VALUES (?, 3, ?)
        ");
        $stmt->execute([$data['task_id'], $data['proof_url']]);
        
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET current_step = 3, review_submitted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$data['task_id']]);
        
        sendSuccessResponse([], 'Review proof submitted successfully');
    } catch (PDOException $e) {
        error_log("Submit review error: " . $e->getMessage());
        sendErrorResponse('Failed to submit review proof', 500);
    }
}

/**
 * Submit refund proof
 */
function submitRefund($pdo, $user) {
    $data = getRequestBody();
    
    $required = ['task_id', 'proof_url'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['task_id'], $user['id']]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            sendErrorResponse('Task not found', 404);
        }
        
        if ($task['current_step'] != 3) {
            sendErrorResponse('Invalid step', 400);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO task_proofs (task_id, step_number, proof_url)
            VALUES (?, 4, ?)
        ");
        $stmt->execute([$data['task_id'], $data['proof_url']]);
        
        $stmt = $pdo->prepare("
            UPDATE tasks 
            SET current_step = 4, refund_requested_at = NOW(), refund_requested = 1
            WHERE id = ?
        ");
        $stmt->execute([$data['task_id']]);
        
        sendSuccessResponse([], 'Refund proof submitted successfully');
    } catch (PDOException $e) {
        error_log("Submit refund error: " . $e->getMessage());
        sendErrorResponse('Failed to submit refund proof', 500);
    }
}
