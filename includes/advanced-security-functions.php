<?php
declare(strict_types=1);

/**
 * Advanced Security Functions
 * IP Management, Session Tracking, Audit Logging
 */

/**
 * Check if IP is blacklisted
 */
function isIPBlacklisted(string $ipAddress): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM ip_blacklist 
            WHERE ip_address = ? 
            AND (is_permanent = 1 OR expires_at > NOW() OR expires_at IS NULL)
        ");
        $stmt->execute([$ipAddress]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking IP blacklist: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if IP is whitelisted
 */
function isIPWhitelisted(string $ipAddress): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM ip_whitelist 
            WHERE ip_address = ? AND is_active = 1 
            AND (expires_at > NOW() OR expires_at IS NULL)
        ");
        $stmt->execute([$ipAddress]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error checking IP whitelist: " . $e->getMessage());
        return false;
    }
}

/**
 * Add IP to blacklist
 */
function blacklistIP(string $ipAddress, string $reason, int $blockedBy, bool $isPermanent = false, ?string $expiresAt = null): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ip_blacklist (ip_address, reason, blocked_by, is_permanent, expires_at)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE reason = ?, blocked_by = ?, is_permanent = ?, expires_at = ?
        ");
        return $stmt->execute([
            $ipAddress, $reason, $blockedBy, $isPermanent ? 1 : 0, $expiresAt,
            $reason, $blockedBy, $isPermanent ? 1 : 0, $expiresAt
        ]);
    } catch (PDOException $e) {
        error_log("Error blacklisting IP: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove IP from blacklist
 */
function unblacklistIP(string $ipAddress): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM ip_blacklist WHERE ip_address = ?");
        return $stmt->execute([$ipAddress]);
    } catch (PDOException $e) {
        error_log("Error removing IP from blacklist: " . $e->getMessage());
        return false;
    }
}

/**
 * Add IP to whitelist
 */
function whitelistIP(string $ipAddress, string $description, int $addedBy, ?string $expiresAt = null): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ip_whitelist (ip_address, description, added_by, expires_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE description = ?, added_by = ?, expires_at = ?
        ");
        return $stmt->execute([
            $ipAddress, $description, $addedBy, $expiresAt,
            $description, $addedBy, $expiresAt
        ]);
    } catch (PDOException $e) {
        error_log("Error whitelisting IP: " . $e->getMessage());
        return false;
    }
}

/**
 * Track active session
 */
function trackSession(int $userId, string $sessionId, array $data = []): bool {
    global $pdo;
    
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $deviceType = detectDeviceType($userAgent);
        $location = $data['location'] ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO active_sessions 
            (user_id, session_id, ip_address, user_agent, device_type, location, last_activity)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            last_activity = NOW(), is_active = 1
        ");
        return $stmt->execute([
            $userId, $sessionId, $ipAddress, $userAgent, $deviceType, $location
        ]);
    } catch (PDOException $e) {
        error_log("Error tracking session: " . $e->getMessage());
        return false;
    }
}

/**
 * Get active sessions for a user
 */
function getActiveSessions(int $userId): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM active_sessions 
            WHERE user_id = ? AND is_active = 1 
            ORDER BY last_activity DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting active sessions: " . $e->getMessage());
        return [];
    }
}

/**
 * Terminate a session
 */
function terminateSession(int $sessionRecordId, int $userId): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE active_sessions 
            SET is_active = 0 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$sessionRecordId, $userId]);
    } catch (PDOException $e) {
        error_log("Error terminating session: " . $e->getMessage());
        return false;
    }
}

/**
 * Log audit event
 */
function logAudit(int $userId, string $action, string $module, array $data = []): bool {
    global $pdo;
    
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs 
            (user_id, action, module, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $userId,
            $action,
            $module,
            $data['entity_type'] ?? null,
            $data['entity_id'] ?? null,
            json_encode($data['old_values'] ?? []),
            json_encode($data['new_values'] ?? []),
            $ipAddress,
            $userAgent
        ]);
    } catch (PDOException $e) {
        error_log("Error logging audit: " . $e->getMessage());
        return false;
    }
}

/**
 * Get audit logs
 */
function getAuditLogs(array $filters = [], int $limit = 100, int $offset = 0): array {
    global $pdo;
    
    try {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['module'])) {
            $where[] = 'module = ?';
            $params[] = $filters['module'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare("
            SELECT a.*, u.name as user_name 
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting audit logs: " . $e->getMessage());
        return [];
    }
}

/**
 * Create login alert
 */
function createLoginAlert(int $userId, string $alertType, array $details = []): bool {
    global $pdo;
    
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO login_alerts 
            (user_id, alert_type, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([
            $userId,
            $alertType,
            json_encode($details),
            $ipAddress
        ]);
    } catch (PDOException $e) {
        error_log("Error creating login alert: " . $e->getMessage());
        return false;
    }
}

/**
 * Get login alerts for a user
 */
function getLoginAlerts(int $userId, bool $unreadOnly = false): array {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM login_alerts WHERE user_id = ?";
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting login alerts: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark login alert as read
 */
function markAlertAsRead(int $alertId, int $userId): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE login_alerts 
            SET is_read = 1 
            WHERE id = ? AND user_id = ?
        ");
        return $stmt->execute([$alertId, $userId]);
    } catch (PDOException $e) {
        error_log("Error marking alert as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Detect device type from user agent
 */
function detectDeviceType(string $userAgent): string {
    if (preg_match('/mobile|android|iphone|ipad|phone/i', $userAgent)) {
        return 'mobile';
    } elseif (preg_match('/tablet/i', $userAgent)) {
        return 'tablet';
    }
    return 'desktop';
}

/**
 * Detect suspicious activity
 */
function detectSuspiciousActivity(int $userId): array {
    global $pdo;
    $suspiciousActivities = [];
    
    try {
        // Check for multiple failed login attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM login_alerts 
            WHERE user_id = ? 
            AND alert_type = 'failed_attempt' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$userId]);
        $failedAttempts = $stmt->fetchColumn();
        
        if ($failedAttempts >= 5) {
            $suspiciousActivities[] = [
                'type' => 'multiple_failed_logins',
                'severity' => 'high',
                'count' => $failedAttempts
            ];
        }
        
        // Check for logins from multiple locations
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ip_address) 
            FROM active_sessions 
            WHERE user_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$userId]);
        $locations = $stmt->fetchColumn();
        
        if ($locations >= 3) {
            $suspiciousActivities[] = [
                'type' => 'multiple_locations',
                'severity' => 'medium',
                'count' => $locations
            ];
        }
        
        return $suspiciousActivities;
    } catch (PDOException $e) {
        error_log("Error detecting suspicious activity: " . $e->getMessage());
        return [];
    }
}

/**
 * Get IP management statistics
 */
function getIPStats(): array {
    global $pdo;
    
    try {
        $stats = [];
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM ip_blacklist WHERE is_permanent = 1");
        $stats['permanent_blacklist'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM ip_blacklist WHERE is_permanent = 0 AND expires_at > NOW()");
        $stats['temporary_blacklist'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM ip_whitelist WHERE is_active = 1");
        $stats['whitelist'] = $stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting IP stats: " . $e->getMessage());
        return [];
    }
}
