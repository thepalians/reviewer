<?php
declare(strict_types=1);

/**
 * Telegram Bot Helper Class
 * Sends task assignment notifications to Telegram channel
 */
class TelegramBot {
    private string $token;
    private string $channelId;
    private string $apiUrl;
    
    /**
     * Constructor
     * 
     * @param string|null $token Bot token (defaults to TELEGRAM_BOT_TOKEN constant)
     * @param string|null $channelId Channel ID (defaults to TELEGRAM_CHANNEL_ID constant)
     */
    public function __construct(?string $token = null, ?string $channelId = null) {
        $this->token = $token ?? (defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : '');
        $this->channelId = $channelId ?? (defined('TELEGRAM_CHANNEL_ID') ? TELEGRAM_CHANNEL_ID : '');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
    }
    
    /**
     * Send task assigned notification for single user
     * 
     * @param array $taskData Task data with keys: user_name, task_id, product_link, brand_name, commission, deadline, priority, assigned_by
     * @return bool True on success, false on failure
     */
    public function sendTaskAssignedNotification(array $taskData): bool {
        $priorityEmojis = [
            'low' => '🟢',
            'medium' => '🟡',
            'high' => '🟠',
            'urgent' => '🔴'
        ];
        
        $priority = $taskData['priority'] ?? 'medium';
        $priorityEmoji = $priorityEmojis[$priority] ?? '🟡';
        
        $message = "<b>📋 New Task Assigned</b>\n\n";
        $message .= "👤 <b>User:</b> {$this->escapeHtml($taskData['user_name'])}\n";
        $message .= "🆔 <b>Task ID:</b> #{$taskData['task_id']}\n";
        
        if (!empty($taskData['brand_name'])) {
            $message .= "🏷️ <b>Brand:</b> {$this->escapeHtml($taskData['brand_name'])}\n";
        }
        
        $message .= "💰 <b>Commission:</b> ₹" . number_format($taskData['commission'], 2) . "\n";
        
        if (!empty($taskData['deadline'])) {
            $message .= "⏰ <b>Deadline:</b> {$this->escapeHtml($taskData['deadline'])}\n";
        }
        
        $message .= "🎯 <b>Priority:</b> {$priorityEmoji} " . ucfirst($priority) . "\n";
        $message .= "👨‍💼 <b>Assigned By:</b> {$this->escapeHtml($taskData['assigned_by'])}\n\n";
        $message .= "🔗 <b>Product:</b> <a href=\"{$this->escapeHtml($taskData['product_link'])}\">View Product</a>";
        
        return $this->sendMessage($message);
    }
    
    /**
     * Send bulk task assignment notification
     * 
     * @param int $userCount Number of users assigned
     * @param array $taskData Task data with keys: brand_name, commission, product_link, assigned_by
     * @return bool True on success, false on failure
     */
    public function sendBulkTaskNotification(int $userCount, array $taskData): bool {
        $message = "<b>📢 Bulk Task Assignment</b>\n\n";
        $message .= "👥 <b>Users Assigned:</b> {$userCount}\n";
        
        if (!empty($taskData['brand_name'])) {
            $message .= "🏷️ <b>Brand:</b> {$this->escapeHtml($taskData['brand_name'])}\n";
        }
        
        $message .= "💰 <b>Commission:</b> ₹" . number_format($taskData['commission'], 2) . "\n";
        $message .= "👨‍💼 <b>Assigned By:</b> {$this->escapeHtml($taskData['assigned_by'])}\n\n";
        $message .= "🔗 <b>Product:</b> <a href=\"{$this->escapeHtml($taskData['product_link'])}\">View Product</a>";
        
        return $this->sendMessage($message);
    }
    
    /**
     * Send a message to the Telegram channel
     * 
     * @param string $text Message text in HTML format
     * @param string|null $chatId Chat ID (defaults to channelId)
     * @return bool True on success, false on failure
     */
    public function sendMessage(string $text, ?string $chatId = null): bool {
        if (empty($this->token) || empty($this->channelId)) {
            error_log('Telegram Bot: Token or Channel ID not configured');
            return false;
        }
        
        $url = $this->apiUrl . '/sendMessage';
        $data = [
            'chat_id' => $chatId ?? $this->channelId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false
        ];
        
        $response = $this->makeRequest($url, $data);
        
        if (isset($response['ok']) && $response['ok'] === true) {
            return true;
        } else {
            $errorDescription = $response['description'] ?? 'Unknown error';
            error_log("Telegram Bot Error: {$errorDescription}");
            return false;
        }
    }
    
    /**
     * Make a POST request to Telegram API
     * 
     * @param string $url API endpoint URL
     * @param array $data POST data
     * @return array Response decoded as array
     */
    private function makeRequest(string $url, array $data): array {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            error_log("Telegram Bot cURL Error: {$curlError}");
            return ['ok' => false, 'description' => $curlError];
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Telegram Bot JSON Decode Error: " . json_last_error_msg());
            return ['ok' => false, 'description' => 'Invalid JSON response'];
        }
        
        return $decoded ?? ['ok' => false, 'description' => 'Empty response'];
    }
    
    /**
     * Send personal DM to a user
     */
    public function sendPersonalMessage(string $chatId, string $text): bool {
        return $this->sendMessage($text, $chatId);
    }

    /**
     * Send task assigned notification as personal DM
     */
    public function sendPersonalTaskNotification(string $chatId, array $taskData): bool {
        $message = "🎯 <b>Naya Task Mila!</b>\n\n";
        $message .= "🆔 <b>Task ID:</b> #{$taskData['task_id']}\n";
        if (!empty($taskData['brand_name'])) {
            $message .= "🏷️ <b>Brand:</b> {$this->escapeHtml($taskData['brand_name'])}\n";
        }
        $message .= "💰 <b>Commission:</b> ₹" . number_format($taskData['commission'], 2) . "\n";
        if (!empty($taskData['deadline'])) {
            $message .= "⏰ <b>Deadline:</b> {$this->escapeHtml($taskData['deadline'])}\n";
        }
        $message .= "🔗 <b>Product:</b> <a href=\"{$this->escapeHtml($taskData['product_link'])}\">View Product</a>\n\n";
        $message .= "👉 <a href=\"" . APP_URL . "/user/task-detail.php?id={$taskData['task_id']}\">Open Task</a>";

        return $this->sendPersonalMessage($chatId, $message);
    }

    /**
     * Send payment/withdrawal notification as personal DM
     */
    public function sendPaymentNotification(string $chatId, array $data): bool {
        $message = "💰 <b>Payment Update!</b>\n\n";
        $message .= "💵 <b>Amount:</b> ₹" . number_format($data['amount'], 2) . "\n";
        $message .= "📋 <b>Type:</b> {$this->escapeHtml($data['type'])}\n";
        $message .= "✅ <b>Status:</b> {$this->escapeHtml($data['status'])}\n";
        if (!empty($data['transaction_id'])) {
            $message .= "🔖 <b>Transaction:</b> {$this->escapeHtml($data['transaction_id'])}\n";
        }
        $message .= "\n👉 <a href=\"" . APP_URL . "/user/wallet.php\">View Wallet</a>";

        return $this->sendPersonalMessage($chatId, $message);
    }

    /**
     * Send task status notification as personal DM
     */
    public function sendTaskStatusNotification(string $chatId, array $data): bool {
        $statusEmoji = [
            'approved' => '✅',
            'rejected' => '❌',
            'completed' => '🎉',
        ];
        $emoji = $statusEmoji[$data['status']] ?? '📋';

        $message = "{$emoji} <b>Task Update!</b>\n\n";
        $message .= "🆔 <b>Task ID:</b> #{$data['task_id']}\n";
        $message .= "📋 <b>Status:</b> " . ucfirst($data['status']) . "\n";
        if (!empty($data['reason'])) {
            $message .= "💬 <b>Reason:</b> {$this->escapeHtml($data['reason'])}\n";
        }
        if ($data['status'] === 'approved' && !empty($data['commission'])) {
            $message .= "💰 <b>Earned:</b> ₹" . number_format($data['commission'], 2) . "\n";
        }
        $message .= "\n👉 <a href=\"" . APP_URL . "/user/task-detail.php?id={$data['task_id']}\">View Task</a>";

        return $this->sendPersonalMessage($chatId, $message);
    }

    /**
     * Send KYC status notification as personal DM
     */
    public function sendKYCNotification(string $chatId, array $data): bool {
        $emoji = $data['status'] === 'verified' ? '✅' : '❌';
        $message = "{$emoji} <b>KYC Update!</b>\n\n";
        $message .= "📋 <b>Status:</b> " . ucfirst($data['status']) . "\n";
        if ($data['status'] === 'verified') {
            $message .= "\n🎉 Ab aap withdrawals kar sakte hain!";
        } else {
            $message .= "💬 <b>Reason:</b> {$this->escapeHtml($data['reason'] ?? 'N/A')}\n";
            $message .= "\n👉 <a href=\"" . APP_URL . "/user/kyc.php\">Re-submit KYC</a>";
        }

        return $this->sendPersonalMessage($chatId, $message);
    }

    /**
     * Send referral bonus notification as personal DM
     */
    public function sendReferralNotification(string $chatId, array $data): bool {
        $message = "🎁 <b>Referral Bonus!</b>\n\n";
        $message .= "👤 <b>Referred User:</b> {$this->escapeHtml($data['referred_name'])}\n";
        $message .= "💰 <b>Bonus:</b> ₹" . number_format($data['bonus'], 2) . "\n";
        $message .= "📊 <b>Total Referrals:</b> {$data['total_referrals']}\n";
        $message .= "\n👉 <a href=\"" . APP_URL . "/user/referral.php\">View Referrals</a>";

        return $this->sendPersonalMessage($chatId, $message);
    }

    /**
     * Send deadline reminder as personal DM
     */
    public function sendDeadlineReminder(string $chatId, array $data): bool {
        $message = "⏰ <b>Deadline Reminder!</b>\n\n";
        $message .= "🆔 <b>Task ID:</b> #{$data['task_id']}\n";
        $message .= "📅 <b>Deadline:</b> {$this->escapeHtml($data['deadline'])}\n";
        $message .= "⚠️ Jaldi complete karo, deadline pass aa rahi hai!\n";
        $message .= "\n👉 <a href=\"" . APP_URL . "/user/task-detail.php?id={$data['task_id']}\">Open Task</a>";

        return $this->sendPersonalMessage($chatId, $message);
    }

    /**
     * Send inactive user nudge as personal DM
     */
    public function sendInactiveNudge(string $chatId, array $data): bool {
        $message = "👋 <b>We miss you!</b>\n\n";
        $message .= "📢 {$data['available_tasks']} naye tasks available hain!\n";
        $message .= "💰 Aaj tak ka earning: ₹" . number_format($data['total_earnings'], 2) . "\n";
        $message .= "\n🎯 Abhi login karke tasks complete karo!\n";
        $message .= "👉 <a href=\"" . APP_URL . "/user/dashboard.php\">Go to Dashboard</a>";

        return $this->sendPersonalMessage($chatId, $message);
    }

    /**
     * Escape HTML special characters
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private function escapeHtml(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
