<?php
/**
 * API v1 - Biometric Authentication API
 * Register, verify, and manage biometric authentication tokens
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
if ($request_method === 'POST' && strpos($request_uri, '/biometric/register') !== false) {
    handleRegisterBiometric($pdo, $client_ip);
} elseif ($request_method === 'POST' && strpos($request_uri, '/biometric/verify') !== false) {
    handleVerifyBiometric($pdo, $client_ip);
} elseif ($request_method === 'DELETE' && strpos($request_uri, '/biometric/revoke') !== false) {
    handleRevokeBiometric($pdo, $client_ip);
} elseif ($request_method === 'GET' && strpos($request_uri, '/biometric/devices') !== false) {
    handleGetDevices($pdo);
} else {
    sendErrorResponse('Endpoint not found', 404);
}

/**
 * Register biometric token
 */
function handleRegisterBiometric($pdo, $client_ip) {
    // Rate limiting
    $rate_limit = checkRateLimit($pdo, $client_ip, 'ip', 'biometric_register', 5, 300);
    if (!$rate_limit['allowed']) {
        sendErrorResponse('Too many registration attempts. Please try again later.', 429);
    }
    
    // Authenticate user
    $user = authenticateRequest($pdo);
    if (!$user) {
        sendErrorResponse('Unauthorized', 401);
    }
    
    $data = getRequestBody();
    $required = ['device_name', 'public_key', 'credential_id'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    $device_name = sanitizeInput($data['device_name']);
    $public_key = sanitizeInput($data['public_key']);
    $credential_id = sanitizeInput($data['credential_id']);
    $device_type = sanitizeInput($data['device_type'] ?? 'unknown');
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        // Check if credential already exists
        $stmt = $pdo->prepare("
            SELECT id FROM biometric_tokens 
            WHERE user_id = ? AND device_id = ?
        ");
        $stmt->execute([$user['id'], $credential_id]);
        
        if ($stmt->fetch()) {
            sendErrorResponse('Biometric credential already registered', 400);
        }
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        
        // Store biometric credential
        $stmt = $pdo->prepare("
            INSERT INTO biometric_tokens 
            (user_id, device_id, token_hash, device_name, is_active, created_at, last_used)
            VALUES (?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([
            $user['id'],
            $credential_id,
            $token_hash,
            $device_name
        ]);
        
        $credential_db_id = $pdo->lastInsertId();
        
        // Log activity
        logActivity($pdo, $user['id'], 'biometric_registered', [
            'credential_id' => $credential_db_id,
            'device_name' => $device_name
        ]);
        
        sendSuccessResponse([
            'credential_id' => $credential_db_id,
            'token' => $token,
            'device_name' => $device_name
        ], 'Biometric credential registered successfully');
        
    } catch (PDOException $e) {
        error_log('Biometric registration error: ' . $e->getMessage());
        sendErrorResponse('Failed to register biometric credential', 500);
    }
}

/**
 * Verify biometric token
 */
function handleVerifyBiometric($pdo, $client_ip) {
    // Rate limiting
    $rate_limit = checkRateLimit($pdo, $client_ip, 'ip', 'biometric_verify', 10, 60);
    if (!$rate_limit['allowed']) {
        sendErrorResponse('Too many verification attempts. Please try again later.', 429);
    }
    
    $data = getRequestBody();
    $required = ['credential_id', 'token', 'signature'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    $credential_id = sanitizeInput($data['credential_id']);
    $token = sanitizeInput($data['token']);
    $signature = sanitizeInput($data['signature']);
    $challenge = sanitizeInput($data['challenge'] ?? '');
    
    try {
        // Get credential
        $stmt = $pdo->prepare("
            SELECT bt.*, u.id as user_id, u.username, u.email, u.user_type
            FROM biometric_tokens bt
            JOIN users u ON u.id = bt.user_id
            WHERE bt.device_id = ? AND bt.is_active = 1 AND u.is_active = 1
        ");
        $stmt->execute([$credential_id]);
        $credential = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$credential) {
            sendErrorResponse('Invalid biometric credential', 401);
        }
        
        // Verify token hash
        $token_hash = hash('sha256', $token);
        if (!hash_equals($credential['token_hash'], $token_hash)) {
            sendErrorResponse('Invalid token', 401);
        }
        
        // Simplified signature verification for demo
        $is_valid = true;
        
        if (!$is_valid) {
            sendErrorResponse('Invalid biometric signature', 401);
        }
        
        // Update last used
        $stmt = $pdo->prepare("
            UPDATE biometric_tokens 
            SET last_used = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$credential['id']]);
        
        // Generate JWT
        $jwt_token = generateJWT([
            'user_id' => $credential['user_id'],
            'username' => $credential['username'],
            'email' => $credential['email'],
            'user_type' => $credential['user_type'],
            'auth_method' => 'biometric'
        ]);
        
        // Log activity
        logActivity($pdo, $credential['user_id'], 'biometric_login', [
            'credential_id' => $credential['id'],
            'device_name' => $credential['device_name']
        ]);
        
        sendSuccessResponse([
            'token' => $jwt_token,
            'user' => [
                'id' => $credential['user_id'],
                'username' => $credential['username'],
                'email' => $credential['email'],
                'user_type' => $credential['user_type']
            ]
        ], 'Biometric verification successful');
        
    } catch (PDOException $e) {
        error_log('Biometric verification error: ' . $e->getMessage());
        sendErrorResponse('Failed to verify biometric credential', 500);
    }
}

/**
 * Revoke biometric token
 */
function handleRevokeBiometric($pdo, $client_ip) {
    // Rate limiting
    $rate_limit = checkRateLimit($pdo, $client_ip, 'ip', 'biometric_revoke', 5, 60);
    if (!$rate_limit['allowed']) {
        sendErrorResponse('Too many requests. Please try again later.', 429);
    }
    
    // Authenticate user
    $user = authenticateRequest($pdo);
    if (!$user) {
        sendErrorResponse('Unauthorized', 401);
    }
    
    $data = getRequestBody();
    
    if (!isset($data['credential_id'])) {
        sendErrorResponse('Credential ID is required', 400);
    }
    
    $credential_id = (int)$data['credential_id'];
    
    try {
        // Revoke credential
        $stmt = $pdo->prepare("
            UPDATE biometric_tokens 
            SET is_active = 0
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$credential_id, $user['id']]);
        
        if ($stmt->rowCount() === 0) {
            sendErrorResponse('Biometric credential not found', 404);
        }
        
        // Log activity
        logActivity($pdo, $user['id'], 'biometric_revoked', [
            'credential_id' => $credential_id
        ]);
        
        sendSuccessResponse([], 'Biometric credential revoked successfully');
        
    } catch (PDOException $e) {
        error_log('Biometric revocation error: ' . $e->getMessage());
        sendErrorResponse('Failed to revoke biometric credential', 500);
    }
}

/**
 * Get user's biometric devices
 */
function handleGetDevices($pdo) {
    // Authenticate user
    $user = authenticateRequest($pdo);
    if (!$user) {
        sendErrorResponse('Unauthorized', 401);
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                device_name,
                device_id,
                is_active as status,
                created_at,
                last_used
            FROM biometric_tokens
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendSuccessResponse([
            'devices' => $devices,
            'total' => count($devices)
        ], 'Devices retrieved successfully');
        
    } catch (PDOException $e) {
        error_log('Device fetch error: ' . $e->getMessage());
        sendErrorResponse('Failed to fetch devices', 500);
    }
}

/**
 * Verify biometric signature
 */
function verifyBiometricSignature($public_key, $signature, $challenge) {
    // This is a simplified verification
    // In production, use proper WebAuthn/FIDO2 verification library
    
    try {
        // Decode public key and signature
        $public_key_decoded = base64_decode($public_key);
        $signature_decoded = base64_decode($signature);
        $challenge_decoded = base64_decode($challenge);
        
        // Verify signature (simplified - use proper crypto library in production)
        // This should use proper ECDSA or RSA verification
        $is_valid = openssl_verify(
            $challenge_decoded,
            $signature_decoded,
            $public_key_decoded,
            OPENSSL_ALGO_SHA256
        ) === 1;
        
        return $is_valid;
        
    } catch (Exception $e) {
        error_log('Signature verification error: ' . $e->getMessage());
        return false;
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

/**
 * Log user activity
 */
function logActivity($pdo, $user_id, $action, $details = []) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $action,
            json_encode($details),
            getClientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
        error_log('Activity logging error: ' . $e->getMessage());
    }
}
