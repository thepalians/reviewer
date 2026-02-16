<?php
declare(strict_types=1);

/**
 * Cashfree Payment Gateway Functions
 */

/**
 * Initialize Cashfree payment
 */
function cashfreeCreateOrder(float $amount, array $orderData = []): ?array {
    try {
        $config = getGatewayConfig('cashfree');
        if (!$config) {
            error_log("Cashfree configuration not found");
            return null;
        }
        
        $appId = $config['app_id'] ?? '';
        $secretKey = $config['secret_key'] ?? '';
        
        $orderId = 'ORDER' . time() . rand(1000, 9999);
        
        $url = ($config['mode'] === 'live')
            ? 'https://api.cashfree.com/api/v2/cftoken/order'
            : 'https://test.cashfree.com/api/v2/cftoken/order';
        
        $postData = [
            'orderId' => $orderId,
            'orderAmount' => $amount,
            'orderCurrency' => $orderData['currency'] ?? 'INR',
            'orderNote' => $orderData['note'] ?? 'Payment',
            'customerName' => $orderData['customer_name'] ?? 'Customer',
            'customerEmail' => $orderData['customer_email'] ?? '',
            'customerPhone' => $orderData['customer_phone'] ?? '',
            'returnUrl' => $orderData['return_url'] ?? '',
            'notifyUrl' => $orderData['notify_url'] ?? ''
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-client-id: ' . $appId,
            'x-client-secret: ' . $secretKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            
            if ($result && $result['status'] === 'OK') {
                // Log transaction
                logGatewayTransaction([
                    'gateway_type' => 'cashfree',
                    'transaction_type' => 'payment',
                    'internal_ref' => $orderId,
                    'gateway_ref' => $result['cftoken'] ?? null,
                    'amount' => $amount,
                    'status' => 'pending',
                    'response_data' => $result
                ]);
                
                return [
                    'order_id' => $orderId,
                    'token' => $result['cftoken'],
                    'payment_url' => ($config['mode'] === 'live')
                        ? 'https://www.cashfree.com/checkout/post/submit'
                        : 'https://test.cashfree.com/billpay/checkout/post/submit'
                ];
            }
        }
        
        error_log("Cashfree order creation failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Cashfree error: " . $e->getMessage());
        return null;
    }
}

/**
 * Verify Cashfree payment signature
 */
function cashfreeVerifySignature(string $orderId, string $orderAmount, string $signature): bool {
    try {
        $config = getGatewayConfig('cashfree');
        if (!$config) {
            return false;
        }
        
        $secretKey = $config['secret_key'] ?? '';
        $data = $orderId . $orderAmount;
        $generated = hash_hmac('sha256', $data, $secretKey, true);
        $generatedSignature = base64_encode($generated);
        
        return hash_equals($generatedSignature, $signature);
    } catch (Exception $e) {
        error_log("Cashfree signature verification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Cashfree order status
 */
function cashfreeGetOrderStatus(string $orderId): ?array {
    try {
        $config = getGatewayConfig('cashfree');
        if (!$config) {
            return null;
        }
        
        $appId = $config['app_id'] ?? '';
        $secretKey = $config['secret_key'] ?? '';
        
        $url = ($config['mode'] === 'live')
            ? "https://api.cashfree.com/api/v1/order/info/status"
            : "https://test.cashfree.com/api/v1/order/info/status";
        
        $postData = [
            'appId' => $appId,
            'secretKey' => $secretKey,
            'orderId' => $orderId
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    } catch (Exception $e) {
        error_log("Cashfree status check error: " . $e->getMessage());
        return null;
    }
}

/**
 * Cashfree refund
 */
function cashfreeCreateRefund(string $orderId, string $referenceId, float $amount): ?array {
    try {
        $config = getGatewayConfig('cashfree');
        if (!$config) {
            return null;
        }
        
        $appId = $config['app_id'] ?? '';
        $secretKey = $config['secret_key'] ?? '';
        
        $url = ($config['mode'] === 'live')
            ? "https://api.cashfree.com/api/v1/order/refund"
            : "https://test.cashfree.com/api/v1/order/refund";
        
        $postData = [
            'appId' => $appId,
            'secretKey' => $secretKey,
            'orderId' => $orderId,
            'referenceId' => $referenceId,
            'refundAmount' => $amount,
            'refundNote' => 'Refund requested'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result && $result['status'] === 'OK') {
            // Log transaction
            logGatewayTransaction([
                'gateway_type' => 'cashfree',
                'transaction_type' => 'refund',
                'internal_ref' => $orderId,
                'gateway_ref' => $referenceId,
                'amount' => $amount,
                'status' => 'success',
                'response_data' => $result
            ]);
            
            return $result;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Cashfree refund error: " . $e->getMessage());
        return null;
    }
}

/**
 * Cashfree payout
 */
function cashfreeCreatePayout(array $payoutData): ?array {
    try {
        $config = getGatewayConfig('cashfree');
        if (!$config) {
            return null;
        }
        
        $clientId = $config['client_id'] ?? '';
        $clientSecret = $config['client_secret'] ?? '';
        
        // Get auth token
        $authUrl = ($config['mode'] === 'live')
            ? 'https://payout-api.cashfree.com/payout/v1/authorize'
            : 'https://payout-gamma.cashfree.com/payout/v1/authorize';
        
        $ch = curl_init($authUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Client-Id: ' . $clientId,
            'X-Client-Secret: ' . $clientSecret
        ]);
        
        $authResponse = curl_exec($ch);
        curl_close($ch);
        
        $authResult = json_decode($authResponse, true);
        if (!$authResult || $authResult['status'] !== 'SUCCESS') {
            return null;
        }
        
        $token = $authResult['data']['token'];
        
        // Create payout
        $payoutUrl = ($config['mode'] === 'live')
            ? 'https://payout-api.cashfree.com/payout/v1/requestTransfer'
            : 'https://payout-gamma.cashfree.com/payout/v1/requestTransfer';
        
        $transferId = 'PAYOUT' . time() . rand(1000, 9999);
        
        $postData = [
            'beneId' => $payoutData['beneficiary_id'],
            'amount' => $payoutData['amount'],
            'transferId' => $transferId,
            'transferMode' => $payoutData['transfer_mode'] ?? 'banktransfer',
            'remarks' => $payoutData['remarks'] ?? 'Payout'
        ];
        
        $ch = curl_init($payoutUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result && $result['status'] === 'SUCCESS') {
            // Log transaction
            logGatewayTransaction([
                'gateway_type' => 'cashfree',
                'transaction_type' => 'payout',
                'internal_ref' => $transferId,
                'gateway_ref' => $result['data']['referenceId'] ?? null,
                'amount' => $payoutData['amount'],
                'status' => 'success',
                'response_data' => $result
            ]);
            
            return $result;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Cashfree payout error: " . $e->getMessage());
        return null;
    }
}
