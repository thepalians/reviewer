<?php
/**
 * API v1 - Authentication Endpoints
 * Handles login, register, logout, and password reset
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

// Rate limiting
$rate_limit = checkRateLimit($pdo, $client_ip, 'ip', 'auth', 20, 60);
if (!$rate_limit['allowed']) {
    sendErrorResponse('Too many requests. Please try again later.', 429);
}

// Get request method and path
$request_method = getRequestMethod();
$request_uri = $_SERVER['REQUEST_URI'];

// Route handling
if (strpos($request_uri, '/auth/login') !== false) {
    handleLogin($pdo);
} elseif (strpos($request_uri, '/auth/register') !== false) {
    handleRegister($pdo);
} elseif (strpos($request_uri, '/auth/logout') !== false) {
    handleLogout($pdo);
} elseif (strpos($request_uri, '/auth/refresh') !== false) {
    handleRefresh($pdo);
} elseif (strpos($request_uri, '/auth/forgot-password') !== false) {
    handleForgotPassword($pdo);
} else {
    sendErrorResponse('Endpoint not found', 404);
}

/**
 * Handle login
 */
function handleLogin($pdo) {
    requireRequestMethod('POST');
    
    $data = getRequestBody();
    $required = ['email', 'password'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    $email = sanitizeInput($data['email']);
    $password = $data['password'];
    
    if (!validateEmail($email)) {
        sendErrorResponse('Invalid email format', 400);
    }
    
    try {
        // Get user
        $stmt = $pdo->prepare("
            SELECT id, username, email, password, user_type, is_active
            FROM users
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($password, $user['password'])) {
            sendErrorResponse('Invalid credentials', 401);
        }
        
        if (!$user['is_active']) {
            sendErrorResponse('Account is inactive', 403);
        }
        
        // Create JWT session
        $device_id = $data['device_id'] ?? null;
        $device_type = $data['device_type'] ?? null;
        
        $session = createJwtSession($pdo, $user['id'], $device_id, $device_type);
        
        if (!$session) {
            sendErrorResponse('Failed to create session', 500);
        }
        
        // Update last login
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Get wallet balance
        $stmt = $pdo->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendSuccessResponse([
            'user' => formatUserForApi($user),
            'wallet_balance' => (float)($wallet['balance'] ?? 0),
            'auth' => $session
        ], 'Login successful');
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        sendErrorResponse('Login failed', 500);
    }
}

/**
 * Handle registration
 */
function handleRegister($pdo) {
    requireRequestMethod('POST');
    
    $data = getRequestBody();
    $required = ['username', 'email', 'password', 'phone'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    $username = sanitizeInput($data['username']);
    $email = sanitizeInput($data['email']);
    $password = $data['password'];
    $phone = sanitizeInput($data['phone']);
    $referral_code = sanitizeInput($data['referral_code'] ?? '');
    
    if (!validateEmail($email)) {
        sendErrorResponse('Invalid email format', 400);
    }
    
    if (strlen($password) < 6) {
        sendErrorResponse('Password must be at least 6 characters', 400);
    }
    
    try {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            sendErrorResponse('Email already registered', 400);
        }
        
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            sendErrorResponse('Username already taken', 400);
        }
        
        // Create user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, phone, user_type)
            VALUES (?, ?, ?, ?, 'user')
        ");
        $stmt->execute([$username, $email, $hashed_password, $phone]);
        $user_id = $pdo->lastInsertId();
        
        // Create wallet
        $stmt = $pdo->prepare("INSERT INTO user_wallet (user_id, balance) VALUES (?, 0)");
        $stmt->execute([$user_id]);
        
        // Handle referral
        if ($referral_code) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
            $stmt->execute([$referral_code]);
            $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($referrer) {
                // Add referral bonus
                $stmt = $pdo->prepare("
                    UPDATE user_wallet 
                    SET balance = balance + ?
                    WHERE user_id = ?
                ");
                $stmt->execute([REFERRAL_BONUS, $referrer['id']]);
            }
        }
        
        sendSuccessResponse([
            'user_id' => $user_id,
            'username' => $username,
            'email' => $email
        ], 'Registration successful');
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        sendErrorResponse('Registration failed', 500);
    }
}

/**
 * Handle logout
 */
function handleLogout($pdo) {
    requireRequestMethod('POST');
    
    $token = getBearerToken();
    
    if (!$token) {
        sendErrorResponse('Token required', 400);
    }
    
    if (revokeJwtToken($pdo, $token)) {
        sendSuccessResponse([], 'Logout successful');
    } else {
        sendErrorResponse('Logout failed', 500);
    }
}

/**
 * Handle token refresh
 */
function handleRefresh($pdo) {
    requireRequestMethod('POST');
    
    $data = getRequestBody();
    
    if (!isset($data['refresh_token'])) {
        sendErrorResponse('Refresh token required', 400);
    }
    
    $result = refreshJwtToken($pdo, $data['refresh_token']);
    
    if ($result['success']) {
        sendSuccessResponse([
            'auth' => [
                'token' => $result['token'],
                'refresh_token' => $result['refresh_token'],
                'expires_at' => $result['expires_at'],
                'token_type' => $result['token_type']
            ]
        ], 'Token refreshed');
    } else {
        sendErrorResponse($result['error'], 401);
    }
}

/**
 * Handle forgot password
 */
function handleForgotPassword($pdo) {
    requireRequestMethod('POST');
    
    $data = getRequestBody();
    
    if (!isset($data['email'])) {
        sendErrorResponse('Email required', 400);
    }
    
    $email = sanitizeInput($data['email']);
    
    if (!validateEmail($email)) {
        sendErrorResponse('Invalid email format', 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Generate reset token (implement email sending separately)
            $reset_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token (you'll need to create a password_resets table)
            // For now, just return success
        }
        
        // Always return success to prevent email enumeration
        sendSuccessResponse([], 'If the email exists, a password reset link has been sent');
    } catch (PDOException $e) {
        error_log("Forgot password error: " . $e->getMessage());
        sendErrorResponse('Request failed', 500);
    }
}
