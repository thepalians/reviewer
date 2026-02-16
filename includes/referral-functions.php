<?php
/**
 * Referral System Helper Functions
 * Phase 2: Referral & Affiliate System
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Generate unique referral code for user
 */
if (!function_exists('generateReferralCode')) {
    function generateReferralCode($user_id) {
        return 'REF' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
    }
}

/**
 * Get or create referral code for user
 */
function getUserReferralCode($db, $user_id) {
    $stmt = $db->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $code = $stmt->fetchColumn();
    
    if (empty($code)) {
        $code = generateReferralCode($user_id);
        $update = $db->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
        $update->execute([$code, $user_id]);
    }
    
    return $code;
}

/**
 * Get user by referral code
 */
function getUserByReferralCode($db, $code) {
    $stmt = $db->prepare("SELECT id, username, email FROM users WHERE referral_code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Create referral relationship
 */
function createReferral($db, $referrer_id, $referee_id) {
    try {
        // Check if referee already has a referrer
        $check = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
        $check->execute([$referee_id]);
        $existing = $check->fetchColumn();
        
        if (!empty($existing)) {
            return ['success' => false, 'message' => 'User already referred by someone'];
        }
        
        // Update referee's referred_by
        $update = $db->prepare("UPDATE users SET referred_by = ? WHERE id = ?");
        $update->execute([$referrer_id, $referee_id]);
        
        // Create referral record
        $stmt = $db->prepare("
            INSERT INTO referrals (referrer_id, referee_id, level, status)
            VALUES (?, ?, 1, 'pending')
        ");
        $stmt->execute([$referrer_id, $referee_id]);
        
        // Create multi-level referrals
        createMultiLevelReferrals($db, $referrer_id, $referee_id);
        
        return ['success' => true, 'message' => 'Referral created successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Create multi-level referral relationships
 */
function createMultiLevelReferrals($db, $referrer_id, $referee_id) {
    $level = 2;
    $current_referrer = $referrer_id;
    
    // Get referral settings to check max levels
    $settings_stmt = $db->query("SELECT MAX(level) as max_level FROM referral_settings WHERE is_active = 1");
    $max_level = $settings_stmt->fetchColumn();
    
    while ($level <= $max_level && $current_referrer) {
        // Get next level referrer
        $stmt = $db->prepare("SELECT referred_by FROM users WHERE id = ?");
        $stmt->execute([$current_referrer]);
        $next_referrer = $stmt->fetchColumn();
        
        if ($next_referrer) {
            // Create referral record for this level
            $insert = $db->prepare("
                INSERT IGNORE INTO referrals (referrer_id, referee_id, level, status)
                VALUES (?, ?, ?, 'pending')
            ");
            $insert->execute([$next_referrer, $referee_id, $level]);
            
            $current_referrer = $next_referrer;
            $level++;
        } else {
            break;
        }
    }
}

/**
 * Activate referral when referee completes first task
 */
function activateReferral($db, $referee_id) {
    $stmt = $db->prepare("UPDATE referrals SET status = 'active' WHERE referee_id = ? AND status = 'pending'");
    return $stmt->execute([$referee_id]);
}

/**
 * Calculate and credit referral commission
 */
function creditReferralCommission($db, $referee_id, $task_id, $task_amount) {
    try {
        // Get all referrers for this referee
        $stmt = $db->prepare("
            SELECT r.referrer_id, r.level, rs.commission_percent
            FROM referrals r
            JOIN referral_settings rs ON r.level = rs.level
            WHERE r.referee_id = ? AND r.status = 'active' AND rs.is_active = 1
        ");
        $stmt->execute([$referee_id]);
        $referrers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($referrers as $ref) {
            $commission = ($task_amount * $ref['commission_percent']) / 100;
            
            // Insert earning record
            $insert = $db->prepare("
                INSERT INTO referral_earnings (user_id, from_user_id, task_id, amount, level, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $insert->execute([
                $ref['referrer_id'],
                $referee_id,
                $task_id,
                $commission,
                $ref['level']
            ]);
            
            // Credit to wallet
            $update_wallet = $db->prepare("
                UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?
            ");
            $update_wallet->execute([$commission, $ref['referrer_id']]);
            
            // Mark as credited
            $mark_credited = $db->prepare("
                UPDATE referral_earnings 
                SET status = 'credited', credited_at = NOW() 
                WHERE user_id = ? AND from_user_id = ? AND task_id = ?
            ");
            $mark_credited->execute([$ref['referrer_id'], $referee_id, $task_id]);
            
            // Send notification
            if (function_exists('createNotification')) {
                createNotification($ref['referrer_id'], 'referral_commission', 'Referral Commission',
                    "You earned ₹{$commission} commission from your Level {$ref['level']} referral!");
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Referral commission error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get referral statistics for user
 */
if (!function_exists('getReferralStats')) {
    function getReferralStats($db, $user_id) {
        // Total referrals
        $total_stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND level = 1");
        $total_stmt->execute([$user_id]);
        $total_referrals = $total_stmt->fetchColumn();
        
        // Active referrals
        $active_stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND level = 1 AND status = 'active'");
        $active_stmt->execute([$user_id]);
        $active_referrals = $active_stmt->fetchColumn();
        
        // Total earnings
        $earnings_stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM referral_earnings WHERE user_id = ? AND status = 'credited'");
        $earnings_stmt->execute([$user_id]);
        $total_earnings = $earnings_stmt->fetchColumn();
        
        // Pending earnings
        $pending_stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM referral_earnings WHERE user_id = ? AND status = 'pending'");
        $pending_stmt->execute([$user_id]);
        $pending_earnings = $pending_stmt->fetchColumn();
        
        return [
            'total_referrals' => $total_referrals,
            'active_referrals' => $active_referrals,
            'total_earnings' => $total_earnings,
            'pending_earnings' => $pending_earnings
        ];
    }
}

/**
 * Get referral tree for user
 */
function getReferralTree($db, $user_id, $max_levels = 3) {
    $tree = [];
    
    for ($level = 1; $level <= $max_levels; $level++) {
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                r.status,
                r.created_at,
                (SELECT COUNT(*) FROM orders o 
                 JOIN tasks t ON o.task_id = t.id 
                 WHERE t.user_id = u.id AND o.step4_status = 'approved') as completed_tasks
            FROM referrals r
            JOIN users u ON r.referee_id = u.id
            WHERE r.referrer_id = ? AND r.level = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$user_id, $level]);
        $tree[$level] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $tree;
}

/**
 * Get recent referral earnings
 */
function getRecentReferralEarnings($db, $user_id, $limit = 10) {
    $stmt = $db->prepare("
        SELECT 
            re.*,
            u.username as from_username,
            t.title as task_title
        FROM referral_earnings re
        LEFT JOIN users u ON re.from_user_id = u.id
        LEFT JOIN tasks t ON re.task_id = t.id
        WHERE re.user_id = ?
        ORDER BY re.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get referral commission settings
 */
function getReferralSettings($db) {
    $stmt = $db->query("
        SELECT * FROM referral_settings 
        WHERE is_active = 1 
        ORDER BY level ASC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update referral commission settings (Admin)
 */
function updateReferralSettings($db, $level, $commission_percent, $is_active = 1) {
    $stmt = $db->prepare("
        INSERT INTO referral_settings (level, commission_percent, is_active)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            commission_percent = VALUES(commission_percent),
            is_active = VALUES(is_active)
    ");
    return $stmt->execute([$level, $commission_percent, $is_active]);
}

/**
 * Create referral milestones table
 */
function createReferralMilestonesTable($db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS referral_milestones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            milestone_count INT NOT NULL,
            reward_amount DECIMAL(10,2) NOT NULL,
            achieved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_ref_milestone (user_id, milestone_count),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    } catch (PDOException $e) {
        error_log("Error creating referral milestones table: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and award referral milestones
 */
function checkReferralMilestones($db, $user_id) {
    createReferralMilestonesTable($db);
    
    // Count total referrals
    $stmt = $db->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND level = 1");
    $stmt->execute([$user_id]);
    $total_referrals = (int)$stmt->fetchColumn();
    
    $milestones = [
        5 => 50,
        10 => 100,
        25 => 300,
        50 => 750,
        100 => 2000
    ];
    
    foreach ($milestones as $count => $reward) {
        if ($total_referrals >= $count) {
            // Check if already awarded
            $check = $db->prepare("
                SELECT id FROM referral_milestones 
                WHERE user_id = ? AND milestone_count = ?
            ");
            $check->execute([$user_id, $count]);
            
            if (!$check->fetch()) {
                try {
                    // Award milestone
                    $insert = $db->prepare("
                        INSERT INTO referral_milestones (user_id, milestone_count, reward_amount)
                        VALUES (?, ?, ?)
                    ");
                    $insert->execute([$user_id, $count, $reward]);
                    
                    // Add to wallet
                    if (function_exists('addMoneyToWallet')) {
                        addMoneyToWallet($user_id, $reward, "Referral milestone: {$count} referrals");
                    }
                    
                    // Send notification
                    if (function_exists('createNotification')) {
                        createNotification($user_id, 'referral_milestone', 'Referral Milestone Achieved!',
                            "Congratulations! You've reached {$count} referrals! Earned ₹{$reward} bonus!");
                    }
                } catch (PDOException $e) {
                    error_log("Error awarding referral milestone: " . $e->getMessage());
                }
            }
        }
    }
}

/**
 * Get referral milestone rewards for user
 */
function getReferralMilestoneRewards($db, $user_id) {
    try {
        createReferralMilestonesTable($db);
        $stmt = $db->prepare("
            SELECT * FROM referral_milestones
            WHERE user_id = ?
            ORDER BY milestone_count DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching referral milestones: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total network size across all levels
 */
function getNetworkSize($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT referred_id) as total
            FROM referrals
            WHERE referrer_id = ?
        ");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching network size: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get level-wise referral stats
 */
function getLevelWiseReferralStats($db, $user_id) {
    $stats = [];
    
    for ($level = 1; $level <= 3; $level++) {
        try {
            // Count referrals at this level
            $count_stmt = $db->prepare("
                SELECT COUNT(*) FROM referrals 
                WHERE referrer_id = ? AND level = ?
            ");
            $count_stmt->execute([$user_id, $level]);
            $count = (int)$count_stmt->fetchColumn();
            
            // Get earnings at this level
            $earnings_stmt = $db->prepare("
                SELECT COALESCE(SUM(amount), 0) 
                FROM referral_earnings 
                WHERE user_id = ? AND level = ? AND status = 'credited'
            ");
            $earnings_stmt->execute([$user_id, $level]);
            $earnings = (float)$earnings_stmt->fetchColumn();
            
            // Get commission percentage for this level
            $settings_stmt = $db->prepare("
                SELECT commission_percent FROM referral_settings 
                WHERE level = ? AND is_active = 1
            ");
            $settings_stmt->execute([$level]);
            $commission = (float)$settings_stmt->fetchColumn();
            
            $stats[] = [
                'level' => $level,
                'count' => $count,
                'earnings' => $earnings,
                'commission_percent' => $commission
            ];
        } catch (PDOException $e) {
            error_log("Error fetching level {$level} stats: " . $e->getMessage());
            $stats[] = ['level' => $level, 'count' => 0, 'earnings' => 0, 'commission_percent' => 0];
        }
    }
    
    return $stats;
}

/**
 * Generate shareable referral link
 */
function generateReferralLink($referral_code) {
    $base_url = rtrim(APP_URL, '/');
    return $base_url . '/user/signup.php?ref=' . urlencode($referral_code);
}

/**
 * Get WhatsApp share link for referral
 */
function getWhatsAppShareLink($referral_code) {
    $link = generateReferralLink($referral_code);
    $message = "Join ReviewFlow and earn money by completing tasks! Use my referral code: {$referral_code}\n\n{$link}";
    return 'https://api.whatsapp.com/send?text=' . urlencode($message);
}

/**
 * Get Facebook share link for referral
 */
function getFacebookShareLink($referral_code) {
    $link = generateReferralLink($referral_code);
    return 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($link);
}

/**
 * Get Twitter share link for referral
 */
function getTwitterShareLink($referral_code) {
    $link = generateReferralLink($referral_code);
    $message = "Join ReviewFlow and earn money! Use my code: {$referral_code}";
    return 'https://twitter.com/intent/tweet?text=' . urlencode($message) . '&url=' . urlencode($link);
}

// Auto-create tables on include
try {
    if (isset($pdo)) {
        createReferralMilestonesTable($pdo);
    }
} catch (Exception $e) {
    error_log("Referral milestones table auto-create error: " . $e->getMessage());
}

