<?php
declare(strict_types=1);

/**
 * Razorpay Payment Gateway Functions
 */

/**
 * Initialize Razorpay payment
 */
function razorpayCreateOrder(float $amount, array $orderData = []): ?array {
    global $pdo;
    
    try {
        // Get Razorpay configuration
        $config = getGatewayConfig('razorpay');
        if (!$config) {
            error_log("Razorpay configuration not found");
            return null;
        }
        
        $keyId = $config['key_id'] ?? '';
        $keySecret = $config['key_secret'] ?? '';
        
        if (empty($keyId) || empty($keySecret)) {
            error_log("Razorpay credentials missing");
            return null;
        }
        
        // Create order via Razorpay API
        $url = 'https://api.razorpay.com/v1/orders';
        $data = [
            'amount' => (int)($amount * 100), // Convert to paise
            'currency' => $orderData['currency'] ?? 'INR',
            'receipt' => $orderData['receipt'] ?? 'order_' . time(),
            'notes' => $orderData['notes'] ?? []
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            // Log transaction
            logGatewayTransaction([
                'gateway_type' => 'razorpay',
                'transaction_type' => 'payment',
                'internal_ref' => $orderData['receipt'] ?? 'order_' . time(),
                'gateway_ref' => $result['id'] ?? null,
                'amount' => $amount,
                'status' => 'pending',
                'response_data' => $result
            ]);
            
            return $result;
        }
        
        error_log("Razorpay order creation failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Razorpay error: " . $e->getMessage());
        return null;
    }
}

/**
 * Verify Razorpay payment signature
 */
function razorpayVerifySignature(string $orderId, string $paymentId, string $signature): bool {
    try {
        $config = getGatewayConfig('razorpay');
        if (!$config) {
            return false;
        }
        
        $keySecret = $config['key_secret'] ?? '';
        $generated = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);
        
        return hash_equals($generated, $signature);
    } catch (Exception $e) {
        error_log("Razorpay signature verification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Razorpay payout
 */
function razorpayCreatePayout(array $payoutData): ?array {
    try {
        $config = getGatewayConfig('razorpay');
        if (!$config) {
            return null;
        }
        
        $keyId = $config['key_id'] ?? '';
        $keySecret = $config['key_secret'] ?? '';
        
        $url = 'https://api.razorpay.com/v1/payouts';
        $data = [
            'account_number' => $config['account_number'] ?? '',
            'fund_account_id' => $payoutData['fund_account_id'],
            'amount' => (int)($payoutData['amount'] * 100),
            'currency' => 'INR',
            'mode' => $payoutData['mode'] ?? 'IMPS',
            'purpose' => $payoutData['purpose'] ?? 'payout',
            'queue_if_low_balance' => true,
            'reference_id' => $payoutData['reference_id'] ?? 'payout_' . time(),
            'narration' => $payoutData['narration'] ?? 'Payout'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            // Log transaction
            logGatewayTransaction([
                'gateway_type' => 'razorpay',
                'transaction_type' => 'payout',
                'internal_ref' => $data['reference_id'],
                'gateway_ref' => $result['id'] ?? null,
                'amount' => $payoutData['amount'],
                'status' => $result['status'] ?? 'pending',
                'response_data' => $result
            ]);
            
            return $result;
        }
        
        error_log("Razorpay payout failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Razorpay payout error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get Razorpay payment status
 */
function razorpayGetPaymentStatus(string $paymentId): ?array {
    try {
        $config = getGatewayConfig('razorpay');
        if (!$config) {
            return null;
        }
        
        $keyId = $config['key_id'] ?? '';
        $keySecret = $config['key_secret'] ?? '';
        
        $url = "https://api.razorpay.com/v1/payments/{$paymentId}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Razorpay status check error: " . $e->getMessage());
        return null;
    }
}

/**
 * Razorpay refund
 */
function razorpayCreateRefund(string $paymentId, ?float $amount = null): ?array {
    try {
        $config = getGatewayConfig('razorpay');
        if (!$config) {
            return null;
        }
        
        $keyId = $config['key_id'] ?? '';
        $keySecret = $config['key_secret'] ?? '';
        
        $url = "https://api.razorpay.com/v1/payments/{$paymentId}/refund";
        $data = [];
        
        if ($amount !== null) {
            $data['amount'] = (int)($amount * 100);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            // Log transaction
            logGatewayTransaction([
                'gateway_type' => 'razorpay',
                'transaction_type' => 'refund',
                'internal_ref' => $paymentId,
                'gateway_ref' => $result['id'] ?? null,
                'amount' => $amount ?? 0,
                'status' => 'success',
                'response_data' => $result
            ]);
            
            return $result;
        }
        
        error_log("Razorpay refund failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Razorpay refund error: " . $e->getMessage());
        return null;
    }
}
