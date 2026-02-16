<?php
// Razorpay Configuration

// Get Razorpay credentials from environment or database
function getRazorpayConfig($db) {
    $test_mode = getPaymentConfig($db, 'razorpay_test_mode') === '1';
    
    if ($test_mode) {
        return [
            'key_id' => getPaymentConfig($db, 'razorpay_test_key_id') ?: 'rzp_test_example',
            'key_secret' => getPaymentConfig($db, 'razorpay_test_key_secret') ?: 'secret_example',
            'test_mode' => true
        ];
    } else {
        return [
            'key_id' => getPaymentConfig($db, 'razorpay_live_key_id') ?: 'rzp_live_example',
            'key_secret' => getPaymentConfig($db, 'razorpay_live_key_secret') ?: 'secret_example',
            'test_mode' => false
        ];
    }
}

// Initialize Razorpay
function initRazorpay($db) {
    $config = getRazorpayConfig($db);
    
    // In production, use: return new \Razorpay\Api\Api($config['key_id'], $config['key_secret']);
    // For now, return config
    return $config;
}
