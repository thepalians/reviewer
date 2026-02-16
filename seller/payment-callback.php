<?php
require_once __DIR__ . '/../includes/config.php';


// Check if seller is logged in
if (!isset($_SESSION['seller_id'])) {
    header('Location: index.php');
    exit;
}

$seller_id = $_SESSION['seller_id'];
$error = '';
$success = '';

// Get action
$action = $_GET['action'] ?? '';

// Handle different actions
if ($action === 'initiate') {
    // Initiate payment for a review request
    $request_id = (int) ($_GET['request_id'] ?? 0);
    
    if ($request_id <= 0) {
        header('Location: dashboard.php?error=invalid_request');
        exit;
    }
    
    try {
        // Get review request
        $stmt = $pdo->prepare("
            SELECT * FROM review_requests 
            WHERE id = ? AND seller_id = ? AND payment_status = 'pending'
        ");
        $stmt->execute([$request_id, $seller_id]);
        $request = $stmt->fetch();
        
        if (!$request) {
            header('Location: orders.php?error=request_not_found');
            exit;
        }
        
        // Check demo mode flag from settings (only for development/testing)
        $demo_mode = getSetting('payment_demo_mode', '0') === '1';
        
        // Check if PaymentFactory is available
        if ($demo_mode || !file_exists(__DIR__ . '/../includes/payment/PaymentFactory.php')) {
            // DEMO MODE - Only for development/testing
            error_log('WARNING: Payment processed in DEMO MODE for request #' . $request_id);
            
            // For demo purposes, mark as paid directly
            $pdo->beginTransaction();
            
            // Update review request
            $stmt = $pdo->prepare("
                UPDATE review_requests 
                SET payment_status = 'paid', payment_id = ?, payment_method = 'demo'
                WHERE id = ?
            ");
            $payment_id = 'DEMO_' . time() . '_' . $request_id;
            $stmt->execute([$payment_id, $request_id]);
            
            // Insert payment transaction
            $stmt = $pdo->prepare("
                INSERT INTO payment_transactions 
                (seller_id, review_request_id, amount, gst_amount, total_amount, 
                 payment_gateway, gateway_payment_id, status)
                VALUES (?, ?, ?, ?, ?, 'demo', ?, 'success')
            ");
            $stmt->execute([
                $seller_id,
                $request_id,
                $request['total_amount'],
                $request['gst_amount'],
                $request['grand_total'],
                $payment_id
            ]);
            
            // Update seller wallet
            $stmt = $pdo->prepare("
                UPDATE seller_wallet 
                SET total_spent = total_spent + ?
                WHERE seller_id = ?
            ");
            $stmt->execute([$request['grand_total'], $seller_id]);
            
            $pdo->commit();
            
            header('Location: orders.php?success=payment_completed');
            exit;
        }
        
        // In production, redirect to actual payment gateway
        require_once __DIR__ . '/../includes/payment/PaymentFactory.php';
        
        // Get available gateways
        $gateways = PaymentFactory::getAvailableGateways($pdo);
        
        if (empty($gateways)) {
            throw new Exception('No payment gateway is enabled');
        }
        
        // Use first available gateway
        $gateway = PaymentFactory::getGateway($gateways[0]['code'], $pdo);
        
        // Get seller details
        $stmt2 = $pdo->prepare("SELECT name, email, mobile FROM sellers WHERE id = ?");
        $stmt2->execute([$seller_id]);
        $seller = $stmt2->fetch();
        
        $paymentOrder = $gateway->createOrder(
            (float) $request['grand_total'],
            'REQ_' . $request_id,
            ['name' => $seller['name'], 'email' => $seller['email'], 'phone' => $seller['mobile']],
            ['seller_id' => $seller_id, 'request_id' => $request_id]
        );
        
        // Store order details in session
        $_SESSION['payment_order'] = [
            'request_id' => $request_id,
            'order_id' => $paymentOrder['order_id'],
            'amount' => $request['grand_total']
        ];
        
        // Redirect to payment page
        header('Location: payment-page.php?order_id=' . $paymentOrder['order_id']);
        exit;
        
    } catch (Exception $e) {
        error_log('Payment initiation error: ' . $e->getMessage());
        header('Location: orders.php?error=' . urlencode($e->getMessage()));
        exit;
    }
    
} elseif ($action === 'callback') {
    // Handle payment callback from gateway
    
    try {
        // Get payment details from POST with validation
        $gateway_type = $_POST['gateway'] ?? 'razorpay';
        
        // Validate gateway type
        $allowed_gateways = ['razorpay', 'payumoney'];
        if (!in_array($gateway_type, $allowed_gateways)) {
            throw new Exception('Invalid payment gateway');
        }
        
        $payment_id = $_POST['razorpay_payment_id'] ?? $_POST['payment_id'] ?? '';
        $order_id = $_POST['razorpay_order_id'] ?? $_POST['order_id'] ?? '';
        $signature = $_POST['razorpay_signature'] ?? $_POST['signature'] ?? '';
        
        if (empty($payment_id) || empty($order_id)) {
            throw new Exception('Invalid payment response');
        }
        
        // Get order details from session
        $payment_order = $_SESSION['payment_order'] ?? null;
        
        if (!$payment_order || $payment_order['order_id'] !== $order_id) {
            throw new Exception('Invalid payment session');
        }
        
        $request_id = $payment_order['request_id'];
        
        // Verify payment with gateway
        require_once __DIR__ . '/../includes/payment/PaymentFactory.php';
        $gateway = PaymentFactory::getGateway($gateway_type, $pdo);
        
               $verification = [
            'razorpay_order_id' => $order_id,
            'razorpay_payment_id' => $payment_id,
            'razorpay_signature' => $signature
        ];
        
        $isValid = $gateway->verifyPayment($verification);

         
        
        if (!$isValid) {
            throw new Exception('Payment verification failed');
        }
        
        // Payment is valid, update database
        $pdo->beginTransaction();
        
        // Update review request
        $stmt = $pdo->prepare("
            UPDATE review_requests 
            SET payment_status = 'paid', payment_id = ?, payment_method = ?
            WHERE id = ?
        ");
        $stmt->execute([$payment_id, $gateway_type, $request_id]);
        
        // Get request details
        $stmt = $pdo->prepare("SELECT * FROM review_requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();
        
        // Insert payment transaction
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions 
            (seller_id, review_request_id, amount, gst_amount, total_amount, 
             payment_gateway, gateway_order_id, gateway_payment_id, gateway_signature, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'success')
        ");
        $stmt->execute([
            $seller_id,
            $request_id,
            $request['total_amount'],
            $request['gst_amount'],
            $request['grand_total'],
            $gateway_type,
            $order_id,
            $payment_id,
            $signature
        ]);
        
        // Update seller wallet
        $stmt = $pdo->prepare("
            UPDATE seller_wallet 
            SET total_spent = total_spent + ?
            WHERE seller_id = ?
        ");
        $stmt->execute([$request['grand_total'], $seller_id]);
        

        // Generate Invoice
        $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($request_id, 5, '0', STR_PAD_LEFT);
        
        $gst_stmt = $pdo->query("SELECT * FROM gst_settings LIMIT 1");
        $gst_settings = $gst_stmt->fetch();
        
        $seller_stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
        $seller_stmt->execute([$seller_id]);
        $seller = $seller_stmt->fetch();
        
        $cgst = $request['gst_amount'] / 2;
        $sgst = $request['gst_amount'] / 2;
        
        $invoice_stmt = $pdo->prepare("
            INSERT INTO tax_invoices 
            (invoice_number, seller_id, review_request_id, seller_gst, seller_legal_name, 
             seller_address, platform_gst, platform_legal_name, platform_address,
             base_amount, cgst_amount, sgst_amount, igst_amount, total_gst, grand_total, invoice_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $invoice_stmt->execute([
            $invoice_number,
            $seller_id,
            $request_id,
            $seller['gst_number'] ?? '',
            $seller['company_name'] ?? $seller['name'],
            $seller['address'] ?? '',
            $gst_settings['gst_number'] ?? '',
            $gst_settings['legal_name'] ?? 'ReviewFlow',
            $gst_settings['address'] ?? '',
            $request['total_amount'],
            $cgst,
            $sgst,
            0,
            $request['gst_amount'],
            $request['grand_total']
        ]);

        $pdo->commit();
        
        // Clear session
        unset($_SESSION['payment_order']);
        
        header('Location: orders.php?success=payment_completed');
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Payment callback error: ' . $e->getMessage());
        
        // Mark payment as failed if request_id exists
        if (isset($request_id)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE review_requests 
                    SET payment_status = 'failed'
                    WHERE id = ?
                ");
                $stmt->execute([$request_id]);
            } catch (PDOException $e2) {
                error_log('Failed to update payment status: ' . $e2->getMessage());
            }
        }
        
        header('Location: orders.php?error=' . urlencode($e->getMessage()));
        exit;
    }
    
} elseif ($action === 'add_money') {
    // Handle add money to wallet
    $amount = (float) ($_GET['amount'] ?? 0);
    
    if ($amount < 100 || $amount > 100000) {
        header('Location: wallet.php?error=invalid_amount');
        exit;
    }
    
    // Verify amount from session to prevent parameter tampering
    if (!isset($_SESSION['wallet_add_amount']) || $_SESSION['wallet_add_amount'] != $amount) {
        header('Location: wallet.php?error=invalid_session');
        exit;
    }
    
    // For demo, simulate adding money
    try {
        $pdo->beginTransaction();
        
        // Update wallet balance
        $stmt = $pdo->prepare("
            UPDATE seller_wallet 
            SET balance = balance + ?
            WHERE seller_id = ?
        ");
        $stmt->execute([$amount, $seller_id]);
        
        // Insert transaction
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions 
            (seller_id, amount, gst_amount, total_amount, payment_gateway, 
             gateway_payment_id, status)
            VALUES (?, ?, 0, ?, 'demo', ?, 'success')
        ");
        $payment_id = 'WALLET_' . time() . '_' . $seller_id;
        $stmt->execute([$seller_id, $amount, $amount, $payment_id]);
        
        $pdo->commit();
        
        header('Location: wallet.php?success=money_added');
        exit;
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Add money error: ' . $e->getMessage());
        header('Location: wallet.php?error=transaction_failed');
        exit;
    }
} else {
    header('Location: dashboard.php');
    exit;
}
?>
