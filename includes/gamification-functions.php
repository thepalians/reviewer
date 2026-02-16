<?php
/**
 * Gamification & Rewards System Helper Functions
 * Phase 2: Gamification System
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Initialize user points record
 */
function initializeUserPoints($db, $user_id) {
    $stmt = $db->prepare("
        INSERT IGNORE INTO user_points (user_id, points, level, total_earned, streak_days)
        VALUES (?, 0, 'Bronze', 0, 0)
    ");
    return $stmt->execute([$user_id]);
}

/**
 * Award points to user
 */
function awardPoints($db, $user_id, $points, $type, $description, $reference_id = null, $reference_type = null) {
    try {
        $db->beginTransaction();

        // Ensure user points record exists
        initializeUserPoints($db, $user_id);

        // Insert transaction
        $stmt = $db->prepare("
            INSERT INTO point_transactions (user_id, points, type, description, reference_id, reference_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $points, $type, $description, $reference_id, $reference_type]);

        // Update user points
        $update = $db->prepare("
            UPDATE user_points 
            SET points = points + ?, total_earned = total_earned + ?
            WHERE user_id = ?
        ");
        $update->execute([$points, $points, $user_id]);

        // Check and update level
        updateUserLevel($db, $user_id);

        // Check for badge achievements
        checkBadgeAchievements($db, $user_id);

        $db->commit();

        // Send notification (safe)
        if (function_exists('createNotification')) {
            createNotification($user_id, 'points_earned', 'Points Earned',
                "You earned {$points} points! {$description}");
        }

        return ['success' => true, 'points' => $points];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Award points error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error awarding points'];
    }
}

/**
 * Get user points and level
 */
function getUserPoints($db, $user_id) {
    $stmt = $db->prepare("SELECT * FROM user_points WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        initializeUserPoints($db, $user_id);
        return getUserPoints($db, $user_id);
    }

    return $result;
}

/**
 * Update user level based on points
 */
function updateUserLevel($db, $user_id) {
    $points_data = getUserPoints($db, $user_id);
    $current_points = $points_data['points'];

    $stmt = $db->prepare("
        SELECT level_name 
        FROM level_settings 
        WHERE min_points <= ? AND max_points >= ?
        ORDER BY level_order DESC
        LIMIT 1
    ");
    $stmt->execute([$current_points, $current_points]);
    $new_level = $stmt->fetchColumn();

    if ($new_level && $new_level !== $points_data['level']) {
        $update = $db->prepare("UPDATE user_points SET level = ? WHERE user_id = ?");
        $update->execute([$new_level, $user_id]);

        if (function_exists('createNotification')) {
            createNotification($user_id, 'level_up', 'Level Up',
                "Congratulations! You've reached {$new_level} level!");
        }

        $bonus_points = getLevelUpBonus($new_level);
        if ($bonus_points > 0) {
            awardPoints($db, $user_id, $bonus_points, 'level_up', "Level up bonus: {$new_level}");
        }

        return true;
    }

    return false;
}

/**
 * Get level up bonus points
 */
function getLevelUpBonus($level) {
    $bonuses = [
        'Bronze' => 0,
        'Silver' => 50,
        'Gold' => 100,
        'Platinum' => 200,
        'Diamond' => 500
    ];
    return $bonuses[$level] ?? 0;
}

/**
 * Create streak milestones table
 */
function createStreakMilestonesTable($db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS streak_milestones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            milestone_days INT NOT NULL,
            points_awarded INT NOT NULL,
            badge_awarded VARCHAR(50) DEFAULT NULL,
            achieved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_milestone (user_id, milestone_days),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    } catch (PDOException $e) {
        error_log("Error creating streak milestones table: " . $e->getMessage());
        return false;
    }
}

/**
 * Update daily login streak with enhanced milestones
 */
function updateLoginStreak($db, $user_id) {
    // Ensure streak milestones table exists
    createStreakMilestonesTable($db);
    
    $points_data = getUserPoints($db, $user_id);
    $today = date('Y-m-d');

    if ($points_data['last_login_date'] === $today) {
        return false;
    }

    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $new_streak = 1;

    if ($points_data['last_login_date'] === $yesterday) {
        $new_streak = $points_data['streak_days'] + 1;
    }

    $stmt = $db->prepare("
        UPDATE user_points 
        SET streak_days = ?, last_login_date = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$new_streak, $today, $user_id]);

    // Enhanced streak bonus with higher caps
    $streak_bonus = min(floor($new_streak / 7) * 10, 50);
    $total_points = 5 + $streak_bonus;

    awardPoints($db, $user_id, $total_points, 'daily_login',
        "Daily login (Streak: {$new_streak} days)");

    // Check and award streak milestones
    checkStreakMilestones($db, $user_id, $new_streak);

    return true;
}

/**
 * Check and award streak milestones
 */
function checkStreakMilestones($db, $user_id, $current_streak) {
    $milestones = [
        3 => ['points' => 15, 'badge' => 'streak_3'],
        7 => ['points' => 50, 'badge' => 'streak_7'],
        14 => ['points' => 100, 'badge' => 'streak_14'],
        21 => ['points' => 200, 'badge' => 'streak_21'],
        30 => ['points' => 500, 'badge' => 'streak_30'],
        60 => ['points' => 1000, 'badge' => 'streak_60'],
        100 => ['points' => 2000, 'badge' => 'streak_100']
    ];
    
    foreach ($milestones as $days => $reward) {
        if ($current_streak >= $days) {
            // Check if already awarded
            $check = $db->prepare("
                SELECT id FROM streak_milestones 
                WHERE user_id = ? AND milestone_days = ?
            ");
            $check->execute([$user_id, $days]);
            
            if (!$check->fetch()) {
                // Award milestone
                try {
                    $insert = $db->prepare("
                        INSERT INTO streak_milestones (user_id, milestone_days, points_awarded, badge_awarded)
                        VALUES (?, ?, ?, ?)
                    ");
                    $insert->execute([$user_id, $days, $reward['points'], $reward['badge']]);
                    
                    // Award points
                    awardPoints($db, $user_id, $reward['points'], 'streak_milestone',
                        "Streak milestone: {$days} days");
                    
                    // Award badge
                    if (function_exists('awardBadge') && !empty($reward['badge'])) {
                        awardBadge($user_id, $reward['badge']);
                    }
                    
                    // Send notification
                    if (function_exists('createNotification')) {
                        createNotification($user_id, 'streak_milestone', 'Streak Milestone Achieved!',
                            "Congratulations! You've reached a {$days}-day login streak! Earned {$reward['points']} bonus points! 🔥");
                    }
                } catch (PDOException $e) {
                    error_log("Error awarding streak milestone: " . $e->getMessage());
                }
            }
        }
    }
}

/**
 * Get streak calendar data for last 30 days
 */
function getStreakCalendar($db, $user_id) {
    try {
        $calendar = [];
        $today = date('Y-m-d');
        
        // Get all login dates from point transactions
        $stmt = $db->prepare("
            SELECT DISTINCT DATE(created_at) as login_date
            FROM point_transactions
            WHERE user_id = ? AND type = 'daily_login'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY login_date DESC
        ");
        $stmt->execute([$user_id]);
        $login_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Build 30-day calendar
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $day_of_week = date('D', strtotime($date));
            $day_num = date('j', strtotime($date));
            
            $calendar[] = [
                'date' => $date,
                'day_of_week' => $day_of_week,
                'day_num' => $day_num,
                'active' => in_array($date, $login_dates),
                'is_today' => $date === $today
            ];
        }
        
        return $calendar;
    } catch (PDOException $e) {
        error_log("Error fetching streak calendar: " . $e->getMessage());
        return [];
    }
}

/**
 * Get streak milestones achieved by user
 */
function getStreakMilestones($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM streak_milestones
            WHERE user_id = ?
            ORDER BY milestone_days DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching streak milestones: " . $e->getMessage());
        return [];
    }
}

/**
 * Get next streak milestone
 */
function getNextStreakMilestone($current_streak) {
    $milestones = [3, 7, 14, 21, 30, 60, 100];
    
    foreach ($milestones as $milestone) {
        if ($current_streak < $milestone) {
            return [
                'days' => $milestone,
                'days_remaining' => $milestone - $current_streak,
                'points_reward' => [3 => 15, 7 => 50, 14 => 100, 21 => 200, 30 => 500, 60 => 1000, 100 => 2000][$milestone]
            ];
        }
    }
    
    return null; // All milestones achieved
}

/**
 * Check and award badges based on achievements
 */
function checkBadgeAchievements($db, $user_id) {
    if (!function_exists('awardBadge')) {
        return;
    }

    $stats = getUserAchievementStats($db, $user_id);

    if ($stats['completed_tasks'] >= 1) {
        awardBadge($user_id, 'first_task');
    }
    if ($stats['completed_tasks'] >= 10) {
        awardBadge($user_id, 'ten_tasks');
    }
    if ($stats['completed_tasks'] >= 50) {
        awardBadge($user_id, 'fifty_tasks');
    }
    if ($stats['completed_tasks'] >= 100) {
        awardBadge($user_id, 'hundred_tasks');
    }

    if ($stats['total_referrals'] >= 1) {
        awardBadge($user_id, 'first_referral');
    }
    if ($stats['total_referrals'] >= 10) {
        awardBadge($user_id, 'referral_king');
    }

    if ($stats['kyc_verified']) {
        awardBadge($user_id, 'verified_user');
    }
}

/**
 * Get user achievement statistics
 */
function getUserAchievementStats($db, $user_id) {
    $completed_tasks = 0;
    $total_referrals = 0;
    $kyc_verified = 0;

    try {
        $tasks_stmt = $db->prepare("
            SELECT COUNT(DISTINCT t.id)
            FROM tasks t
            JOIN task_steps ts4 
                ON ts4.task_id = t.id 
                AND ts4.step_number = 4 
                AND ts4.step_status = 'completed'
            WHERE t.user_id = ?
        ");
        $tasks_stmt->execute([$user_id]);
        $completed_tasks = (int)$tasks_stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Achievement tasks error: " . $e->getMessage());
    }

    try {
        $ref_stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?");
        $ref_stmt->execute([$user_id]);
        $total_referrals = (int)$ref_stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Achievement referral error: " . $e->getMessage());
    }

    try {
        $kyc_stmt = $db->prepare("SELECT kyc_status FROM users WHERE id = ?");
        $kyc_stmt->execute([$user_id]);
        $kyc_status = $kyc_stmt->fetchColumn();
        $kyc_verified = ($kyc_status === 'verified') ? 1 : 0;
    } catch (PDOException $e) {
        $kyc_verified = 0;
    }

    return [
        'completed_tasks' => $completed_tasks,
        'total_referrals' => $total_referrals,
        'kyc_verified' => $kyc_verified
    ];
}

/**
 * Get user badges
 */
function getUserBadges($db, $user_id) {
    $stmt = $db->prepare("
        SELECT b.*, ub.earned_at
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get leaderboard
 */
function getLeaderboard($db, $period = 'all_time', $limit = 100) {
    $where_clause = "";

    if ($period === 'daily') {
        $where_clause = "AND pt.created_at >= CURDATE()";
    } elseif ($period === 'weekly') {
        $where_clause = "AND pt.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($period === 'monthly') {
        $where_clause = "AND pt.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    }

    $query = "
        SELECT 
            u.id,
            u.name AS username,
            COALESCE(up.points, 0) AS points,
            COALESCE(up.level, 'Bronze') AS level,
            COALESCE(up.streak_days, 0) AS streak_days,
            COALESCE(SUM(CASE WHEN pt.created_at >= CURDATE() THEN pt.points ELSE 0 END), 0) as points_today,
            COUNT(DISTINCT ub.badge_id) as badge_count
        FROM users u
        LEFT JOIN user_points up ON u.id = up.user_id
        LEFT JOIN point_transactions pt ON u.id = pt.user_id $where_clause
        LEFT JOIN user_badges ub ON u.id = ub.user_id
        WHERE u.user_type = 'user'
        GROUP BY u.id, u.name, up.points, up.level, up.streak_days
        ORDER BY points DESC
        LIMIT ?
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user rank
 */
function getUserRank($db, $user_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) + 1 as `rank`
        FROM user_points up1
        JOIN user_points up2 ON up2.user_id = ?
        WHERE up1.points > up2.points
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

/**
 * Get point transaction history
 */
function getPointTransactions($db, $user_id, $limit = 50, $offset = 0) {
    $stmt = $db->prepare("
        SELECT * FROM point_transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all level settings
 */
function getLevelSettings($db) {
    $stmt = $db->query("SELECT * FROM level_settings ORDER BY level_order ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all badges
 */
function getAllBadges($db) {
    $stmt = $db->query("SELECT * FROM badges WHERE is_active = 1 ORDER BY points_required ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Award task completion points (duplicate guard)
 */
function awardTaskCompletionPoints($db, $user_id, $task_id) {
    $check = $db->prepare("
        SELECT 1 FROM point_transactions 
        WHERE user_id = ? AND type = 'task_completion' AND reference_id = ?
        LIMIT 1
    ");
    $check->execute([$user_id, $task_id]);
    if ($check->fetchColumn()) {
        return ['success' => false, 'message' => 'Points already awarded'];
    }

    return awardPoints($db, $user_id, 10, 'task_completion',
        'Task completed successfully', $task_id, 'task');
}

/**
 * Award referral points
 */
function awardReferralPoints($db, $user_id, $referee_id) {
    return awardPoints($db, $user_id, 50, 'referral',
        'New referral', $referee_id, 'user');
}

/**
 * Award profile completion points
 */
function awardProfileCompletionPoints($db, $user_id) {
    $check = $db->prepare("
        SELECT id FROM point_transactions 
        WHERE user_id = ? AND type = 'profile_completion'
    ");
    $check->execute([$user_id]);
    if ($check->fetch()) {
        return false;
    }

    return awardPoints($db, $user_id, 20, 'profile_completion',
        'Profile completed');
}

/**
 * Get gamification dashboard data
 */
function getGamificationDashboard($db, $user_id) {
    $user_points = getUserPoints($db, $user_id);
    $badges = getUserBadges($db, $user_id);
    $rank = getUserRank($db, $user_id);
    $recent_transactions = getPointTransactions($db, $user_id, 10);

    $next_level_stmt = $db->prepare("
        SELECT * FROM level_settings 
        WHERE level_order > (
            SELECT level_order FROM level_settings WHERE level_name = ?
        )
        ORDER BY level_order ASC
        LIMIT 1
    ");
    $next_level_stmt->execute([$user_points['level']]);
    $next_level = $next_level_stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'user_points' => $user_points,
        'badges' => $badges,
        'rank' => $rank,
        'recent_transactions' => $recent_transactions,
        'next_level' => $next_level,
        'total_badges' => count(getAllBadges($db)),
        'earned_badges' => count($badges)
    ];
}

/**
 * Add money to user wallet (for badge rewards and other credits)
 */
if (!function_exists('addMoneyToWallet')) {
    function addMoneyToWallet(int $userId, float $amount, string $description = 'Wallet Credit'): bool {
        global $pdo;
        
        try {
            // Check if user_wallet exists, create if not
            $checkStmt = $pdo->prepare("SELECT user_id FROM user_wallet WHERE user_id = ?");
            $checkStmt->execute([$userId]);
            
            if (!$checkStmt->fetch()) {
                // Create wallet entry if it doesn't exist
                $createStmt = $pdo->prepare("INSERT INTO user_wallet (user_id, balance) VALUES (?, 0)");
                $createStmt->execute([$userId]);
            }
            
            // Update wallet balance
            $updateStmt = $pdo->prepare("UPDATE user_wallet SET balance = balance + ? WHERE user_id = ?");
            $updateStmt->execute([$amount, $userId]);
            
            // Insert transaction record
            $transactionStmt = $pdo->prepare("
                INSERT INTO wallet_transactions (user_id, type, amount, description, created_at) 
                VALUES (?, 'credit', ?, ?, NOW())
            ");
            $transactionStmt->execute([$userId, $amount, $description]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Add Money to Wallet Error: " . $e->getMessage());
            return false;
        }
    }
}
