<?php
/**
 * API v1 - Notifications Endpoints
 * Handles notifications listing and management
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/api-functions.php';
require_once __DIR__ . '/../../includes/jwt-functions.php';

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
if ($request_method === 'GET') {
    getNotifications($pdo, $user);
} elseif ($request_method === 'POST' && strpos($request_uri, '/mark-read') !== false) {
    markAsRead($pdo, $user);
} elseif ($request_method === 'POST' && strpos($request_uri, '/mark-all-read') !== false) {
    markAllAsRead($pdo, $user);
} elseif ($request_method === 'DELETE' && preg_match('/\/notifications\/(\d+)$/', $request_uri, $matches)) {
    deleteNotification($pdo, $user, $matches[1]);
} else {
    sendErrorResponse('Endpoint not found', 404);
}

/**
 * Get notifications
 */
function getNotifications($pdo, $user) {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? min((int)$_GET['per_page'], 50) : 20;
        $unread_only = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
        
        $query = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$user['id']];
        
        if ($unread_only) {
            $query .= " AND is_read = 0";
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $result = paginateResults($pdo, $query, $params, $page, $per_page);
        
        // Format notifications
        $notifications = array_map('formatNotificationForApi', $result['data']);
        
        // Get unread count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user['id']]);
        $unread_count = $stmt->fetchColumn();
        
        sendSuccessResponse([
            'notifications' => $notifications,
            'unread_count' => (int)$unread_count,
            'pagination' => $result['pagination']
        ]);
    } catch (PDOException $e) {
        error_log("Get notifications error: " . $e->getMessage());
        sendErrorResponse('Failed to fetch notifications', 500);
    }
}

/**
 * Mark notification as read
 */
function markAsRead($pdo, $user) {
    $data = getRequestBody();
    
    if (!isset($data['notification_id'])) {
        sendErrorResponse('Notification ID required', 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$data['notification_id'], $user['id']]);
        
        sendSuccessResponse([], 'Notification marked as read');
    } catch (PDOException $e) {
        error_log("Mark as read error: " . $e->getMessage());
        sendErrorResponse('Failed to update notification', 500);
    }
}

/**
 * Mark all notifications as read
 */
function markAllAsRead($pdo, $user) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user['id']]);
        
        sendSuccessResponse([], 'All notifications marked as read');
    } catch (PDOException $e) {
        error_log("Mark all as read error: " . $e->getMessage());
        sendErrorResponse('Failed to update notifications', 500);
    }
}

/**
 * Delete notification
 */
function deleteNotification($pdo, $user, $notification_id) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$notification_id, $user['id']]);
        
        if ($stmt->rowCount() > 0) {
            sendSuccessResponse([], 'Notification deleted');
        } else {
            sendErrorResponse('Notification not found', 404);
        }
    } catch (PDOException $e) {
        error_log("Delete notification error: " . $e->getMessage());
        sendErrorResponse('Failed to delete notification', 500);
    }
}
