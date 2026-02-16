<?php
declare(strict_types=1);

/**
 * Analytics Helper Functions
 * Data fetching and calculation functions for analytics dashboards
 */

if (!function_exists('getRevenueStats')) {
    /**
     * Get revenue statistics for admin
     */
    function getRevenueStats($pdo, int $days = 30): array {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COALESCE(SUM(grand_total), 0) as revenue
                FROM review_requests
                WHERE payment_status = 'paid'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get revenue stats error: {$e->getMessage()}");
            return [];
        }
    }
}

if (!function_exists('getUserGrowthStats')) {
    /**
     * Get user growth statistics
     */
    function getUserGrowthStats($pdo, int $days = 30): array {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM users
                WHERE user_type = 'user'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get user growth stats error: {$e->getMessage()}");
            return [];
        }
    }
}

if (!function_exists('getTaskCompletionStats')) {
    /**
     * Get task completion rate statistics
     */
    function getTaskCompletionStats($pdo): array {
        try {
            $stmt = $pdo->query("
                SELECT 
                    task_status,
                    COUNT(*) as count
                FROM tasks
                GROUP BY task_status
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get task completion stats error: {$e->getMessage()}");
            return [];
        }
    }
}

if (!function_exists('getTopPerformers')) {
    /**
     * Get top performing users
     */
    function getTopPerformers($pdo, int $limit = 10): array {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    u.id,
                    u.username,
                    u.email,
                    COUNT(t.id) as completed_tasks,
                    COALESCE(SUM(t.reward_amount), 0) as total_earned
                FROM users u
                LEFT JOIN tasks t ON u.id = t.user_id AND t.task_status = 'completed'
                WHERE u.user_type = 'user'
                GROUP BY u.id
                ORDER BY completed_tasks DESC, total_earned DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get top performers error: {$e->getMessage()}");
            return [];
        }
    }
}

if (!function_exists('getDashboardSummary')) {
    /**
     * Get overall dashboard summary
     */
    function getDashboardSummary($pdo): array {
        try {
            $summary = [];
            
            // Total users
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'user'");
            $summary['total_users'] = (int)$stmt->fetchColumn();
            
            // Total sellers
            $stmt = $pdo->query("SELECT COUNT(*) FROM sellers");
            $summary['total_sellers'] = (int)$stmt->fetchColumn();
            
            // Total tasks
            $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
            $summary['total_tasks'] = (int)$stmt->fetchColumn();
            
            // Completed tasks
            $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'completed'");
            $summary['completed_tasks'] = (int)$stmt->fetchColumn();
            
            // Total revenue
            $stmt = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM review_requests WHERE payment_status = 'paid'");
            $summary['total_revenue'] = (float)$stmt->fetchColumn();
            
            // Total wallet balance
            $stmt = $pdo->query("SELECT COALESCE(SUM(balance), 0) FROM user_wallet");
            $summary['total_wallet_balance'] = (float)$stmt->fetchColumn();
            
            // Total paid out
            $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = 'completed'");
            $summary['total_paid_out'] = (float)$stmt->fetchColumn();
            
            // This month stats
            $stmt = $pdo->query("
                SELECT COUNT(*) 
                FROM users 
                WHERE user_type = 'user' 
                AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            $summary['new_users_month'] = (int)$stmt->fetchColumn();
            
            $stmt = $pdo->query("
                SELECT COUNT(*) 
                FROM tasks 
                WHERE task_status = 'completed' 
                AND completed_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            $summary['completed_tasks_month'] = (int)$stmt->fetchColumn();
            
            return $summary;
        } catch (PDOException $e) {
            error_log("Get dashboard summary error: {$e->getMessage()}");
            return [];
        }
    }
}

if (!function_exists('getUserAnalytics')) {
    /**
     * Get analytics for a specific user
     */
    function getUserAnalytics($pdo, int $userId): array {
        try {
            $analytics = [];
            
            // Total tasks
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ?");
            $stmt->execute([$userId]);
            $analytics['total_tasks'] = (int)$stmt->fetchColumn();
            
            // Completed tasks
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND task_status = 'completed'");
            $stmt->execute([$userId]);
            $analytics['completed_tasks'] = (int)$stmt->fetchColumn();
            
            // Pending tasks
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND task_status = 'pending'");
            $stmt->execute([$userId]);
            $analytics['pending_tasks'] = (int)$stmt->fetchColumn();
            
            // Rejected tasks
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND task_status = 'rejected'");
            $stmt->execute([$userId]);
            $analytics['rejected_tasks'] = (int)$stmt->fetchColumn();
            
            // Total earnings
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(reward_amount), 0) FROM tasks WHERE user_id = ? AND task_status = 'completed'");
            $stmt->execute([$userId]);
            $analytics['total_earnings'] = (float)$stmt->fetchColumn();
            
            // Current wallet balance
            $stmt = $pdo->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
            $stmt->execute([$userId]);
            $analytics['wallet_balance'] = (float)($stmt->fetchColumn() ?? 0);
            
            // Total withdrawals
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE user_id = ? AND status = 'completed'");
            $stmt->execute([$userId]);
            $analytics['total_withdrawn'] = (float)$stmt->fetchColumn();
            
            // Earnings by month (last 6 months)
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(completed_at, '%Y-%m') as month,
                    COALESCE(SUM(reward_amount), 0) as earnings
                FROM tasks
                WHERE user_id = ? 
                AND task_status = 'completed'
                AND completed_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(completed_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->execute([$userId]);
            $analytics['monthly_earnings'] = $stmt->fetchAll();
            
            // Success rate
            if ($analytics['total_tasks'] > 0) {
                $analytics['success_rate'] = round(($analytics['completed_tasks'] / $analytics['total_tasks']) * 100, 2);
            } else {
                $analytics['success_rate'] = 0;
            }
            
            return $analytics;
        } catch (PDOException $e) {
            error_log("Get user analytics error: {$e->getMessage()}");
            return [];
        }
    }
}

if (!function_exists('getSellerAnalytics')) {
    /**
     * Get analytics for a specific seller
     */
    function getSellerAnalytics($pdo, int $sellerId): array {
        try {
            $analytics = [];
            
            // Total review requests
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM review_requests WHERE seller_id = ?");
            $stmt->execute([$sellerId]);
            $analytics['total_requests'] = (int)$stmt->fetchColumn();
            
            // Completed requests
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM review_requests WHERE seller_id = ? AND status = 'completed'");
            $stmt->execute([$sellerId]);
            $analytics['completed_requests'] = (int)$stmt->fetchColumn();
            
            // Pending requests
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM review_requests WHERE seller_id = ? AND status = 'pending'");
            $stmt->execute([$sellerId]);
            $analytics['pending_requests'] = (int)$stmt->fetchColumn();
            
            // Total spent
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(grand_total), 0) FROM review_requests WHERE seller_id = ? AND payment_status = 'paid'");
            $stmt->execute([$sellerId]);
            $analytics['total_spent'] = (float)$stmt->fetchColumn();
            
            // Wallet balance
            $stmt = $pdo->prepare("SELECT balance FROM seller_wallet WHERE seller_id = ?");
            $stmt->execute([$sellerId]);
            $analytics['wallet_balance'] = (float)($stmt->fetchColumn() ?? 0);
            
            // Monthly spending (last 6 months)
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COALESCE(SUM(grand_total), 0) as spending
                FROM review_requests
                WHERE seller_id = ? 
                AND payment_status = 'paid'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->execute([$sellerId]);
            $analytics['monthly_spending'] = $stmt->fetchAll();
            
            // Average reviews per request
            $stmt = $pdo->prepare("
                SELECT AVG(number_of_reviews) as avg_reviews 
                FROM review_requests 
                WHERE seller_id = ?
            ");
            $stmt->execute([$sellerId]);
            $analytics['avg_reviews_per_request'] = round((float)($stmt->fetchColumn() ?? 0), 2);
            
            return $analytics;
        } catch (PDOException $e) {
            error_log("Get seller analytics error: {$e->getMessage()}");
            return [];
        }
    }
}

if (!function_exists('getTaskDistribution')) {
    /**
     * Get task distribution by brand for admin analytics
     */
    function getTaskDistribution($pdo): array {
        try {
            $stmt = $pdo->query("
                SELECT 
                    brand_name,
                    COUNT(*) as task_count
                FROM tasks
                WHERE brand_name IS NOT NULL
                GROUP BY brand_name
                ORDER BY task_count DESC
                LIMIT 10
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get task distribution error: {$e->getMessage()}");
            return [];
        }
    }
}

if (!function_exists('getWithdrawalTrends')) {
    /**
     * Get withdrawal trends over time
     */
    function getWithdrawalTrends($pdo, int $days = 30): array {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count,
                    COALESCE(SUM(amount), 0) as total_amount
                FROM withdrawal_requests
                WHERE status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get withdrawal trends error: {$e->getMessage()}");
            return [];
        }
    }
}
?>
