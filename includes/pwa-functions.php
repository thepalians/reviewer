<?php
/**
 * Progressive Web App (PWA) Functions
 * Handles push notifications, service worker registration, and offline support
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Save push subscription to database
 * 
 * @param int $userId
 * @param array $subscription
 * @return bool
 */
function savePushSubscription(int $userId, array $subscription): bool {
    global $conn;
    
    $endpoint = $subscription['endpoint'] ?? '';
    $p256dhKey = $subscription['keys']['p256dh'] ?? '';
    $authKey = $subscription['keys']['auth'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($endpoint) || empty($p256dhKey) || empty($authKey)) {
        return false;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO push_subscriptions 
        (user_id, endpoint, p256dh_key, auth_key, user_agent, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
        p256dh_key = VALUES(p256dh_key),
        auth_key = VALUES(auth_key),
        user_agent = VALUES(user_agent),
        is_active = 1,
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param('sssss', $userId, $endpoint, $p256dhKey, $authKey, $userAgent);
    
    return $stmt->execute();
}

/**
 * Get push subscriptions for a user
 * 
 * @param int $userId
 * @return array
 */
function getUserPushSubscriptions(int $userId): array {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM push_subscriptions 
        WHERE user_id = ? AND is_active = 1
        ORDER BY created_at DESC
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subscriptions = [];
    while ($row = $result->fetch_assoc()) {
        $subscriptions[] = $row;
    }
    
    return $subscriptions;
}

/**
 * Delete push subscription
 * 
 * @param int $userId
 * @param string $endpoint
 * @return bool
 */
function deletePushSubscription(int $userId, string $endpoint): bool {
    global $conn;
    
    $stmt = $conn->prepare("
        DELETE FROM push_subscriptions 
        WHERE user_id = ? AND endpoint = ?
    ");
    
    $stmt->bind_param('is', $userId, $endpoint);
    
    return $stmt->execute();
}

/**
 * Send push notification to user
 * 
 * @param int $userId
 * @param string $title
 * @param string $body
 * @param array $options Additional options
 * @return int Number of notifications sent
 */
function sendPushNotification(int $userId, string $title, string $body, array $options = []): int {
    $subscriptions = getUserPushSubscriptions($userId);
    
    if (empty($subscriptions)) {
        return 0;
    }
    
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => $options['icon'] ?? '/reviewer/assets/img/icon-192.png',
        'badge' => $options['badge'] ?? '/reviewer/assets/img/badge-72.png',
        'data' => $options['data'] ?? ['url' => '/reviewer/user/'],
        'vibrate' => $options['vibrate'] ?? [100, 50, 100]
    ]);
    
    $sentCount = 0;
    
    foreach ($subscriptions as $subscription) {
        $success = sendWebPush(
            $subscription['endpoint'],
            $subscription['p256dh_key'],
            $subscription['auth_key'],
            $payload
        );
        
        if ($success) {
            $sentCount++;
        }
    }
    
    return $sentCount;
}

/**
 * Send Web Push notification
 * 
 * @param string $endpoint
 * @param string $p256dh
 * @param string $auth
 * @param string $payload
 * @return bool
 */
function sendWebPush(string $endpoint, string $p256dh, string $auth, string $payload): bool {
    // In a real implementation, you would use a library like web-push-php
    // For now, this is a placeholder that logs the notification
    
    // Check if web-push library is available
    if (!class_exists('Minishlink\WebPush\WebPush')) {
        error_log("Web Push library not available. Notification not sent: " . $payload);
        return false;
    }
    
    try {
        // Get VAPID keys from settings
        $vapidPublic = getPWASetting('push_vapid_public');
        $vapidPrivate = getPWASetting('push_vapid_private');
        
        if (empty($vapidPublic) || empty($vapidPrivate)) {
            error_log("VAPID keys not configured");
            return false;
        }
        
        $auth = [
            'VAPID' => [
                'subject' => APP_URL,
                'publicKey' => $vapidPublic,
                'privateKey' => $vapidPrivate
            ]
        ];
        
        $webPush = new \Minishlink\WebPush\WebPush($auth);
        
        $subscription = \Minishlink\WebPush\Subscription::create([
            'endpoint' => $endpoint,
            'publicKey' => $p256dh,
            'authToken' => $auth
        ]);
        
        $result = $webPush->sendOneNotification($subscription, $payload);
        
        return $result->isSuccess();
    } catch (Exception $e) {
        error_log("Web Push error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get PWA setting value
 * 
 * @param string $key
 * @return string|null
 */
function getPWASetting(string $key): ?string {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT setting_value FROM pwa_settings WHERE setting_key = ?
    ");
    
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    
    return null;
}

/**
 * Update PWA setting
 * 
 * @param string $key
 * @param string $value
 * @return bool
 */
function updatePWASetting(string $key, string $value): bool {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE pwa_settings 
        SET setting_value = ?, updated_at = CURRENT_TIMESTAMP
        WHERE setting_key = ?
    ");
    
    $stmt->bind_param('ss', $value, $key);
    
    return $stmt->execute();
}

/**
 * Check if push notifications are enabled
 * 
 * @return bool
 */
function isPushEnabled(): bool {
    $setting = getPWASetting('push_enabled');
    return $setting === '1';
}

/**
 * Check if offline mode is enabled
 * 
 * @return bool
 */
function isOfflineEnabled(): bool {
    $setting = getPWASetting('offline_enabled');
    return $setting === '1';
}

/**
 * Get all PWA settings
 * 
 * @return array
 */
function getAllPWASettings(): array {
    global $conn;
    
    $result = $conn->query("
        SELECT * FROM pwa_settings ORDER BY setting_key
    ");
    
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    return $settings;
}

/**
 * Generate VAPID keys for push notifications
 * 
 * @return array ['publicKey' => string, 'privateKey' => string]
 */
function generateVAPIDKeys(): array {
    // In a real implementation, use web-push-php library
    // For now, return placeholder keys
    
    if (class_exists('Minishlink\WebPush\VAPID')) {
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        return [
            'publicKey' => $keys['publicKey'],
            'privateKey' => $keys['privateKey']
        ];
    }
    
    // Fallback to random keys (NOT SECURE - for demo only)
    return [
        'publicKey' => base64_encode(random_bytes(65)),
        'privateKey' => base64_encode(random_bytes(32))
    ];
}

/**
 * Save VAPID keys to database
 * 
 * @param string $publicKey
 * @param string $privateKey
 * @return bool
 */
function saveVAPIDKeys(string $publicKey, string $privateKey): bool {
    $success1 = updatePWASetting('push_vapid_public', $publicKey);
    $success2 = updatePWASetting('push_vapid_private', $privateKey);
    
    return $success1 && $success2;
}

/**
 * Cleanup inactive subscriptions
 * 
 * @param int $daysInactive
 * @return int Number of subscriptions removed
 */
function cleanupInactiveSubscriptions(int $daysInactive = 90): int {
    global $conn;
    
    $stmt = $conn->prepare("
        DELETE FROM push_subscriptions 
        WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    
    $stmt->bind_param('i', $daysInactive);
    $stmt->execute();
    
    return $conn->affected_rows;
}

/**
 * Get push notification statistics
 * 
 * @return array
 */
function getPushStatistics(): array {
    global $conn;
    
    $stats = [
        'total_subscriptions' => 0,
        'active_subscriptions' => 0,
        'inactive_subscriptions' => 0,
        'unique_users' => 0
    ];
    
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(is_active) as active,
            COUNT(DISTINCT user_id) as unique_users
        FROM push_subscriptions
    ");
    
    if ($row = $result->fetch_assoc()) {
        $stats['total_subscriptions'] = (int)$row['total'];
        $stats['active_subscriptions'] = (int)$row['active'];
        $stats['inactive_subscriptions'] = $stats['total_subscriptions'] - $stats['active_subscriptions'];
        $stats['unique_users'] = (int)$row['unique_users'];
    }
    
    return $stats;
}
