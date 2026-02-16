<?php
declare(strict_types=1);

/**
 * Export Functions for Phase 4
 * Handles data export to various formats (CSV, Excel, PDF)
 */

/**
 * Export data to CSV
 */
function exportToCSV($data, $filename, $headers = []) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    if (!empty($headers)) {
        fputcsv($output, $headers);
    } elseif (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Get tasks report data
 */
function getTasksReportData($pdo, $filters = []) {
    try {
        $sql = "SELECT 
                    t.id,
                    t.task_code,
                    u.name as user_name,
                    u.email as user_email,
                    s.name as seller_name,
                    t.amount,
                    t.task_status,
                    t.created_at,
                    t.completed_at
                FROM tasks t
                LEFT JOIN users u ON t.user_id = u.id
                LEFT JOIN users s ON t.seller_id = s.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND t.task_status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(t.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(t.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        if (!empty($filters['seller_id'])) {
            $sql .= " AND t.seller_id = :seller_id";
            $params[':seller_id'] = $filters['seller_id'];
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get users report data
 */
function getUsersReportData($pdo, $filters = []) {
    try {
        $sql = "SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.mobile,
                    u.user_type,
                    u.status,
                    u.created_at,
                    COALESCE(w.balance, 0) as wallet_balance,
                    COUNT(DISTINCT t.id) as total_tasks,
                    SUM(CASE WHEN t.task_status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                FROM users u
                LEFT JOIN user_wallet w ON u.id = w.user_id
                LEFT JOIN tasks t ON u.id = t.user_id
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['user_type'])) {
            $sql .= " AND u.user_type = :user_type";
            $params[':user_type'] = $filters['user_type'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND u.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(u.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(u.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get financial report data
 */
function getFinancialReportData($pdo, $filters = []) {
    try {
        $sql = "SELECT 
                    DATE(t.created_at) as date,
                    COUNT(*) as total_tasks,
                    SUM(t.amount) as total_amount,
                    SUM(CASE WHEN t.task_status = 'completed' THEN t.amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN t.task_status = 'pending' THEN t.amount ELSE 0 END) as pending_amount
                FROM tasks t
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(t.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(t.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $sql .= " GROUP BY DATE(t.created_at) ORDER BY date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Generate report based on type
 */
function generateReport($pdo, $type, $format, $filters = []) {
    $data = [];
    $filename = '';
    $headers = [];
    
    switch ($type) {
        case 'tasks':
            $data = getTasksReportData($pdo, $filters);
            $filename = 'tasks_report_' . date('Y-m-d') . '.csv';
            $headers = ['ID', 'Task Code', 'User', 'Email', 'Seller', 'Amount', 'Status', 'Created', 'Completed'];
            break;
            
        case 'users':
            $data = getUsersReportData($pdo, $filters);
            $filename = 'users_report_' . date('Y-m-d') . '.csv';
            $headers = ['ID', 'Name', 'Email', 'Mobile', 'Type', 'Status', 'Joined', 'Wallet', 'Total Tasks', 'Completed'];
            break;
            
        case 'financial':
            $data = getFinancialReportData($pdo, $filters);
            $filename = 'financial_report_' . date('Y-m-d') . '.csv';
            $headers = ['Date', 'Total Tasks', 'Total Amount', 'Paid Amount', 'Pending Amount'];
            break;
    }
    
    if ($format === 'csv') {
        exportToCSV($data, $filename, $headers);
    }
    
    return ['success' => false, 'message' => 'Format not supported'];
}
