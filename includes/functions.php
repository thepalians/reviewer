<?php
declare(strict_types=1);

/**
 * ReviewFlow - Complete Functions Library
 * All helper functions for the application
 * 
 * NOTE: Functions are wrapped with function_exists() to avoid 
 * redeclaration errors when security.php is also included
 */

// ============================================
// SECURITY & SANITIZATION FUNCTIONS
// ============================================

/**
 * Sanitize user input for display
 * Note: For database operations, always use prepared statements
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput(string $input): string {
        $input = trim($input);
        // Removed stripslashes - not needed with magic_quotes disabled (PHP 5.4+)
        // and can corrupt legitimate data
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return $input;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void {
        $fallback_path = APP_URL . '/index.php';
        if (str_contains($path, "\r") || str_contains($path, "\n")) {
            $path = $fallback_path;
        }
        if (str_starts_with($path, '//')) {
            $path = $fallback_path;
        }
        $parsed = parse_url($path);
        if ($parsed !== false && (isset($parsed['scheme']) || isset($parsed['host']))) {
            $app_url = parse_url(APP_URL);
            $app_host = $app_url['host'] ?? null;
            $target_host = $parsed['host'] ?? null;
            if ($app_host === null || $target_host === null || strcasecmp($app_host, $target_host) !== 0) {
                $path = $fallback_path;
            }
        }
        if ($path === '') {
            $path = $fallback_path;
        }
        header('Location: ' . $path);
        exit;
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin(): bool {
        return isset($_SESSION['admin_name']) && !empty($_SESSION['admin_name']);
    }
}

if (!function_exists('isUser')) {
    function isUser(): bool {
        return isLoggedIn() && !isAdmin();
    }
}

/**
 * Escape output for HTML
 */
if (!function_exists('escape')) {
    function escape(?string $string): string {
        if ($string === null) return '';
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Generate CSRF token
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Verify CSRF token
 */
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken(?string $token): bool {
        if (!isset($_SESSION['csrf_token']) || !$token) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Generate secure random string
 */
if (!function_exists('generateRandomString')) {
    function generateRandomString(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }
}

/**
 * Hash password securely
 */
if (!function_exists('hashPassword')) {
    function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

/**
 * Verify password
 */
if (!function_exists('verifyPassword')) {
    function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}

/**
 * Rate limiting check
 */
if (!function_exists('checkRateLimit')) {
    function checkRateLimit(string $key, int $maxAttempts = 5, int $decayMinutes = 15): bool {
        global $pdo;
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = md5($key . $ip);
        
        try {
            // Clean old entries
            $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE expires_at < NOW()");
            $stmt->execute();
            
            // Check current count
            $stmt = $pdo->prepare("SELECT attempts FROM rate_limits WHERE identifier = ?");
            $stmt->execute([$identifier]);
            $result = $stmt->fetch();
            
            if ($result && $result['attempts'] >= $maxAttempts) {
                return false; // Rate limited
            }
            
            // Increment attempts
            $stmt = $pdo->prepare("
                INSERT INTO rate_limits (identifier, attempts, expires_at) 
                VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? MINUTE))
                ON DUPLICATE KEY UPDATE attempts = attempts + 1
            ");
            $stmt->execute([$identifier, $decayMinutes]);
            
            return true;
        } catch (PDOException $e) {
            return true; // Allow on error
        }
    }
}

/**
 * Clear rate limit
 */
if (!function_exists('clearRateLimit')) {
    function clearRateLimit(string $key): void {
        global $pdo;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = md5($key . $ip);
        
        try {
            $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE identifier = ?");
            $stmt->execute([$identifier]);
        } catch (PDOException $e) {}
    }
}

// ============================================
// WALLET FUNCTIONS
// ============================================

/**
 * Get wallet balance
 */
if (!function_exists('getWalletBalance')) {
    function getWalletBalance(int $user_id): float {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $balance = $stmt->fetchColumn();
            return $balance !== false ? (float)$balance : 0.00;
        } catch (PDOException $e) {
            error_log("Wallet Balance Error: " . $e->getMessage());
            return 0.00;
        }
    }
}

/**
 * Create wallet for user
 */
if (!function_exists('createWallet')) {
    function createWallet(int $user_id): bool {
        global $pdo;
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_wallet (user_id, balance) VALUES (?, 0)");
            return $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Create Wallet Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Add money to wallet
 */
if (!function_exists('addToWallet')) {
    function addToWallet(int $user_id, float $amount, string $type, string $description, ?int $ref_id = null, ?string $ref_type = null): bool {
        global $pdo;
        
        if ($amount <= 0) return false;
        
        try {
            $pdo->beginTransaction();
            
            // Ensure wallet exists
            createWallet($user_id);
            
            // Get current balance
            $balance = getWalletBalance($user_id);
            $new_balance = $balance + $amount;
            
            // Update wallet
            $stmt = $pdo->prepare("
                UPDATE user_wallet 
                SET balance = balance + ?, total_earned = total_earned + ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$amount, $amount, $user_id]);
            
            // Log transaction
            $stmt = $pdo->prepare("
                INSERT INTO wallet_transactions 
                (user_id, type, amount, balance_after, description, reference_id, reference_type, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $stmt->execute([$user_id, $type, $amount, $new_balance, $description, $ref_id, $ref_type]);
            
            $pdo->commit();
            
            // Send notification
            createNotification($user_id, 'wallet', '💰 Wallet Credited', "₹" . number_format($amount, 2) . " added. $description");
            
            return true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Add to Wallet Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Deduct money from wallet
 */
if (!function_exists('deductFromWallet')) {
    function deductFromWallet(int $user_id, float $amount, string $description): bool {
        global $pdo;
        
        if ($amount <= 0) return false;
        
        $balance = getWalletBalance($user_id);
        if ($balance < $amount) return false;
        
        try {
            $pdo->beginTransaction();
            
            $new_balance = $balance - $amount;
            
            $stmt = $pdo->prepare("UPDATE user_wallet SET balance = balance - ? WHERE user_id = ? AND balance >= ?");
            $stmt->execute([$amount, $user_id, $amount]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Insufficient balance");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO wallet_transactions 
                (user_id, type, amount, balance_after, description, status, created_at)
                VALUES (?, 'debit', ?, ?, ?, 'completed', NOW())
            ");
            $stmt->execute([$user_id, $amount, $new_balance, $description]);
            
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Deduct from Wallet Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get wallet details
 */
if (!function_exists('getWalletDetails')) {
    function getWalletDetails(int $user_id): array {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT * FROM user_wallet WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $wallet = $stmt->fetch();
            
            if (!$wallet) {
                createWallet($user_id);
                return ['balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0];
            }
            
            return $wallet;
        } catch (PDOException $e) {
            return ['balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0];
        }
    }
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

/**
 * Create notification
 */
if (!function_exists('createNotification')) {
    function createNotification(int $user_id, string $type, string $title, string $message, ?string $link = null): bool {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([$user_id, $type, $title, $message, $link]);
        } catch (PDOException $e) {
            error_log("Create Notification Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get unread notification count
 */
if (!function_exists('getUnreadNotificationCount')) {
    function getUnreadNotificationCount(int $user_id): int {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}

/**
 * Get notifications
 */
if (!function_exists('getNotifications')) {
    function getNotifications(int $user_id, int $limit = 10, int $offset = 0): array {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$user_id, $limit, $offset]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}

/**
 * Mark notification as read
 */
if (!function_exists('markNotificationRead')) {
    function markNotificationRead(int $notification_id, int $user_id): bool {
        global $pdo;
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notification_id, $user_id]);
        } catch (PDOException $e) {
            return false;
        }
    }
}

/**
 * Mark all notifications as read
 */
if (!function_exists('markAllNotificationsRead')) {
    function markAllNotificationsRead(int $user_id): bool {
        global $pdo;
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            return $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            return false;
        }
    }
}

// ============================================
// REFERRAL FUNCTIONS
// ============================================

/**
 * Generate referral code
 */
if (!function_exists('generateReferralCode')) {
    function generateReferralCode(int $user_id): string {
        global $pdo;
        
        $code = 'RF' . strtoupper(substr(md5((string)$user_id . time() . bin2hex(random_bytes(4))), 0, 6));
        
        try {
            // Check uniqueness
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referral_code = ?");
            $stmt->execute([$code]);
            
            if ($stmt->fetchColumn() > 0) {
                // Regenerate if exists
                return generateReferralCode($user_id);
            }
            
            $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
            $stmt->execute([$code, $user_id]);
            
            return $code;
        } catch (PDOException $e) {
            error_log("Generate Referral Code Error: " . $e->getMessage());
            return 'RF' . strtoupper(substr(md5((string)$user_id), 0, 6));
        }
    }
}

/**
 * Get referral code
 */
if (!function_exists('getReferralCode')) {
    function getReferralCode(int $user_id): string {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $code = $stmt->fetchColumn();
            
            if (!$code) {
                $code = generateReferralCode($user_id);
            }
            
            return $code;
        } catch (PDOException $e) {
            return '';
        }
    }
}

/**
 * Process referral on signup
 */
if (!function_exists('processReferral')) {
    function processReferral(int $referred_user_id, string $referral_code): bool {
        global $pdo;
        
        if (empty($referral_code)) return false;
        
        try {
            // Find referrer
            $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND id != ?");
            $stmt->execute([$referral_code, $referred_user_id]);
            $referrer = $stmt->fetch();
            
            if (!$referrer) return false;
            
            $referrer_id = $referrer['id'];
            $bonus = (float)getSetting('referral_bonus', 50);
            
            // Update referred user
            $stmt = $pdo->prepare("UPDATE users SET referred_by = ? WHERE id = ?");
            $stmt->execute([$referrer_id, $referred_user_id]);
            
            // Create referral record
            $stmt = $pdo->prepare("
                INSERT INTO referrals (referrer_id, referred_id, bonus_amount, status, created_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$referrer_id, $referred_user_id, $bonus]);
            
            // Notify referrer
            createNotification($referrer_id, 'info', '👥 New Referral!', 'Someone signed up using your referral code. Complete their first task to get bonus!');
            
            return true;
        } catch (PDOException $e) {
            error_log("Process Referral Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Complete referral and give bonus (called when referred user completes first task)
 */
if (!function_exists('completeReferralBonus')) {
    function completeReferralBonus(int $referred_user_id): bool {
        global $pdo;
        
        try {
            // Check for pending referral
            $stmt = $pdo->prepare("SELECT * FROM referrals WHERE referred_id = ? AND status = 'pending'");
            $stmt->execute([$referred_user_id]);
            $referral = $stmt->fetch();
            
            if (!$referral) return false;
            
            $bonus = (float)$referral['bonus_amount'];
            $referrer_id = (int)$referral['referrer_id'];
            
            $pdo->beginTransaction();
            
            // Add bonus to referrer wallet
            addToWallet($referrer_id, $bonus, 'referral', 'Referral bonus for inviting a friend', $referred_user_id, 'referral');
            
            // Update referral status
            $stmt = $pdo->prepare("UPDATE referrals SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$referral['id']]);
            
            // Update referrer stats
            $stmt = $pdo->prepare("UPDATE users SET referral_earnings = referral_earnings + ? WHERE id = ?");
            $stmt->execute([$bonus, $referrer_id]);
            
            $pdo->commit();
            
            // Notify referrer
            createNotification($referrer_id, 'success', '🎉 Referral Bonus Received!', "You earned ₹$bonus referral bonus!");
            
            return true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Complete Referral Bonus Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get referral stats
 */
if (!function_exists('getReferralStats')) {
    function getReferralStats(int $user_id): array {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN bonus_amount ELSE 0 END), 0) as earnings
                FROM referrals WHERE referrer_id = ?
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch() ?: ['total' => 0, 'completed' => 0, 'pending' => 0, 'earnings' => 0];
        } catch (PDOException $e) {
            return ['total' => 0, 'completed' => 0, 'pending' => 0, 'earnings' => 0];
        }
    }
}

// ============================================
// USER STATS FUNCTIONS
// ============================================

/**
 * Update user stats
 */
if (!function_exists('updateUserStats')) {
    function updateUserStats(int $user_id): bool {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_stats (user_id, tasks_completed, tasks_pending, total_earnings, last_active)
                SELECT 
                    ?,
                    (SELECT COUNT(*) FROM tasks WHERE user_id = ? AND task_status = 'completed'),
                    (SELECT COUNT(*) FROM tasks WHERE user_id = ? AND task_status != 'completed'),
                    (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE user_id = ? AND type IN ('credit', 'bonus', 'referral') AND status = 'completed'),
                    CURDATE()
                ON DUPLICATE KEY UPDATE
                    tasks_completed = VALUES(tasks_completed),
                    tasks_pending = VALUES(tasks_pending),
                    total_earnings = VALUES(total_earnings),
                    last_active = VALUES(last_active),
                    updated_at = NOW()
            ");
            return $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
        } catch (PDOException $e) {
            error_log("Update User Stats Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get user stats
 */
if (!function_exists('getUserStats')) {
    function getUserStats(int $user_id): array {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT * FROM user_stats WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stats = $stmt->fetch();
            
            if (!$stats) {
                updateUserStats($user_id);
                return [
                    'tasks_completed' => 0,
                    'tasks_pending' => 0,
                    'total_earnings' => 0,
                    'rating' => 5.00,
                    'level' => 1,
                    'experience_points' => 0,
                    'streak_days' => 0
                ];
            }
            
            return $stats;
        } catch (PDOException $e) {
            return [
                'tasks_completed' => 0,
                'tasks_pending' => 0,
                'total_earnings' => 0,
                'rating' => 5.00,
                'level' => 1
            ];
        }
    }
}

/**
 * Calculate user level based on tasks completed
 */
if (!function_exists('calculateUserLevel')) {
    function calculateUserLevel(int $tasks_completed): int {
        if ($tasks_completed >= 100) return 10;
        if ($tasks_completed >= 75) return 9;
        if ($tasks_completed >= 50) return 8;
        if ($tasks_completed >= 35) return 7;
        if ($tasks_completed >= 25) return 6;
        if ($tasks_completed >= 15) return 5;
        if ($tasks_completed >= 10) return 4;
        if ($tasks_completed >= 5) return 3;
        if ($tasks_completed >= 2) return 2;
        return 1;
    }
}

// ============================================
// EMAIL & NOTIFICATION FUNCTIONS
// ============================================

/**
 * Send email
 */
if (!function_exists('sendEmail')) {
    function sendEmail(string $to, string $subject, string $body, ?int $user_id = null): bool {
        global $pdo;
        
        // Check if email is enabled
        if (getSetting('email_enabled', '1') !== '1') {
            return false;
        }
        
        // Log the email
        $log_id = null;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO notification_logs (user_id, type, recipient, subject, message, status, created_at)
                VALUES (?, 'email', ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $to, $subject, $body]);
            $log_id = $pdo->lastInsertId();
        } catch (PDOException $e) {}
        
        // Prepare headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : APP_NAME) . " <" . (defined('SMTP_FROM') ? SMTP_FROM : 'noreply@palians.com') . ">\r\n";
        
        // Send email
        $result = @mail($to, $subject, $body, $headers);
        
        // Update log
        if ($log_id) {
            try {
                $status = $result ? 'sent' : 'failed';
                $stmt = $pdo->prepare("UPDATE notification_logs SET status = ? WHERE id = ?");
                $stmt->execute([$status, $log_id]);
            } catch (PDOException $e) {}
        }
        
        return $result;
    }
}

/**
 * Send task notification
 */
if (!function_exists('sendTaskNotification')) {
    function sendTaskNotification(int $user_id, string $type, array $data = []): void {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT name, email, mobile FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) return;
            
            $templates = [
                'task_assigned' => [
                    'title' => '📋 New Task Assigned',
                    'message' => "Hi {$user['name']}, a new task has been assigned to you.",
                    'email_subject' => 'New Task Assigned - ' . APP_NAME
                ],
                'refund_processed' => [
                    'title' => '💰 Refund Processed',
                    'message' => "Your refund of ₹{$data['amount']} has been processed.",
                    'email_subject' => 'Refund Processed - ' . APP_NAME
                ],
                'withdrawal_approved' => [
                    'title' => '✅ Withdrawal Approved',
                    'message' => "Your withdrawal of ₹{$data['amount']} has been approved.",
                    'email_subject' => 'Withdrawal Approved - ' . APP_NAME
                ],
                'task_deadline' => [
                    'title' => '⚠️ Task Deadline Reminder',
                    'message' => "Your task #{$data['task_id']} is due soon.",
                    'email_subject' => 'Task Deadline Reminder - ' . APP_NAME
                ],
                'task_completed' => [
                    'title' => '✅ Task Completed',
                    'message' => "Congratulations! Your task has been marked as completed.",
                    'email_subject' => 'Task Completed - ' . APP_NAME
                ]
            ];
            
            if (!isset($templates[$type])) return;
            
            $template = $templates[$type];
            
            // Create in-app notification
            createNotification($user_id, 'task', $template['title'], $template['message']);
            
            // Send email
            $emailBody = getEmailTemplate($template['title'], $template['message'], $user['name']);
            sendEmail($user['email'], $template['email_subject'], $emailBody, $user_id);
            
        } catch (PDOException $e) {
            error_log("Send Task Notification Error: " . $e->getMessage());
        }
    }
}

/**
 * Get email template
 */
if (!function_exists('getEmailTemplate')) {
    function getEmailTemplate(string $title, string $message, string $name): string {
        $app_name = defined('APP_NAME') ? APP_NAME : 'ReviewFlow';
        $app_url = defined('APP_URL') ? APP_URL : '';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
                .container { max-width: 600px; margin: 20px auto; }
                .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { background: #ffffff; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .content h2 { color: #333; margin-top: 0; }
                .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea, #764ba2); color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { text-align: center; margin-top: 20px; color: #999; font-size: 12px; padding: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>$app_name</h1>
                </div>
                <div class='content'>
                    <h2>$title</h2>
                    <p>Hi $name,</p>
                    <p>$message</p>
                    <a href='$app_url/user/' class='button'>Go to Dashboard</a>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " $app_name. All rights reserved.</p>
                    <p>This is an automated message. Please do not reply.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}

/**
 * Get WhatsApp link
 */
if (!function_exists('getWhatsAppLink')) {
    function getWhatsAppLink(string $phone, string $message): string {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }
        return "https://wa.me/$phone?text=" . urlencode($message);
    }
}

// ============================================
// SETTINGS FUNCTIONS
// ============================================

/**
 * Get setting value
 */
if (!function_exists('getSetting')) {
    function getSetting(string $key, $default = null) {
        global $pdo;
        
        static $cache = [];
        
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetchColumn();
            
            $value = $result !== false ? $result : $default;
            $cache[$key] = $value;
            
            return $value;
        } catch (PDOException $e) {
            return $default;
        }
    }
}

/**
 * Set setting value
 */
if (!function_exists('setSetting')) {
    function setSetting(string $key, string $value): bool {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            return $stmt->execute([$key, $value]);
        } catch (PDOException $e) {
            error_log("Set Setting Error: " . $e->getMessage());
            return false;
        }
    }
}

// ============================================
// LOGIN & SECURITY FUNCTIONS
// ============================================

/**
 * Log login attempt
 */
if (!function_exists('logLoginAttempt')) {
    function logLoginAttempt(int $user_id, string $status): void {
        global $pdo;
        
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            // Detect device type
            $device = 'desktop';
            if (preg_match('/mobile|android|iphone|ipod/i', $agent)) {
                $device = 'mobile';
            } elseif (preg_match('/tablet|ipad/i', $agent)) {
                $device = 'tablet';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO login_history (user_id, ip_address, user_agent, device_type, status, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $ip, substr($agent, 0, 500), $device, $status]);
            
            // Update user last login
            if ($status === 'success') {
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?");
                $stmt->execute([$user_id]);
            }
        } catch (PDOException $e) {
            error_log("Log Login Attempt Error: " . $e->getMessage());
        }
    }
}

/**
 * Log admin activity
 */
if (!function_exists('logActivity')) {
    function logActivity(string $action, ?int $task_id = null, ?int $user_id = null): void {
        global $pdo;
        
        try {
            $admin = $_SESSION['admin_name'] ?? 'System';
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            $stmt = $pdo->prepare("
                INSERT INTO admin_logs (admin_name, action, task_id, user_id, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$admin, $action, $task_id, $user_id, $ip]);
        } catch (PDOException $e) {
            error_log("Log Activity Error: " . $e->getMessage());
        }
    }
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Format currency
 */
if (!function_exists('formatCurrency')) {
    function formatCurrency(float $amount, bool $symbol = true): string {
        $formatted = number_format($amount, 2);
        return $symbol ? '₹' . $formatted : $formatted;
    }
}

/**
 * Get time ago string
 */
if (!function_exists('getTimeAgo')) {
    function getTimeAgo(string $datetime): string {
        $time = strtotime($datetime);
        $diff = time() - $time;
        
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
        
        return date('d M Y', $time);
    }
}

/**
 * Validate email
 */
if (!function_exists('isValidEmail')) {
    function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Validate phone number (Indian)
 */
if (!function_exists('isValidPhone')) {
    function isValidPhone(string $phone): bool {
        return preg_match('/^[6-9]\d{9}$/', $phone) === 1;
    }
}

/**
 * Generate OTP
 */
if (!function_exists('generateOTP')) {
    function generateOTP(int $length = 6): string {
        return str_pad((string)random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}

/**
 * Truncate text
 */
if (!function_exists('truncateText')) {
    function truncateText(string $text, int $length = 100, string $suffix = '...'): string {
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . $suffix;
    }
}

/**
 * Get user by ID
 */
if (!function_exists('getUserById')) {
    function getUserById(int $user_id): ?array {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            return $user ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }
}

/**
 * Check if user exists
 */
if (!function_exists('userExists')) {
    function userExists(string $email): bool {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}

/**
 * Get task by ID
 */
if (!function_exists('getTaskById')) {
    function getTaskById(int $task_id): ?array {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            return $task ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }
}

/**
 * Export data to CSV
 */
if (!function_exists('exportToCSV')) {
    function exportToCSV(array $data, string $filename): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM for Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        if (!empty($data)) {
            // Headers
            fputcsv($output, array_keys($data[0]));
            
            // Data
            foreach ($data as $row) {
                fputcsv($output, array_values($row));
            }
        }
        
        fclose($output);
        exit;
    }
}

/**
 * Check maintenance mode
 */
if (!function_exists('isMaintenanceMode')) {
    function isMaintenanceMode(): bool {
        return getSetting('maintenance_mode', '0') === '1';
    }
}

/**
 * Check if registration is enabled
 */
if (!function_exists('isRegistrationEnabled')) {
    function isRegistrationEnabled(): bool {
        return getSetting('registration_enabled', '1') === '1';
    }
}

/**
 * Clean old data (for cron job)
 */
if (!function_exists('cleanOldData')) {
    function cleanOldData(): array {
        global $pdo;
        
        $results = [];
        
        try {
            // Clean old notifications (read, older than 30 days)
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $results['notifications'] = $stmt->rowCount();
            
            // Clean old login history (older than 90 days)
            $stmt = $pdo->prepare("DELETE FROM login_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            $results['login_history'] = $stmt->rowCount();
            
            // Clean old notification logs (older than 30 days)
            $stmt = $pdo->prepare("DELETE FROM notification_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $results['notification_logs'] = $stmt->rowCount();
            
        } catch (PDOException $e) {
            error_log("Clean Old Data Error: " . $e->getMessage());
        }
        
        return $results;
    }
}

// ============================================
// TIER & BADGE SYSTEM FUNCTIONS (v3.0)
// ============================================

/**
 * Calculate tier points for a user
 */
if (!function_exists('calculateTierPoints')) {
    function calculateTierPoints(int $userId): float {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(COUNT(DISTINCT t.id), 0) as tasks_completed,
                    COALESCE(u.active_days, 0) as active_days,
                    COALESCE(COUNT(DISTINCT r.id), 0) as successful_referrals,
                    COALESCE(u.quality_score, 5.0) as quality_score,
                    COALESCE(u.consistency_score, 5.0) as consistency_score
                FROM users u
                LEFT JOIN tasks t ON t.user_id = u.id AND t.status = 'completed'
                LEFT JOIN referrals r ON r.referrer_id = u.id AND r.status = 'completed'
                WHERE u.id = ?
                GROUP BY u.id
            ");
            $stmt->execute([$userId]);
            $data = $stmt->fetch();
            
            if (!$data) return 0;
            
            // Calculate weighted points
            $points = 0;
            $points += $data['tasks_completed'] * 1; // 1 point per task
            $points += $data['active_days'] * 0.5; // 0.5 point per active day
            $points += $data['successful_referrals'] * 5; // 5 points per referral
            
            // Quality bonus (up to 10 points)
            $qualityBonus = ($data['quality_score'] >= 4.5) ? 10 : ($data['quality_score'] >= 4.0 ? 5 : 0);
            $points += $qualityBonus;
            
            // Consistency bonus (up to 5 points)
            $consistencyBonus = ($data['consistency_score'] >= 4.5) ? 5 : ($data['consistency_score'] >= 4.0 ? 3 : 0);
            $points += $consistencyBonus;
            
            return round($points, 2);
            
        } catch (PDOException $e) {
            error_log("Calculate Tier Points Error: " . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Get user tier information
 */
if (!function_exists('getUserTier')) {
    function getUserTier(int $userId): ?array {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT t.*, u.tier_points
                FROM users u
                JOIN reviewer_tiers t ON u.tier_id = t.id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Get User Tier Error: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Check and upgrade user tier if eligible
 */
if (!function_exists('checkTierUpgrade')) {
    function checkTierUpgrade(int $userId): bool {
        global $pdo;
        
        try {
            // Calculate current points
            $points = calculateTierPoints($userId);
            
            // Get appropriate tier
            $stmt = $pdo->prepare("
                SELECT id FROM reviewer_tiers
                WHERE min_points <= ? AND (max_points >= ? OR max_points IS NULL)
                ORDER BY min_points DESC
                LIMIT 1
            ");
            $stmt->execute([$points, $points]);
            $tier = $stmt->fetch();
            
            if (!$tier) return false;
            
            // Update user tier
            $stmt = $pdo->prepare("UPDATE users SET tier_id = ?, tier_points = ? WHERE id = ?");
            $stmt->execute([$tier['id'], $points, $userId]);
            
            // Create notification
            createNotification(
                $userId,
                'tier_upgrade',
                'Tier Upgraded!',
                "Congratulations! You've been upgraded to a new tier level.",
                "user/dashboard.php"
            );
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Check Tier Upgrade Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Award badge to user
 */
if (!function_exists('awardBadge')) {
    function awardBadge(int $userId, string $badgeCode): bool {
        global $pdo;
        
        try {
            // Get badge ID
            $stmt = $pdo->prepare("SELECT id, reward_points, reward_amount FROM badges WHERE badge_code = ? AND is_active = 1");
            $stmt->execute([$badgeCode]);
            $badge = $stmt->fetch();
            
            if (!$badge) return false;
            
            // Check if already earned
            $stmt = $pdo->prepare("SELECT id FROM user_badges WHERE user_id = ? AND badge_id = ?");
            $stmt->execute([$userId, $badge['id']]);
            if ($stmt->fetch()) return false; // Already has badge
            
            // Award badge
            $stmt = $pdo->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)");
            $stmt->execute([$userId, $badge['id']]);
            
            // Add reward points to tier points
            if ($badge['reward_points'] > 0) {
                $stmt = $pdo->prepare("UPDATE users SET tier_points = tier_points + ? WHERE id = ?");
                $stmt->execute([$badge['reward_points'], $userId]);
            }
            
            // Add reward amount to wallet
            if ($badge['reward_amount'] > 0) {
                addMoneyToWallet($userId, $badge['reward_amount'], 'Badge Reward: ' . $badgeCode);
            }
            
            // Check for tier upgrade
            checkTierUpgrade($userId);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Award Badge Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Check badge eligibility for user
 */
if (!function_exists('checkBadgeEligibility')) {
    function checkBadgeEligibility(int $userId): array {
        global $pdo;
        
        $awarded = [];
        
        try {
            // Get all active badges not yet earned
            $stmt = $pdo->prepare("
                SELECT b.* FROM badges b
                WHERE b.is_active = 1
                AND b.id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = ?)
            ");
            $stmt->execute([$userId]);
            $badges = $stmt->fetchAll();
            
            foreach ($badges as $badge) {
                $eligible = false;
                
                switch ($badge['criteria_type']) {
                    case 'tasks':
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'completed'");
                        $stmt->execute([$userId]);
                        $count = (int)$stmt->fetchColumn();
                        $eligible = $count >= $badge['criteria_value'];
                        break;
                        
                    case 'referrals':
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND status = 'completed'");
                        $stmt->execute([$userId]);
                        $count = (int)$stmt->fetchColumn();
                        $eligible = $count >= $badge['criteria_value'];
                        break;
                        
                    case 'quality':
                        $stmt = $pdo->prepare("SELECT quality_score FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $score = (float)$stmt->fetchColumn();
                        $eligible = ($score * 10) >= $badge['criteria_value'];
                        break;
                        
                    case 'streak':
                        $stmt = $pdo->prepare("SELECT active_days FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $days = (int)$stmt->fetchColumn();
                        $eligible = $days >= $badge['criteria_value'];
                        break;
                        
                    case 'earnings':
                        $stmt = $pdo->prepare("
                            SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions 
                            WHERE user_id = ? AND type IN ('credit', 'bonus', 'referral') AND status = 'completed'
                        ");
                        $stmt->execute([$userId]);
                        $earnings = (float)$stmt->fetchColumn();
                        $eligible = $earnings >= $badge['criteria_value'];
                        break;
                }
                
                if ($eligible) {
                    if (awardBadge($userId, $badge['badge_code'])) {
                        $awarded[] = $badge['badge_code'];
                    }
                }
            }
            
        } catch (PDOException $e) {
            error_log("Check Badge Eligibility Error: " . $e->getMessage());
        }
        
        return $awarded;
    }
}

// ============================================
// GST & INVOICE FUNCTIONS (v3.0)
// ============================================

/**
 * Calculate GST amount
 */
if (!function_exists('calculateGST')) {
    function calculateGST(float $amount, bool $breakdown = false) {
        $gstRate = getSetting('gst_rate', 18) / 100;
        $gstAmount = $amount * $gstRate;
        
        if (!$breakdown) {
            return $gstAmount;
        }
        
        // For same state: CGST + SGST, for different state: IGST
        return [
            'cgst' => $gstAmount / 2,
            'sgst' => $gstAmount / 2,
            'igst' => 0, // Set based on state comparison
            'total' => $gstAmount
        ];
    }
}

/**
 * Generate invoice number
 */
if (!function_exists('generateInvoiceNumber')) {
    function generateInvoiceNumber(): string {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        $random = str_pad((string)rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}/{$year}/{$month}/{$random}";
    }
}

/**
 * Generate GST invoice
 */
if (!function_exists('generateInvoice')) {
    function generateInvoice(int $reviewRequestId): ?int {
        global $pdo;
        
        try {
            // Get review request details
            $stmt = $pdo->prepare("
                SELECT rr.*, s.name as seller_name, s.gst_number as seller_gst, 
                       s.company_name as seller_legal_name, s.billing_address as seller_address,
                       pt.id as payment_id
                FROM review_requests rr
                JOIN sellers s ON rr.seller_id = s.id
                LEFT JOIN payment_transactions pt ON pt.review_request_id = rr.id AND pt.status = 'success'
                WHERE rr.id = ?
            ");
            $stmt->execute([$reviewRequestId]);
            $request = $stmt->fetch();
            
            if (!$request) return null;
            
            // Get platform GST settings
            $stmt = $pdo->query("SELECT * FROM gst_settings WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
            $platformGst = $stmt->fetch();
            
            if (!$platformGst) return null;
            
            // Calculate GST breakdown
            $gst = calculateGST($request['total_amount'], true);
            
            // Generate invoice number
            $invoiceNumber = generateInvoiceNumber();
            
            // Insert invoice
            $stmt = $pdo->prepare("
                INSERT INTO tax_invoices (
                    invoice_number, seller_id, review_request_id, payment_transaction_id,
                    seller_gst, seller_legal_name, seller_address,
                    platform_gst, platform_legal_name, platform_address,
                    base_amount, cgst_amount, sgst_amount, igst_amount, total_gst, grand_total,
                    sac_code, invoice_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())
            ");
            
            $stmt->execute([
                $invoiceNumber,
                $request['seller_id'],
                $reviewRequestId,
                $request['payment_id'],
                $request['seller_gst'],
                $request['seller_legal_name'] ?? $request['seller_name'],
                $request['seller_address'],
                $platformGst['gst_number'],
                $platformGst['legal_name'],
                $platformGst['registered_address'],
                $request['total_amount'],
                $gst['cgst'],
                $gst['sgst'],
                $gst['igst'],
                $gst['total'],
                $request['grand_total'],
                getSetting('gst_sac_code', '998371')
            ]);
            
            return (int)$pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Generate Invoice Error: " . $e->getMessage());
            return null;
        }
    }
}

// ============================================
// FRAUD DETECTION FUNCTIONS (v3.0)
// ============================================

/**
 * Detect potential fraud
 */
if (!function_exists('detectFraud')) {
    function detectFraud(int $userId, string $activityType, string $description, string $severity = 'medium'): bool {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO suspicious_activities (user_id, activity_type, description, severity, status)
                VALUES (?, ?, ?, ?, 'pending')
            ");
            
            return $stmt->execute([$userId, $activityType, $description, $severity]);
            
        } catch (PDOException $e) {
            error_log("Detect Fraud Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Check if feature is enabled
 */
if (!function_exists('isFeatureEnabled')) {
    function isFeatureEnabled(string $featureKey, ?int $userId = null): bool {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT is_enabled, is_beta FROM feature_flags WHERE feature_key = ?");
            $stmt->execute([$featureKey]);
            $feature = $stmt->fetch();
            
            if (!$feature) return false;
            
            // If not enabled, return false
            if (!$feature['is_enabled']) return false;
            
            // If beta and user provided, check beta access
            if ($feature['is_beta'] && $userId) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM beta_users bu
                    JOIN feature_flags ff ON bu.feature_id = ff.id
                    WHERE ff.feature_key = ? AND bu.user_id = ?
                ");
                $stmt->execute([$featureKey, $userId]);
                return (int)$stmt->fetchColumn() > 0;
            }
            
            // If beta but no user, return false
            if ($feature['is_beta']) return false;
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Check Feature Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
