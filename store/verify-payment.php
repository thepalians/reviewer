<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/razorpay.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . STORE_URL);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: ' . STORE_URL . '?error=csrf');
    exit;
}

$paymentId  = trim($_POST['razorpay_payment_id'] ?? '');
$rzpOrderId = trim($_POST['razorpay_order_id'] ?? '');
$signature  = trim($_POST['razorpay_signature'] ?? '');
$localOrderId = trim($_POST['local_order_id'] ?? '');

// Verify signature
if (!verifyRazorpaySignature($rzpOrderId, $paymentId, $signature)) {
    $order = getOrderById($localOrderId);
    $redirectUrl = $order
        ? STORE_URL . '/product.php?slug=' . urlencode($order['product_name'] ?? '')
        : STORE_URL;
    header('Location: ' . $redirectUrl . '?error=payment_failed');
    exit;
}

// Fetch local order
$order = getOrderById($localOrderId);
if (!$order) {
    header('Location: ' . STORE_URL . '?error=order_not_found');
    exit;
}

try {
    $db = getStoreDB();

    // Update order to paid
    $stmt = $db->prepare('UPDATE store_orders SET payment_status = "paid", razorpay_payment_id = ?, razorpay_signature = ? WHERE order_id = ?');
    $stmt->execute([$paymentId, $signature, $localOrderId]);

    // Increment product sales
    $db->prepare('UPDATE store_products SET total_sales = total_sales + 1 WHERE id = ?')
       ->execute([$order['product_id']]);

    // Re-fetch order for email (with download_token)
    $stmt2 = $db->prepare('SELECT o.*, p.name as product_name FROM store_orders o JOIN store_products p ON o.product_id = p.id WHERE o.order_id = ? LIMIT 1');
    $stmt2->execute([$localOrderId]);
    $fullOrder = $stmt2->fetch();

    // Get product for email
    $product = getProductById((int)$order['product_id']);
    if ($fullOrder && $product) {
        sendOrderConfirmationEmail($fullOrder, $product);
    }

    // Redirect to download page
    $downloadToken = $fullOrder['download_token'] ?? '';
    header('Location: ' . STORE_URL . '/download.php?token=' . urlencode($downloadToken));
    exit;

} catch (Exception $e) {
    error_log('verify-payment error: ' . $e->getMessage());
    header('Location: ' . STORE_URL . '?error=server_error');
    exit;
}
