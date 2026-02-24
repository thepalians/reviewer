<?php
/**
 * Cron: Send Telegram DM to inactive users (no task completed in 3+ days)
 * Run: 0 10 * * * php /path/to/cron/telegram_inactive_nudge.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/TelegramBot.php';

if (!defined('TELEGRAM_ENABLED') || !TELEGRAM_ENABLED) exit;

try {
    // Find users with telegram connected but inactive for 3+ days
    $stmt = $pdo->query("
        SELECT u.id, u.telegram_chat_id, u.name,
            (SELECT COALESCE(SUM(w.balance), 0) FROM user_wallet w WHERE w.user_id = u.id) as wallet_balance,
            (SELECT COUNT(*) FROM tasks t WHERE t.task_status = 'pending' AND t.user_id IS NULL) as available_tasks
        FROM users u
        WHERE u.telegram_chat_id IS NOT NULL
        AND u.status = 'active'
        AND u.id NOT IN (
            SELECT DISTINCT user_id FROM tasks 
            WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            AND task_status IN ('completed', 'pending')
        )
        LIMIT 50
    ");
    
    $bot = new TelegramBot();
    $sent = 0;
    
    while ($user = $stmt->fetch()) {
        try {
            $bot->sendInactiveNudge($user['telegram_chat_id'], [
                'available_tasks' => $user['available_tasks'],
                'total_earnings' => $user['wallet_balance'],
            ]);
            $sent++;
            usleep(100000); // 100ms delay between messages (Telegram rate limit)
        } catch (Exception $e) {
            error_log("Nudge error for user {$user['id']}: " . $e->getMessage());
        }
    }
    
    echo "Sent {$sent} nudge messages.\n";
} catch (Exception $e) {
    error_log("Inactive nudge cron error: " . $e->getMessage());
}
