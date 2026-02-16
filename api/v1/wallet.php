<?php
/**
 * API v1 - Wallet Endpoints
 * Handles wallet balance, transactions, and withdrawals
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
if (strpos($request_uri, '/wallet/balance') !== false) {
    getBalance($pdo, $user);
} elseif (strpos($request_uri, '/wallet/transactions') !== false) {
    getTransactions($pdo, $user);
} elseif (strpos($request_uri, '/wallet/withdraw') !== false) {
    requestWithdrawal($pdo, $user);
} elseif (strpos($request_uri, '/wallet/withdrawal-history') !== false) {
    getWithdrawalHistory($pdo, $user);
} else {
    sendErrorResponse('Endpoint not found', 404);
}

/**
 * Get wallet balance
 */
function getBalance($pdo, $user) {
    try {
        $stmt = $pdo->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$wallet) {
            // Create wallet if doesn't exist
            $stmt = $pdo->prepare("INSERT INTO user_wallet (user_id, balance) VALUES (?, 0)");
            $stmt->execute([$user['id']]);
            $balance = 0;
        } else {
            $balance = (float)$wallet['balance'];
        }
        
        // Get pending withdrawals
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as pending_amount
            FROM withdrawal_requests
            WHERE user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$user['id']]);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendSuccessResponse([
            'balance' => $balance,
            'pending_withdrawal' => (float)$pending['pending_amount'],
            'available_balance' => $balance - (float)$pending['pending_amount']
        ]);
    } catch (PDOException $e) {
        error_log("Get balance error: " . $e->getMessage());
        sendErrorResponse('Failed to fetch balance', 500);
    }
}

/**
 * Get transactions
 */
function getTransactions($pdo, $user) {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? min((int)$_GET['per_page'], 50) : 20;
        $type = $_GET['type'] ?? 'all';
        
        $query = "
            SELECT * FROM wallet_transactions
            WHERE user_id = ?
        ";
        
        $params = [$user['id']];
        
        if ($type !== 'all') {
            $query .= " AND type = ?";
            $params[] = $type;
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $result = paginateResults($pdo, $query, $params, $page, $per_page);
        
        // Format transactions
        $transactions = array_map('formatTransactionForApi', $result['data']);
        
        sendSuccessResponse([
            'transactions' => $transactions,
            'pagination' => $result['pagination']
        ]);
    } catch (PDOException $e) {
        error_log("Get transactions error: " . $e->getMessage());
        sendErrorResponse('Failed to fetch transactions', 500);
    }
}

/**
 * Request withdrawal
 */
function requestWithdrawal($pdo, $user) {
    requireRequestMethod('POST');
    
    $data = getRequestBody();
    
    $required = ['amount', 'payment_method'];
    $missing = validateRequiredFields($data, $required);
    
    if (!empty($missing)) {
        sendErrorResponse('Missing required fields', 400, $missing);
    }
    
    $amount = (float)$data['amount'];
    $payment_method = sanitizeInput($data['payment_method']);
    $payment_details = sanitizeInput($data['payment_details'] ?? '');
    
    if ($amount < MIN_WITHDRAWAL) {
        sendErrorResponse('Minimum withdrawal amount is ' . MIN_WITHDRAWAL, 400);
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get current balance
        $stmt = $pdo->prepare("SELECT balance FROM user_wallet WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$user['id']]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$wallet || $wallet['balance'] < $amount) {
            $pdo->rollBack();
            sendErrorResponse('Insufficient balance', 400);
        }
        
        // Check pending withdrawals
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as pending_amount
            FROM withdrawal_requests
            WHERE user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$user['id']]);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($wallet['balance'] - $pending['pending_amount'] < $amount) {
            $pdo->rollBack();
            sendErrorResponse('Insufficient available balance (pending withdrawals)', 400);
        }
        
        // Create withdrawal request
        $stmt = $pdo->prepare("
            INSERT INTO withdrawal_requests
            (user_id, amount, payment_method, payment_details, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$user['id'], $amount, $payment_method, $payment_details]);
        
        $pdo->commit();
        
        sendSuccessResponse([
            'withdrawal_id' => $pdo->lastInsertId(),
            'amount' => $amount,
            'status' => 'pending'
        ], 'Withdrawal request submitted successfully');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Withdrawal error: " . $e->getMessage());
        sendErrorResponse('Failed to process withdrawal', 500);
    }
}

/**
 * Get withdrawal history
 */
function getWithdrawalHistory($pdo, $user) {
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? min((int)$_GET['per_page'], 50) : 20;
        
        $query = "
            SELECT * FROM withdrawal_requests
            WHERE user_id = ?
            ORDER BY created_at DESC
        ";
        
        $result = paginateResults($pdo, $query, [$user['id']], $page, $per_page);
        
        sendSuccessResponse([
            'withdrawals' => $result['data'],
            'pagination' => $result['pagination']
        ]);
    } catch (PDOException $e) {
        error_log("Get withdrawal history error: " . $e->getMessage());
        sendErrorResponse('Failed to fetch withdrawal history', 500);
    }
}
