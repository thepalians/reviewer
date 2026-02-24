<?php
/**
 * Cron: Send Telegram deadline reminders (tasks due within 24 hours)
 * Run: 0 9 * * * php /path/to/cron/telegram_deadline_reminder.php
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/TelegramBot.php';

if (!defined('TELEGRAM_ENABLED') || !TELEGRAM_ENABLED) exit;

try {
    $stmt = $pdo->query("
        SELECT t.id as task_id, t.deadline, t.user_id, u.telegram_chat_id
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        WHERE t.task_status = 'pending'
        AND t.deadline IS NOT NULL
        AND t.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        AND u.telegram_chat_id IS NOT NULL
        LIMIT 100
    ");
    
    $bot = new TelegramBot();
    $sent = 0;
    
    while ($task = $stmt->fetch()) {
        try {
            $bot->sendDeadlineReminder($task['telegram_chat_id'], [
                'task_id' => $task['task_id'],
                'deadline' => date('d M Y, h:i A', strtotime($task['deadline'])),
            ]);
            $sent++;
            usleep(100000);
        } catch (Exception $e) {
            error_log("Deadline reminder error for task {$task['task_id']}: " . $e->getMessage());
        }
    }
    
    echo "Sent {$sent} deadline reminders.\n";
} catch (Exception $e) {
    error_log("Deadline reminder cron error: " . $e->getMessage());
}
