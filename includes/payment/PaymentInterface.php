<?php
declare(strict_types=1);

/**
 * Payment Gateway Interface
 * All payment gateways must implement this interface
 */
interface PaymentInterface {
    
    /**
     * Initialize payment gateway with credentials
     * 
     * @param array $config Configuration array with keys and secrets
     * @return void
     */
    public function initialize(array $config): void;
    
    /**
     * Create a new payment order
     * 
     * @param float $amount Amount in INR
     * @param string $orderId Unique order ID
     * @param array $customerInfo Customer information
     * @param array $metadata Additional metadata
     * @return array Order details including gateway order ID
     */
    public function createOrder(float $amount, string $orderId, array $customerInfo, array $metadata = []): array;
    
    /**
     * Verify payment signature/callback
     * 
     * @param array $paymentData Payment callback data
     * @return bool True if payment is verified
     */
    public function verifyPayment(array $paymentData): bool;
    
    /**
     * Get payment status
     * 
     * @param string $paymentId Gateway payment ID
     * @return array Payment status and details
     */
    public function getPaymentStatus(string $paymentId): array;
    
    /**
     * Initiate refund
     * 
     * @param string $paymentId Gateway payment ID
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @return array Refund details
     */
    public function refundPayment(string $paymentId, float $amount, string $reason = ''): array;
    
    /**
     * Get gateway name
     * 
     * @return string Gateway name (razorpay, payumoney, etc.)
     */
    public function getGatewayName(): string;
}
?>
