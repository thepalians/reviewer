<?php
declare(strict_types=1);

/**
 * Firebase Functions
 * Push notifications and Firebase Cloud Messaging
 */

/**
 * Send Firebase push notification
 */
function sendFirebasePushNotification(int $userId, string $title, string $body, array $data = []): bool {
    global $pdo;
    
    try {
        // Get user's FCM tokens
        $stmt = $pdo->prepare("
            SELECT fcm_token FROM firebase_tokens 
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tokens)) {
            return false;
        }
        
        // Get Firebase server key from settings
        $serverKey = getSetting('firebase_server_key');
        if (!$serverKey) {
            error_log("Firebase server key not configured");
            return false;
        }
        
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        foreach ($tokens as $token) {
            $notification = [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => '1'
            ];
            
            $payload = [
                'to' => $token,
                'notification' => $notification,
                'data' => $data,
                'priority' => 'high'
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: key=' . $serverKey
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                error_log("Firebase notification failed for token: " . substr($token, 0, 20) . "...");
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Firebase push notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Register FCM token
 */
function registerFCMToken(int $userId, string $fcmToken, string $deviceType = 'web'): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO firebase_tokens (user_id, fcm_token, device_type, last_used)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_used = NOW(), is_active = 1
        ");
        return $stmt->execute([$userId, $fcmToken, $deviceType]);
    } catch (PDOException $e) {
        error_log("Error registering FCM token: " . $e->getMessage());
        return false;
    }
}

/**
 * Unregister FCM token
 */
function unregisterFCMToken(int $userId, string $fcmToken): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE firebase_tokens 
            SET is_active = 0 
            WHERE user_id = ? AND fcm_token = ?
        ");
        return $stmt->execute([$userId, $fcmToken]);
    } catch (PDOException $e) {
        error_log("Error unregistering FCM token: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification to multiple users
 */
function sendBulkFirebaseNotification(array $userIds, string $title, string $body, array $data = []): int {
    $successCount = 0;
    
    foreach ($userIds as $userId) {
        if (sendFirebasePushNotification($userId, $title, $body, $data)) {
            $successCount++;
        }
    }
    
    return $successCount;
}

/**
 * Send notification to topic
 */
function sendFirebaseTopicNotification(string $topic, string $title, string $body, array $data = []): bool {
    try {
        $serverKey = getSetting('firebase_server_key');
        if (!$serverKey) {
            return false;
        }
        
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $payload = [
            'to' => '/topics/' . $topic,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default'
            ],
            'data' => $data,
            'priority' => 'high'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: key=' . $serverKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    } catch (Exception $e) {
        error_log("Firebase topic notification error: " . $e->getMessage());
        return false;
    }
}
