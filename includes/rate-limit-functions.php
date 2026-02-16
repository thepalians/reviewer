<?php
/**
 * Rate Limit Functions
 * Helper functions for API rate limiting and throttling
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Check rate limit
 */
function checkRateLimit($pdo, $identifier, $identifier_type = 'ip', $endpoint = 'general', $limit = 100, $window_minutes = 60) {
    try {
        $window_start = date('Y-m-d H:i:s', strtotime("-$window_minutes minutes"));
        $window_end = date('Y-m-d H:i:s');
        
        // Get or create rate limit record
        $stmt = $pdo->prepare("
            SELECT request_count FROM rate_limit_tracking
            WHERE identifier = ? 
            AND identifier_type = ?
            AND endpoint = ?
            AND window_end > NOW()
            ORDER BY window_start DESC
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier_type, $endpoint]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            if ($record['request_count'] >= $limit) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_at' => $window_end
                ];
            }
            
            // Increment count
            $stmt = $pdo->prepare("
                UPDATE rate_limit_tracking
                SET request_count = request_count + 1
                WHERE identifier = ? 
                AND identifier_type = ?
                AND endpoint = ?
                AND window_end > NOW()
            ");
            $stmt->execute([$identifier, $identifier_type, $endpoint]);
            
            return [
                'allowed' => true,
                'remaining' => $limit - $record['request_count'] - 1,
                'reset_at' => $window_end
            ];
        } else {
            // Create new rate limit record
            $stmt = $pdo->prepare("
                INSERT INTO rate_limit_tracking
                (identifier, identifier_type, endpoint, request_count, window_start, window_end)
                VALUES (?, ?, ?, 1, ?, ?)
            ");
            $stmt->execute([$identifier, $identifier_type, $endpoint, $window_start, $window_end]);
            
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'reset_at' => $window_end
            ];
        }
    } catch (PDOException $e) {
        error_log("Error checking rate limit: " . $e->getMessage());
        // On error, allow the request
        return ['allowed' => true, 'remaining' => $limit, 'reset_at' => $window_end];
    }
}

/**
 * Clean up old rate limit records
 */
function cleanupRateLimitRecords($pdo, $days = 7) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM rate_limit_tracking
            WHERE window_end < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        return $stmt->execute([$days]);
    } catch (PDOException $e) {
        error_log("Error cleaning up rate limits: " . $e->getMessage());
        return false;
    }
}

/**
 * Get API key details
 */
function getApiKey($pdo, $api_key) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM api_keys
            WHERE api_key = ? AND is_active = 1
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$api_key]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting API key: " . $e->getMessage());
        return null;
    }
}

/**
 * Validate API key
 */
function validateApiKey($pdo, $api_key, $secret_key = null) {
    try {
        $key_data = getApiKey($pdo, $api_key);
        
        if (!$key_data) {
            return ['valid' => false, 'error' => 'Invalid API key'];
        }
        
        if ($secret_key && $key_data['secret_key'] !== $secret_key) {
            return ['valid' => false, 'error' => 'Invalid secret key'];
        }
        
        // Update last used timestamp
        $stmt = $pdo->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?");
        $stmt->execute([$key_data['id']]);
        
        return ['valid' => true, 'key_data' => $key_data];
    } catch (PDOException $e) {
        error_log("Error validating API key: " . $e->getMessage());
        return ['valid' => false, 'error' => 'Validation error'];
    }
}

/**
 * Create new API key
 */
function createApiKey($pdo, $user_id, $name, $permissions = [], $rate_limit = 1000, $expires_days = null) {
    try {
        $api_key = bin2hex(random_bytes(32));
        $secret_key = bin2hex(random_bytes(32));
        
        $expires_at = null;
        if ($expires_days) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_days days"));
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO api_keys
            (user_id, name, api_key, secret_key, permissions, rate_limit, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $permissions_json = json_encode($permissions);
        
        $stmt->execute([
            $user_id,
            $name,
            $api_key,
            $secret_key,
            $permissions_json,
            $rate_limit,
            $expires_at
        ]);
        
        return [
            'id' => $pdo->lastInsertId(),
            'api_key' => $api_key,
            'secret_key' => $secret_key
        ];
    } catch (PDOException $e) {
        error_log("Error creating API key: " . $e->getMessage());
        return false;
    }
}

/**
 * Revoke API key
 */
function revokeApiKey($pdo, $api_key_id) {
    try {
        $stmt = $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ?");
        return $stmt->execute([$api_key_id]);
    } catch (PDOException $e) {
        error_log("Error revoking API key: " . $e->getMessage());
        return false;
    }
}

/**
 * Log API usage
 */
function logApiUsage($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO api_usage_logs
            (user_id, api_key_id, endpoint, method, ip_address, request_data, response_code, response_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $request_data = isset($data['request_data']) ? json_encode($data['request_data']) : null;
        
        return $stmt->execute([
            $data['user_id'] ?? null,
            $data['api_key_id'] ?? null,
            $data['endpoint'],
            $data['method'],
            $data['ip_address'],
            $request_data,
            $data['response_code'] ?? 200,
            $data['response_time'] ?? 0
        ]);
    } catch (PDOException $e) {
        error_log("Error logging API usage: " . $e->getMessage());
        return false;
    }
}

/**
 * Get API usage statistics
 */
function getApiUsageStats($pdo, $user_id = null, $days = 30) {
    try {
        $query = "
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as request_count,
                AVG(response_time) as avg_response_time,
                SUM(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as error_count
            FROM api_usage_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        
        $params = [$days];
        
        if ($user_id) {
            $query .= " AND user_id = ?";
            $params[] = $user_id;
        }
        
        $query .= " GROUP BY DATE(created_at) ORDER BY date DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting API usage stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Get endpoint usage statistics
 */
function getEndpointStats($pdo, $days = 7) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                endpoint,
                COUNT(*) as request_count,
                AVG(response_time) as avg_response_time,
                MAX(response_time) as max_response_time,
                SUM(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as error_count
            FROM api_usage_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY endpoint
            ORDER BY request_count DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting endpoint stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's API keys
 */
function getUserApiKeys($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, api_key, rate_limit, is_active, last_used_at, expires_at, created_at
            FROM api_keys
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user API keys: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if endpoint requires throttling
 */
function shouldThrottleEndpoint($endpoint) {
    $heavy_endpoints = [
        '/api/v1/tasks',
        '/api/v1/wallet/transactions',
        '/api/v1/notifications'
    ];
    
    foreach ($heavy_endpoints as $heavy) {
        if (strpos($endpoint, $heavy) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get rate limit for endpoint
 */
function getEndpointRateLimit($endpoint) {
    if (shouldThrottleEndpoint($endpoint)) {
        return 50; // Lower limit for heavy endpoints
    }
    
    return 100; // Default limit
}
