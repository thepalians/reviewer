<?php
/**
 * ReviewFlow - Security Middleware
 * Handles authentication, CSRF, XSS protection, and security headers
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('APP_NAME')) {
    die('Direct access not permitted');
}

/**
 * Security Class - Core security functions
 */
class Security {
    
    /**
     * Initialize security measures
     */
    public static function init(): void {
        // Set secure session settings
        self::configureSession();
        
        // Set security headers
        self::setSecurityHeaders();
        
        // Prevent clickjacking
        self::preventClickjacking();
        
        // Check for session hijacking
        self::validateSession();
        
        // Regenerate session periodically
        self::regenerateSession();
    }
    
    /**
     * Configure secure session settings
     */
    private static function configureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session settings
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.gc_maxlifetime', '3600');
            
            session_name('REVIEWFLOW_SESS');
        }
    }
    
    /**
     * Set security headers
     */
    private static function setSecurityHeaders(): void {
        // Prevent XSS attacks
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://wa.me; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'");
        
        // Permissions Policy
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
        
        // HSTS (only on HTTPS)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Prevent clickjacking attacks
     */
    private static function preventClickjacking(): void {
        // Already handled in setSecurityHeaders with X-Frame-Options
    }
    
    /**
     * Validate session to prevent hijacking
     */
    private static function validateSession(): void {
        if (!isset($_SESSION)) return;
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ip = self::getClientIP();
        
        // Store fingerprint on first visit
        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = self::generateFingerprint($user_agent, $ip);
            $_SESSION['_created'] = time();
            $_SESSION['_last_activity'] = time();
            return;
        }
        
        // Validate fingerprint (user agent only, IP can change)
        $current_fingerprint = md5($user_agent);
        $stored_fingerprint = md5(explode('|', $_SESSION['_fingerprint'])[0] ?? '');
        
        if ($current_fingerprint !== $stored_fingerprint) {
            // Possible session hijacking
            self::destroySession();
            return;
        }
        
        // Check session timeout
        $timeout = (int)getSetting('session_timeout', 3600);
        if (time() - ($_SESSION['_last_activity'] ?? 0) > $timeout) {
            self::destroySession();
            return;
        }
        
        // Update last activity
        $_SESSION['_last_activity'] = time();
    }
    
    /**
     * Generate session fingerprint
     */
    private static function generateFingerprint(string $user_agent, string $ip): string {
        return $user_agent . '|' . $ip;
    }
    
    /**
     * Regenerate session ID periodically
     */
    private static function regenerateSession(): void {
        if (!isset($_SESSION['_created'])) return;
        
        // Regenerate every 30 minutes
        if (time() - $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }
    
    /**
     * Destroy session securely
     */
    public static function destroySession(): void {
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        session_destroy();
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIP(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateToken(): string {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
            time() - $_SESSION['csrf_token_time'] > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyToken(?string $token): bool {
        if (!$token || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token input field
     */
    public static function csrfField(): string {
        return '<input type="hidden" name="csrf_token" value="' . self::generateToken() . '">';
    }
    
    /**
     * Validate request method
     */
    public static function validateMethod(array $allowed = ['GET', 'POST']): bool {
        return in_array($_SERVER['REQUEST_METHOD'], $allowed);
    }
    
    /**
     * Sanitize input string for display
     * Note: For database operations, use prepared statements instead
     */
    public static function sanitize(string $input): string {
        $input = trim($input);
        // Remove stripslashes - not needed with magic_quotes disabled (PHP 5.4+)
        // stripslashes can corrupt legitimate data
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return $input;
    }
    
    /**
     * Sanitize array recursively
     */
    public static function sanitizeArray(array $data): array {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $key = self::sanitize((string)$key);
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value);
            } else {
                $sanitized[$key] = self::sanitize((string)$value);
            }
        }
        return $sanitized;
    }
    
    /**
     * Escape output
     */
    public static function escape(?string $string): string {
        if ($string === null) return '';
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public static function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate URL
     */
    public static function isValidURL(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate phone (Indian)
     */
    public static function isValidPhone(string $phone): bool {
        return preg_match('/^[6-9]\d{9}$/', $phone) === 1;
    }
    
    /**
     * Hash password
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate secure random string
     */
    public static function randomString(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Generate OTP
     */
    public static function generateOTP(int $length = 6): string {
        return str_pad((string)random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Check if request is from same origin
     */
    public static function isSameOrigin(): bool {
        if (empty($_SERVER['HTTP_REFERER'])) {
            return false;
        }
        
        $referer = parse_url($_SERVER['HTTP_REFERER']);
        $server = parse_url(APP_URL);
        
        return ($referer['host'] ?? '') === ($server['host'] ?? '');
    }
    
    /**
     * Rate limiting
     */
    public static function rateLimit(string $key, int $maxAttempts = 5, int $decayMinutes = 15): bool {
        return checkRateLimit($key, $maxAttempts, $decayMinutes);
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, array $data = []): void {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO security_logs (event, ip_address, user_agent, user_id, data, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $event,
                self::getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $_SESSION['user_id'] ?? null,
                json_encode($data)
            ]);
        } catch (PDOException $e) {
            error_log("Security Log Error: " . $e->getMessage());
        }
    }
    
    /**
     * Check for suspicious activity
     */
    public static function checkSuspiciousActivity(): bool {
        global $pdo;
        
        $ip = self::getClientIP();
        
        try {
            // Check failed logins
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM login_history 
                WHERE ip_address = ? AND status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$ip]);
            
            if ($stmt->fetchColumn() > 10) {
                self::logSecurityEvent('suspicious_activity', ['reason' => 'too_many_failed_logins']);
                return true;
            }
            
            // Check rate limit violations
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM rate_limits WHERE identifier LIKE ?
            ");
            $stmt->execute(["%$ip%"]);
            
            if ($stmt->fetchColumn() > 20) {
                self::logSecurityEvent('suspicious_activity', ['reason' => 'rate_limit_violations']);
                return true;
            }
            
        } catch (PDOException $e) {
            error_log("Suspicious Activity Check Error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Block IP address
     */
    public static function blockIP(string $ip, string $reason = '', int $hours = 24): bool {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO blocked_ips (ip_address, reason, blocked_until, created_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), NOW())
                ON DUPLICATE KEY UPDATE reason = VALUES(reason), blocked_until = VALUES(blocked_until)
            ");
            $stmt->execute([$ip, $reason, $hours]);
            
            self::logSecurityEvent('ip_blocked', ['ip' => $ip, 'reason' => $reason, 'hours' => $hours]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Block IP Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if IP is blocked
     */
    public static function isIPBlocked(): bool {
        global $pdo;
        
        $ip = self::getClientIP();
        
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM blocked_ips 
                WHERE ip_address = ? AND blocked_until > NOW()
            ");
            $stmt->execute([$ip]);
            
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Encrypt data
     */
    public static function encrypt(string $data): string {
        $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_key_change_this';
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data
     */
    public static function decrypt(string $data): string {
        $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_key_change_this';
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Sanitize filename
     */
    public static function sanitizeFilename(string $filename): string {
        // Remove path components
        $filename = basename($filename);
        
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Prevent directory traversal
        $filename = str_replace(['..', '/', '\\'], '', $filename);
        
        return $filename;
    }
    
    /**
     * Validate file upload
     */
    public static function validateUpload(array $file, array $options = []): array {
        $errors = [];
        
        $max_size = $options['max_size'] ?? 5 * 1024 * 1024; // 5MB default
        $allowed_types = $options['allowed_types'] ?? ['image/jpeg', 'image/png', 'image/gif'];
        $allowed_extensions = $options['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'gif'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed';
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors[] = 'File size exceeds limit';
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed_types)) {
            $errors[] = 'File type not allowed';
        }
        
        // Check extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions)) {
            $errors[] = 'File extension not allowed';
        }
        
        // Additional checks for images
        if (strpos($mime, 'image/') === 0) {
            $image_info = @getimagesize($file['tmp_name']);
            if ($image_info === false) {
                $errors[] = 'Invalid image file';
            }
        }
        
        return $errors;
    }
}

/**
 * Authentication Guard
 */
class AuthGuard {
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Check if admin is logged in
     */
    public static function isAdmin(): bool {
        return isset($_SESSION['admin_name']) && !empty($_SESSION['admin_name']);
    }
    
    /**
     * Require user login
     */
    public static function requireUser(): void {
        if (!self::isLoggedIn()) {
            if (Security::isAjax()) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
    }
    
    /**
     * Require admin login
     */
    public static function requireAdmin(): void {
        if (!self::isAdmin()) {
            if (Security::isAjax()) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
            header('Location: ' . ADMIN_URL);
            exit;
        }
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId(): ?int {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
    
    /**
     * Get current user
     */
    public static function getUser(): ?array {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return getUserById((int)$_SESSION['user_id']);
    }
    
    /**
     * Check if user has active status
     */
    public static function isUserActive(): bool {
        $user = self::getUser();
        return $user && $user['status'] === 'active';
    }
    
    /**
     * Logout user
     */
    public static function logout(): void {
        // Clear remember me cookies
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
            setcookie('remember_user', '', time() - 3600, '/');
        }
        
        // Destroy session
        Security::destroySession();
    }
}

/**
 * Input Validator
 */
class Validator {
    private $errors = [];
    private $data = [];
    
    public function __construct(array $data) {
        $this->data = $data;
    }
    
    /**
     * Validate required field
     */
    public function required(string $field, string $message = ''): self {
        if (empty($this->data[$field])) {
            $this->errors[$field] = $message ?: "$field is required";
        }
        return $this;
    }
    
    /**
     * Validate email
     */
    public function email(string $field, string $message = ''): self {
        if (!empty($this->data[$field]) && !Security::isValidEmail($this->data[$field])) {
            $this->errors[$field] = $message ?: "Invalid email address";
        }
        return $this;
    }
    
    /**
     * Validate phone
     */
    public function phone(string $field, string $message = ''): self {
        if (!empty($this->data[$field]) && !Security::isValidPhone($this->data[$field])) {
            $this->errors[$field] = $message ?: "Invalid phone number";
        }
        return $this;
    }
    
    /**
     * Validate minimum length
     */
    public function minLength(string $field, int $min, string $message = ''): self {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) < $min) {
            $this->errors[$field] = $message ?: "$field must be at least $min characters";
        }
        return $this;
    }
    
    /**
     * Validate maximum length
     */
    public function maxLength(string $field, int $max, string $message = ''): self {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) > $max) {
            $this->errors[$field] = $message ?: "$field must not exceed $max characters";
        }
        return $this;
    }
    
    /**
     * Validate match
     */
    public function match(string $field1, string $field2, string $message = ''): self {
        if (($this->data[$field1] ?? '') !== ($this->data[$field2] ?? '')) {
            $this->errors[$field1] = $message ?: "$field1 and $field2 must match";
        }
        return $this;
    }
    
    /**
     * Validate numeric
     */
    public function numeric(string $field, string $message = ''): self {
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?: "$field must be numeric";
        }
        return $this;
    }
    
    /**
     * Validate URL
     */
    public function url(string $field, string $message = ''): self {
        if (!empty($this->data[$field]) && !Security::isValidURL($this->data[$field])) {
            $this->errors[$field] = $message ?: "Invalid URL";
        }
        return $this;
    }
    
    /**
     * Custom validation
     */
    public function custom(string $field, callable $callback, string $message = ''): self {
        if (!empty($this->data[$field]) && !$callback($this->data[$field])) {
            $this->errors[$field] = $message ?: "$field is invalid";
        }
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails(): bool {
        return !empty($this->errors);
    }
    
    /**
     * Get errors
     */
    public function errors(): array {
        return $this->errors;
    }
    
    /**
     * Get first error
     */
    public function firstError(): ?string {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
    
    /**
     * Get validated data
     */
    public function validated(): array {
        return Security::sanitizeArray($this->data);
    }
}

// Initialize security on include
Security::init();

// Check if IP is blocked
if (Security::isIPBlocked()) {
    http_response_code(403);
    die('Access denied. Your IP has been blocked.');
}

// Helper functions for backward compatibility
if (!function_exists('sanitizeInput')) {
    function sanitizeInput(string $input): string {
        return Security::sanitize($input);
    }
}

if (!function_exists('escape')) {
    function escape(?string $string): string {
        return Security::escape($string);
    }
}

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken(): string {
        return Security::generateToken();
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken(?string $token): bool {
        return Security::verifyToken($token);
    }
}

if (!function_exists('generateRandomString')) {
    function generateRandomString(int $length = 32): string {
        return Security::randomString($length);
    }
}

if (!function_exists('hashPassword')) {
    function hashPassword(string $password): string {
        return Security::hashPassword($password);
    }
}

if (!function_exists('verifyPassword')) {
    function verifyPassword(string $password, string $hash): bool {
        return Security::verifyPassword($password, $hash);
    }
}

if (!function_exists('isValidEmail')) {
    function isValidEmail(string $email): bool {
        return Security::isValidEmail($email);
    }
}

if (!function_exists('isValidPhone')) {
    function isValidPhone(string $phone): bool {
        return Security::isValidPhone($phone);
    }
}
?>
