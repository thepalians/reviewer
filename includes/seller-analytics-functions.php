<?php
/**
 * Seller Analytics Functions
 * Helper functions for seller dashboard and analytics
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Get seller analytics for date range
 */
function getSellerAnalytics($pdo, $seller_id, $start_date = null, $end_date = null) {
    try {
        if (!$start_date) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN task_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN task_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN task_status = 'rejected' THEN 1 ELSE 0 END) as rejected_orders,
                SUM(commission_amount) as total_spent,
                AVG(TIMESTAMPDIFF(HOUR, created_at, 
                    CASE WHEN task_status = 'completed' THEN updated_at ELSE NULL END)) as avg_completion_hours
            FROM tasks
            WHERE seller_id = ?
            AND DATE(created_at) BETWEEN ? AND ?
        ");
        
        $stmt->execute([$seller_id, $start_date, $end_date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting seller analytics: " . $e->getMessage());
        return null;
    }
}

/**
 * Get daily analytics for chart
 */
function getDailyAnalytics($pdo, $seller_id, $days = 30) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders_count,
                SUM(commission_amount) as daily_spent,
                SUM(CASE WHEN task_status = 'completed' THEN 1 ELSE 0 END) as completed_count
            FROM tasks
            WHERE seller_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        
        $stmt->execute([$seller_id, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting daily analytics: " . $e->getMessage());
        return [];
    }
}

/**
 * Cache seller analytics
 */
function cacheSellerAnalytics($pdo, $seller_id, $date = null) {
    try {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $analytics = getSellerAnalytics($pdo, $seller_id, $date, $date);
        
        if ($analytics) {
            $stmt = $pdo->prepare("
                INSERT INTO seller_analytics_cache 
                (seller_id, date, total_orders, completed_orders, pending_orders, 
                 total_spent, avg_completion_time)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_orders = VALUES(total_orders),
                    completed_orders = VALUES(completed_orders),
                    pending_orders = VALUES(pending_orders),
                    total_spent = VALUES(total_spent),
                    avg_completion_time = VALUES(avg_completion_time)
            ");
            
            $stmt->execute([
                $seller_id,
                $date,
                $analytics['total_orders'] ?? 0,
                $analytics['completed_orders'] ?? 0,
                $analytics['pending_orders'] ?? 0,
                $analytics['total_spent'] ?? 0,
                $analytics['avg_completion_hours'] ?? 0
            ]);
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error caching seller analytics: " . $e->getMessage());
        return false;
    }
}

/**
 * Get seller order templates
 */
function getSellerTemplates($pdo, $seller_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM seller_order_templates
            WHERE seller_id = ? AND is_active = 1
            ORDER BY use_count DESC, created_at DESC
        ");
        $stmt->execute([$seller_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting templates: " . $e->getMessage());
        return [];
    }
}

/**
 * Create order template
 */
function createOrderTemplate($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO seller_order_templates
            (seller_id, name, platform, product_link, commission_amount, instructions)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['seller_id'],
            $data['name'],
            $data['platform'] ?? null,
            $data['product_link'] ?? null,
            $data['commission_amount'] ?? null,
            $data['instructions'] ?? null
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating template: " . $e->getMessage());
        return false;
    }
}

/**
 * Update template usage
 */
function incrementTemplateUsage($pdo, $template_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE seller_order_templates
            SET use_count = use_count + 1
            WHERE id = ?
        ");
        return $stmt->execute([$template_id]);
    } catch (PDOException $e) {
        error_log("Error updating template usage: " . $e->getMessage());
        return false;
    }
}

/**
 * Create bulk order batch
 */
function createBulkOrderBatch($pdo, $seller_id, $batch_name, $template_id = null, $total_orders = 0) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO bulk_order_batches
            (seller_id, batch_name, template_id, total_orders)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([$seller_id, $batch_name, $template_id, $total_orders]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error creating bulk batch: " . $e->getMessage());
        return false;
    }
}

/**
 * Update bulk order batch
 */
function updateBulkOrderBatch($pdo, $batch_id, $created_orders, $status = null) {
    try {
        $sql = "UPDATE bulk_order_batches SET created_orders = ?";
        $params = [$created_orders];
        
        if ($status) {
            $sql .= ", status = ?";
            $params[] = $status;
            
            if ($status === 'completed') {
                $sql .= ", completed_at = NOW()";
            }
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $batch_id;
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error updating bulk batch: " . $e->getMessage());
        return false;
    }
}

/**
 * Get review tracking for seller
 */
function getReviewTracking($pdo, $seller_id, $status = null) {
    try {
        $query = "
            SELECT rt.*, t.task_name, t.platform
            FROM review_tracking rt
            LEFT JOIN tasks t ON rt.task_id = t.id
            WHERE rt.seller_id = ?
        ";
        
        $params = [$seller_id];
        
        if ($status) {
            $query .= " AND rt.review_status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY rt.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting review tracking: " . $e->getMessage());
        return [];
    }
}

/**
 * Add review tracking
 */
function addReviewTracking($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO review_tracking
            (seller_id, task_id, product_link, review_date, review_rating, 
             review_text, review_status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['seller_id'],
            $data['task_id'],
            $data['product_link'] ?? null,
            $data['review_date'] ?? null,
            $data['review_rating'] ?? null,
            $data['review_text'] ?? null,
            $data['review_status'] ?? 'pending',
            $data['notes'] ?? null
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error adding review tracking: " . $e->getMessage());
        return false;
    }
}

/**
 * Update review tracking
 */
function updateReviewTracking($pdo, $tracking_id, $data) {
    try {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $fields[] = "last_checked_at = NOW()";
        $values[] = $tracking_id;
        
        $sql = "UPDATE review_tracking SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute($values);
    } catch (PDOException $e) {
        error_log("Error updating review tracking: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate seller ROI
 */
function calculateSellerROI($pdo, $seller_id, $start_date = null, $end_date = null) {
    try {
        if (!$start_date) {
            $start_date = date('Y-m-01'); // First day of current month
        }
        if (!$end_date) {
            $end_date = date('Y-m-d');
        }
        
        // Get total spent
        $stmt = $pdo->prepare("
            SELECT SUM(commission_amount) as total_spent
            FROM tasks
            WHERE seller_id = ?
            AND task_status = 'completed'
            AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$seller_id, $start_date, $end_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_spent = $result['total_spent'] ?? 0;
        
        // Get completed tasks count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as completed_tasks
            FROM tasks
            WHERE seller_id = ?
            AND task_status = 'completed'
            AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$seller_id, $start_date, $end_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $completed_tasks = $result['completed_tasks'] ?? 0;
        
        return [
            'total_spent' => $total_spent,
            'completed_tasks' => $completed_tasks,
            'cost_per_task' => $completed_tasks > 0 ? $total_spent / $completed_tasks : 0,
            'period' => [
                'start' => $start_date,
                'end' => $end_date
            ]
        ];
    } catch (PDOException $e) {
        error_log("Error calculating ROI: " . $e->getMessage());
        return null;
    }
}

/**
 * Get spending report
 */
function getSpendingReport($pdo, $seller_id, $group_by = 'day') {
    try {
        $date_format = match($group_by) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d'
        };
        
        $stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(created_at, ?) as period,
                COUNT(*) as order_count,
                SUM(commission_amount) as total_spent,
                AVG(commission_amount) as avg_commission
            FROM tasks
            WHERE seller_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY period
            ORDER BY period DESC
        ");
        
        $stmt->execute([$date_format, $seller_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting spending report: " . $e->getMessage());
        return [];
    }
}
