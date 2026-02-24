<?php
require_once __DIR__ . '/../includes/config.php';

// Only allow from CLI or admin
if (php_sapi_name() !== 'cli') {
    session_start();
    require_once __DIR__ . '/../includes/security.php';
    if (!isset($_SESSION['admin_name'])) {
        die('Admin access required');
    }
}

$webhook_url = APP_URL . '/api/telegram-webhook.php';
$api_url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/setWebhook";

$ch = curl_init($api_url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['url' => $webhook_url]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$response = curl_exec($ch);
curl_close($ch);

echo "Webhook Response: " . $response;
