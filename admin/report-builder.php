<?php
/**
 * Report Builder - Create Custom Reports
 * Simple report generation interface
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/security.php';

// Check admin authentication
if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$admin_id = $_SESSION['admin_id'] ?? 1;
$page_title = 'Report Builder';

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $reportType = $_POST['report_type'] ?? 'users';
    $dateRange = $_POST['date_range'] ?? 'last_30_days';
    $format = $_POST['format'] ?? 'html';
    
    // Generate report based on type
    $reportData = [];
    $reportTitle = '';
    
    switch ($reportType) {
        case 'users':
            $reportTitle = 'User Activity Report';
            $reportData = $conn->query("
                SELECT 
                    u.id,
                    u.name,
                    u.email,
                    u.mobile,
                    u.user_type,
                    u.created_at,
                    COALESCE(COUNT(t.id), 0) as total_tasks,
                    COALESCE(SUM(t.reward), 0) as total_earned
                FROM users u
                LEFT JOIN tasks t ON u.id = t.user_id AND t.task_status = 'completed'
                WHERE u.user_type = 'user'
                GROUP BY u.id
                ORDER BY total_earned DESC
                LIMIT 100
            ")->fetch_all(MYSQLI_ASSOC);
            break;
            
        case 'tasks':
            $reportTitle = 'Task Completion Report';
            $reportData = $conn->query("
                SELECT 
                    t.id,
                    t.title,
                    t.brand,
                    u.name as assigned_to,
                    t.task_status,
                    t.reward,
                    t.created_at,
                    t.completed_at
                FROM tasks t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.task_status IN ('completed', 'paid')
                ORDER BY t.completed_at DESC
                LIMIT 100
            ")->fetch_all(MYSQLI_ASSOC);
            break;
            
        case 'revenue':
            $reportTitle = 'Revenue Summary';
            $reportData = $conn->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as transactions,
                    SUM(amount) as total_revenue
                FROM payment_transactions
                WHERE status = 'success'
                GROUP BY DATE(created_at)
                ORDER BY date DESC
                LIMIT 30
            ")->fetch_all(MYSQLI_ASSOC);
            break;
            
        case 'withdrawals':
            $reportTitle = 'Withdrawal Report';
            $reportData = $conn->query("
                SELECT 
                    wr.id,
                    u.name as user_name,
                    wr.amount,
                    wr.status,
                    wr.payment_method,
                    wr.created_at,
                    wr.processed_at
                FROM withdrawal_requests wr
                JOIN users u ON wr.user_id = u.id
                ORDER BY wr.created_at DESC
                LIMIT 100
            ")->fetch_all(MYSQLI_ASSOC);
            break;
    }
    
    $reportGenerated = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - ReviewFlow Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .form-section {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .form-group {
            margin: 15px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        select, input[type="date"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th { background: #f9fafb; font-weight: 600; }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background: #eff6ff;
            border-radius: 8px;
        }
        @media print {
            .form-section, .btn { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $page_title; ?></h1>
        <p>Generate custom reports with your preferred filters and export formats.</p>
        
        <!-- Report Generation Form -->
        <div class="form-section">
            <h2>Configure Report</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Report Type</label>
                    <select name="report_type" required>
                        <option value="users">User Activity Report</option>
                        <option value="tasks">Task Completion Report</option>
                        <option value="revenue">Revenue Summary</option>
                        <option value="withdrawals">Withdrawal Report</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Date Range</label>
                    <select name="date_range">
                        <option value="today">Today</option>
                        <option value="last_7_days">Last 7 Days</option>
                        <option value="last_30_days" selected>Last 30 Days</option>
                        <option value="this_month">This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="all_time">All Time</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Export Format</label>
                    <select name="format">
                        <option value="html">HTML (View in Browser)</option>
                        <option value="csv">CSV (Download)</option>
                        <option value="pdf">PDF (Coming Soon)</option>
                    </select>
                </div>
                
                <button type="submit" name="generate_report" class="btn btn-primary">Generate Report</button>
            </form>
        </div>
        
        <!-- Display Generated Report -->
        <?php if (isset($reportGenerated) && $reportGenerated): ?>
            <div class="report-header">
                <h2><?php echo htmlspecialchars($reportTitle); ?></h2>
                <div>
                    <button onclick="window.print()" class="btn btn-secondary">Print</button>
                    <button onclick="exportToCSV()" class="btn btn-primary">Export CSV</button>
                </div>
            </div>
            
            <?php if (!empty($reportData)): ?>
                <table id="reportTable">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($reportData[0]) as $column): ?>
                                <th><?php echo ucwords(str_replace('_', ' ', $column)); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <?php foreach ($row as $value): ?>
                                    <td><?php echo htmlspecialchars($value ?? ''); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No data available for the selected report.</p>
            <?php endif; ?>
        <?php endif; ?>
        
        <div style="margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            <a href="scheduled-reports.php" class="btn btn-primary">Scheduled Reports</a>
        </div>
    </div>
    
    <script>
        function exportToCSV() {
            const table = document.getElementById('reportTable');
            if (!table) return;
            
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let row of rows) {
                let cols = row.querySelectorAll('td, th');
                let csvRow = [];
                for (let col of cols) {
                    csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
                }
                csv.push(csvRow.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'report_' + new Date().toISOString().slice(0,10) + '.csv';
            a.click();
        }
    </script>
</body>
</html>
