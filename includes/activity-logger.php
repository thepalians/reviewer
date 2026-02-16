<?php
// Activity Logger Functions

function logUserActivity($db, $user_id, $action, $description = '', $metadata = []) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $metadata_json = !empty($metadata) ? json_encode($metadata) : null;
        
        $stmt = $db->prepare("
            INSERT INTO user_activity_logs (user_id, action, description, ip_address, user_agent, metadata, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent, $metadata_json]);
        
        // Update last activity
        $stmt = $db->prepare("UPDATE users SET last_activity_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        
        return true;
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}

function logLoginAttempt($db, $user_id, $status = 'success', $failure_reason = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Parse user agent for device and browser info
        $device_type = 'desktop';
        $browser = 'unknown';
        
        if (preg_match('/mobile/i', $user_agent)) {
            $device_type = 'mobile';
        } elseif (preg_match('/tablet/i', $user_agent)) {
            $device_type = 'tablet';
        }
        
        if (preg_match('/Chrome/i', $user_agent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $user_agent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $user_agent)) {
            $browser = 'Safari';
        }
        
        $stmt = $db->prepare("
            INSERT INTO login_history (user_id, ip_address, user_agent, device_type, browser, status, failure_reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $ip_address, $user_agent, $device_type, $browser, $status, $failure_reason]);
        
        return true;
    } catch (Exception $e) {
        error_log("Login Log Error: " . $e->getMessage());
        return false;
    }
}

function getUserActivity($db, $user_id, $limit = 50) {
    $stmt = $db->prepare("
        SELECT * FROM user_activity_logs
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserLoginHistory($db, $user_id, $limit = 20) {
    $stmt = $db->prepare("
        SELECT * FROM login_history
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getActivityStats($db, $user_id = null) {
    if ($user_id) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_activities,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                COUNT(DISTINCT action) as unique_actions
            FROM user_activity_logs
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_activities,
                COUNT(DISTINCT user_id) as active_users,
                COUNT(DISTINCT DATE(created_at)) as active_days
            FROM user_activity_logs
        ");
    }
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserLevel($db, $user_id) {
    // Get user stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT t.id) as total_tasks,
            SUM(t.amount) as total_revenue,
            AVG(t.rating) as avg_rating
        FROM tasks t
        WHERE t.user_id = ? AND t.status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Determine level based on criteria
    $stmt = $db->query("
        SELECT * FROM user_levels
        WHERE is_active = 1
        ORDER BY sort_order DESC
    ");
    $levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $current_level = 'Bronze';
    foreach ($levels as $level) {
        if (($stats['total_tasks'] >= $level['min_tasks']) &&
            ($stats['total_revenue'] >= $level['min_revenue']) &&
            ($stats['avg_rating'] >= $level['min_rating'])) {
            $current_level = $level['level_name'];
            break;
        }
    }
    
    return $current_level;
}

function updateUserLevel($db, $user_id) {
    $new_level = getUserLevel($db, $user_id);
    $stmt = $db->prepare("UPDATE users SET user_level = ? WHERE id = ?");
    $stmt->execute([$new_level, $user_id]);
    
    logUserActivity($db, $user_id, 'level_update', "Level upgraded to {$new_level}");
    
    return $new_level;
}

function suspendUser($db, $user_id, $reason, $duration_days = null) {
    try {
        $until = $duration_days ? date('Y-m-d H:i:s', strtotime("+{$duration_days} days")) : null;
        
        $stmt = $db->prepare("
            UPDATE users 
            SET account_status = 'suspended',
                suspension_reason = ?,
                suspended_until = ?
            WHERE id = ?
        ");
        $stmt->execute([$reason, $until, $user_id]);
        
        logUserActivity($db, $user_id, 'account_suspended', "Account suspended: {$reason}");
        
        return ['success' => true, 'message' => 'User suspended successfully'];
    } catch (Exception $e) {
        error_log("User Suspension Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to suspend user'];
    }
}

function reactivateUser($db, $user_id) {
    try {
        $stmt = $db->prepare("
            UPDATE users 
            SET account_status = 'active',
                suspension_reason = NULL,
                suspended_until = NULL
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        
        logUserActivity($db, $user_id, 'account_reactivated', 'Account reactivated by admin');
        
        return ['success' => true, 'message' => 'User reactivated successfully'];
    } catch (Exception $e) {
        error_log("User Reactivation Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to reactivate user'];
    }
}
