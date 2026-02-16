<?php
declare(strict_types=1);

/**
 * BI Dashboard Functions
 * Business Intelligence and Analytics Helper Functions
 */

/**
 * Get dashboard widgets for a user
 */
function getDashboardWidgets(int $userId): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM dashboard_widgets 
            WHERE user_id = ? AND is_active = 1 
            ORDER BY position_y, position_x
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching dashboard widgets: " . $e->getMessage());
        return [];
    }
}

/**
 * Create or update a dashboard widget
 */
function saveWidget(array $data): bool {
    global $pdo;
    
    try {
        if (isset($data['id']) && $data['id'] > 0) {
            // Update existing widget
            $stmt = $pdo->prepare("
                UPDATE dashboard_widgets 
                SET title = ?, widget_type = ?, data_source = ?, config = ?, 
                    position_x = ?, position_y = ?, width = ?, height = ?
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([
                $data['title'],
                $data['widget_type'],
                $data['data_source'],
                json_encode($data['config'] ?? []),
                $data['position_x'] ?? 0,
                $data['position_y'] ?? 0,
                $data['width'] ?? 4,
                $data['height'] ?? 3,
                $data['id'],
                $data['user_id']
            ]);
        } else {
            // Create new widget
            $stmt = $pdo->prepare("
                INSERT INTO dashboard_widgets 
                (user_id, widget_type, title, data_source, config, position_x, position_y, width, height)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['user_id'],
                $data['widget_type'],
                $data['title'],
                $data['data_source'],
                json_encode($data['config'] ?? []),
                $data['position_x'] ?? 0,
                $data['position_y'] ?? 0,
                $data['width'] ?? 4,
                $data['height'] ?? 3
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error saving widget: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a widget
 */
function deleteWidget(int $widgetId, int $userId): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM dashboard_widgets WHERE id = ? AND user_id = ?");
        return $stmt->execute([$widgetId, $userId]);
    } catch (PDOException $e) {
        error_log("Error deleting widget: " . $e->getMessage());
        return false;
    }
}

/**
 * Get widget data based on data source
 */
function getWidgetData(string $dataSource, array $config = []): array {
    global $pdo;
    
    try {
        switch ($dataSource) {
            case 'total_users':
                $stmt = $pdo->query("SELECT COUNT(*) as value FROM users WHERE user_type = 'user'");
                return ['value' => $stmt->fetchColumn()];
                
            case 'active_tasks':
                $stmt = $pdo->query("SELECT COUNT(*) as value FROM tasks WHERE task_status = 'pending'");
                return ['value' => $stmt->fetchColumn()];
                
            case 'total_revenue':
                $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as value FROM payments WHERE status = 'completed'");
                return ['value' => number_format($stmt->fetchColumn(), 2)];
                
            case 'pending_withdrawals':
                $stmt = $pdo->query("SELECT COUNT(*) as value FROM withdrawal_requests WHERE status = 'pending'");
                return ['value' => $stmt->fetchColumn()];
                
            case 'tasks_by_status':
                $stmt = $pdo->query("
                    SELECT task_status as label, COUNT(*) as value 
                    FROM tasks 
                    GROUP BY task_status
                ");
                return $stmt->fetchAll();
                
            case 'revenue_trend':
                $days = $config['days'] ?? 30;
                $stmt = $pdo->prepare("
                    SELECT DATE(created_at) as label, COALESCE(SUM(amount), 0) as value 
                    FROM payments 
                    WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY DATE(created_at)
                ");
                $stmt->execute([$days]);
                return $stmt->fetchAll();
                
            case 'top_performers':
                $limit = $config['limit'] ?? 10;
                $stmt = $pdo->prepare("
                    SELECT u.name as label, COUNT(t.id) as value 
                    FROM tasks t
                    JOIN users u ON t.assigned_to = u.id
                    WHERE t.task_status = 'completed'
                    GROUP BY t.assigned_to, u.name
                    ORDER BY value DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                return $stmt->fetchAll();
                
            default:
                return [];
        }
    } catch (PDOException $e) {
        error_log("Error fetching widget data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all KPI metrics
 */
function getAllKPIMetrics(): array {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM kpi_metrics WHERE is_active = 1 ORDER BY name");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching KPI metrics: " . $e->getMessage());
        return [];
    }
}

/**
 * Get KPI metric with current value
 */
function getKPIMetric(int $kpiId): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM kpi_metrics WHERE id = ?");
        $stmt->execute([$kpiId]);
        $kpi = $stmt->fetch();
        
        if (!$kpi) return null;
        
        // Get current value
        $kpi['current_value'] = calculateKPIValue($kpi);
        
        // Get history
        $stmt = $pdo->prepare("
            SELECT recorded_date, value 
            FROM kpi_history 
            WHERE kpi_id = ? 
            ORDER BY recorded_date DESC 
            LIMIT 30
        ");
        $stmt->execute([$kpiId]);
        $kpi['history'] = $stmt->fetchAll();
        
        return $kpi;
    } catch (PDOException $e) {
        error_log("Error fetching KPI metric: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate current KPI value based on data source
 * 
 * @param array $kpi KPI array containing 'data_source' and 'metric_type' keys
 *                   data_source: The data source to query (e.g., 'tasks_completed', 'revenue')
 *                   metric_type: The type of calculation (e.g., 'count', 'sum', 'average')
 * @return float The calculated KPI value, or 0 if calculation fails
 */
function calculateKPIValue(array $kpi): float {
    global $pdo;
    
    try {
        $dataSource = $kpi['data_source'];
        $metricType = $kpi['metric_type'];
        
        switch ($dataSource) {
            case 'tasks_completed':
                if ($metricType === 'count') {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'completed'");
                    return (float)$stmt->fetchColumn();
                }
                break;
                
            case 'revenue':
                if ($metricType === 'sum') {
                    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'");
                    return (float)$stmt->fetchColumn();
                }
                break;
                
            case 'user_satisfaction':
                if ($metricType === 'average') {
                    $stmt = $pdo->query("SELECT COALESCE(AVG(rating), 0) FROM feedback WHERE rating IS NOT NULL");
                    return (float)$stmt->fetchColumn();
                }
                break;
        }
        
        return 0;
    } catch (PDOException $e) {
        error_log("Error calculating KPI value: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get widget by ID
 */
function getWidgetById(int $widgetId, int $userId): ?array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM dashboard_widgets 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$widgetId, $userId]);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Error fetching widget: " . $e->getMessage());
        return null;
    }
}

/**
 * Get widget data by widget ID (wrapper function)
 */
function getWidgetDataById(int $widgetId): array {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT data_source, config FROM dashboard_widgets WHERE id = ?");
        $stmt->execute([$widgetId]);
        $widget = $stmt->fetch();
        
        if (!$widget) {
            return [];
        }
        
        $config = json_decode($widget['config'], true) ?? [];
        return getWidgetData($widget['data_source'], $config);
    } catch (PDOException $e) {
        error_log("Error fetching widget data: " . $e->getMessage());
        return [];
    }
}

/**
 * Record KPI history
 */
function recordKPIHistory(int $kpiId, float $value, ?string $date = null): bool {
    global $pdo;
    
    try {
        $recordDate = $date ?? date('Y-m-d');
        $stmt = $pdo->prepare("
            INSERT INTO kpi_history (kpi_id, value, recorded_date) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE value = ?
        ");
        return $stmt->execute([$kpiId, $value, $recordDate, $value]);
    } catch (PDOException $e) {
        error_log("Error recording KPI history: " . $e->getMessage());
        return false;
    }
}

/**
 * Create KPI metric
 */
function createKPIMetric(array $data): bool {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO kpi_metrics 
            (name, description, metric_type, data_source, target_value, warning_threshold, critical_threshold)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['metric_type'],
            $data['data_source'],
            $data['target_value'] ?? null,
            $data['warning_threshold'] ?? null,
            $data['critical_threshold'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Error creating KPI metric: " . $e->getMessage());
        return false;
    }
}

/**
 * Get analytics summary
 */
function getAnalyticsSummary(): array {
    global $pdo;
    
    try {
        $summary = [];
        
        // Total metrics
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'user'");
        $summary['total_users'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
        $summary['total_tasks'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'");
        $summary['total_revenue'] = $stmt->fetchColumn();
        
        // Growth metrics (comparing last 30 days to previous 30 days)
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM users 
            WHERE user_type = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $summary['new_users_30d'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM tasks 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $summary['new_tasks_30d'] = $stmt->fetchColumn();
        
        return $summary;
    } catch (PDOException $e) {
        error_log("Error getting analytics summary: " . $e->getMessage());
        return [];
    }
}
