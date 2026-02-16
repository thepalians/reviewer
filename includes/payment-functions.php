<?php
// Payment Gateway Functions

function getPaymentConfig($db, $key) {
    $stmt = $db->prepare("SELECT config_value FROM payment_config WHERE config_key = ? AND is_active = 1");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['config_value'] : null;
}

function createRazorpayOrder($db, $user_id, $amount) {
    try {
        // Get Razorpay credentials
        $key_id = getPaymentConfig($db, 'razorpay_key_id') ?: 'rzp_test_example';
        $key_secret = getPaymentConfig($db, 'razorpay_key_secret') ?: 'secret_example';
        
        // Check min/max limits
        $min_amount = (float)getPaymentConfig($db, 'min_recharge_amount') ?: 100;
        $max_amount = (float)getPaymentConfig($db, 'max_recharge_amount') ?: 50000;
        
        if ($amount < $min_amount || $amount > $max_amount) {
            return [
                'success' => false,
                'message' => "Amount must be between ₹{$min_amount} and ₹{$max_amount}"
            ];
        }
        
        // Create order in Razorpay
        $order_data = [
            'amount' => $amount * 100, // Convert to paise
            'currency' => 'INR',
            'receipt' => 'rcpt_' . time() . '_' . $user_id,
            'notes' => [
                'user_id' => $user_id,
                'purpose' => 'wallet_recharge'
            ]
        ];
        
        // In production, use Razorpay SDK
        // For now, create a mock order ID
        $order_id = 'order_' . uniqid();
        
        // Save payment record
        $stmt = $db->prepare("
            INSERT INTO payments (user_id, amount, payment_method, razorpay_order_id, status, created_at)
            VALUES (?, ?, 'razorpay', ?, 'pending', NOW())
        ");
        $stmt->execute([$user_id, $amount, $order_id]);
        $payment_id = $db->lastInsertId();
        
        return [
            'success' => true,
            'order_id' => $order_id,
            'payment_id' => $payment_id,
            'amount' => $amount,
            'key_id' => $key_id
        ];
        
    } catch (Exception $e) {
        error_log("Razorpay Order Creation Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to create payment order'
        ];
    }
}

function verifyRazorpayPayment($db, $payment_id, $razorpay_payment_id, $razorpay_signature) {
    try {
        // Get payment details
        $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            return ['success' => false, 'message' => 'Payment not found'];
        }
        
        // In production, verify signature using Razorpay SDK
        // For now, mark as success
        $key_secret = getPaymentConfig($db, 'razorpay_key_secret') ?: 'secret_example';
        
        // Update payment status
        $stmt = $db->prepare("
            UPDATE payments 
            SET razorpay_payment_id = ?, 
                razorpay_signature = ?,
                status = 'success',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$razorpay_payment_id, $razorpay_signature, $payment_id]);
        
        // Credit wallet
        creditWallet($db, $payment['user_id'], $payment['amount'], 'payment', 'Wallet recharge via Razorpay');
        
        // Log activity
        logUserActivity($db, $payment['user_id'], 'wallet_recharge', "Recharged wallet with ₹{$payment['amount']}");
        
        return [
            'success' => true,
            'message' => 'Payment verified successfully',
            'amount' => $payment['amount']
        ];
        
    } catch (Exception $e) {
        error_log("Payment Verification Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Payment verification failed'];
    }
}

function creditWallet($db, $user_id, $amount, $type, $description) {
    try {
        // Update wallet balance
        $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);
        
        // Record transaction
        $stmt = $db->prepare("
            INSERT INTO wallet_transactions (user_id, amount, type, description, status, created_at)
            VALUES (?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([$user_id, $amount, $type, $description]);
        
        return true;
    } catch (Exception $e) {
        error_log("Wallet Credit Error: " . $e->getMessage());
        return false;
    }
}

function getUserPayments($db, $user_id, $limit = 50) {
    $stmt = $db->prepare("
        SELECT * FROM payments
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPaymentStats($db, $user_id = null) {
    if ($user_id) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_amount,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
            FROM payments
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_amount,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
            FROM payments
        ");
    }
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAllPayments($db, $status = null, $limit = 100) {
    if ($status) {
        $stmt = $db->prepare("
            SELECT p.*, u.username, u.email
            FROM payments p
            JOIN users u ON p.user_id = u.id
            WHERE p.status = ?
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$status, $limit]);
    } else {
        $stmt = $db->prepare("
            SELECT p.*, u.username, u.email
            FROM payments p
            JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
