<?php
/**
 * Competition & Leaderboard Helper Functions
 * Phase 7: Advanced Automation & Intelligence Features
 */

if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Create a new competition
 */
function createCompetition($db, $data) {
    try {
        $stmt = $db->prepare("
            INSERT INTO competitions 
            (name, description, competition_type, start_date, end_date, prizes, rules, 
             min_participants, max_participants, entry_fee, prize_pool, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['competition_type'],
            $data['start_date'],
            $data['end_date'],
            json_encode($data['prizes']),
            json_encode($data['rules'] ?? []),
            $data['min_participants'] ?? 0,
            $data['max_participants'] ?? null,
            $data['entry_fee'] ?? 0,
            $data['prize_pool'] ?? 0,
            $data['created_by']
        ]);
    } catch (Exception $e) {
        error_log("Error creating competition: " . $e->getMessage());
        return false;
    }
}

/**
 * Join a competition
 */
function joinCompetition($db, $competition_id, $user_id) {
    try {
        // Check if competition exists and is active
        $comp_stmt = $db->prepare("
            SELECT * FROM competitions 
            WHERE id = ? 
            AND status IN ('upcoming', 'active')
        ");
        $comp_stmt->execute([$competition_id]);
        $competition = $comp_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$competition) {
            return ['success' => false, 'message' => 'Competition not found or inactive'];
        }
        
        // Check participant limit
        if ($competition['max_participants']) {
            $count_stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM competition_participants 
                WHERE competition_id = ?
            ");
            $count_stmt->execute([$competition_id]);
            $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count >= $competition['max_participants']) {
                return ['success' => false, 'message' => 'Competition is full'];
            }
        }
        
        // Join competition
        $stmt = $db->prepare("
            INSERT INTO competition_participants (competition_id, user_id)
            VALUES (?, ?)
        ");
        
        if ($stmt->execute([$competition_id, $user_id])) {
            return ['success' => true, 'message' => 'Successfully joined competition'];
        }
        
        return ['success' => false, 'message' => 'Failed to join competition'];
        
    } catch (Exception $e) {
        error_log("Error joining competition: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Update competition leaderboard
 */
function updateCompetitionLeaderboard($db, $competition_id) {
    try {
        $comp_stmt = $db->prepare("SELECT * FROM competitions WHERE id = ?");
        $comp_stmt->execute([$competition_id]);
        $competition = $comp_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$competition) {
            return false;
        }
        
        $metric_data = buildMetricQuery($competition['competition_type'], $competition);
        
        if (!$metric_data) {
            return false;
        }
        
        // Get scores for all participants
        $stmt = $db->prepare($metric_data['query']);
        $params = array_merge($metric_data['params'], [$competition_id]);
        $stmt->execute($params);
        $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update leaderboard
        $rank = 1;
        foreach ($scores as $score_data) {
            $update_stmt = $db->prepare("
                INSERT INTO competition_leaderboard 
                (competition_id, user_id, metric_value, rank)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    metric_value = VALUES(metric_value),
                    rank = VALUES(rank)
            ");
            $update_stmt->execute([
                $competition_id,
                $score_data['user_id'],
                $score_data['metric_value'],
                $rank
            ]);
            $rank++;
        }
        
        // Update participant scores
        $update_participants = $db->prepare("
            UPDATE competition_participants cp
            JOIN competition_leaderboard cl ON cp.competition_id = cl.competition_id 
                AND cp.user_id = cl.user_id
            SET cp.score = cl.metric_value, cp.rank = cl.rank
            WHERE cp.competition_id = ?
        ");
        $update_participants->execute([$competition_id]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error updating leaderboard: " . $e->getMessage());
        return false;
    }
}

/**
 * Build metric query based on competition type
 */
function buildMetricQuery($type, $competition) {
    // Return array with query and parameters
    $start = $competition['start_date'];
    $end = $competition['end_date'];
    
    switch ($type) {
        case 'tasks':
            return [
                'query' => "
                    SELECT cp.user_id, COUNT(t.id) as metric_value
                    FROM competition_participants cp
                    LEFT JOIN tasks t ON cp.user_id = t.user_id 
                        AND t.status = 'completed'
                        AND t.completed_at BETWEEN ? AND ?
                    WHERE cp.competition_id = ?
                    GROUP BY cp.user_id
                    ORDER BY metric_value DESC
                ",
                'params' => [$start, $end]
            ];
            
        case 'earnings':
            return [
                'query' => "
                    SELECT cp.user_id, COALESCE(SUM(t.commission_amount), 0) as metric_value
                    FROM competition_participants cp
                    LEFT JOIN tasks t ON cp.user_id = t.user_id 
                        AND t.status = 'completed'
                        AND t.completed_at BETWEEN ? AND ?
                    WHERE cp.competition_id = ?
                    GROUP BY cp.user_id
                    ORDER BY metric_value DESC
                ",
                'params' => [$start, $end]
            ];
            
        case 'quality':
            return [
                'query' => "
                    SELECT cp.user_id, COALESCE(AVG(t.rating), 0) as metric_value
                    FROM competition_participants cp
                    LEFT JOIN tasks t ON cp.user_id = t.user_id 
                        AND t.status = 'completed'
                        AND t.rating > 0
                        AND t.completed_at BETWEEN ? AND ?
                    WHERE cp.competition_id = ?
                    GROUP BY cp.user_id
                    ORDER BY metric_value DESC
                ",
                'params' => [$start, $end]
            ];
            
        case 'referrals':
            return [
                'query' => "
                    SELECT cp.user_id, COUNT(r.id) as metric_value
                    FROM competition_participants cp
                    LEFT JOIN referrals r ON cp.user_id = r.referrer_id 
                        AND r.created_at BETWEEN ? AND ?
                    WHERE cp.competition_id = ?
                    GROUP BY cp.user_id
                    ORDER BY metric_value DESC
                ",
                'params' => [$start, $end]
            ];
            
        case 'speed':
            return [
                'query' => "
                    SELECT cp.user_id, 
                        COALESCE(AVG(TIMESTAMPDIFF(HOUR, t.created_at, t.completed_at)), 999) as metric_value
                    FROM competition_participants cp
                    LEFT JOIN tasks t ON cp.user_id = t.user_id 
                        AND t.status = 'completed'
                        AND t.completed_at BETWEEN ? AND ?
                    WHERE cp.competition_id = ?
                    GROUP BY cp.user_id
                    ORDER BY metric_value ASC
                ",
                'params' => [$start, $end]
            ];
            
        default:
            return null;
    }
}

/**
 * Get competition leaderboard
 */
function getCompetitionLeaderboard($db, $competition_id, $limit = 100) {
    try {
        $stmt = $db->prepare("
            SELECT 
                cl.*,
                u.name,
                u.email
            FROM competition_leaderboard cl
            JOIN users u ON cl.user_id = u.id
            WHERE cl.competition_id = ?
            ORDER BY cl.rank ASC
            LIMIT ?
        ");
        $stmt->execute([$competition_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting leaderboard: " . $e->getMessage());
        return [];
    }
}

/**
 * Distribute prizes for completed competition
 */
function distributePrizes($db, $competition_id) {
    try {
        $db->beginTransaction();
        
        $comp_stmt = $db->prepare("SELECT * FROM competitions WHERE id = ?");
        $comp_stmt->execute([$competition_id]);
        $competition = $comp_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$competition || $competition['status'] != 'ended') {
            $db->rollBack();
            return false;
        }
        
        $prizes = json_decode($competition['prizes'], true);
        
        // Get winners
        $winners_stmt = $db->prepare("
            SELECT * FROM competition_participants 
            WHERE competition_id = ? 
            AND rank IS NOT NULL
            ORDER BY rank ASC
        ");
        $winners_stmt->execute([$competition_id]);
        $winners = $winners_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($winners as $winner) {
            $rank = $winner['rank'];
            if (isset($prizes[$rank])) {
                $prize_amount = $prizes[$rank];
                
                // Update participant with prize
                $update_stmt = $db->prepare("
                    UPDATE competition_participants 
                    SET prize_won = ?, prize_paid = 1
                    WHERE id = ?
                ");
                $update_stmt->execute([$prize_amount, $winner['id']]);
                
                // Credit to user wallet
                $wallet_stmt = $db->prepare("
                    UPDATE users 
                    SET balance = balance + ? 
                    WHERE id = ?
                ");
                $wallet_stmt->execute([$prize_amount, $winner['user_id']]);
            }
        }
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error distributing prizes: " . $e->getMessage());
        return false;
    }
}

/**
 * Get active competitions
 */
function getActiveCompetitions($db) {
    try {
        $stmt = $db->query("
            SELECT * FROM competitions 
            WHERE status IN ('upcoming', 'active')
            ORDER BY start_date ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting active competitions: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's competition history
 */
function getUserCompetitionHistory($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                c.*,
                cp.score,
                cp.rank,
                cp.prize_won,
                cp.joined_at
            FROM competition_participants cp
            JOIN competitions c ON cp.competition_id = c.id
            WHERE cp.user_id = ?
            ORDER BY c.start_date DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting competition history: " . $e->getMessage());
        return [];
    }
}

/**
 * Update competition status based on dates
 */
function updateCompetitionStatuses($db) {
    try {
        $now = date('Y-m-d H:i:s');
        
        // Set to active
        $stmt1 = $db->prepare("
            UPDATE competitions 
            SET status = 'active' 
            WHERE status = 'upcoming' 
            AND start_date <= ?
        ");
        $stmt1->execute([$now]);
        
        // Set to ended
        $stmt2 = $db->prepare("
            UPDATE competitions 
            SET status = 'ended' 
            WHERE status = 'active' 
            AND end_date <= ?
        ");
        $stmt2->execute([$now]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error updating competition statuses: " . $e->getMessage());
        return false;
    }
}
