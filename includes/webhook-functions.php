<?php
/**
 * Webhook System Helper Functions
 * Phase 7: Advanced Automation & Intelligence Features
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Register a new webhook
 */
function registerWebhook($db, $webhook_data) {
    try {
        $secret_key = bin2hex(random_bytes(32));
        
        $stmt = $db->prepare("
            INSERT INTO webhooks 
            (name, url, events, secret_key, retry_count, timeout, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $webhook_data['name'],
            $webhook_data['url'],
            json_encode($webhook_data['events']),
            $secret_key,
            $webhook_data['retry_count'] ?? 3,
            $webhook_data['timeout'] ?? 30,
            $webhook_data['created_by']
        ]);
    } catch (Exception $e) {
        error_log("Error registering webhook: " . $e->getMessage());
        return false;
    }
}

/**
 * Trigger webhook for an event
 */
function triggerWebhook($db, $event_type, $payload) {
    try {
        // Get all active webhooks for this event
        $stmt = $db->prepare("
            SELECT * FROM webhooks 
            WHERE is_active = 1 
            AND JSON_CONTAINS(events, ?)
        ");
        $stmt->execute([json_encode($event_type)]);
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($webhooks as $webhook) {
            sendWebhook($db, $webhook, $event_type, $payload);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error triggering webhook: " . $e->getMessage());
        return false;
    }
}

/**
 * Send webhook HTTP request
 */
function sendWebhook($db, $webhook, $event_type, $payload) {
    try {
        // Prepare payload
        $webhook_payload = [
            'event' => $event_type,
            'timestamp' => time(),
            'data' => $payload
        ];
        
        $json_payload = json_encode($webhook_payload);
        
        // Generate signature
        $signature = hash_hmac('sha256', $json_payload, $webhook['secret_key']);
        
        // Log the webhook attempt
        $log_stmt = $db->prepare("
            INSERT INTO webhook_logs 
            (webhook_id, event_type, payload, status, attempts)
            VALUES (?, ?, ?, 'pending', 1)
        ");
        $log_stmt->execute([$webhook['id'], $event_type, $json_payload]);
        $log_id = $db->lastInsertId();
        
        // Send HTTP request
        $ch = curl_init($webhook['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, $webhook['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Signature: ' . $signature,
            'User-Agent: ReviewFlow-Webhook/1.0'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Update log
        if ($http_code >= 200 && $http_code < 300) {
            $update_stmt = $db->prepare("
                UPDATE webhook_logs 
                SET status = 'success', response_code = ?, response_body = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$http_code, $response, $log_id]);
        } else {
            // Schedule retry if configured
            $retry_after = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            $update_stmt = $db->prepare("
                UPDATE webhook_logs 
                SET status = 'failed', response_code = ?, response_body = ?, next_retry = ?
                WHERE id = ?
            ");
            $update_stmt->execute([$http_code, $error ?: $response, $retry_after, $log_id]);
        }
        
        return $http_code >= 200 && $http_code < 300;
        
    } catch (Exception $e) {
        error_log("Error sending webhook: " . $e->getMessage());
        return false;
    }
}

/**
 * Retry failed webhooks
 */
function retryFailedWebhooks($db) {
    try {
        // Get webhooks that need retry
        $stmt = $db->query("
            SELECT wl.*, w.url, w.secret_key, w.timeout, w.retry_count as max_retries
            FROM webhook_logs wl
            JOIN webhooks w ON wl.webhook_id = w.id
            WHERE wl.status = 'failed'
            AND wl.next_retry <= NOW()
            AND wl.attempts < w.retry_count
            AND w.is_active = 1
        ");
        $failed_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $retried = 0;
        foreach ($failed_logs as $log) {
            // Increment attempt count
            $update_attempts = $db->prepare("
                UPDATE webhook_logs 
                SET attempts = attempts + 1
                WHERE id = ?
            ");
            $update_attempts->execute([$log['id']]);
            
            // Recreate webhook structure for sendWebhook
            $webhook = [
                'id' => $log['webhook_id'],
                'url' => $log['url'],
                'secret_key' => $log['secret_key'],
                'timeout' => $log['timeout']
            ];
            
            $payload = json_decode($log['payload'], true);
            
            if (sendWebhook($db, $webhook, $log['event_type'], $payload['data'] ?? [])) {
                $retried++;
            }
        }
        
        return $retried;
    } catch (Exception $e) {
        error_log("Error retrying webhooks: " . $e->getMessage());
        return 0;
    }
}

/**
 * Verify webhook signature for incoming webhooks
 */
function verifyWebhookSignature($payload, $signature, $secret_key) {
    $expected_signature = hash_hmac('sha256', $payload, $secret_key);
    return hash_equals($expected_signature, $signature);
}

/**
 * Process incoming webhook
 */
function processIncomingWebhook($db, $headers, $body) {
    try {
        // Extract signature from headers
        $signature = $headers['X-Webhook-Signature'] ?? '';
        
        if (empty($signature)) {
            return ['success' => false, 'message' => 'Missing signature'];
        }
        
        // Decode payload
        $payload = json_decode($body, true);
        
        if (!$payload) {
            return ['success' => false, 'message' => 'Invalid JSON payload'];
        }
        
        // Verify signature (in production, validate against registered webhooks)
        // For now, just log the incoming webhook
        
        // Log incoming webhook
        // You'd need a separate table for incoming webhooks if needed
        
        return ['success' => true, 'message' => 'Webhook received'];
        
    } catch (Exception $e) {
        error_log("Error processing incoming webhook: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get webhook logs
 */
function getWebhookLogs($db, $webhook_id = null, $limit = 100) {
    try {
        if ($webhook_id) {
            $stmt = $db->prepare("
                SELECT * FROM webhook_logs 
                WHERE webhook_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$webhook_id, $limit]);
        } else {
            $stmt = $db->prepare("
                SELECT wl.*, w.name as webhook_name
                FROM webhook_logs wl
                JOIN webhooks w ON wl.webhook_id = w.id
                ORDER BY wl.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting webhook logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Get webhook statistics
 */
function getWebhookStatistics($db, $webhook_id = null, $days = 7) {
    try {
        $where = "WHERE wl.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params = [$days];
        
        if ($webhook_id) {
            $where .= " AND wl.webhook_id = ?";
            $params[] = $webhook_id;
        }
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(CASE WHEN status = 'success' THEN attempts ELSE NULL END) as avg_attempts
            FROM webhook_logs wl
            $where
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting webhook statistics: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all registered webhooks
 */
function getAllWebhooks($db, $active_only = false) {
    try {
        if ($active_only) {
            $stmt = $db->query("SELECT * FROM webhooks WHERE is_active = 1 ORDER BY created_at DESC");
        } else {
            $stmt = $db->query("SELECT * FROM webhooks ORDER BY created_at DESC");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting webhooks: " . $e->getMessage());
        return [];
    }
}

/**
 * Update webhook
 */
function updateWebhook($db, $webhook_id, $data) {
    try {
        $updates = [];
        $params = [];
        
        if (isset($data['name'])) {
            $updates[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['url'])) {
            $updates[] = "url = ?";
            $params[] = $data['url'];
        }
        if (isset($data['events'])) {
            $updates[] = "events = ?";
            $params[] = json_encode($data['events']);
        }
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = $data['is_active'];
        }
        if (isset($data['retry_count'])) {
            $updates[] = "retry_count = ?";
            $params[] = $data['retry_count'];
        }
        if (isset($data['timeout'])) {
            $updates[] = "timeout = ?";
            $params[] = $data['timeout'];
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $webhook_id;
        $set_clause = implode(', ', $updates);
        
        $stmt = $db->prepare("UPDATE webhooks SET $set_clause WHERE id = ?");
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Error updating webhook: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete webhook
 */
function deleteWebhook($db, $webhook_id) {
    try {
        $stmt = $db->prepare("DELETE FROM webhooks WHERE id = ?");
        return $stmt->execute([$webhook_id]);
    } catch (Exception $e) {
        error_log("Error deleting webhook: " . $e->getMessage());
        return false;
    }
}

/**
 * Test webhook
 */
function testWebhook($db, $webhook_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM webhooks WHERE id = ?");
        $stmt->execute([$webhook_id]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$webhook) {
            return ['success' => false, 'message' => 'Webhook not found'];
        }
        
        $test_payload = [
            'test' => true,
            'message' => 'This is a test webhook'
        ];
        
        $result = sendWebhook($db, $webhook, 'test', $test_payload);
        
        if ($result) {
            return ['success' => true, 'message' => 'Test webhook sent successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to send test webhook'];
        }
    } catch (Exception $e) {
        error_log("Error testing webhook: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Available webhook events
 */
function getAvailableWebhookEvents() {
    return [
        'task.created' => 'Task Created',
        'task.assigned' => 'Task Assigned',
        'task.completed' => 'Task Completed',
        'task.rejected' => 'Task Rejected',
        'payment.processed' => 'Payment Processed',
        'payment.failed' => 'Payment Failed',
        'user.registered' => 'User Registered',
        'user.verified' => 'User Verified',
        'review.submitted' => 'Review Submitted',
        'withdrawal.requested' => 'Withdrawal Requested',
        'withdrawal.completed' => 'Withdrawal Completed'
    ];
}
