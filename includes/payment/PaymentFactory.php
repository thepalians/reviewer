<?php
declare(strict_types=1);

require_once __DIR__ . '/PaymentInterface.php';
require_once __DIR__ . '/RazorpayPayment.php';
require_once __DIR__ . '/PayUPayment.php';

/**
 * Payment Gateway Factory
 * Factory pattern to get the appropriate payment gateway instance
 */
class PaymentFactory {
    
    /**
     * Get payment gateway instance
     * 
     * @param string $gateway Gateway name (razorpay, payumoney)
     * @param PDO $pdo Database connection
     * @return PaymentInterface Payment gateway instance
     * @throws Exception If gateway is not supported or not configured
     */
    public static function getGateway(string $gateway, PDO $pdo): PaymentInterface {
        $gateway = strtolower($gateway);
        
        switch ($gateway) {
            case 'razorpay':
                return self::initializeRazorpay($pdo);
                
            case 'payumoney':
                return self::initializePayU($pdo);
                
            default:
                throw new Exception("Unsupported payment gateway: {$gateway}");
        }
    }
    
    /**
     * Initialize Razorpay gateway
     */
    private static function initializeRazorpay(PDO $pdo): RazorpayPayment {
        $config = self::getGatewayConfig($pdo, 'razorpay');
        
        if (!$config['enabled']) {
            throw new Exception('Razorpay is not enabled');
        }
        
        $razorpay = new RazorpayPayment();
        $razorpay->initialize([
            'key_id' => $config['key_id'],
            'key_secret' => $config['key_secret'],
            'test_mode' => $config['test_mode']
        ]);
        
        return $razorpay;
    }
    
    /**
     * Initialize PayU Money gateway
     */
    private static function initializePayU(PDO $pdo): PayUPayment {
        $config = self::getGatewayConfig($pdo, 'payumoney');
        
        if (!$config['enabled']) {
            throw new Exception('PayU Money is not enabled');
        }
        
        $payu = new PayUPayment();
        $payu->initialize([
            'merchant_key' => $config['merchant_key'],
            'merchant_salt' => $config['merchant_salt'],
            'test_mode' => $config['test_mode']
        ]);
        
        return $payu;
    }
    
    /**
     * Get gateway configuration from database
     */
    private static function getGatewayConfig(PDO $pdo, string $gateway): array {
        try {
            $prefix = $gateway === 'razorpay' ? 'razorpay' : 'payumoney';
            
            $stmt = $pdo->prepare("
                SELECT setting_key, setting_value 
                FROM system_settings 
                WHERE setting_key LIKE ?
            ");
            $stmt->execute(["{$prefix}_%"]);
            $settings = $stmt->fetchAll();
            
            $config = [
                'enabled' => false,
                'test_mode' => true
            ];
            
            foreach ($settings as $setting) {
                $key = str_replace($prefix . '_', '', $setting['setting_key']);
                $config[$key] = $setting['setting_value'];
            }
            
            // Convert string booleans to actual booleans
            $config['enabled'] = ($config['enabled'] ?? '0') === '1';
            $config['test_mode'] = ($config['test_mode'] ?? '1') === '1';
            
            return $config;
            
        } catch (PDOException $e) {
            error_log('Failed to get gateway config: ' . $e->getMessage());
            throw new Exception('Failed to load payment gateway configuration');
        }
    }
    
    /**
     * Get list of available/enabled gateways
     */
    public static function getAvailableGateways(PDO $pdo): array {
        try {
            $gateways = [];
            
            // Check Razorpay
            $razorpayConfig = self::getGatewayConfig($pdo, 'razorpay');
            if ($razorpayConfig['enabled']) {
                $gateways[] = [
                    'code' => 'razorpay',
                    'name' => 'Razorpay',
                    'logo' => 'assets/images/razorpay-logo.png'
                ];
            }
            
            // Check PayU Money
            $payuConfig = self::getGatewayConfig($pdo, 'payumoney');
            if ($payuConfig['enabled']) {
                $gateways[] = [
                    'code' => 'payumoney',
                    'name' => 'PayU Money',
                    'logo' => 'assets/images/payu-logo.png'
                ];
            }
            
            return $gateways;
            
        } catch (Exception $e) {
            error_log('Failed to get available gateways: ' . $e->getMessage());
            return [];
        }
    }
}
?>
