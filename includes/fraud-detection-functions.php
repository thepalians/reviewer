<?php
/**
 * Fraud Detection System Helper Functions
 * Phase 7: Advanced Automation & Intelligence Features
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Calculate fraud score for a user
 */
function calculateFraudScore($db, $user_id) {
    try {
        $scores = [
            'ip_score' => calculateIPScore($db, $user_id),
            'device_score' => calculateDeviceScore($db, $user_id),
            'behavior_score' => calculateBehaviorScore($db, $user_id),
            'content_score' => calculateContentScore($db, $user_id),
            'velocity_score' => calculateVelocityScore($db, $user_id)
        ];
        
        // Calculate overall score (weighted average)
        $weights = [
            'ip_score' => 0.25,
            'device_score' => 0.20,
            'behavior_score' => 0.25,
            'content_score' => 0.15,
            'velocity_score' => 0.15
        ];
        
        $overall_score = 0;
        foreach ($scores as $key => $score) {
            $overall_score += $score * $weights[$key];
        }
        
        // Determine risk level
        $risk_level = determineRiskLevel($overall_score);
        
        // Collect flags
        $flags = [];
        if ($scores['ip_score'] > 70) $flags[] = 'high_ip_risk';
        if ($scores['device_score'] > 70) $flags[] = 'multiple_devices';
        if ($scores['behavior_score'] > 70) $flags[] = 'suspicious_behavior';
        if ($scores['content_score'] > 70) $flags[] = 'duplicate_content';
        if ($scores['velocity_score'] > 70) $flags[] = 'abnormal_velocity';
        
        // Update fraud score record
        $stmt = $db->prepare("
            INSERT INTO fraud_scores 
            (user_id, overall_score, ip_score, device_score, behavior_score, content_score, 
             velocity_score, risk_level, flags)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                overall_score = VALUES(overall_score),
                ip_score = VALUES(ip_score),
                device_score = VALUES(device_score),
                behavior_score = VALUES(behavior_score),
                content_score = VALUES(content_score),
                velocity_score = VALUES(velocity_score),
                risk_level = VALUES(risk_level),
                flags = VALUES(flags)
        ");
        
        $stmt->execute([
            $user_id,
            $overall_score,
            $scores['ip_score'],
            $scores['device_score'],
            $scores['behavior_score'],
            $scores['content_score'],
            $scores['velocity_score'],
            $risk_level,
            json_encode($flags)
        ]);
        
        // Create alerts for high-risk users
        if ($risk_level == 'high' || $risk_level == 'critical') {
            createFraudAlert($db, $user_id, 'high_risk_score', $risk_level, 
                'User has high fraud risk score', $scores);
        }
        
        return [
            'overall_score' => $overall_score,
            'risk_level' => $risk_level,
            'scores' => $scores,
            'flags' => $flags
        ];
        
    } catch (Exception $e) {
        error_log("Error calculating fraud score: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate IP-based fraud score
 */
function calculateIPScore($db, $user_id) {
    try {
        $score = 0;
        
        // Get user's IP addresses
        $stmt = $db->prepare("
            SELECT DISTINCT ip_address 
            FROM user_sessions 
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $ips = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ips)) {
            return 0;
        }
        
        foreach ($ips as $ip) {
            $intel = getIPIntelligence($db, $ip);
            
            if ($intel) {
                if ($intel['is_vpn']) $score += 20;
                if ($intel['is_proxy']) $score += 15;
                if ($intel['is_tor']) $score += 30;
                if ($intel['is_datacenter']) $score += 10;
                $score += $intel['risk_score'];
            }
        }
        
        // Check for shared IPs
        $shared_stmt = $db->prepare("
            SELECT COUNT(DISTINCT user_id) as user_count
            FROM user_sessions
            WHERE ip_address IN (" . str_repeat('?,', count($ips) - 1) . "?)
        ");
        $shared_stmt->execute($ips);
        $shared = $shared_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($shared['user_count'] > 5) {
            $score += 20;
        }
        
        return min($score, 100);
        
    } catch (Exception $e) {
        error_log("Error calculating IP score: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get or create IP intelligence data
 */
function getIPIntelligence($db, $ip_address) {
    try {
        $stmt = $db->prepare("SELECT * FROM ip_intelligence WHERE ip_address = ?");
        $stmt->execute([$ip_address]);
        $intel = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$intel || strtotime($intel['last_checked']) < strtotime('-7 days')) {
            // Fetch new data (in production, use an IP intelligence API)
            $intel = analyzeIPAddress($db, $ip_address);
        }
        
        return $intel;
    } catch (Exception $e) {
        error_log("Error getting IP intelligence: " . $e->getMessage());
        return null;
    }
}

/**
 * Analyze IP address (mock implementation)
 */
function analyzeIPAddress($db, $ip_address) {
    try {
        // In production, integrate with services like IPHub, IPQualityScore, etc.
        $is_vpn = 0;
        $is_proxy = 0;
        $is_tor = 0;
        $is_datacenter = 0;
        $risk_score = 0;
        
        // Mock analysis based on IP patterns
        if (preg_match('/^10\.|^192\.168\.|^172\.(1[6-9]|2\d|3[01])\./', $ip_address)) {
            // Private IP - low risk
            $risk_score = 5;
        }
        
        $stmt = $db->prepare("
            INSERT INTO ip_intelligence 
            (ip_address, is_vpn, is_proxy, is_tor, is_datacenter, risk_score)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                is_vpn = VALUES(is_vpn),
                is_proxy = VALUES(is_proxy),
                is_tor = VALUES(is_tor),
                is_datacenter = VALUES(is_datacenter),
                risk_score = VALUES(risk_score)
        ");
        
        $stmt->execute([$ip_address, $is_vpn, $is_proxy, $is_tor, $is_datacenter, $risk_score]);
        
        return [
            'ip_address' => $ip_address,
            'is_vpn' => $is_vpn,
            'is_proxy' => $is_proxy,
            'is_tor' => $is_tor,
            'is_datacenter' => $is_datacenter,
            'risk_score' => $risk_score
        ];
    } catch (Exception $e) {
        error_log("Error analyzing IP: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate device-based fraud score
 */
function calculateDeviceScore($db, $user_id) {
    try {
        $score = 0;
        
        // Count unique devices/user agents
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT user_agent) as device_count
            FROM user_sessions
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['device_count'] > 10) {
            $score += 40;
        } elseif ($result['device_count'] > 5) {
            $score += 20;
        }
        
        return min($score, 100);
    } catch (Exception $e) {
        error_log("Error calculating device score: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calculate behavior-based fraud score
 */
function calculateBehaviorScore($db, $user_id) {
    try {
        $score = 0;
        
        // Check for bot-like patterns
        $stmt = $db->prepare("
            SELECT 
                AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_completion,
                STDDEV(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as stddev_completion
            FROM tasks
            WHERE user_id = ?
            AND status = 'completed'
            AND completed_at IS NOT NULL
        ");
        $stmt->execute([$user_id]);
        $timing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Very consistent timing suggests automation
        if ($timing['stddev_completion'] < 10 && $timing['avg_completion'] < 300) {
            $score += 40;
        }
        
        // Check for abnormal activity hours
        $hours_stmt = $db->prepare("
            SELECT HOUR(created_at) as hour, COUNT(*) as count
            FROM tasks
            WHERE user_id = ?
            GROUP BY HOUR(created_at)
            ORDER BY count DESC
            LIMIT 1
        ");
        $hours_stmt->execute([$user_id]);
        $peak_hour = $hours_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($peak_hour && ($peak_hour['hour'] < 4 || $peak_hour['hour'] > 23)) {
            $score += 15;
        }
        
        return min($score, 100);
    } catch (Exception $e) {
        error_log("Error calculating behavior score: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calculate content-based fraud score
 */
function calculateContentScore($db, $user_id) {
    try {
        $score = 0;
        
        // Get user's review texts
        $stmt = $db->prepare("
            SELECT review_text 
            FROM tasks 
            WHERE user_id = ? 
            AND review_text IS NOT NULL
            ORDER BY completed_at DESC
            LIMIT 20
        ");
        $stmt->execute([$user_id]);
        $reviews = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($reviews) < 2) {
            return 0;
        }
        
        // Check for duplicate content
        $duplicates = 0;
        for ($i = 0; $i < count($reviews) - 1; $i++) {
            for ($j = $i + 1; $j < count($reviews); $j++) {
                $similarity = similar_text($reviews[$i], $reviews[$j], $percent);
                if ($percent > 80) {
                    $duplicates++;
                }
            }
        }
        
        if ($duplicates > 5) {
            $score += 60;
        } elseif ($duplicates > 2) {
            $score += 30;
        }
        
        return min($score, 100);
    } catch (Exception $e) {
        error_log("Error calculating content score: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calculate velocity-based fraud score
 */
function calculateVelocityScore($db, $user_id) {
    try {
        $score = 0;
        
        // Check tasks per day
        $stmt = $db->prepare("
            SELECT DATE(created_at) as task_date, COUNT(*) as task_count
            FROM tasks
            WHERE user_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY task_count DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $peak_day = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($peak_day && $peak_day['task_count'] > 50) {
            $score += 50;
        } elseif ($peak_day && $peak_day['task_count'] > 30) {
            $score += 30;
        }
        
        return min($score, 100);
    } catch (Exception $e) {
        error_log("Error calculating velocity score: " . $e->getMessage());
        return 0;
    }
}

/**
 * Determine risk level from score
 */
function determineRiskLevel($score) {
    if ($score >= 75) return 'critical';
    if ($score >= 50) return 'high';
    if ($score >= 25) return 'medium';
    return 'low';
}

/**
 * Create fraud alert
 */
function createFraudAlert($db, $user_id, $alert_type, $severity, $description, $evidence = []) {
    try {
        $stmt = $db->prepare("
            INSERT INTO fraud_alerts 
            (user_id, alert_type, severity, description, evidence)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $user_id,
            $alert_type,
            $severity,
            $description,
            json_encode($evidence)
        ]);
    } catch (Exception $e) {
        error_log("Error creating fraud alert: " . $e->getMessage());
        return false;
    }
}

/**
 * Get fraud alerts
 */
function getFraudAlerts($db, $status = 'new', $limit = 100) {
    try {
        $stmt = $db->prepare("
            SELECT 
                fa.*,
                u.name,
                u.email,
                u.phone
            FROM fraud_alerts fa
            JOIN users u ON fa.user_id = u.id
            WHERE fa.status = ?
            ORDER BY fa.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$status, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting fraud alerts: " . $e->getMessage());
        return [];
    }
}

/**
 * Update fraud alert status
 */
function updateFraudAlertStatus($db, $alert_id, $status, $reviewed_by) {
    try {
        $stmt = $db->prepare("
            UPDATE fraud_alerts 
            SET status = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$status, $reviewed_by, $alert_id]);
    } catch (Exception $e) {
        error_log("Error updating alert status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get high-risk users
 */
function getHighRiskUsers($db, $min_risk_level = 'high') {
    try {
        $risk_filter = $min_risk_level == 'high' 
            ? "risk_level IN ('high', 'critical')"
            : "risk_level = 'critical'";
        
        $stmt = $db->query("
            SELECT 
                fs.*,
                u.name,
                u.email,
                u.created_at as user_created
            FROM fraud_scores fs
            JOIN users u ON fs.user_id = u.id
            WHERE $risk_filter
            ORDER BY fs.overall_score DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting high-risk users: " . $e->getMessage());
        return [];
    }
}

/**
 * Run fraud detection for all active users
 */
function runBatchFraudDetection($db, $limit = 100) {
    try {
        $stmt = $db->prepare("
            SELECT id FROM users 
            WHERE status = 'active'
            ORDER BY RAND()
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $processed = 0;
        foreach ($users as $user_id) {
            calculateFraudScore($db, $user_id);
            $processed++;
        }
        
        return $processed;
    } catch (Exception $e) {
        error_log("Error running batch fraud detection: " . $e->getMessage());
        return 0;
    }
}
