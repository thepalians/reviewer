<?php
declare(strict_types=1);

/**
 * Telegram Webhook Handler
 * Called by Telegram servers when users message the bot.
 * No session/auth required — Telegram calls this directly.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/TelegramBot.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Read and validate the incoming update
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    exit;
}

$update = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($update['message'])) {
    http_response_code(400);
    exit;
}

$message = $update['message'];

// Validate required fields
if (!isset($message['chat']['id'], $message['from']['id'])) {
    http_response_code(400);
    exit;
}

$chatId = (string)$message['chat']['id'];
$text = trim($message['text'] ?? '');

$bot = new TelegramBot();

if (str_starts_with($text, '/start')) {
    // Extract user_id from /start USER_ID
    $parts = explode(' ', $text, 2);
    $userId = isset($parts[1]) ? (int)trim($parts[1]) : 0;

    if ($userId <= 0) {
        $bot->sendMessage('❌ Invalid link. Please use the Connect button from your ReviewFlow dashboard.', $chatId);
        exit;
    }

    // Validate user exists in database
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $bot->sendMessage('❌ Invalid link. Please use the Connect button from your ReviewFlow dashboard.', $chatId);
            exit;
        }

        // Save telegram_chat_id and connected timestamp
        $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = ?, telegram_connected_at = NOW() WHERE id = ?");
        $stmt->execute([$chatId, $userId]);

        $bot->sendMessage('✅ Telegram connected successfully! Ab aapko personal notifications milenge.', $chatId);
    } catch (PDOException $e) {
        error_log("Telegram webhook DB error: " . $e->getMessage());
        $bot->sendMessage('❌ An error occurred. Please try again later.', $chatId);
    }

} elseif ($text === '/disconnect') {
    // Disconnect by clearing telegram_chat_id for any user with this chat_id
    try {
        $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = NULL, telegram_connected_at = NULL WHERE telegram_chat_id = ?");
        $stmt->execute([$chatId]);

        $bot->sendMessage('✅ Telegram disconnected. Aapko ab notifications nahi milenge.', $chatId);
    } catch (PDOException $e) {
        error_log("Telegram webhook disconnect error: " . $e->getMessage());
        $bot->sendMessage('❌ An error occurred. Please try again later.', $chatId);
    }

} elseif ($text === '/status') {
    // Show connection status
    try {
        $stmt = $pdo->prepare("SELECT id, name, telegram_connected_at FROM users WHERE telegram_chat_id = ?");
        $stmt->execute([$chatId]);
        $user = $stmt->fetch();

        if ($user) {
            $connectedAt = date('d M Y', strtotime($user['telegram_connected_at']));
            $bot->sendMessage("✅ Connected! Account: {$user['name']}\nConnected since: {$connectedAt}", $chatId);
        } else {
            $bot->sendMessage('❌ Not connected. Use the Connect button from your ReviewFlow dashboard.', $chatId);
        }
    } catch (PDOException $e) {
        error_log("Telegram webhook status error: " . $e->getMessage());
        $bot->sendMessage('❌ An error occurred. Please try again later.', $chatId);
    }

} else {
    $bot->sendMessage('👋 Use /status to check connection, or /disconnect to unlink your account.', $chatId);
}

http_response_code(200);
exit;
