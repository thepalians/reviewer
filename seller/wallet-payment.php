<?php
require_once __DIR__ . '/../includes/config.php';

// Check if seller is logged in
if (!isset($_SESSION['seller_id'])) {
    header('Location: index.php');
    exit;
}

$seller_id = $_SESSION['seller_id'];

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php?error=invalid_request');
    exit;
}

// Get request ID
$request_id = (int) ($_POST['request_id'] ?? 0);

if ($request_id <= 0) {
    header('Location: orders.php?error=invalid_request');
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get review request details with row lock
    $stmt = $pdo->prepare("
        SELECT * FROM review_requests 
        WHERE id = ? AND seller_id = ? AND payment_status = 'pending'
        FOR UPDATE
    ");
    $stmt->execute([$request_id, $seller_id]);
    $request = $stmt->fetch();
    
    if (!$request) {
        throw new Exception('Review request not found or already paid');
    }
    
    $amount = (float) $request['grand_total'];
    
    // Get current wallet balance with row lock
    $stmt = $pdo->prepare("
        SELECT balance FROM seller_wallet 
        WHERE seller_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$seller_id]);
    $wallet = $stmt->fetch();
    
    if (!$wallet) {
        throw new Exception('Wallet not found');
    }
    
    $balance = (float) $wallet['balance'];
    
    // Verify sufficient balance
    if ($balance < $amount) {
        throw new Exception('Insufficient wallet balance. Current balance: ₹' . number_format($balance, 2) . ', Required: ₹' . number_format($amount, 2));
    }
    
    // Deduct from wallet
    $stmt = $pdo->prepare("
        UPDATE seller_wallet 
        SET balance = balance - ?, total_spent = total_spent + ?
        WHERE seller_id = ?
    ");
    $stmt->execute([$amount, $amount, $seller_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Failed to update wallet balance');
    }
    
    // Generate payment ID with better uniqueness
    $payment_id = 'WALLET_' . time() . '_' . uniqid() . '_' . $request_id;
    
    // Update review request
    $stmt = $pdo->prepare("
        UPDATE review_requests 
        SET payment_status = 'paid', payment_id = ?, payment_method = 'wallet'
        WHERE id = ?
    ");
    $stmt->execute([$payment_id, $request_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Failed to update review request status');
    }
    
    // Insert payment transaction record
    $stmt = $pdo->prepare("
        INSERT INTO payment_transactions 
        (seller_id, review_request_id, amount, gst_amount, total_amount, 
         payment_gateway, gateway_payment_id, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'wallet', ?, 'success', NOW())
    ");
    $stmt->execute([
        $seller_id,
        $request_id,
        $request['total_amount'],
        $request['gst_amount'],
        $request['grand_total'],
        $payment_id
    ]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Failed to record payment transaction');
    }
    
    // Generate Invoice
    $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($request_id, 5, '0', STR_PAD_LEFT);
    
    // Get GST settings
    $gst_stmt = $pdo->query("SELECT * FROM gst_settings LIMIT 1");
    $gst_settings = $gst_stmt->fetch();
    
    // Get seller details
    $seller_stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
    $seller_stmt->execute([$seller_id]);
    $seller = $seller_stmt->fetch();
    
    // Calculate CGST and SGST (split GST equally)
    $cgst = $request['gst_amount'] / 2;
    $sgst = $request['gst_amount'] / 2;
    
    // Insert invoice
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
        $seller['billing_address'] ?? '',
        $gst_settings['gst_number'] ?? '',
        $gst_settings['legal_name'] ?? 'ReviewFlow',
        $gst_settings['registered_address'] ?? '',
        $request['total_amount'],
        $cgst,
        $sgst,
        0, // IGST
        $request['gst_amount'],
        $request['grand_total']
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Clear payment session
    unset($_SESSION['payment_order']);
    
    // Log success
    error_log('Wallet payment successful - Request ID: ' . $request_id . ', Seller ID: ' . $seller_id . ', Amount: ₹' . $amount);
    
    // Redirect to success page
    header('Location: orders.php?success=payment_completed_wallet');
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error
    error_log('Wallet payment error - Seller ID: ' . $seller_id . ', Request ID: ' . $request_id . ' - ' . $e->getMessage());
    
    // Redirect with error message
    header('Location: orders.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
