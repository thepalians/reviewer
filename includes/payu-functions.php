<?php
declare(strict_types=1);

/**
 * PayU Payment Gateway Functions
 */

/**
 * Initialize PayU payment
 */
function payuCreatePayment(float $amount, array $paymentData = []): ?array {
    try {
        $config = getGatewayConfig('payu');
        if (!$config) {
            error_log("PayU configuration not found");
            return null;
        }
        
        $merchantKey = $config['merchant_key'] ?? '';
        $merchantSalt = $config['merchant_salt'] ?? '';
        
        $txnId = 'TXN' . time() . rand(1000, 9999);
        
        $hashString = $merchantKey . '|' . $txnId . '|' . $amount . '|' . 
                     ($paymentData['product_info'] ?? 'Product') . '|' .
                     ($paymentData['firstname'] ?? 'Customer') . '|' .
                     ($paymentData['email'] ?? '') . '|||||||||||' . $merchantSalt;
        
        $hash = strtolower(hash('sha512', $hashString));
        
        $paymentParams = [
            'key' => $merchantKey,
            'txnid' => $txnId,
            'amount' => $amount,
            'productinfo' => $paymentData['product_info'] ?? 'Product',
            'firstname' => $paymentData['firstname'] ?? 'Customer',
            'email' => $paymentData['email'] ?? '',
            'phone' => $paymentData['phone'] ?? '',
            'surl' => $paymentData['success_url'] ?? '',
            'furl' => $paymentData['failure_url'] ?? '',
            'hash' => $hash,
            'service_provider' => 'payu_paisa',
        ];
        
        // Log transaction
        logGatewayTransaction([
            'gateway_type' => 'payu',
            'transaction_type' => 'payment',
            'internal_ref' => $txnId,
            'amount' => $amount,
            'status' => 'pending',
            'response_data' => $paymentParams
        ]);
        
        return [
            'payment_url' => ($config['mode'] === 'live') 
                ? 'https://secure.payu.in/_payment' 
                : 'https://test.payu.in/_payment',
            'params' => $paymentParams,
            'txnid' => $txnId
        ];
    } catch (Exception $e) {
        error_log("PayU payment error: " . $e->getMessage());
        return null;
    }
}

/**
 * Verify PayU payment response
 */
function payuVerifyPayment(array $response): bool {
    try {
        $config = getGatewayConfig('payu');
        if (!$config) {
            return false;
        }
        
        $merchantSalt = $config['merchant_salt'] ?? '';
        
        $hashString = $merchantSalt . '|' . 
                     ($response['status'] ?? '') . '|||||||||||' .
                     ($response['email'] ?? '') . '|' .
                     ($response['firstname'] ?? '') . '|' .
                     ($response['productinfo'] ?? '') . '|' .
                     ($response['amount'] ?? '') . '|' .
                     ($response['txnid'] ?? '') . '|' .
                     ($config['merchant_key'] ?? '');
        
        $hash = strtolower(hash('sha512', $hashString));
        
        return hash_equals($hash, ($response['hash'] ?? ''));
    } catch (Exception $e) {
        error_log("PayU verification error: " . $e->getMessage());
        return false;
    }
}

/**
 * PayU refund
 */
function payuCreateRefund(string $paymentId, float $amount): ?array {
    try {
        $config = getGatewayConfig('payu');
        if (!$config) {
            return null;
        }
        
        $merchantKey = $config['merchant_key'] ?? '';
        $merchantSalt = $config['merchant_salt'] ?? '';
        
        $refundUrl = ($config['mode'] === 'live')
            ? 'https://info.payu.in/merchant/postservice?form=2'
            : 'https://test.payu.in/merchant/postservice?form=2';
        
        $data = [
            'key' => $merchantKey,
            'command' => 'cancel_refund_transaction',
            'var1' => $paymentId,
            'var2' => $amount,
            'var3' => 'Refund for order',
            'hash' => hash('sha512', $merchantKey . '|cancel_refund_transaction|' . $paymentId . '|' . $merchantSalt)
        ];
        
        $ch = curl_init($refundUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($result && isset($result['status']) && $result['status'] === 1) {
            // Log transaction
            logGatewayTransaction([
                'gateway_type' => 'payu',
                'transaction_type' => 'refund',
                'internal_ref' => $paymentId,
                'amount' => $amount,
                'status' => 'success',
                'response_data' => $result
            ]);
            
            return $result;
        }
        
        return null;
    } catch (Exception $e) {
        error_log("PayU refund error: " . $e->getMessage());
        return null;
    }
}

/**
 * Check PayU transaction status
 */
function payuCheckStatus(string $txnId): ?array {
    try {
        $config = getGatewayConfig('payu');
        if (!$config) {
            return null;
        }
        
        $merchantKey = $config['merchant_key'] ?? '';
        $merchantSalt = $config['merchant_salt'] ?? '';
        
        $command = 'verify_payment';
        $hash = hash('sha512', $merchantKey . '|' . $command . '|' . $txnId . '|' . $merchantSalt);
        
        $statusUrl = ($config['mode'] === 'live')
            ? 'https://info.payu.in/merchant/postservice?form=2'
            : 'https://test.payu.in/merchant/postservice?form=2';
        
        $data = [
            'key' => $merchantKey,
            'command' => $command,
            'var1' => $txnId,
            'hash' => $hash
        ];
        
        $ch = curl_init($statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    } catch (Exception $e) {
        error_log("PayU status check error: " . $e->getMessage());
        return null;
    }
}
