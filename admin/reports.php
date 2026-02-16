<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];

// Date filters
$date_from = $_GET['from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['to'] ?? date('Y-m-d'); // Today
$report_type = $_GET['type'] ?? 'overview';

// Validate dates
if (!strtotime($date_from)) $date_from = date('Y-m-01');
if (!strtotime($date_to)) $date_to = date('Y-m-d');

// Export handlers
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    
    try {
        switch ($export_type) {
            case 'users':
                $stmt = $pdo->prepare("
                    SELECT u.id, u.name, u.email, u.mobile, u.status, u.referral_code, u.created_at,
                           COALESCE(us.tasks_completed, 0) as tasks_completed,
                           COALESCE(us.total_earnings, 0) as total_earnings,
                           COALESCE(uw.balance, 0) as wallet_balance
                    FROM users u
                    LEFT JOIN user_stats us ON u.id = us.user_id
                    LEFT JOIN user_wallet uw ON u.id = uw.user_id
                    WHERE u.user_type = 'user' AND DATE(u.created_at) BETWEEN ? AND ?
                    ORDER BY u.created_at DESC
                ");
                $stmt->execute([$date_from, $date_to]);
                $data = $stmt->fetchAll();
                
                exportCSV($data, 'users_report_' . date('Y-m-d') . '.csv', [
                    'ID', 'Name', 'Email', 'Mobile', 'Status', 'Referral Code', 'Joined', 'Tasks Completed', 'Total Earnings', 'Wallet Balance'
                ]);
                break;
                
            case 'tasks':
                $stmt = $pdo->prepare("
                    SELECT t.id, u.name as user_name, t.product_link, t.task_status, t.commission,
                           t.deadline, t.refund_requested, t.created_at,
                           (SELECT COUNT(*) FROM task_steps WHERE task_id = t.id AND step_status = 'completed') as completed_steps
                    FROM tasks t
                    JOIN users u ON t.user_id = u.id
                    WHERE DATE(t.created_at) BETWEEN ? AND ?
                    ORDER BY t.created_at DESC
                ");
                $stmt->execute([$date_from, $date_to]);
                $data = $stmt->fetchAll();
                
                exportCSV($data, 'tasks_report_' . date('Y-m-d') . '.csv', [
                    'Task ID', 'User', 'Product Link', 'Status', 'Commission', 'Deadline', 'Refund Requested', 'Created', 'Steps Completed'
                ]);
                break;
                
            case 'transactions':
                $stmt = $pdo->prepare("
                    SELECT wt.id, u.name as user_name, wt.type, wt.amount, wt.balance_after,
                           wt.description, wt.status, wt.created_at
                    FROM wallet_transactions wt
                    JOIN users u ON wt.user_id = u.id
                    WHERE DATE(wt.created_at) BETWEEN ? AND ?
                    ORDER BY wt.created_at DESC
                ");
                $stmt->execute([$date_from, $date_to]);
                $data = $stmt->fetchAll();
                
                exportCSV($data, 'transactions_report_' . date('Y-m-d') . '.csv', [
                    'ID', 'User', 'Type', 'Amount', 'Balance After', 'Description', 'Status', 'Date'
                ]);
                break;
                
            case 'withdrawals':
                $stmt = $pdo->prepare("
                    SELECT wr.id, u.name as user_name, u.email, wr.amount, wr.payment_method,
                           wr.payment_details, wr.status, wr.admin_note, wr.processed_by,
                           wr.created_at, wr.processed_at
                    FROM withdrawal_requests wr
                    JOIN users u ON wr.user_id = u.id
                    WHERE DATE(wr.created_at) BETWEEN ? AND ?
                    ORDER BY wr.created_at DESC
                ");
                $stmt->execute([$date_from, $date_to]);
                $data = $stmt->fetchAll();
                
                exportCSV($data, 'withdrawals_report_' . date('Y-m-d') . '.csv', [
                    'ID', 'User', 'Email', 'Amount', 'Method', 'Payment Details', 'Status', 'Admin Note', 'Processed By', 'Requested', 'Processed'
                ]);
                break;
                
            case 'referrals':
                $stmt = $pdo->prepare("
                    SELECT r.id, referrer.name as referrer_name, referred.name as referred_name,
                           r.bonus_amount, r.status, r.created_at, r.completed_at
                    FROM referrals r
                    JOIN users referrer ON r.referrer_id = referrer.id
                    JOIN users referred ON r.referred_id = referred.id
                    WHERE DATE(r.created_at) BETWEEN ? AND ?
                    ORDER BY r.created_at DESC
                ");
                $stmt->execute([$date_from, $date_to]);
                $data = $stmt->fetchAll();
                
                exportCSV($data, 'referrals_report_' . date('Y-m-d') . '.csv', [
                    'ID', 'Referrer', 'Referred User', 'Bonus Amount', 'Status', 'Created', 'Completed'
                ]);
                break;
                
            case 'gst':
                // Export GST report for seller transactions
                $stmt = $pdo->prepare("
                    SELECT 
                        pt.id as transaction_id,
                        DATE(pt.created_at) as transaction_date,
                        s.name as seller_name,
                        s.gst_number as seller_gst,
                        s.company_name,
                        rr.id as invoice_number,
                        rr.product_name,
                        rr.reviews_needed as quantity,
                        rr.total_amount as taxable_amount,
                        rr.gst_amount as gst_amount,
                        rr.grand_total as total_amount,
                        pt.payment_gateway as payment_method,
                        pt.gateway_payment_id as payment_reference,
                        gs.gst_number as company_gst,
                        gs.legal_name as company_name,
                        gs.state_code
                    FROM payment_transactions pt
                    JOIN sellers s ON pt.seller_id = s.id
                    LEFT JOIN review_requests rr ON pt.review_request_id = rr.id
                    LEFT JOIN gst_settings gs ON gs.is_active = 1
                    WHERE pt.status = 'success' 
                    AND DATE(pt.created_at) BETWEEN ? AND ?
                    ORDER BY pt.created_at DESC
                ");
                $stmt->execute([$date_from, $date_to]);
                $data = $stmt->fetchAll();
                
                // Check if JSON export is requested
                if (isset($_GET['format']) && $_GET['format'] === 'json') {
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename="gst_report_' . date('Y-m-d') . '.json"');
                    
                    $json_data = [
                        'report_title' => 'GST Report',
                        'period' => [
                            'from' => $date_from,
                            'to' => $date_to
                        ],
                        'generated_at' => date('Y-m-d H:i:s'),
                        'total_transactions' => count($data),
                        'total_taxable_amount' => array_sum(array_column($data, 'taxable_amount')),
                        'total_gst_amount' => array_sum(array_column($data, 'gst_amount')),
                        'total_amount' => array_sum(array_column($data, 'total_amount')),
                        'transactions' => $data
                    ];
                    
                    echo json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    // CSV export
                    exportCSV($data, 'gst_report_' . date('Y-m-d') . '.csv', [
                        'Transaction ID', 'Date', 'Seller Name', 'Seller GST', 'Company', 'Invoice #', 
                        'Product', 'Quantity', 'Taxable Amount', 'GST Amount', 'Total Amount', 
                        'Payment Method', 'Payment Reference', 'Our GST', 'Our Company', 'State Code'
                    ]);
                }
                break;
        }
    } catch (PDOException $e) {
        error_log("Export Error: " . $e->getMessage());
    }
}

// Get report data
try {
    // Overview Stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'user' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $new_users = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $new_tasks = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE task_status = 'completed' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $completed_tasks = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type IN ('credit', 'bonus', 'referral') AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $total_credited = (float)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $total_withdrawn = (float)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawal_requests WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $withdrawal_requests = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $new_referrals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(bonus_amount), 0) FROM referrals WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $referral_bonus_paid = (float)$stmt->fetchColumn();
    
    // Daily breakdown for chart
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users WHERE user_type = 'user' AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at) ORDER BY date ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $daily_users = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM tasks WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at) ORDER BY date ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $daily_tasks = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, 
               SUM(CASE WHEN type IN ('credit', 'bonus', 'referral') THEN amount ELSE 0 END) as credited,
               SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as withdrawn
        FROM wallet_transactions WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at) ORDER BY date ASC
    ");
    $stmt->execute([$date_from, $date_to]);
    $daily_transactions = $stmt->fetchAll();
    
    // Top performers
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, COUNT(t.id) as task_count, COALESCE(SUM(t.commission), 0) as earnings
        FROM users u
        LEFT JOIN tasks t ON u.id = t.user_id AND t.task_status = 'completed' AND DATE(t.created_at) BETWEEN ? AND ?
        WHERE u.user_type = 'user'
        GROUP BY u.id
        HAVING task_count > 0
        ORDER BY task_count DESC
        LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to]);
    $top_performers = $stmt->fetchAll();
    
    // Recent registrations
    $stmt = $pdo->prepare("
        SELECT id, name, email, mobile, created_at
        FROM users WHERE user_type = 'user' AND DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC LIMIT 10
    ");
    $stmt->execute([$date_from, $date_to]);
    $recent_users = $stmt->fetchAll();
    
    // Task status breakdown
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN task_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN task_status = 'pending' AND refund_requested = 1 THEN 1 ELSE 0 END) as pending_refund,
            SUM(CASE WHEN task_status = 'pending' AND refund_requested = 0 THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN task_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM tasks WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    $task_breakdown = $stmt->fetch();
    
    // Withdrawal status breakdown
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM withdrawal_requests WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$date_from, $date_to]);
    $withdrawal_breakdown = $stmt->fetchAll(PDO::FETCH_UNIQUE);
    
    // Payment method breakdown
    $stmt = $pdo->prepare("
        SELECT payment_method, COUNT(*) as count, COALESCE(SUM(amount), 0) as total
        FROM withdrawal_requests WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY payment_method
    ");
    $stmt->execute([$date_from, $date_to]);
    $payment_method_breakdown = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Reports Error: " . $e->getMessage());
    $new_users = $new_tasks = $completed_tasks = $withdrawal_requests = $new_referrals = 0;
    $total_credited = $total_withdrawn = $referral_bonus_paid = 0;
    $daily_users = $daily_tasks = $daily_transactions = [];
    $top_performers = $recent_users = [];
    $task_breakdown = ['completed' => 0, 'pending_refund' => 0, 'in_progress' => 0, 'cancelled' => 0];
    $withdrawal_breakdown = $payment_method_breakdown = [];
}

// Helper function for CSV export
function exportCSV($data, $filename, $headers) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($output, $headers);
    
    foreach ($data as $row) {
        fputcsv($output, array_values($row));
    }
    
    fclose($output);
    exit;
}

// Get sidebar badge counts
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND is_read = 0");
    $unread_messages = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_questions = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks_count = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_withdrawals = $unread_messages = $unanswered_questions = $pending_tasks_count = 0;
}

// Generate dates array for chart
$dates = [];
$current = strtotime($date_from);
$end = strtotime($date_to);
while ($current <= $end) {
    $dates[] = date('Y-m-d', $current);
    $current = strtotime('+1 day', $current);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        
        /* Sidebar */
        .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
        .sidebar-menu{list-style:none;padding:15px 0}
        .sidebar-menu li{margin-bottom:5px}
        .sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
        .sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
        .sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
        .sidebar-menu a.logout{color:#e74c3c}
        
        .main-content{padding:25px;overflow-x:hidden}
        
        /* Header */
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:15px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .page-subtitle{color:#64748b;font-size:14px;margin-top:5px}
        
        /* Filters */
        .filters-card{background:#fff;border-radius:12px;padding:20px;margin-bottom:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .filters-row{display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end}
        .filter-group{display:flex;flex-direction:column;gap:5px}
        .filter-group label{font-size:12px;font-weight:600;color:#64748b}
        .filter-group input,.filter-group select{padding:10px 15px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px}
        .filter-group input:focus,.filter-group select:focus{border-color:#667eea;outline:none}
        .filter-actions{display:flex;gap:10px;margin-left:auto}
        .btn{padding:10px 20px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-secondary{background:#f1f5f9;color:#475569}
        .btn-success{background:#10b981;color:#fff}
        .btn:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        
        /* Quick Stats */
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .stat-card.highlight{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .stat-icon{font-size:24px;margin-bottom:10px}
        .stat-value{font-size:28px;font-weight:700;margin-bottom:3px}
        .stat-label{font-size:13px;opacity:0.8}
        .stat-change{font-size:11px;margin-top:8px;display:flex;align-items:center;gap:4px}
        .stat-change.up{color:#10b981}
        .stat-change.down{color:#ef4444}
        .stat-card.highlight .stat-change{color:rgba(255,255,255,0.8)}
        
        /* Charts Row */
        .charts-row{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:25px}
        .chart-card{background:#fff;border-radius:12px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04)}
        .chart-title{font-size:16px;font-weight:600;color:#1e293b;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
        .chart-title span{font-size:12px;color:#64748b;font-weight:400}
        
        /* Line Chart */
        .line-chart{height:250px;position:relative}
        .chart-canvas{width:100%;height:100%}
        
        /* Donut/Pie */
        .pie-chart{display:flex;align-items:center;gap:25px}
        .pie-visual{width:150px;height:150px;border-radius:50%;position:relative}
        .pie-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:80px;height:80px;background:#fff;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center}
        .pie-value{font-size:24px;font-weight:700;color:#1e293b}
        .pie-label{font-size:11px;color:#64748b}
        .pie-legend{flex:1}
        .legend-item{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:13px}
        .legend-color{width:12px;height:12px;border-radius:3px}
        .legend-text{color:#64748b;flex:1}
        .legend-value{font-weight:600;color:#1e293b}
        
        /* Bar Chart */
        .bar-chart{display:flex;align-items:flex-end;gap:8px;height:200px;padding:10px 0}
        .bar-item{flex:1;display:flex;flex-direction:column;align-items:center}
        .bar{width:100%;max-width:40px;border-radius:4px 4px 0 0;transition:height 0.3s;cursor:pointer}
        .bar:hover{opacity:0.8}
        .bar.primary{background:linear-gradient(180deg,#667eea,#764ba2)}
        .bar.success{background:linear-gradient(180deg,#10b981,#059669)}
        .bar.warning{background:linear-gradient(180deg,#f59e0b,#d97706)}
        .bar-label{font-size:10px;color:#94a3b8;margin-top:8px;text-align:center}
        
        /* Tables */
        .table-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:25px}
        .table-header{padding:20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center}
        .table-title{font-size:16px;font-weight:600;color:#1e293b}
        .table-action{font-size:13px;color:#667eea;text-decoration:none;font-weight:500}
        table{width:100%;border-collapse:collapse}
        th{background:#f8fafc;padding:12px 15px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase}
        td{padding:12px 15px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#1e293b}
        tr:last-child td{border-bottom:none}
        tr:hover{background:#f8fafc}
        
        .rank-badge{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:12px}
        .rank-1{background:#fef3c7;color:#d97706}
        .rank-2{background:#e5e7eb;color:#4b5563}
        .rank-3{background:#fed7aa;color:#c2410c}
        .rank-other{background:#f3f4f6;color:#6b7280}
        
        .status-badge{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}
        .status-badge.completed{background:#dcfce7;color:#16a34a}
        .status-badge.pending{background:#fef3c7;color:#d97706}
        .status-badge.rejected{background:#fee2e2;color:#dc2626}
        
        /* Export Buttons */
        .export-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:25px}
        .export-btn{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:15px;text-align:center;text-decoration:none;transition:all 0.2s}
        .export-btn:hover{border-color:#667eea;background:#f8f9ff;transform:translateY(-2px)}
        .export-btn .icon{font-size:24px;margin-bottom:8px}
        .export-btn .label{font-size:13px;font-weight:600;color:#1e293b}
        .export-btn .desc{font-size:11px;color:#64748b;margin-top:3px}
        
        /* Two Column */
        .two-column{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        
        /* Responsive */
        @media(max-width:1200px){
            .stats-grid{grid-template-columns:repeat(2,1fr)}
            .charts-row{grid-template-columns:1fr}
            .export-grid{grid-template-columns:repeat(3,1fr)}
        }
        @media(max-width:992px){
            .admin-layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .two-column{grid-template-columns:1fr}
        }
        @media(max-width:768px){
            .filters-row{flex-direction:column}
            .filter-group{width:100%}
            .filter-group input,.filter-group select{width:100%}
            .filter-actions{width:100%;margin-left:0}
            .filter-actions .btn{flex:1}
            .stats-grid{grid-template-columns:1fr}
            .export-grid{grid-template-columns:repeat(2,1fr)}
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <div class="page-title">📈 Reports & Analytics</div>
                <div class="page-subtitle">Data from <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?></div>
            </div>
        </div>
        
        <!-- Date Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" name="from" value="<?php echo $date_from; ?>">
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" name="to" value="<?php echo $date_to; ?>">
                </div>
                <div class="filter-group">
                    <label>Quick Range</label>
                    <select onchange="setDateRange(this.value)">
                        <option value="">Select...</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="week">Last 7 Days</option>
                        <option value="month">This Month</option>
                        <option value="lastmonth">Last Month</option>
                        <option value="quarter">Last 3 Months</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">📊 Generate Report</button>
                </div>
            </form>
        </div>
        
        <!-- Export Buttons -->
        <div class="export-grid">
            <a href="?from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&export=users" class="export-btn">
                <div class="icon">👥</div>
                <div class="label">Users Report</div>
                <div class="desc">Export as CSV</div>
            </a>
            <a href="?from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&export=tasks" class="export-btn">
                <div class="icon">📋</div>
                <div class="label">Tasks Report</div>
                <div class="desc">Export as CSV</div>
            </a>
            <a href="?from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&export=transactions" class="export-btn">
                <div class="icon">💳</div>
                <div class="label">Transactions</div>
                <div class="desc">Export as CSV</div>
            </a>
            <a href="?from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&export=withdrawals" class="export-btn">
                <div class="icon">💸</div>
                <div class="label">Withdrawals</div>
                <div class="desc">Export as CSV</div>
            </a>
            <a href="?from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&export=referrals" class="export-btn">
                <div class="icon">🎁</div>
                <div class="label">Referrals</div>
                <div class="desc">Export as CSV</div>
            </a>
            <a href="?from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&export=gst" class="export-btn">
                <div class="icon">💰</div>
                <div class="label">GST Report</div>
                <div class="desc">Export as CSV</div>
            </a>
            <a href="?from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&export=gst&format=json" class="export-btn" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff">
                <div class="icon">📊</div>
                <div class="label">GST Report</div>
                <div class="desc">Export as JSON</div>
            </a>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card highlight">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?php echo number_format($new_users); ?></div>
                <div class="stat-label">New Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?php echo number_format($new_tasks); ?></div>
                <div class="stat-label">Tasks Created</div>
                <div class="stat-change"><?php echo $completed_tasks; ?> completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">₹<?php echo number_format($total_credited, 0); ?></div>
                <div class="stat-label">Total Credited</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💸</div>
                <div class="stat-value">₹<?php echo number_format($total_withdrawn, 0); ?></div>
                <div class="stat-label">Total Withdrawn</div>
                <div class="stat-change"><?php echo $withdrawal_requests; ?> requests</div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="charts-row">
            <!-- Daily Trend -->
            <div class="chart-card">
                <div class="chart-title">
                    📈 Daily Trend
                    <span>Users & Tasks</span>
                </div>
                <div class="bar-chart" id="dailyChart">
                    <?php 
                    $max_val = 1;
                    foreach ($dates as $date) {
                        $max_val = max($max_val, $daily_users[$date] ?? 0, $daily_tasks[$date] ?? 0);
                    }
                    
                    // Show last 14 days max
                    $show_dates = array_slice($dates, -14);
                    foreach ($show_dates as $date): 
                        $users = $daily_users[$date] ?? 0;
                        $tasks = $daily_tasks[$date] ?? 0;
                        $user_h = $max_val > 0 ? ($users / $max_val) * 150 : 0;
                        $task_h = $max_val > 0 ? ($tasks / $max_val) * 150 : 0;
                    ?>
                        <div class="bar-item">
                            <div style="display:flex;gap:3px;align-items:flex-end;height:160px">
                                <div class="bar primary" style="height:<?php echo max(5, $user_h); ?>px;width:15px" title="Users: <?php echo $users; ?>"></div>
                                <div class="bar success" style="height:<?php echo max(5, $task_h); ?>px;width:15px" title="Tasks: <?php echo $tasks; ?>"></div>
                            </div>
                            <div class="bar-label"><?php echo date('d/m', strtotime($date)); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;justify-content:center;gap:20px;margin-top:15px;font-size:12px;color:#64748b">
                    <span><span style="display:inline-block;width:12px;height:12px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:2px;margin-right:5px"></span> Users</span>
                    <span><span style="display:inline-block;width:12px;height:12px;background:linear-gradient(135deg,#10b981,#059669);border-radius:2px;margin-right:5px"></span> Tasks</span>
                </div>
            </div>
            
            <!-- Task Breakdown -->
            <div class="chart-card">
                <div class="chart-title">📋 Task Status</div>
                <?php 
                $task_total = ($task_breakdown['completed'] ?? 0) + ($task_breakdown['pending_refund'] ?? 0) + ($task_breakdown['in_progress'] ?? 0) + ($task_breakdown['cancelled'] ?? 0);
                $completed_deg = $task_total > 0 ? (($task_breakdown['completed'] ?? 0) / $task_total) * 360 : 0;
                $pending_deg = $task_total > 0 ? (($task_breakdown['pending_refund'] ?? 0) / $task_total) * 360 : 0;
                $progress_deg = $task_total > 0 ? (($task_breakdown['in_progress'] ?? 0) / $task_total) * 360 : 0;
                ?>
                <div class="pie-chart">
                    <div class="pie-visual" style="background:conic-gradient(#10b981 0deg <?php echo $completed_deg; ?>deg, #f59e0b <?php echo $completed_deg; ?>deg <?php echo $completed_deg + $pending_deg; ?>deg, #3b82f6 <?php echo $completed_deg + $pending_deg; ?>deg <?php echo $completed_deg + $pending_deg + $progress_deg; ?>deg, #ef4444 <?php echo $completed_deg + $pending_deg + $progress_deg; ?>deg 360deg)">
                        <div class="pie-center">
                            <div class="pie-value"><?php echo $task_total; ?></div>
                            <div class="pie-label">Total</div>
                        </div>
                    </div>
                    <div class="pie-legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background:#10b981"></div>
                            <span class="legend-text">Completed</span>
                            <span class="legend-value"><?php echo $task_breakdown['completed'] ?? 0; ?></span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background:#f59e0b"></div>
                            <span class="legend-text">Pending Refund</span>
                            <span class="legend-value"><?php echo $task_breakdown['pending_refund'] ?? 0; ?></span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background:#3b82f6"></div>
                            <span class="legend-text">In Progress</span>
                            <span class="legend-value"><?php echo $task_breakdown['in_progress'] ?? 0; ?></span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background:#ef4444"></div>
                            <span class="legend-text">Cancelled</span>
                            <span class="legend-value"><?php echo $task_breakdown['cancelled'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Two Column Section -->
        <div class="two-column">
            <!-- Top Performers -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">🏆 Top Performers</div>
                </div>
                <?php if (empty($top_performers)): ?>
                    <div style="padding:40px;text-align:center;color:#94a3b8">No data for selected period</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>User</th>
                            <th>Tasks</th>
                            <th>Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_performers as $index => $performer): ?>
                        <tr>
                            <td>
                                <span class="rank-badge <?php echo $index < 3 ? 'rank-' . ($index + 1) : 'rank-other'; ?>">
                                    <?php echo $index + 1; ?>
                                </span>
                            </td>
                            <td><?php echo escape($performer['name']); ?></td>
                            <td><?php echo $performer['task_count']; ?></td>
                            <td style="font-weight:600;color:#10b981">₹<?php echo number_format($performer['earnings'], 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <!-- Recent Registrations -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">👥 Recent Registrations</div>
                </div>
                <?php if (empty($recent_users)): ?>
                    <div style="padding:40px;text-align:center;color:#94a3b8">No registrations in selected period</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td><?php echo escape($user['name']); ?></td>
                            <td style="font-size:12px;color:#64748b"><?php echo escape($user['email']); ?></td>
                            <td style="font-size:12px"><?php echo date('d M, H:i', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Withdrawal & Payment Methods -->
        <div class="two-column">
            <!-- Withdrawal Status -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">💸 Withdrawal Summary</div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $statuses = ['pending', 'approved', 'completed', 'rejected'];
                        foreach ($statuses as $status):
                            $data = $withdrawal_breakdown[$status] ?? ['count' => 0, 'total' => 0];
                        ?>
                        <tr>
                            <td><span class="status-badge <?php echo $status; ?>"><?php echo ucfirst($status); ?></span></td>
                            <td><?php echo $data['count'] ?? 0; ?></td>
                            <td style="font-weight:600">₹<?php echo number_format($data['total'] ?? 0, 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Payment Methods -->
            <div class="table-card">
                <div class="table-header">
                    <div class="table-title">💳 Payment Methods</div>
                </div>
                <?php if (empty($payment_method_breakdown)): ?>
                    <div style="padding:40px;text-align:center;color:#94a3b8">No withdrawal data</div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Count</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_method_breakdown as $method): ?>
                        <tr>
                            <td>
                                <?php echo $method['payment_method'] === 'upi' ? '📱' : ($method['payment_method'] === 'bank' ? '🏦' : '💳'); ?>
                                <?php echo strtoupper($method['payment_method']); ?>
                            </td>
                            <td><?php echo $method['count']; ?></td>
                            <td style="font-weight:600">₹<?php echo number_format($method['total'], 0); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Referral Stats -->
        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
            <div class="stat-card">
                <div class="stat-icon">🎁</div>
                <div class="stat-value"><?php echo number_format($new_referrals); ?></div>
                <div class="stat-label">New Referrals</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">₹<?php echo number_format($referral_bonus_paid, 0); ?></div>
                <div class="stat-label">Referral Bonus Paid</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo $new_referrals > 0 ? round(($referral_bonus_paid / (getSetting('referral_bonus', 50) ?: 1)), 0) : 0; ?></div>
                <div class="stat-label">Successful Referrals</div>
            </div>
        </div>
    </div>
</div>

<script>
function setDateRange(range) {
    const today = new Date();
    let from, to;
    
    switch(range) {
        case 'today':
            from = to = formatDate(today);
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            from = to = formatDate(yesterday);
            break;
        case 'week':
            const weekAgo = new Date(today);
            weekAgo.setDate(weekAgo.getDate() - 7);
            from = formatDate(weekAgo);
            to = formatDate(today);
            break;
        case 'month':
            from = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
            to = formatDate(today);
            break;
        case 'lastmonth':
            const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
            from = formatDate(lastMonth);
            to = formatDate(lastMonthEnd);
            break;
        case 'quarter':
            const quarterAgo = new Date(today);
            quarterAgo.setMonth(quarterAgo.getMonth() - 3);
            from = formatDate(quarterAgo);
            to = formatDate(today);
            break;
        case 'year':
            from = formatDate(new Date(today.getFullYear(), 0, 1));
            to = formatDate(today);
            break;
        default:
            return;
    }
    
    document.querySelector('input[name="from"]').value = from;
    document.querySelector('input[name="to"]').value = to;
}

function formatDate(date) {
    return date.toISOString().split('T')[0];
}
</script>
</body>
</html>
