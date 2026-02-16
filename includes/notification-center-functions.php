<?php
/**
 * Notification Center Functions
 * Helper functions for advanced notification management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Get all notification categories
 */
function getNotificationCategories($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT * FROM notification_categories
            WHERE is_active = 1
            ORDER BY name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting notification categories: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user notification settings
 */
function getUserNotificationSettings($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT uns.*, nc.name as category_name, nc.icon, nc.color
            FROM user_notification_settings uns
            JOIN notification_categories nc ON uns.category_id = nc.id
            WHERE uns.user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user notification settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Initialize user notification settings
 */
function initializeUserNotificationSettings($pdo, $user_id) {
    try {
        $categories = getNotificationCategories($pdo);
        
        foreach ($categories as $category) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO user_notification_settings
                (user_id, category_id, email_enabled, push_enabled, in_app_enabled)
                VALUES (?, ?, 1, 1, 1)
            ");
            $stmt->execute([$user_id, $category['id']]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error initializing notification settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Update notification settings
 */
function updateNotificationSettings($pdo, $user_id, $category_id, $settings) {
    try {
        $fields = [];
        $values = [];
        
        foreach ($settings as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $user_id;
        $values[] = $category_id;
        
        $sql = "UPDATE user_notification_settings SET " . implode(', ', $fields) . " WHERE user_id = ? AND category_id = ?";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute($values);
    } catch (PDOException $e) {
        error_log("Error updating notification settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user notifications with category info
 */
function getUserNotificationsWithCategory($pdo, $user_id, $limit = 50, $offset = 0, $category_id = null) {
    try {
        $query = "
            SELECT n.*, nc.name as category_name, nc.icon, nc.color
            FROM notifications n
            LEFT JOIN notification_categories nc ON n.category_id = nc.id
            WHERE n.user_id = ?
        ";
        
        $params = [$user_id];
        
        if ($category_id) {
            $query .= " AND n.category_id = ?";
            $params[] = $category_id;
        }
        
        $query .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting notifications with category: " . $e->getMessage());
        return [];
    }
}

/**
 * Get notification category by type
 */
function getNotificationCategoryByType($pdo, $type) {
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM notification_categories
            WHERE LOWER(name) = LOWER(?)
            AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    } catch (PDOException $e) {
        error_log("Error getting category by type: " . $e->getMessage());
        return null;
    }
}

/**
 * Create notification with category
 */
function createNotificationWithCategory($pdo, $user_id, $title, $message, $category_name = 'System', $link = null) {
    try {
        $category_id = getNotificationCategoryByType($pdo, $category_name);
        
        if (!$category_id) {
            $category_id = 1; // Default to System category
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, category_id, title, message, link)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$user_id, $category_id, $title, $message, $link]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark notifications as read by category
 */
function markNotificationsReadByCategory($pdo, $user_id, $category_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ? AND category_id = ? AND is_read = 0
        ");
        return $stmt->execute([$user_id, $category_id]);
    } catch (PDOException $e) {
        error_log("Error marking notifications read: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread count by category
 */
function getUnreadCountByCategory($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT nc.id, nc.name, nc.icon, nc.color,
                   COUNT(n.id) as unread_count
            FROM notification_categories nc
            LEFT JOIN notifications n ON nc.id = n.category_id 
                AND n.user_id = ? AND n.is_read = 0
            WHERE nc.is_active = 1
            GROUP BY nc.id
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting unread count by category: " . $e->getMessage());
        return [];
    }
}

/**
 * Delete notifications by category
 */
function deleteNotificationsByCategory($pdo, $user_id, $category_id) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE user_id = ? AND category_id = ?
        ");
        return $stmt->execute([$user_id, $category_id]);
    } catch (PDOException $e) {
        error_log("Error deleting notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has notification preferences enabled
 */
function isNotificationEnabled($pdo, $user_id, $category_name, $channel = 'in_app') {
    try {
        $category_id = getNotificationCategoryByType($pdo, $category_name);
        
        if (!$category_id) {
            return true; // Default to enabled if category not found
        }
        
        $column = $channel . '_enabled';
        
        $stmt = $pdo->prepare("
            SELECT $column FROM user_notification_settings
            WHERE user_id = ? AND category_id = ?
        ");
        $stmt->execute([$user_id, $category_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (bool)$result[$column] : true;
    } catch (PDOException $e) {
        error_log("Error checking notification enabled: " . $e->getMessage());
        return true; // Default to enabled on error
    }
}

/**
 * Send push notification (placeholder for integration)
 */
function sendPushNotification($pdo, $user_id, $title, $message, $data = []) {
    try {
        // This is a placeholder for push notification integration
        // You would integrate with services like Firebase Cloud Messaging, OneSignal, etc.
        
        // Log the attempt
        error_log("Push notification to user $user_id: $title");
        
        // Return true for now
        return true;
    } catch (Exception $e) {
        error_log("Error sending push notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Bulk mark notifications as read
 */
function bulkMarkNotificationsRead($pdo, $user_id, $notification_ids) {
    try {
        if (empty($notification_ids)) {
            return false;
        }
        
        $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
        $params = array_merge([$user_id], $notification_ids);
        
        $stmt = $pdo->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ? AND id IN ($placeholders)
        ");
        
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error bulk marking notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Bulk delete notifications
 */
function bulkDeleteNotifications($pdo, $user_id, $notification_ids) {
    try {
        if (empty($notification_ids)) {
            return false;
        }
        
        $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
        $params = array_merge([$user_id], $notification_ids);
        
        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE user_id = ? AND id IN ($placeholders)
        ");
        
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error bulk deleting notifications: " . $e->getMessage());
        return false;
    }
}
