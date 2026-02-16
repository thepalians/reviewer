<?php
/**
 * API v1 - Profile Endpoints
 * Handles user profile and settings
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
    getProfile($pdo, $user);
} elseif ($request_method === 'PUT' || $request_method === 'POST') {
    updateProfile($pdo, $user);
} else {
    sendErrorResponse('Endpoint not found', 404);
}

/**
 * Get user profile
 */
function getProfile($pdo, $user) {
    try {
        // Get full user details
        $stmt = $pdo->prepare("
            SELECT id, username, email, phone, user_type, referral_code, 
                   kyc_status, is_active, created_at, last_login
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profile) {
            sendErrorResponse('User not found', 404);
        }
        
        // Get wallet balance
        $stmt = $pdo->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        $profile['wallet_balance'] = (float)($wallet['balance'] ?? 0);
        
        // Get statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN task_status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN task_status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN task_status = 'completed' THEN commission_amount ELSE 0 END) as total_earned
            FROM tasks
            WHERE user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $profile['statistics'] = [
            'total_tasks' => (int)$stats['total_tasks'],
            'completed_tasks' => (int)$stats['completed_tasks'],
            'pending_tasks' => (int)$stats['pending_tasks'],
            'total_earned' => (float)$stats['total_earned']
        ];
        
        // Get referral stats
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as referral_count
            FROM referrals
            WHERE referrer_id = ?
        ");
        $stmt->execute([$user['id']]);
        $referrals = $stmt->fetch(PDO::FETCH_ASSOC);
        $profile['referral_count'] = (int)$referrals['referral_count'];
        
        sendSuccessResponse(['profile' => $profile]);
    } catch (PDOException $e) {
        error_log("Get profile error: " . $e->getMessage());
        sendErrorResponse('Failed to fetch profile', 500);
    }
}

/**
 * Update user profile
 */
function updateProfile($pdo, $user) {
    $data = getRequestBody();
    
    $allowed_fields = ['username', 'phone', 'email'];
    $update_fields = [];
    $update_values = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $value = sanitizeInput($data[$field]);
            
            // Validate email if updating
            if ($field === 'email' && !validateEmail($value)) {
                sendErrorResponse('Invalid email format', 400);
            }
            
            // Check if username/email already exists using safe queries
            if ($field === 'username') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$value, $user['id']]);
                if ($stmt->fetch()) {
                    sendErrorResponse('Username already taken', 400);
                }
            } elseif ($field === 'email') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$value, $user['id']]);
                if ($stmt->fetch()) {
                    sendErrorResponse('Email already taken', 400);
                }
            }
            
            // Use whitelisted field names only
            $update_fields[] = "$field = ?";
            $update_values[] = $value;
        }
    }
    
    if (empty($update_fields)) {
        sendErrorResponse('No fields to update', 400);
    }
    
    try {
        $update_values[] = $user['id'];
        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($update_values);
        
        sendSuccessResponse([], 'Profile updated successfully');
    } catch (PDOException $e) {
        error_log("Update profile error: " . $e->getMessage());
        sendErrorResponse('Failed to update profile', 500);
    }
}
