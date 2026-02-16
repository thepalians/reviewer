<?php
declare(strict_types=1);

/**
 * Notifications Class - Email and SMS Notifications System
 * Uses PHPMailer for email notifications
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Notifications {
    private $pdo;
    private $mailer;
    
    /**
     * Constructor
     */
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initMailer();
    }
    
    /**
     * Initialize PHPMailer
     */
    private function initMailer(): void {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USER;
            $this->mailer->Password = SMTP_PASS;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = SMTP_PORT;
            
            // Sender info
            $this->mailer->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            
            // Content type
            $this->mailer->isHTML(true);
        } catch (Exception $e) {
            error_log("Mailer initialization error: {$e->getMessage()}");
        }
    }
    
    /**
     * Send email notification
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @return bool Success status
     */
    public function sendEmail(string $to, string $subject, string $body): bool {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Email sent successfully to: $to");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Email send error to $to: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Send SMS notification (placeholder for future SMS gateway integration)
     * 
     * @param string $mobile Mobile number
     * @param string $message SMS message
     * @return bool Success status
     */
    public function sendSMS(string $mobile, string $message): bool {
        // TODO: Integrate with SMS gateway (MSG91, Twilio, etc.)
        error_log("SMS to $mobile: $message");
        return true; // Placeholder
    }
    
    /**
     * Queue a notification for later sending
     * 
     * @param int|null $userId User ID
     * @param string $type Notification type
     * @param string $channel Channel (email/sms)
     * @param string $recipient Recipient email/mobile
     * @param string $subject Subject (for email)
     * @param string $body Message body
     * @param string|null $scheduledAt Scheduled send time
     * @return bool Success status
     */
    public function queueNotification(
        ?int $userId,
        string $type,
        string $channel,
        string $recipient,
        string $subject,
        string $body,
        ?string $scheduledAt = null
    ): bool {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notification_queue 
                (user_id, type, channel, recipient, subject, body, scheduled_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            return $stmt->execute([
                $userId,
                $type,
                $channel,
                $recipient,
                $subject,
                $body,
                $scheduledAt
            ]);
        } catch (PDOException $e) {
            error_log("Queue notification error: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Send notification using template
     * 
     * @param string $type Notification type
     * @param int|null $userId User ID
     * @param string $recipient Email or mobile
     * @param array $variables Template variables
     * @param string $channel Channel (email/sms)
     * @param bool $queue Queue for later sending
     * @return bool Success status
     */
    public function sendTemplateNotification(
        string $type,
        ?int $userId,
        string $recipient,
        array $variables = [],
        string $channel = 'email',
        bool $queue = false
    ): bool {
        try {
            // Get template
            $stmt = $this->pdo->prepare("
                SELECT subject, body, sms_body 
                FROM notification_templates 
                WHERE type = ? AND is_active = 1
            ");
            $stmt->execute([$type]);
            $template = $stmt->fetch();
            
            if (!$template) {
                error_log("Template not found: $type");
                return false;
            }
            
            // Replace variables in template
            $subject = $this->replaceVariables($template['subject'], $variables);
            $body = $channel === 'email' 
                ? $this->replaceVariables($template['body'], $variables)
                : $this->replaceVariables($template['sms_body'] ?? $template['body'], $variables);
            
            // Queue or send immediately
            if ($queue) {
                return $this->queueNotification($userId, $type, $channel, $recipient, $subject, $body);
            } else {
                if ($channel === 'email') {
                    return $this->sendEmail($recipient, $subject, $body);
                } else {
                    return $this->sendSMS($recipient, $body);
                }
            }
        } catch (PDOException $e) {
            error_log("Template notification error: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Replace template variables
     * 
     * @param string $template Template string
     * @param array $variables Variables to replace
     * @return string Processed template
     */
    private function replaceVariables(string $template, array $variables): string {
        foreach ($variables as $key => $value) {
            $template = str_replace("{{" . $key . "}}", (string)$value, $template);
        }
        return $template;
    }
    
    /**
     * Process queued notifications
     * 
     * @param int $limit Number of notifications to process
     * @return int Number of notifications processed
     */
    public function processQueue(int $limit = 50): int {
        try {
            // Get pending notifications
            $stmt = $this->pdo->prepare("
                SELECT * FROM notification_queue 
                WHERE status = 'pending' 
                AND (scheduled_at IS NULL OR scheduled_at <= NOW())
                ORDER BY created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $notifications = $stmt->fetchAll();
            
            $processed = 0;
            
            foreach ($notifications as $notification) {
                $success = false;
                $errorMessage = null;
                
                try {
                    if ($notification['channel'] === 'email') {
                        $success = $this->sendEmail(
                            $notification['recipient'],
                            $notification['subject'],
                            $notification['body']
                        );
                    } else {
                        $success = $this->sendSMS(
                            $notification['recipient'],
                            $notification['body']
                        );
                    }
                } catch (Exception $e) {
                    $errorMessage = $e->getMessage();
                }
                
                // Update status
                $updateStmt = $this->pdo->prepare("
                    UPDATE notification_queue 
                    SET status = ?, sent_at = ?, error_message = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $success ? 'sent' : 'failed',
                    $success ? date('Y-m-d H:i:s') : null,
                    $errorMessage,
                    $notification['id']
                ]);
                
                if ($success) {
                    $processed++;
                }
            }
            
            return $processed;
        } catch (PDOException $e) {
            error_log("Process queue error: {$e->getMessage()}");
            return 0;
        }
    }
    
    /**
     * Get notification templates
     * 
     * @return array Templates
     */
    public function getTemplates(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM notification_templates 
                ORDER BY type ASC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get templates error: {$e->getMessage()}");
            return [];
        }
    }
    
    /**
     * Get single notification template
     * 
     * @param int $id Template ID
     * @return array|null Template
     */
    public function getTemplate(int $id): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM notification_templates WHERE id = ?
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Get template error: {$e->getMessage()}");
            return null;
        }
    }
    
    /**
     * Update notification template
     * 
     * @param int $id Template ID
     * @param array $data Template data
     * @return bool Success status
     */
    public function updateTemplate(int $id, array $data): bool {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notification_templates 
                SET subject = ?, body = ?, sms_body = ?, is_active = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([
                $data['subject'],
                $data['body'],
                $data['sms_body'] ?? null,
                $data['is_active'] ?? 1,
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Update template error: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Get queue statistics
     * 
     * @return array Statistics
     */
    public function getQueueStats(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM notification_queue
            ");
            return $stmt->fetch() ?: [];
        } catch (PDOException $e) {
            error_log("Get queue stats error: {$e->getMessage()}");
            return [];
        }
    }
}
?>
