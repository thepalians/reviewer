<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentInterface.php';

/**
 * PayU Money Payment Gateway Integration
 * Handles payment creation, verification, and refunds via PayU Money API
 */
class PayUPayment implements PaymentInterface {
    
    private $merchantKey;
    private $merchantSalt;
    private $testMode = false;
    private $paymentUrl;
    private $apiUrl;
    
    /**
     * Initialize PayU Money with credentials
     */
    public function initialize(array $config): void {
        $this->merchantKey = $config['merchant_key'] ?? '';
        $this->merchantSalt = $config['merchant_salt'] ?? '';
        $this->testMode = $config['test_mode'] ?? false;
        
        // Set URLs based on test mode
        if ($this->testMode) {
            $this->paymentUrl = 'https://test.payu.in/_payment';
            $this->apiUrl = 'https://test.payu.in/merchant/postservice';
        } else {
            $this->paymentUrl = 'https://secure.payu.in/_payment';
            $this->apiUrl = 'https://info.payu.in/merchant/postservice';
        }
        
        if (empty($this->merchantKey) || empty($this->merchantSalt)) {
            throw new Exception('PayU Money credentials not configured');
        }
    }
    
    /**
     * Create PayU Money order
     */
    public function createOrder(float $amount, string $orderId, array $customerInfo, array $metadata = []): array {
        try {
            $txnId = 'TXN' . time() . rand(1000, 9999);
            $productInfo = $metadata['product_info'] ?? 'Review Service';
            $firstName = $customerInfo['name'] ?? '';
            $email = $customerInfo['email'] ?? '';
            $phone = $customerInfo['phone'] ?? '';
            $surl = $metadata['success_url'] ?? '';
            $furl = $metadata['failure_url'] ?? '';
            
            // Generate hash
            $hashString = $this->merchantKey . '|' . $txnId . '|' . $amount . '|' . $productInfo . '|' . $firstName . '|' . $email . '|||||||||||' . $this->merchantSalt;
            $hash = strtolower(hash('sha512', $hashString));
            
            return [
                'success' => true,
                'payment_url' => $this->paymentUrl,
                'params' => [
                    'key' => $this->merchantKey,
                    'txnid' => $txnId,
                    'amount' => $amount,
                    'productinfo' => $productInfo,
                    'firstname' => $firstName,
                    'email' => $email,
                    'phone' => $phone,
                    'surl' => $surl,
                    'furl' => $furl,
                    'hash' => $hash,
                    'service_provider' => 'payu_paisa'
                ],
                'order_id' => $txnId,
                'amount' => $amount
            ];
            
        } catch (Exception $e) {
            error_log('PayU Order Creation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify PayU Money payment
     */
    public function verifyPayment(array $paymentData): bool {
        try {
            $status = $paymentData['status'] ?? '';
            $txnId = $paymentData['txnid'] ?? '';
            $amount = $paymentData['amount'] ?? '';
            $productInfo = $paymentData['productinfo'] ?? '';
            $firstName = $paymentData['firstname'] ?? '';
            $email = $paymentData['email'] ?? '';
            $receivedHash = $paymentData['hash'] ?? '';
            
            if ($status !== 'success') {
                return false;
            }
            
            // Generate hash for verification
            $hashString = $this->merchantSalt . '|' . $status . '|||||||||||' . $email . '|' . $firstName . '|' . $productInfo . '|' . $amount . '|' . $txnId . '|' . $this->merchantKey;
            $hash = strtolower(hash('sha512', $hashString));
            
            return hash_equals($hash, $receivedHash);
            
        } catch (Exception $e) {
            error_log('PayU Verification Failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payment status from PayU Money
     */
    public function getPaymentStatus(string $paymentId): array {
        try {
            $command = 'verify_payment';
            $var1 = $paymentId;
            $hashString = $this->merchantKey . '|' . $command . '|' . $var1 . '|' . $this->merchantSalt;
            $hash = strtolower(hash('sha512', $hashString));
            
            $data = [
                'key' => $this->merchantKey,
                'command' => $command,
                'var1' => $var1,
                'hash' => $hash
            ];
            
            $response = $this->makeApiCall($data);
            
            if (isset($response['status']) && $response['status'] == 1) {
                $transactionDetails = $response['transaction_details'][$paymentId] ?? [];
                
                return [
                    'success' => true,
                    'status' => $transactionDetails['status'] ?? 'unknown',
                    'amount' => $transactionDetails['amt'] ?? 0,
                    'method' => $transactionDetails['mode'] ?? '',
                    'response' => $transactionDetails
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['msg'] ?? 'Unknown error'
            ];
            
        } catch (Exception $e) {
            error_log('PayU Status Check Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Initiate refund via PayU Money
     */
    public function refundPayment(string $paymentId, float $amount, string $reason = ''): array {
        try {
            $command = 'cancel_refund_transaction';
            $var1 = $paymentId;
            $var2 = $paymentId; // Bank reference number (same as txn id for now)
            $var3 = $amount;
            
            $hashString = $this->merchantKey . '|' . $command . '|' . $var1 . '|' . $this->merchantSalt;
            $hash = strtolower(hash('sha512', $hashString));
            
            $data = [
                'key' => $this->merchantKey,
                'command' => $command,
                'var1' => $var1,
                'var2' => $var2,
                'var3' => $var3,
                'hash' => $hash
            ];
            
            $response = $this->makeApiCall($data);
            
            if (isset($response['status']) && $response['status'] == 1) {
                return [
                    'success' => true,
                    'refund_id' => $response['request_id'] ?? '',
                    'amount' => $amount,
                    'status' => 'processed',
                    'response' => $response
                ];
            }
            
            return [
                'success' => false,
                'error' => $response['msg'] ?? 'Refund failed'
            ];
            
        } catch (Exception $e) {
            error_log('PayU Refund Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get gateway name
     */
    public function getGatewayName(): string {
        return 'payumoney';
    }
    
    /**
     * Make API call to PayU Money
     */
    private function makeApiCall(array $data): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("API request failed with status {$httpCode}");
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }
        
        return $result;
    }
}
?>
