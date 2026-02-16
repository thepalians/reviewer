<?php
/**
 * Advanced Commission System Helper Functions
 * Phase 7: Advanced Automation & Intelligence Features
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Calculate commission for a task with bonuses
 */
function calculateTaskCommission($db, $user_id, $task_id, $base_commission) {
    try {
        $total_commission = $base_commission;
        $bonus_amount = 0;
        $bonus_types = [];
        $multiplier = 1.00;
        
        // Get commission tier
        $tier = getUserCommissionTier($db, $user_id);
        if ($tier) {
            $multiplier = $tier['base_multiplier'];
            $total_commission = $base_commission * $multiplier;
        }
        
        // Check for first task bonus
        $first_task_bonus = checkFirstTaskBonus($db, $user_id);
        if ($first_task_bonus) {
            $bonus_amount += $first_task_bonus['amount'];
            $bonus_types[] = 'first_task';
        }
        
        // Check for streak bonus
        $streak_bonus = checkStreakBonus($db, $user_id);
        if ($streak_bonus) {
            $bonus_amount += $streak_bonus['amount'];
            $bonus_types[] = 'streak';
        }
        
        // Check for speed bonus
        $speed_bonus = checkSpeedBonus($db, $task_id);
        if ($speed_bonus) {
            $bonus_amount += $speed_bonus['amount'];
            $bonus_types[] = 'speed';
        }
        
        $total_commission += $bonus_amount;
        
        return [
            'base_commission' => $base_commission,
            'multiplier' => $multiplier,
            'bonus_amount' => $bonus_amount,
            'bonus_types' => $bonus_types,
            'total_commission' => $total_commission
        ];
        
    } catch (Exception $e) {
        error_log("Error calculating commission: " . $e->getMessage());
        return [
            'base_commission' => $base_commission,
            'multiplier' => 1.00,
            'bonus_amount' => 0,
            'bonus_types' => [],
            'total_commission' => $base_commission
        ];
    }
}

/**
 * Get user's commission tier based on task count
 */
function getUserCommissionTier($db, $user_id) {
    try {
        // Get user's completed tasks in current month
        $stmt = $db->prepare("
            SELECT COUNT(*) as task_count 
            FROM tasks 
            WHERE user_id = ? 
            AND status = 'completed'
            AND MONTH(completed_at) = MONTH(CURRENT_DATE())
            AND YEAR(completed_at) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $task_count = $result['task_count'];
        
        // Get applicable tier
        $tier_stmt = $db->prepare("
            SELECT * FROM commission_tiers 
            WHERE is_active = 1 
            AND min_tasks <= ?
            AND (max_tasks IS NULL OR max_tasks >= ?)
            ORDER BY min_tasks DESC
            LIMIT 1
        ");
        $tier_stmt->execute([$task_count, $task_count]);
        return $tier_stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting commission tier: " . $e->getMessage());
        return null;
    }
}

/**
 * Check for first task of the day bonus
 */
function checkFirstTaskBonus($db, $user_id) {
    try {
        // Check if this is the first task completed today
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM tasks 
            WHERE user_id = ? 
            AND DATE(completed_at) = CURDATE()
            AND status = 'completed'
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 1) {
            // Get first task bonus
            $bonus_stmt = $db->prepare("
                SELECT * FROM commission_bonuses 
                WHERE bonus_type = 'first_task' 
                AND is_active = 1
                AND (valid_from IS NULL OR valid_from <= CURDATE())
                AND (valid_until IS NULL OR valid_until >= CURDATE())
                LIMIT 1
            ");
            $bonus_stmt->execute();
            $bonus = $bonus_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bonus) {
                return [
                    'amount' => $bonus['bonus_amount'] ?? 0,
                    'percentage' => $bonus['bonus_percentage'] ?? 0
                ];
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error checking first task bonus: " . $e->getMessage());
        return null;
    }
}

/**
 * Check for streak bonus (consecutive days)
 */
function checkStreakBonus($db, $user_id) {
    try {
        // Get current streak
        $streak = calculateUserStreak($db, $user_id);
        
        if ($streak >= 7) {
            $bonus_stmt = $db->prepare("
                SELECT * FROM commission_bonuses 
                WHERE bonus_type = 'streak' 
                AND is_active = 1
                AND JSON_EXTRACT(conditions, '$.min_days') <= ?
                AND (valid_from IS NULL OR valid_from <= CURDATE())
                AND (valid_until IS NULL OR valid_until >= CURDATE())
                ORDER BY JSON_EXTRACT(conditions, '$.min_days') DESC
                LIMIT 1
            ");
            $bonus_stmt->execute([$streak]);
            $bonus = $bonus_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bonus) {
                return [
                    'amount' => $bonus['bonus_amount'] ?? 0,
                    'percentage' => $bonus['bonus_percentage'] ?? 0,
                    'streak_days' => $streak
                ];
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error checking streak bonus: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate user's current streak
 */
function calculateUserStreak($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT DISTINCT DATE(completed_at) as completion_date 
            FROM tasks 
            WHERE user_id = ? 
            AND status = 'completed'
            AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY completion_date DESC
        ");
        $stmt->execute([$user_id]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $streak = 0;
        $expected_date = new DateTime();
        
        foreach ($dates as $date_str) {
            $date = new DateTime($date_str);
            $diff = $expected_date->diff($date)->days;
            
            if ($diff <= 1) {
                $streak++;
                $expected_date = $date;
                $expected_date->modify('-1 day');
            } else {
                break;
            }
        }
        
        return $streak;
    } catch (Exception $e) {
        error_log("Error calculating streak: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check for speed bonus (early completion)
 */
function checkSpeedBonus($db, $task_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                TIMESTAMPDIFF(HOUR, created_at, completed_at) as hours_taken,
                expected_hours
            FROM tasks 
            WHERE id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($task && $task['expected_hours']) {
            $time_saved = $task['expected_hours'] - $task['hours_taken'];
            
            if ($time_saved > 0) {
                $bonus_stmt = $db->prepare("
                    SELECT * FROM commission_bonuses 
                    WHERE bonus_type = 'speed' 
                    AND is_active = 1
                    AND (valid_from IS NULL OR valid_from <= CURDATE())
                    AND (valid_until IS NULL OR valid_until >= CURDATE())
                    LIMIT 1
                ");
                $bonus_stmt->execute();
                $bonus = $bonus_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($bonus) {
                    return [
                        'amount' => $bonus['bonus_amount'] ?? 0,
                        'percentage' => $bonus['bonus_percentage'] ?? 0,
                        'time_saved' => $time_saved
                    ];
                }
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error checking speed bonus: " . $e->getMessage());
        return null;
    }
}

/**
 * Record commission in history
 */
function recordCommission($db, $user_id, $task_id, $commission_data) {
    try {
        $stmt = $db->prepare("
            INSERT INTO user_commission_history 
            (user_id, task_id, base_commission, bonus_amount, bonus_type, total_commission, multiplier)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $bonus_type = !empty($commission_data['bonus_types']) 
            ? implode(',', $commission_data['bonus_types']) 
            : null;
        
        return $stmt->execute([
            $user_id,
            $task_id,
            $commission_data['base_commission'],
            $commission_data['bonus_amount'],
            $bonus_type,
            $commission_data['total_commission'],
            $commission_data['multiplier']
        ]);
    } catch (Exception $e) {
        error_log("Error recording commission: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's commission summary
 */
function getUserCommissionSummary($db, $user_id, $start_date = null, $end_date = null) {
    try {
        $where_clause = "WHERE user_id = ?";
        $params = [$user_id];
        
        if ($start_date && $end_date) {
            $where_clause .= " AND DATE(created_at) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(base_commission) as total_base,
                SUM(bonus_amount) as total_bonuses,
                SUM(total_commission) as total_earned,
                AVG(multiplier) as avg_multiplier
            FROM user_commission_history
            $where_clause
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting commission summary: " . $e->getMessage());
        return [];
    }
}

/**
 * Get commission breakdown by bonus type
 */
function getCommissionBreakdown($db, $user_id, $start_date = null, $end_date = null) {
    try {
        $where_clause = "WHERE user_id = ?";
        $params = [$user_id];
        
        if ($start_date && $end_date) {
            $where_clause .= " AND DATE(created_at) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        $stmt = $db->prepare("
            SELECT 
                bonus_type,
                COUNT(*) as count,
                SUM(bonus_amount) as total_bonus
            FROM user_commission_history
            $where_clause
            AND bonus_type IS NOT NULL
            GROUP BY bonus_type
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting commission breakdown: " . $e->getMessage());
        return [];
    }
}

/**
 * Get active commission tiers
 */
function getActiveCommissionTiers($db) {
    try {
        $stmt = $db->query("
            SELECT * FROM commission_tiers 
            WHERE is_active = 1 
            ORDER BY min_tasks ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting commission tiers: " . $e->getMessage());
        return [];
    }
}

/**
 * Get active commission bonuses
 */
function getActiveCommissionBonuses($db) {
    try {
        $stmt = $db->query("
            SELECT * FROM commission_bonuses 
            WHERE is_active = 1 
            AND (valid_from IS NULL OR valid_from <= CURDATE())
            AND (valid_until IS NULL OR valid_until >= CURDATE())
            ORDER BY bonus_type, name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting commission bonuses: " . $e->getMessage());
        return [];
    }
}
