<?php
declare(strict_types=1);

/**
 * Security Functions for Phase 4
 * Handles security logging, 2FA, and IP management
 */

/**
 * Log security event
 */
function logSecurityEvent($pdo, $user_id, $event_type, $details = '', $severity = 'low') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, details, severity)
            VALUES (:user_id, :event_type, :ip_address, :user_agent, :details, :severity)
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':event_type' => $event_type,
            ':ip_address' => $ip,
            ':user_agent' => $user_agent,
            ':details' => $details,
            ':severity' => $severity
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get security logs with filters
 */
function getSecurityLogs($pdo, $filters = [], $limit = 100) {
    try {
        $sql = "SELECT sl.*, u.name as user_name, u.email as user_email
                FROM security_logs sl
                LEFT JOIN users u ON sl.user_id = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND sl.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['event_type'])) {
            $sql .= " AND sl.event_type = :event_type";
            $params[':event_type'] = $filters['event_type'];
        }
        
        if (!empty($filters['severity'])) {
            $sql .= " AND sl.severity = :severity";
            $params[':severity'] = $filters['severity'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(sl.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(sl.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY sl.created_at DESC LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get security statistics
 */
function getSecurityStats($pdo, $days = 30) {
    try {
        $sql = "SELECT 
                    COUNT(*) as total_events,
                    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical,
                    SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium,
                    SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low
                FROM security_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_events' => 0, 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0
        ];
    } catch (PDOException $e) {
        return ['total_events' => 0, 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
    }
}

/**
 * Check if IP is blacklisted
 */
function isIPBlacklisted($pdo, $ip) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM ip_access_list
            WHERE ip_address = :ip 
            AND type = 'blacklist'
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([':ip' => $ip]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Add IP to blacklist
 */
function addIPToBlacklist($pdo, $ip, $reason, $admin_id, $expires_at = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO ip_access_list (ip_address, type, reason, created_by, expires_at)
            VALUES (:ip, 'blacklist', :reason, :admin_id, :expires_at)
        ");
        $stmt->execute([
            ':ip' => $ip,
            ':reason' => $reason,
            ':admin_id' => $admin_id,
            ':expires_at' => $expires_at
        ]);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to add IP to blacklist'];
    }
}

/**
 * Get IP access list
 */
function getIPAccessList($pdo, $type = null) {
    try {
        $sql = "SELECT i.*, u.name as created_by_name
                FROM ip_access_list i
                LEFT JOIN users u ON i.created_by = u.id
                WHERE 1=1";
        $params = [];
        
        if ($type) {
            $sql .= " AND i.type = :type";
            $params[':type'] = $type;
        }
        
        $sql .= " ORDER BY i.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Remove IP from access list
 */
function removeIPFromAccessList($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM ip_access_list WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to remove IP'];
    }
}

/**
 * Get suspicious activity summary
 */
function getSuspiciousActivity($pdo, $days = 7) {
    try {
        $sql = "SELECT 
                    event_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as affected_users
                FROM security_logs
                WHERE severity IN ('high', 'critical')
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY event_type
                ORDER BY count DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
