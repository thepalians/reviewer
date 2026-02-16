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
     * Send welcome message for new user registration
     *
     * @param string $userName Name of the new user
     * @param int $userId User ID
     * @return bool True on success, false on failure
     */
    public function sendWelcomeMessage(string $userName, int $userId): bool {
        $message = "🎉 <b>New User Registered!</b>\n\n";
        $message .= "👤 <b>Name:</b> {$this->escapeHtml($userName)}\n";
        $message .= "🆔 <b>User ID:</b> #{$userId}\n";
        $message .= "📅 <b>Joined:</b> " . date('d M Y, h:i A') . "\n\n";
        $message .= "✅ Welcome to ReviewFlow! 🚀\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "🤖 <i>ReviewFlow Task Bot</i>";
        
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
     * Escape HTML special characters
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private function escapeHtml(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
