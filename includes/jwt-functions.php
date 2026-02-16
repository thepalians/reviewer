<?php
/**
 * JWT (JSON Web Token) Functions
 * Helper functions for JWT authentication
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// JWT Configuration
define('JWT_SECRET', env('JWT_SECRET', bin2hex(random_bytes(32))));
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 3600); // 1 hour
define('JWT_REFRESH_EXPIRY', 2592000); // 30 days

/**
 * Base64 URL encode
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL decode
 */
function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Generate JWT token
 */
function generateJwtToken($user_id, $user_type = 'user', $additional_claims = []) {
    $header = [
        'typ' => 'JWT',
        'alg' => JWT_ALGORITHM
    ];
    
    $issued_at = time();
    $expiry = $issued_at + JWT_EXPIRY;
    
    $payload = array_merge([
        'iss' => APP_NAME,
        'iat' => $issued_at,
        'exp' => $expiry,
        'user_id' => $user_id,
        'user_type' => $user_type
    ], $additional_claims);
    
    $header_encoded = base64UrlEncode(json_encode($header));
    $payload_encoded = base64UrlEncode(json_encode($payload));
    
    $signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", JWT_SECRET, true);
    $signature_encoded = base64UrlEncode($signature);
    
    return "$header_encoded.$payload_encoded.$signature_encoded";
}

/**
 * Generate refresh token
 */
function generateRefreshToken($user_id) {
    return bin2hex(random_bytes(32)) . '_' . $user_id . '_' . time();
}

/**
 * Verify JWT token
 */
function verifyJwtToken($token) {
    try {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return ['valid' => false, 'error' => 'Invalid token format'];
        }
        
        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;
        
        // Verify signature
        $signature = base64UrlDecode($signature_encoded);
        $expected_signature = hash_hmac('sha256', "$header_encoded.$payload_encoded", JWT_SECRET, true);
        
        if (!hash_equals($signature, $expected_signature)) {
            return ['valid' => false, 'error' => 'Invalid signature'];
        }
        
        // Decode payload
        $payload = json_decode(base64UrlDecode($payload_encoded), true);
        
        if (!$payload) {
            return ['valid' => false, 'error' => 'Invalid payload'];
        }
        
        // Check expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return ['valid' => false, 'error' => 'Token expired'];
        }
        
        return ['valid' => true, 'payload' => $payload];
    } catch (Exception $e) {
        error_log("JWT verification error: " . $e->getMessage());
        return ['valid' => false, 'error' => 'Verification failed'];
    }
}

/**
 * Create JWT session
 */
function createJwtSession($pdo, $user_id, $device_id = null, $device_type = null) {
    try {
        $token = generateJwtToken($user_id);
        $refresh_token = generateRefreshToken($user_id);
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $expires_at = date('Y-m-d H:i:s', time() + JWT_EXPIRY);
        $refresh_expires_at = date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRY);
        
        $stmt = $pdo->prepare("
            INSERT INTO jwt_tokens
            (user_id, token, refresh_token, device_id, device_type, ip_address, 
             user_agent, expires_at, refresh_expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $token,
            $refresh_token,
            $device_id,
            $device_type,
            $ip_address,
            $user_agent,
            $expires_at,
            $refresh_expires_at
        ]);
        
        return [
            'token' => $token,
            'refresh_token' => $refresh_token,
            'expires_at' => $expires_at,
            'token_type' => 'Bearer'
        ];
    } catch (PDOException $e) {
        error_log("Error creating JWT session: " . $e->getMessage());
        return false;
    }
}

/**
 * Refresh JWT token
 */
function refreshJwtToken($pdo, $refresh_token) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM jwt_tokens
            WHERE refresh_token = ?
            AND is_revoked = 0
            AND refresh_expires_at > NOW()
        ");
        $stmt->execute([$refresh_token]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$token_data) {
            return ['success' => false, 'error' => 'Invalid refresh token'];
        }
        
        // Generate new tokens
        $new_token = generateJwtToken($token_data['user_id']);
        $new_refresh_token = generateRefreshToken($token_data['user_id']);
        
        $expires_at = date('Y-m-d H:i:s', time() + JWT_EXPIRY);
        $refresh_expires_at = date('Y-m-d H:i:s', time() + JWT_REFRESH_EXPIRY);
        
        // Update token
        $stmt = $pdo->prepare("
            UPDATE jwt_tokens
            SET token = ?, refresh_token = ?, expires_at = ?, refresh_expires_at = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $new_token,
            $new_refresh_token,
            $expires_at,
            $refresh_expires_at,
            $token_data['id']
        ]);
        
        return [
            'success' => true,
            'token' => $new_token,
            'refresh_token' => $new_refresh_token,
            'expires_at' => $expires_at,
            'token_type' => 'Bearer'
        ];
    } catch (PDOException $e) {
        error_log("Error refreshing JWT token: " . $e->getMessage());
        return ['success' => false, 'error' => 'Refresh failed'];
    }
}

/**
 * Revoke JWT token
 */
function revokeJwtToken($pdo, $token) {
    try {
        $stmt = $pdo->prepare("
            UPDATE jwt_tokens
            SET is_revoked = 1
            WHERE token = ?
        ");
        return $stmt->execute([$token]);
    } catch (PDOException $e) {
        error_log("Error revoking JWT token: " . $e->getMessage());
        return false;
    }
}

/**
 * Revoke all user tokens
 */
function revokeAllUserTokens($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE jwt_tokens
            SET is_revoked = 1
            WHERE user_id = ? AND is_revoked = 0
        ");
        return $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Error revoking all user tokens: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean up expired tokens
 */
function cleanupExpiredTokens($pdo, $days = 30) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM jwt_tokens
            WHERE refresh_expires_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        return $stmt->execute([$days]);
    } catch (PDOException $e) {
        error_log("Error cleaning up expired tokens: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user from JWT token
 */
function getUserFromJwtToken($pdo, $token) {
    try {
        // Verify token
        $verification = verifyJwtToken($token);
        
        if (!$verification['valid']) {
            return ['success' => false, 'error' => $verification['error']];
        }
        
        $payload = $verification['payload'];
        $user_id = $payload['user_id'];
        
        // Check if token is revoked
        $stmt = $pdo->prepare("
            SELECT * FROM jwt_tokens
            WHERE token = ? AND is_revoked = 0
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$token_data) {
            return ['success' => false, 'error' => 'Token revoked or not found'];
        }
        
        // Get user data
        $stmt = $pdo->prepare("
            SELECT id, username, email, user_type, phone, is_active
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }
        
        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'User account is inactive'];
        }
        
        return ['success' => true, 'user' => $user];
    } catch (PDOException $e) {
        error_log("Error getting user from JWT: " . $e->getMessage());
        return ['success' => false, 'error' => 'Authentication failed'];
    }
}

/**
 * Get active tokens for user
 */
function getUserActiveTokens($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, device_type, device_id, ip_address, created_at, expires_at
            FROM jwt_tokens
            WHERE user_id = ?
            AND is_revoked = 0
            AND expires_at > NOW()
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active tokens: " . $e->getMessage());
        return [];
    }
}

/**
 * Require authentication middleware
 */
function requireJwtAuth($pdo) {
    require_once __DIR__ . '/api-functions.php';
    
    $token = getBearerToken();
    
    if (!$token) {
        sendErrorResponse('Authentication required', 401);
    }
    
    $result = getUserFromJwtToken($pdo, $token);
    
    if (!$result['success']) {
        sendErrorResponse($result['error'], 401);
    }
    
    return $result['user'];
}
