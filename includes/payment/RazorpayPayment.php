<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentInterface.php';

/**
 * Razorpay Payment Gateway Integration
 * Handles payment creation, verification, and refunds via Razorpay API
 */
class RazorpayPayment implements PaymentInterface {
    
    private $keyId;
    private $keySecret;
    private $testMode = false;
    private $apiUrl = 'https://api.razorpay.com/v1';
    
    /**
     * Initialize Razorpay with credentials
     */
    public function initialize(array $config): void {
        $this->keyId = $config['key_id'] ?? '';
        $this->keySecret = $config['key_secret'] ?? '';
        $this->testMode = $config['test_mode'] ?? false;
        
        if (empty($this->keyId) || empty($this->keySecret)) {
            throw new Exception('Razorpay credentials not configured');
        }
    }
    
    /**
     * Create Razorpay order
     */
    public function createOrder(float $amount, string $orderId, array $customerInfo, array $metadata = []): array {
        try {
            $data = [
                'amount' => (int)($amount * 100), // Convert to paisa
                'currency' => 'INR',
                'receipt' => $orderId,
                'notes' => $metadata
            ];
            
            $response = $this->makeApiCall('/orders', 'POST', $data);
            
            if (isset($response['id'])) {
                return [
                    'success' => true,
                    'order_id' => $response['id'],
                    'amount' => $amount,
                    'currency' => 'INR',
                    'key_id' => $this->keyId,
                    'receipt' => $orderId,
                    'response' => $response
                ];
            }
            
            throw new Exception('Failed to create Razorpay order');
            
        } catch (Exception $e) {
            error_log('Razorpay Order Creation Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify Razorpay payment signature
     */
    public function verifyPayment(array $paymentData): bool {
        try {
            $orderId = $paymentData['razorpay_order_id'] ?? '';
            $paymentId = $paymentData['razorpay_payment_id'] ?? '';
            $signature = $paymentData['razorpay_signature'] ?? '';
            
            if (empty($orderId) || empty($paymentId) || empty($signature)) {
                return false;
            }
            
            $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret);
            
            return hash_equals($expectedSignature, $signature);
            
        } catch (Exception $e) {
            error_log('Razorpay Verification Failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payment status from Razorpay
     */
    public function getPaymentStatus(string $paymentId): array {
        try {
            $response = $this->makeApiCall("/payments/{$paymentId}", 'GET');
            
            return [
                'success' => true,
                'status' => $response['status'] ?? 'unknown',
                'amount' => isset($response['amount']) ? $response['amount'] / 100 : 0,
                'method' => $response['method'] ?? '',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            error_log('Razorpay Status Check Failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Initiate refund via Razorpay
     */
    public function refundPayment(string $paymentId, float $amount, string $reason = ''): array {
        try {
            $data = [
                'amount' => (int)($amount * 100), // Convert to paisa
                'notes' => ['reason' => $reason]
            ];
            
            $response = $this->makeApiCall("/payments/{$paymentId}/refund", 'POST', $data);
            
            if (isset($response['id'])) {
                return [
                    'success' => true,
                    'refund_id' => $response['id'],
                    'amount' => $amount,
                    'status' => $response['status'] ?? 'processed',
                    'response' => $response
                ];
            }
            
            throw new Exception('Failed to process refund');
            
        } catch (Exception $e) {
            error_log('Razorpay Refund Failed: ' . $e->getMessage());
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
        return 'razorpay';
    }
    
    /**
     * Make API call to Razorpay
     */
    private function makeApiCall(string $endpoint, string $method = 'GET', array $data = []): array {
        $url = $this->apiUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->keyId . ':' . $this->keySecret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("API request failed with status {$httpCode}: {$response}");
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from API');
        }
        
        return $result;
    }
}
?>
