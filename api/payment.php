<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/payment-functions.php';
require_once '../includes/activity-logger.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create_order':
        $amount = $input['amount'] ?? 0;
        $result = createRazorpayOrder($db, $user_id, $amount);
        echo json_encode($result);
        break;
        
    case 'verify_payment':
        $payment_id = $input['payment_id'] ?? 0;
        $razorpay_payment_id = $input['razorpay_payment_id'] ?? '';
        $razorpay_signature = $input['razorpay_signature'] ?? '';
        
        $result = verifyRazorpayPayment($db, $payment_id, $razorpay_payment_id, $razorpay_signature);
        echo json_encode($result);
        break;
        
    case 'get_payments':
        $limit = $input['limit'] ?? 50;
        $payments = getUserPayments($db, $user_id, $limit);
        echo json_encode(['success' => true, 'payments' => $payments]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
