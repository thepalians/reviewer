<?php
require_once __DIR__ . '/config.php';

function getRazorpayKeyId(): string {
    return getSetting('razorpay_key_id', '');
}

function getRazorpayKeySecret(): string {
    return getSetting('razorpay_key_secret', '');
}

function createRazorpayOrder(float $amount, string $receiptId, array $notes = []): array {
    $keyId = getRazorpayKeyId();
    $keySecret = getRazorpayKeySecret();
    $payload = json_encode([
        'amount'   => (int)($amount * 100),
        'currency' => 'INR',
        'receipt'  => $receiptId,
        'notes'    => $notes,
    ]);
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => $keyId . ':' . $keySecret,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

function verifyRazorpaySignature(string $orderId, string $paymentId, string $signature): bool {
    $secret = getRazorpayKeySecret();
    if (empty($secret)) return false;
    $payload = $orderId . '|' . $paymentId;
    $expected = hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}
