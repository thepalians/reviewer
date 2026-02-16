<?php
/**
 * Two-Factor Authentication (2FA) Functions
 * Handles TOTP, SMS OTP, backup codes, and trusted devices
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Generate a random secret key for TOTP
 * 
 * @return string 32-character secret key
 */
function generate2FASecret(): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 characters
    $secret = '';
    for ($i = 0; $i < 32; $i++) {
        $secret .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $secret;
}

/**
 * Generate backup codes for 2FA recovery
 * 
 * @param int $count Number of codes to generate
 * @return array Array of backup codes
 */
function generateBackupCodes(int $count = 10): array {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        // Generate 8-digit code
        $code = sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
        $codes[] = $code;
    }
    return $codes;
}

/**
 * Create QR code URL for Google Authenticator
 * 
 * @param string $secret
 * @param string $email
 * @param string $issuer
 * @return string QR code URL
 */
function get2FAQRCodeUrl(string $secret, string $email, string $issuer = 'ReviewFlow'): string {
    $encodedIssuer = rawurlencode($issuer);
    $encodedEmail = rawurlencode($email);
    
    $otpauthUrl = "otpauth://totp/{$encodedIssuer}:{$encodedEmail}?secret={$secret}&issuer={$encodedIssuer}";
    
    // Using Google Charts API for QR code generation
    $qrCodeUrl = "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($otpauthUrl);
    
    return $qrCodeUrl;
}

/**
 * Verify TOTP code
 * 
 * @param string $secret
 * @param string $code
 * @param int $window Time window for validation (default 1 = 30s before/after)
 * @return bool
 */
function verifyTOTP(string $secret, string $code, int $window = 1): bool {
    $code = str_replace(' ', '', $code);
    
    // Get current time-based counter
    $time = floor(time() / 30);
    
    // Check current time and windows before/after
    for ($i = -$window; $i <= $window; $i++) {
        $calculatedCode = getTOTPCode($secret, $time + $i);
        if (hash_equals($calculatedCode, $code)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate TOTP code for a given time
 * 
 * @param string $secret
 * @param int $time
 * @return string 6-digit code
 */
function getTOTPCode(string $secret, int $time): string {
    // Decode Base32 secret
    $secret = base32_decode($secret);
    
    // Pack time as binary
    $time = pack('N*', 0) . pack('N*', $time);
    
    // Generate HMAC hash
    $hash = hash_hmac('sha1', $time, $secret, true);
    
    // Dynamic truncation
    $offset = ord($hash[19]) & 0xf;
    $code = (
        ((ord($hash[$offset + 0]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

/**
 * Decode Base32 string
 * 
 * @param string $secret
 * @return string
 */
function base32_decode(string $secret): string {
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32charsFlipped = array_flip(str_split($base32chars));
    
    $paddingCharCount = substr_count($secret, '=');
    $allowedValues = [6, 4, 3, 1, 0];
    
    if (!in_array($paddingCharCount, $allowedValues)) {
        return '';
    }
    
    for ($i = 0; $i < 4; $i++) {
        if ($paddingCharCount == $allowedValues[$i] && 
            substr($secret, -($allowedValues[$i])) != str_repeat('=', $allowedValues[$i])) {
            return '';
        }
    }
    
    $secret = str_replace('=', '', $secret);
    $secret = str_split($secret);
    $binaryString = '';
    
    for ($i = 0; $i < count($secret); $i = $i + 8) {
        $x = '';
        if (!in_array($secret[$i], $base32charsFlipped)) {
            return '';
        }
        
        for ($j = 0; $j < 8; $j++) {
            $x .= str_pad(base_convert(@$base32charsFlipped[@$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
        }
        
        $eightBits = str_split($x, 8);
        
        for ($z = 0; $z < count($eightBits); $z++) {
            $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
        }
    }
    
    return $binaryString;
}

/**
 * Enable 2FA for a user
 * 
 * @param int $userId
 * @param string $secret
 * @param string $method
 * @param string|null $phoneNumber
 * @return bool
 */
function enable2FA(int $userId, string $secret, string $method = 'totp', ?string $phoneNumber = null): bool {
    global $conn;
    
    $backupCodes = generateBackupCodes(10);
    $backupCodesJson = json_encode(array_map(function($code) {
        return ['code' => $code, 'used' => false];
    }, $backupCodes));
    
    $stmt = $conn->prepare("
        INSERT INTO two_factor_auth 
        (user_id, secret_key, backup_codes, is_enabled, method, phone_number)
        VALUES (?, ?, ?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE
        secret_key = VALUES(secret_key),
        backup_codes = VALUES(backup_codes),
        is_enabled = 1,
        method = VALUES(method),
        phone_number = VALUES(phone_number),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->bind_param('issss', $userId, $secret, $backupCodesJson, $method, $phoneNumber);
    
    return $stmt->execute();
}

/**
 * Disable 2FA for a user
 * 
 * @param int $userId
 * @return bool
 */
function disable2FA(int $userId): bool {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE two_factor_auth 
        SET is_enabled = 0, updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ?
    ");
    
    $stmt->bind_param('i', $userId);
    
    return $stmt->execute();
}

/**
 * Check if user has 2FA enabled
 * 
 * @param int $userId
 * @return bool
 */
function is2FAEnabled(int $userId): bool {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT is_enabled FROM two_factor_auth WHERE user_id = ?
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return (bool)$row['is_enabled'];
    }
    
    return false;
}

/**
 * Get 2FA settings for a user
 * 
 * @param int $userId
 * @return array|null
 */
function get2FASettings(int $userId): ?array {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM two_factor_auth WHERE user_id = ?
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $row['backup_codes'] = json_decode($row['backup_codes'], true);
        return $row;
    }
    
    return null;
}

/**
 * Verify backup code
 * 
 * @param int $userId
 * @param string $code
 * @return bool
 */
function verifyBackupCode(int $userId, string $code): bool {
    global $conn;
    
    $settings = get2FASettings($userId);
    if (!$settings) {
        return false;
    }
    
    $backupCodes = $settings['backup_codes'];
    $codeFound = false;
    
    foreach ($backupCodes as &$backupCode) {
        if ($backupCode['code'] === $code && !$backupCode['used']) {
            $backupCode['used'] = true;
            $codeFound = true;
            break;
        }
    }
    
    if ($codeFound) {
        // Update backup codes
        $backupCodesJson = json_encode($backupCodes);
        $stmt = $conn->prepare("
            UPDATE two_factor_auth 
            SET backup_codes = ?, updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmt->bind_param('si', $backupCodesJson, $userId);
        $stmt->execute();
        
        return true;
    }
    
    return false;
}

/**
 * Add a trusted device
 * 
 * @param int $userId
 * @param string $deviceName
 * @param int $expiryDays
 * @return bool
 */
function addTrustedDevice(int $userId, string $deviceName, int $expiryDays = 30): bool {
    global $conn;
    
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $deviceHash = hash('sha256', $userId . $userAgent . $ipAddress . time());
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
    
    $stmt = $conn->prepare("
        INSERT INTO trusted_devices 
        (user_id, device_hash, device_name, ip_address, user_agent, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param('isssss', $userId, $deviceHash, $deviceName, $ipAddress, $userAgent, $expiresAt);
    
    return $stmt->execute();
}

/**
 * Check if current device is trusted
 * 
 * @param int $userId
 * @return bool
 */
function isTrustedDevice(int $userId): bool {
    global $conn;
    
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM trusted_devices 
        WHERE user_id = ? 
        AND ip_address = ? 
        AND user_agent = ?
        AND expires_at > NOW()
    ");
    
    $stmt->bind_param('iss', $userId, $ipAddress, $userAgent);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] > 0;
}

/**
 * Remove expired trusted devices
 * 
 * @return int Number of devices removed
 */
function cleanupExpiredDevices(): int {
    global $conn;
    
    $result = $conn->query("
        DELETE FROM trusted_devices WHERE expires_at < NOW()
    ");
    
    return $conn->affected_rows;
}

/**
 * Get trusted devices for a user
 * 
 * @param int $userId
 * @return array
 */
function getTrustedDevices(int $userId): array {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM trusted_devices 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $devices = [];
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
    
    return $devices;
}

/**
 * Remove a trusted device
 * 
 * @param int $deviceId
 * @param int $userId
 * @return bool
 */
function removeTrustedDevice(int $deviceId, int $userId): bool {
    global $conn;
    
    $stmt = $conn->prepare("
        DELETE FROM trusted_devices WHERE id = ? AND user_id = ?
    ");
    
    $stmt->bind_param('ii', $deviceId, $userId);
    
    return $stmt->execute();
}

/**
 * Update last 2FA usage time
 * 
 * @param int $userId
 * @return bool
 */
function update2FALastUsed(int $userId): bool {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE two_factor_auth 
        SET last_used_at = NOW()
        WHERE user_id = ?
    ");
    
    $stmt->bind_param('i', $userId);
    
    return $stmt->execute();
}
