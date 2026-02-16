<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Filters
$filter_type = $_GET['type'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';
$filter_date = $_GET['date'] ?? 'all';
$search = sanitizeInput($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query
$where = "user_id = ?";
$params = [$user_id];

if ($filter_type !== 'all') {
    $where .= " AND type = ?";
    $params[] = $filter_type;
}

if ($filter_status !== 'all') {
    $where .= " AND status = ?";
    $params[] = $filter_status;
}

if ($filter_date !== 'all') {
    switch ($filter_date) {
        case 'today':
            $where .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $where .= " AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
    }
}

if (!empty($search)) {
    $where .= " AND description LIKE ?";
    $params[] = "%$search%";
}

// Get transactions
try {
    // Count total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $total_pages = ceil($total / $per_page);
    
    // Get transactions
    $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    // Get summary stats
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type IN ('credit', 'bonus', 'referral') THEN amount ELSE 0 END) as total_credit,
            SUM(CASE WHEN type IN ('debit', 'withdrawal') THEN amount ELSE 0 END) as total_debit,
            COUNT(CASE WHEN type IN ('credit', 'bonus', 'referral') THEN 1 END) as credit_count,
            COUNT(CASE WHEN type IN ('debit', 'withdrawal') THEN 1 END) as debit_count
        FROM wallet_transactions 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $summary = $stmt->fetch();
    
    // Get wallet balance
    $wallet_balance = getWalletBalance($user_id);
    
    // Get monthly data for chart (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(CASE WHEN type IN ('credit', 'bonus', 'referral') THEN amount ELSE 0 END) as credits,
            SUM(CASE WHEN type IN ('debit', 'withdrawal') THEN amount ELSE 0 END) as debits
        FROM wallet_transactions 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$user_id]);
    $monthly_data = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Transactions Error: " . $e->getMessage());
    $transactions = [];
    $total = 0;
    $total_pages = 0;
    $summary = ['total_credit' => 0, 'total_debit' => 0, 'credit_count' => 0, 'debit_count' => 0];
    $wallet_balance = 0;
    $monthly_data = [];
}

// Export to CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $stmt = $pdo->prepare("SELECT id, type, amount, balance_after, description, status, created_at FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $export_data = $stmt->fetchAll();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Type', 'Amount', 'Balance After', 'Description', 'Status', 'Date']);
        
        foreach ($export_data as $row) {
            fputcsv($output, [
                $row['id'],
                ucfirst($row['type']),
                $row['amount'],
                $row['balance_after'],
                $row['description'],
                ucfirst($row['status']),
                $row['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        // Continue to show page
    }
}

// Transaction type styles
function getTransactionStyle($type) {
    $styles = [
        'credit' => ['icon' => '↓', 'color' => '#27ae60', 'bg' => '#e8f5e9', 'label' => 'Credit'],
        'debit' => ['icon' => '↑', 'color' => '#e74c3c', 'bg' => '#ffebee', 'label' => 'Debit'],
        'withdrawal' => ['icon' => '💸', 'color' => '#e74c3c', 'bg' => '#ffebee', 'label' => 'Withdrawal'],
        'bonus' => ['icon' => '🎁', 'color' => '#f39c12', 'bg' => '#fff8e1', 'label' => 'Bonus'],
        'referral' => ['icon' => '👥', 'color' => '#9b59b6', 'bg' => '#f3e5f5', 'label' => 'Referral'],
    ];
    return $styles[$type] ?? $styles['credit'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px}
        
        .container{max-width:1100px;margin:0 auto}
        
        .back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fff;color:#333;text-decoration:none;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:14px;transition:transform 0.2s;box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        .back-btn:hover{transform:translateY(-2px)}
        
        /* Summary Cards */
        .summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}
        .summary-card{background:#fff;border-radius:15px;padding:20px;box-shadow:0 5px 20px rgba(0,0,0,0.1);position:relative;overflow:hidden}
        .summary-card::after{content:'';position:absolute;top:0;right:0;width:60px;height:60px;border-radius:0 0 0 60px;opacity:0.1}
        .summary-card.balance{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .summary-card.balance::after{background:#fff}
        .summary-card.credit::after{background:#27ae60}
        .summary-card.debit::after{background:#e74c3c}
        .summary-card.count::after{background:#3498db}
        .summary-icon{font-size:24px;margin-bottom:8px}
        .summary-value{font-size:28px;font-weight:700}
        .summary-card.balance .summary-value{font-size:32px}
        .summary-label{font-size:12px;opacity:0.8;margin-top:3px}
        
        /* Chart Section */
        .chart-card{background:#fff;border-radius:15px;padding:25px;margin-bottom:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .chart-title{font-size:18px;font-weight:600;color:#333;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
        .chart-container{height:200px;display:flex;align-items:flex-end;gap:15px;padding:10px 0}
        .chart-bar-group{flex:1;display:flex;flex-direction:column;align-items:center}
        .chart-bars{display:flex;gap:4px;align-items:flex-end;height:150px}
        .chart-bar{width:20px;border-radius:5px 5px 0 0;transition:height 0.3s}
        .chart-bar.credit{background:linear-gradient(180deg,#27ae60,#2ecc71)}
        .chart-bar.debit{background:linear-gradient(180deg,#e74c3c,#c0392b)}
        .chart-label{font-size:11px;color:#888;margin-top:8px}
        
        /* Filters */
        .filters-card{background:#fff;border-radius:15px;padding:20px;margin-bottom:20px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .filters-row{display:flex;gap:15px;flex-wrap:wrap;align-items:center}
        .filter-group{display:flex;flex-direction:column;gap:5px}
        .filter-group label{font-size:12px;font-weight:600;color:#666}
        .filter-group select,.filter-group input{padding:10px 15px;border:2px solid #eee;border-radius:8px;font-size:13px;min-width:140px}
        .filter-group select:focus,.filter-group input:focus{border-color:#667eea;outline:none}
        .filter-actions{margin-left:auto;display:flex;gap:10px}
        .btn{padding:10px 20px;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;transition:all 0.2s;display:inline-flex;align-items:center;gap:6px}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-secondary{background:#f5f5f5;color:#666}
        .btn-success{background:#27ae60;color:#fff}
        .btn:hover{transform:translateY(-2px)}
        
        /* Transactions Table */
        .table-card{background:#fff;border-radius:15px;overflow:hidden;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .table-header{padding:20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
        .table-title{font-size:18px;font-weight:600;color:#333}
        .table-count{font-size:14px;color:#888}
        
        .table-responsive{overflow-x:auto}
        table{width:100%;border-collapse:collapse}
        th{background:#f8f9fa;padding:15px;text-align:left;font-size:12px;font-weight:600;color:#666;text-transform:uppercase;letter-spacing:0.5px}
        td{padding:15px;border-bottom:1px solid #f5f5f5;font-size:14px}
        tr:hover{background:#fafafa}
        tr:last-child td{border-bottom:none}
        
        .txn-type{display:inline-flex;align-items:center;gap:8px}
        .txn-icon{width:35px;height:35px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px}
        .txn-label{font-weight:600}
        
        .txn-amount{font-weight:700;font-size:15px}
        .txn-amount.credit{color:#27ae60}
        .txn-amount.debit{color:#e74c3c}
        
        .txn-status{padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600}
        .txn-status.completed{background:#d4edda;color:#155724}
        .txn-status.pending{background:#fff3cd;color:#856404}
        .txn-status.failed{background:#f8d7da;color:#721c24}
        
        .txn-desc{max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#666}
        .txn-date{color:#888;font-size:13px}
        .txn-balance{color:#888;font-size:13px}
        
        /* Empty State */
        .empty-state{text-align:center;padding:60px 20px}
        .empty-state .icon{font-size:60px;margin-bottom:20px;opacity:0.5}
        .empty-state h3{color:#666;margin-bottom:10px}
        .empty-state p{color:#888;font-size:14px}
        
        /* Pagination */
        .pagination{display:flex;justify-content:center;gap:8px;padding:20px}
        .page-btn{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#f5f5f5;color:#666;text-decoration:none;font-weight:600;font-size:14px;transition:all 0.2s;border:none;cursor:pointer}
        .page-btn:hover{background:#667eea;color:#fff}
        .page-btn.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .page-btn.disabled{opacity:0.5;cursor:not-allowed;pointer-events:none}
        
        /* Responsive */
        @media(max-width:768px){
            .summary-grid{grid-template-columns:repeat(2,1fr)}
            .filters-row{flex-direction:column}
            .filter-group{width:100%}
            .filter-group select,.filter-group input{width:100%}
            .filter-actions{width:100%;margin-left:0;margin-top:10px}
            .filter-actions .btn{flex:1}
            th,td{padding:10px;font-size:12px}
            .txn-icon{width:30px;height:30px;font-size:14px}
            .txn-desc{max-width:120px}
        }
    </style>
</head>
<body>
<div class="container">
    <a href="<?php echo APP_URL; ?>/user/" class="back-btn">← Back to Dashboard</a>
    
    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card balance">
            <div class="summary-icon">💰</div>
            <div class="summary-value">₹<?php echo number_format($wallet_balance, 2); ?></div>
            <div class="summary-label">Current Balance</div>
        </div>
        <div class="summary-card credit">
            <div class="summary-icon">📥</div>
            <div class="summary-value">₹<?php echo number_format($summary['total_credit'] ?? 0, 2); ?></div>
            <div class="summary-label">Total Credited (<?php echo $summary['credit_count'] ?? 0; ?>)</div>
        </div>
        <div class="summary-card debit">
            <div class="summary-icon">📤</div>
            <div class="summary-value">₹<?php echo number_format($summary['total_debit'] ?? 0, 2); ?></div>
            <div class="summary-label">Total Debited (<?php echo $summary['debit_count'] ?? 0; ?>)</div>
        </div>
        <div class="summary-card count">
            <div class="summary-icon">📊</div>
            <div class="summary-value"><?php echo $total; ?></div>
            <div class="summary-label">Total Transactions</div>
        </div>
    </div>
    
    <!-- Chart -->
    <?php if (!empty($monthly_data)): ?>
    <div class="chart-card">
        <div class="chart-title">
            <span>📈 Monthly Overview</span>
            <span style="font-size:12px;color:#888">Last 6 months</span>
        </div>
        <div class="chart-container">
            <?php 
            $max_value = 1;
            foreach ($monthly_data as $m) {
                $max_value = max($max_value, $m['credits'], $m['debits']);
            }
            foreach ($monthly_data as $m): 
                $credit_height = ($m['credits'] / $max_value) * 130;
                $debit_height = ($m['debits'] / $max_value) * 130;
            ?>
                <div class="chart-bar-group">
                    <div class="chart-bars">
                        <div class="chart-bar credit" style="height:<?php echo max(5, $credit_height); ?>px" title="Credit: ₹<?php echo number_format($m['credits'], 0); ?>"></div>
                        <div class="chart-bar debit" style="height:<?php echo max(5, $debit_height); ?>px" title="Debit: ₹<?php echo number_format($m['debits'], 0); ?>"></div>
                    </div>
                    <div class="chart-label"><?php echo date('M', strtotime($m['month'] . '-01')); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:center;gap:20px;margin-top:10px;font-size:12px">
            <span><span style="display:inline-block;width:12px;height:12px;background:#27ae60;border-radius:3px;margin-right:5px"></span> Credits</span>
            <span><span style="display:inline-block;width:12px;height:12px;background:#e74c3c;border-radius:3px;margin-right:5px"></span> Debits</span>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" class="filters-row">
            <div class="filter-group">
                <label>Type</label>
                <select name="type">
                    <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="credit" <?php echo $filter_type === 'credit' ? 'selected' : ''; ?>>Credit</option>
                    <option value="debit" <?php echo $filter_type === 'debit' ? 'selected' : ''; ?>>Debit</option>
                    <option value="withdrawal" <?php echo $filter_type === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                    <option value="bonus" <?php echo $filter_type === 'bonus' ? 'selected' : ''; ?>>Bonus</option>
                    <option value="referral" <?php echo $filter_type === 'referral' ? 'selected' : ''; ?>>Referral</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Date Range</label>
                <select name="date">
                    <option value="all" <?php echo $filter_date === 'all' ? 'selected' : ''; ?>>All Time</option>
                    <option value="today" <?php echo $filter_date === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo $filter_date === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $filter_date === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="year" <?php echo $filter_date === 'year' ? 'selected' : ''; ?>>Last Year</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Search description..." value="<?php echo escape($search); ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">🔍 Filter</button>
                <a href="?" class="btn btn-secondary">↺ Reset</a>
                <a href="?export=csv" class="btn btn-success">📥 Export CSV</a>
            </div>
        </form>
    </div>
    
    <!-- Transactions Table -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">📋 Transaction History</div>
            <div class="table-count"><?php echo $total; ?> transactions found</div>
        </div>
        
        <?php if (empty($transactions)): ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <h3>No Transactions Found</h3>
                <p>
                    <?php if ($filter_type !== 'all' || $filter_status !== 'all' || $filter_date !== 'all' || !empty($search)): ?>
                        Try adjusting your filters. <a href="?" style="color:#667eea">Clear filters</a>
                    <?php else: ?>
                        Your transaction history will appear here.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Balance After</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): 
                            $style = getTransactionStyle($txn['type']);
                            $is_credit = in_array($txn['type'], ['credit', 'bonus', 'referral']);
                        ?>
                            <tr>
                                <td>
                                    <div class="txn-type">
                                        <div class="txn-icon" style="background:<?php echo $style['bg']; ?>;color:<?php echo $style['color']; ?>">
                                            <?php echo $style['icon']; ?>
                                        </div>
                                        <span class="txn-label"><?php echo $style['label']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="txn-amount <?php echo $is_credit ? 'credit' : 'debit'; ?>">
                                        <?php echo $is_credit ? '+' : '-'; ?>₹<?php echo number_format($txn['amount'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="txn-desc" title="<?php echo escape($txn['description']); ?>">
                                        <?php echo escape($txn['description']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="txn-balance">₹<?php echo number_format($txn['balance_after'], 2); ?></span>
                                </td>
                                <td>
                                    <span class="txn-status <?php echo $txn['status']; ?>">
                                        <?php echo ucfirst($txn['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="txn-date">
                                        <?php echo date('d M Y', strtotime($txn['created_at'])); ?><br>
                                        <small style="color:#aaa"><?php echo date('H:i', strtotime($txn['created_at'])); ?></small>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $query_params = $_GET;
                    unset($query_params['page']);
                    $query_string = http_build_query($query_params);
                    $query_string = $query_string ? "&$query_string" : '';
                    ?>
                    <a href="?page=1<?php echo $query_string; ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">«</a>
                    <a href="?page=<?php echo max(1, $page - 1) . $query_string; ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">‹</a>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="?page=<?php echo $i . $query_string; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <a href="?page=<?php echo min($total_pages, $page + 1) . $query_string; ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">›</a>
                    <a href="?page=<?php echo $total_pages . $query_string; ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">»</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto submit on filter change
document.querySelectorAll('.filters-card select').forEach(select => {
    select.addEventListener('change', function() {
        this.closest('form').submit();
    });
});
</script>
</body>
</html>
