<?php
/**
 * Spin Wheel Functions
 * Daily spin wheel feature for user rewards
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Create spin wheel tables if they don't exist
 */
function createSpinTables($db) {
    try {
        // Daily spins tracking table
        $db->exec("CREATE TABLE IF NOT EXISTS daily_spins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            spin_date DATE NOT NULL,
            reward_type ENUM('money', 'points', 'nothing') NOT NULL,
            reward_amount DECIMAL(10,2) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_spin (user_id, spin_date),
            KEY idx_user_date (user_id, spin_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating spin tables: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user can spin today
 */
function canUserSpin($db, $user_id) {
    try {
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT COUNT(*) FROM daily_spins WHERE user_id = ? AND spin_date = ?");
        $stmt->execute([$user_id, $today]);
        $count = (int)$stmt->fetchColumn();
        
        return $count === 0;
    } catch (PDOException $e) {
        error_log("Error checking spin status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get time until next spin
 */
function getTimeUntilNextSpin() {
    $now = time();
    $tomorrow = strtotime('tomorrow');
    $seconds = $tomorrow - $now;
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    return [
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $secs,
        'total_seconds' => $seconds
    ];
}

/**
 * Process spin and award prize
 */
function processSpinResult($db, $user_id) {
    try {
        // Check if user can spin
        if (!canUserSpin($db, $user_id)) {
            return ['success' => false, 'message' => 'You have already spun today'];
        }
        
        // Weighted random selection - 8 segments matching the wheel
        $prizes = [
            ['type' => 'money', 'amount' => 5, 'label' => '₹5', 'weight' => 15, 'index' => 0],
            ['type' => 'points', 'amount' => 10, 'label' => '10 Pts', 'weight' => 20, 'index' => 1],
            ['type' => 'money', 'amount' => 10, 'label' => '₹10', 'weight' => 12, 'index' => 2],
            ['type' => 'points', 'amount' => 25, 'label' => '25 Pts', 'weight' => 15, 'index' => 3],
            ['type' => 'money', 'amount' => 25, 'label' => '₹25', 'weight' => 8, 'index' => 4],
            ['type' => 'money', 'amount' => 50, 'label' => '₹50', 'weight' => 5, 'index' => 5],
            ['type' => 'money', 'amount' => 100, 'label' => '₹100', 'weight' => 2, 'index' => 6],
            ['type' => 'nothing', 'amount' => 0, 'label' => 'Better Luck', 'weight' => 23, 'index' => 7],
        ];
        
        // Calculate total weight
        $totalWeight = array_sum(array_column($prizes, 'weight'));
        
        // Generate random number
        $random = mt_rand(0, (int)($totalWeight * 100)) / 100;
        
        // Select prize
        $cumulativeWeight = 0;
        $selectedPrize = $prizes[0]; // Default to first prize
        $prizeIndex = 0;
        
        foreach ($prizes as $index => $prize) {
            $cumulativeWeight += $prize['weight'];
            if ($random <= $cumulativeWeight) {
                $selectedPrize = $prize;
                $prizeIndex = $index;
                break;
            }
        }
        
        $db->beginTransaction();
        
        // Record the spin
        $today = date('Y-m-d');
        $stmt = $db->prepare("
            INSERT INTO daily_spins (user_id, spin_date, reward_type, reward_amount)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $today, $selectedPrize['type'], $selectedPrize['amount']]);
        
        // Award the prize
        if ($selectedPrize['type'] === 'money' && $selectedPrize['amount'] > 0) {
            // Add to wallet
            if (function_exists('addMoneyToWallet')) {
                addMoneyToWallet($user_id, $selectedPrize['amount'], 'Daily Spin Reward: ₹' . $selectedPrize['amount']);
            } else {
                // Fallback wallet update
                $updateStmt = $db->prepare("UPDATE user_wallet SET balance = balance + ? WHERE user_id = ?");
                $updateStmt->execute([$selectedPrize['amount'], $user_id]);
                
                // Insert transaction
                $txnStmt = $db->prepare("
                    INSERT INTO wallet_transactions (user_id, type, amount, description, created_at)
                    VALUES (?, 'credit', ?, ?, NOW())
                ");
                $txnStmt->execute([$user_id, $selectedPrize['amount'], 'Daily Spin Reward: ₹' . $selectedPrize['amount']]);
            }
        } elseif ($selectedPrize['type'] === 'points' && $selectedPrize['amount'] > 0) {
            // Award points
            if (function_exists('awardPoints')) {
                awardPoints($db, $user_id, (int)$selectedPrize['amount'], 'daily_spin', 'Daily Spin Reward: ' . $selectedPrize['amount'] . ' points');
            }
        }
        
        // Send notification
        if (function_exists('createNotification')) {
            if ($selectedPrize['type'] !== 'nothing') {
                createNotification($user_id, 'spin_reward', 'Spin Wheel Reward', 'You won ' . $selectedPrize['label'] . ' from the daily spin!');
            }
        }
        
        $db->commit();
        
        return [
            'success' => true,
            'prize' => $selectedPrize,
            'prize_index' => $prizeIndex,
            'message' => 'Congratulations! You won ' . $selectedPrize['label']
        ];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error processing spin: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error processing spin'];
    }
}

/**
 * Get spin history for user
 */
function getSpinHistory($db, $user_id, $limit = 10) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM daily_spins 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching spin history: " . $e->getMessage());
        return [];
    }
}

/**
 * Get spin statistics for user
 */
function getSpinStats($db, $user_id) {
    try {
        $stats = [
            'total_spins' => 0,
            'total_money_won' => 0,
            'total_points_won' => 0,
            'nothing_count' => 0
        ];
        
        // Total spins
        $stmt = $db->prepare("SELECT COUNT(*) FROM daily_spins WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $stats['total_spins'] = (int)$stmt->fetchColumn();
        
        // Money won
        $stmt = $db->prepare("SELECT COALESCE(SUM(reward_amount), 0) FROM daily_spins WHERE user_id = ? AND reward_type = 'money'");
        $stmt->execute([$user_id]);
        $stats['total_money_won'] = (float)$stmt->fetchColumn();
        
        // Points won
        $stmt = $db->prepare("SELECT COALESCE(SUM(reward_amount), 0) FROM daily_spins WHERE user_id = ? AND reward_type = 'points'");
        $stmt->execute([$user_id]);
        $stats['total_points_won'] = (int)$stmt->fetchColumn();
        
        // Nothing count
        $stmt = $db->prepare("SELECT COUNT(*) FROM daily_spins WHERE user_id = ? AND reward_type = 'nothing'");
        $stmt->execute([$user_id]);
        $stats['nothing_count'] = (int)$stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error fetching spin stats: " . $e->getMessage());
        return ['total_spins' => 0, 'total_money_won' => 0, 'total_points_won' => 0, 'nothing_count' => 0];
    }
}

// Auto-create tables on include
try {
    if (isset($pdo)) {
        createSpinTables($pdo);
    }
} catch (Exception $e) {
    error_log("Spin tables auto-create error: " . $e->getMessage());
}
