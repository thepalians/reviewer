<?php
/**
 * Auto Task Assignment System Helper Functions
 * Phase 7: Advanced Automation & Intelligence Features
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Calculate user assignment score based on multiple factors
 */
function calculateAssignmentScore($db, $user_id, $task_data) {
    $score = 0;
    $factors = [];
    
    try {
        // Get user data
        $stmt = $db->prepare("
            SELECT u.*, up.level, up.points, up.total_earned 
            FROM users u 
            LEFT JOIN user_points up ON u.id = up.user_id 
            WHERE u.id = ? AND u.status = 'active'
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return 0;
        }
        
        // Factor 1: User Level/Tier (0-25 points)
        $level_scores = [
            'Bronze' => 5,
            'Silver' => 10,
            'Gold' => 15,
            'Platinum' => 20,
            'Diamond' => 25
        ];
        $level_score = $level_scores[$user['level']] ?? 5;
        $score += $level_score;
        $factors['level'] = $level_score;
        
        // Factor 2: Past Performance (0-25 points)
        $performance_score = calculatePerformanceScore($db, $user_id);
        $score += $performance_score;
        $factors['performance'] = $performance_score;
        
        // Factor 3: Availability (0-20 points)
        $availability_score = checkUserAvailability($db, $user_id);
        $score += $availability_score;
        $factors['availability'] = $availability_score;
        
        // Factor 4: Workload Balance (0-20 points)
        $workload_score = calculateWorkloadScore($db, $user_id);
        $score += $workload_score;
        $factors['workload'] = $workload_score;
        
        // Factor 5: Category Expertise (0-10 points)
        if (isset($task_data['category_id'])) {
            $expertise_score = calculateCategoryExpertise($db, $user_id, $task_data['category_id']);
            $score += $expertise_score;
            $factors['expertise'] = $expertise_score;
        }
        
        return [
            'score' => $score,
            'factors' => $factors
        ];
        
    } catch (Exception $e) {
        error_log("Error calculating assignment score: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calculate user performance score
 */
function calculatePerformanceScore($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                AVG(CASE WHEN rating > 0 THEN rating ELSE NULL END) as avg_rating
            FROM tasks 
            WHERE user_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats['total_tasks'] == 0) {
            return 15; // Default score for new users
        }
        
        $completion_rate = $stats['completed_tasks'] / $stats['total_tasks'];
        $rating_score = ($stats['avg_rating'] ?? 4) / 5; // Normalize to 0-1
        
        // Weighted score: 60% completion rate, 40% rating
        $score = ($completion_rate * 0.6 + $rating_score * 0.4) * 25;
        
        return round($score, 2);
        
    } catch (Exception $e) {
        error_log("Error calculating performance score: " . $e->getMessage());
        return 15;
    }
}

/**
 * Check user availability
 */
function checkUserAvailability($db, $user_id) {
    try {
        // Get current day of week (0 = Sunday, 6 = Saturday)
        $day_of_week = date('w');
        $current_time = date('H:i:s');
        
        $stmt = $db->prepare("
            SELECT * FROM user_availability 
            WHERE user_id = ? 
            AND day_of_week = ? 
            AND is_available = 1
            AND start_time <= ? 
            AND end_time >= ?
        ");
        $stmt->execute([$user_id, $day_of_week, $current_time, $current_time]);
        
        if ($stmt->fetch()) {
            return 20; // User is available now
        }
        
        return 10; // User might be available later
        
    } catch (Exception $e) {
        error_log("Error checking availability: " . $e->getMessage());
        return 15;
    }
}

/**
 * Calculate workload balance score
 */
function calculateWorkloadScore($db, $user_id) {
    try {
        // Get user preferences
        $pref_stmt = $db->prepare("
            SELECT max_daily_tasks FROM user_assignment_preferences 
            WHERE user_id = ?
        ");
        $pref_stmt->execute([$user_id]);
        $prefs = $pref_stmt->fetch(PDO::FETCH_ASSOC);
        $max_tasks = $prefs['max_daily_tasks'] ?? 10;
        
        // Count tasks assigned today
        $task_stmt = $db->prepare("
            SELECT COUNT(*) as today_tasks 
            FROM tasks 
            WHERE user_id = ? 
            AND DATE(created_at) = CURDATE()
            AND status NOT IN ('cancelled', 'rejected')
        ");
        $task_stmt->execute([$user_id]);
        $result = $task_stmt->fetch(PDO::FETCH_ASSOC);
        $today_tasks = $result['today_tasks'];
        
        if ($today_tasks >= $max_tasks) {
            return 0; // User at capacity
        }
        
        // Score decreases as user approaches capacity
        $utilization = $today_tasks / $max_tasks;
        $score = (1 - $utilization) * 20;
        
        return round($score, 2);
        
    } catch (Exception $e) {
        error_log("Error calculating workload score: " . $e->getMessage());
        return 15;
    }
}

/**
 * Calculate category expertise score
 */
function calculateCategoryExpertise($db, $user_id, $category_id) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) as category_tasks 
            FROM tasks 
            WHERE user_id = ? 
            AND category_id = ?
            AND status = 'completed'
        ");
        $stmt->execute([$user_id, $category_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $category_tasks = $result['category_tasks'];
        
        if ($category_tasks >= 20) return 10;
        if ($category_tasks >= 10) return 7;
        if ($category_tasks >= 5) return 5;
        if ($category_tasks >= 1) return 3;
        
        return 0;
        
    } catch (Exception $e) {
        error_log("Error calculating expertise: " . $e->getMessage());
        return 0;
    }
}

/**
 * Auto-assign task to best available user
 */
function autoAssignTask($db, $task_id) {
    try {
        // Get task details
        $task_stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $task_stmt->execute([$task_id]);
        $task = $task_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            return ['success' => false, 'message' => 'Task not found'];
        }
        
        // Get active assignment rules
        $rules_stmt = $db->query("
            SELECT * FROM auto_assignment_rules 
            WHERE is_active = 1 
            ORDER BY priority DESC
        ");
        $rules = $rules_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get eligible users
        $users_stmt = $db->query("
            SELECT id FROM users 
            WHERE status = 'active' 
            AND user_type = 'reviewer'
        ");
        $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($users)) {
            return ['success' => false, 'message' => 'No eligible users found'];
        }
        
        // Calculate scores for each user
        $user_scores = [];
        foreach ($users as $user) {
            $score_data = calculateAssignmentScore($db, $user['id'], $task);
            if ($score_data && $score_data['score'] > 0) {
                $user_scores[$user['id']] = $score_data;
            }
        }
        
        if (empty($user_scores)) {
            return ['success' => false, 'message' => 'No users available for assignment'];
        }
        
        // Sort by score
        uasort($user_scores, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        // Get top user
        $best_user_id = array_key_first($user_scores);
        $best_score = $user_scores[$best_user_id];
        
        // Assign task
        $update_stmt = $db->prepare("
            UPDATE tasks 
            SET user_id = ?, 
                status = 'assigned',
                assigned_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->execute([$best_user_id, $task_id]);
        
        // Log assignment
        logAssignment($db, $task_id, $best_user_id, null, 'auto', $best_score['score'], 
            'Auto-assigned based on score: ' . json_encode($best_score['factors']));
        
        return [
            'success' => true,
            'user_id' => $best_user_id,
            'score' => $best_score['score'],
            'factors' => $best_score['factors']
        ];
        
    } catch (Exception $e) {
        error_log("Error auto-assigning task: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Round-robin task assignment
 */
function roundRobinAssignment($db, $task_id) {
    try {
        // Get users ordered by least recent assignment
        $stmt = $db->query("
            SELECT u.id, MAX(al.created_at) as last_assignment
            FROM users u
            LEFT JOIN assignment_logs al ON u.id = al.user_id
            WHERE u.status = 'active' AND u.user_type = 'reviewer'
            GROUP BY u.id
            ORDER BY last_assignment ASC, u.id ASC
            LIMIT 1
        ");
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'No users available'];
        }
        
        // Assign task
        $update_stmt = $db->prepare("
            UPDATE tasks 
            SET user_id = ?, 
                status = 'assigned',
                assigned_at = NOW()
            WHERE id = ?
        ");
        $update_stmt->execute([$user['id'], $task_id]);
        
        // Log assignment
        logAssignment($db, $task_id, $user['id'], null, 'round_robin', 100, 'Round-robin assignment');
        
        return ['success' => true, 'user_id' => $user['id']];
        
    } catch (Exception $e) {
        error_log("Error in round-robin assignment: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Log assignment for audit trail
 */
function logAssignment($db, $task_id, $user_id, $rule_id, $type, $score, $reason) {
    try {
        $stmt = $db->prepare("
            INSERT INTO assignment_logs (task_id, user_id, rule_id, assignment_type, score, reason)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$task_id, $user_id, $rule_id, $type, $score, $reason]);
    } catch (Exception $e) {
        error_log("Error logging assignment: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user assignment preferences
 */
function getUserAssignmentPreferences($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM user_assignment_preferences WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$prefs) {
            // Return defaults
            return [
                'user_id' => $user_id,
                'max_daily_tasks' => 10,
                'preferred_categories' => [],
                'preferred_platforms' => [],
                'blacklisted_brands' => [],
                'availability_schedule' => [],
                'auto_accept' => 0
            ];
        }
        
        // Decode JSON fields
        $prefs['preferred_categories'] = json_decode($prefs['preferred_categories'] ?? '[]', true);
        $prefs['preferred_platforms'] = json_decode($prefs['preferred_platforms'] ?? '[]', true);
        $prefs['blacklisted_brands'] = json_decode($prefs['blacklisted_brands'] ?? '[]', true);
        $prefs['availability_schedule'] = json_decode($prefs['availability_schedule'] ?? '[]', true);
        
        return $prefs;
        
    } catch (Exception $e) {
        error_log("Error getting preferences: " . $e->getMessage());
        return null;
    }
}

/**
 * Update user assignment preferences
 */
function updateUserAssignmentPreferences($db, $user_id, $preferences) {
    try {
        // Encode JSON fields
        $preferred_categories = json_encode($preferences['preferred_categories'] ?? []);
        $preferred_platforms = json_encode($preferences['preferred_platforms'] ?? []);
        $blacklisted_brands = json_encode($preferences['blacklisted_brands'] ?? []);
        $availability_schedule = json_encode($preferences['availability_schedule'] ?? []);
        
        $stmt = $db->prepare("
            INSERT INTO user_assignment_preferences 
            (user_id, max_daily_tasks, preferred_categories, preferred_platforms, 
             blacklisted_brands, availability_schedule, auto_accept)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                max_daily_tasks = VALUES(max_daily_tasks),
                preferred_categories = VALUES(preferred_categories),
                preferred_platforms = VALUES(preferred_platforms),
                blacklisted_brands = VALUES(blacklisted_brands),
                availability_schedule = VALUES(availability_schedule),
                auto_accept = VALUES(auto_accept),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([
            $user_id,
            $preferences['max_daily_tasks'] ?? 10,
            $preferred_categories,
            $preferred_platforms,
            $blacklisted_brands,
            $availability_schedule,
            $preferences['auto_accept'] ?? 0
        ]);
        
    } catch (Exception $e) {
        error_log("Error updating preferences: " . $e->getMessage());
        return false;
    }
}

/**
 * Get assignment statistics
 */
function getAssignmentStatistics($db, $days = 7) {
    try {
        $stmt = $db->prepare("
            SELECT 
                assignment_type,
                COUNT(*) as count,
                AVG(score) as avg_score
            FROM assignment_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY assignment_type
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting statistics: " . $e->getMessage());
        return [];
    }
}
