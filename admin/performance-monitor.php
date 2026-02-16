<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/performance-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');

// Get performance metrics
$metrics = [
    'cache_hit_rate' => 0,
    'cache_size' => 0,
    'queue_pending' => 0,
    'queue_failed' => 0,
    'avg_response_time' => 0,
    'slow_queries' => 0
];

try {
    // Cache metrics
    if (function_exists('apcu_cache_info')) {
        $cacheInfo = apcu_cache_info();
        $metrics['cache_hit_rate'] = $cacheInfo['num_hits'] > 0 ? 
            ($cacheInfo['num_hits'] / ($cacheInfo['num_hits'] + $cacheInfo['num_misses'])) * 100 : 0;
        $metrics['cache_size'] = $cacheInfo['mem_size'] ?? 0;
    }
    
    // Queue metrics
    $stmt = $pdo->query("SELECT COUNT(*) FROM job_queue WHERE status = 'pending'");
    $metrics['queue_pending'] = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM job_queue WHERE status = 'failed'");
    $metrics['queue_failed'] = (int)$stmt->fetchColumn();
    
    // Slow queries
    $stmt = $pdo->query("SELECT COUNT(*) FROM slow_query_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $metrics['slow_queries'] = (int)$stmt->fetchColumn();
    
    // Response time
    $stmt = $pdo->query("SELECT AVG(response_time) FROM api_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $metrics['avg_response_time'] = (float)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Performance metrics error: " . $e->getMessage());
}

// Get slow queries
$slowQueries = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM slow_query_log 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY query_time DESC 
        LIMIT 20
    ");
    $slowQueries = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Slow queries error: " . $e->getMessage());
}

$current_page = 'performance-monitor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Monitor - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:25px}
        .stat{background:#fff;padding:20px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .stat .val{font-size:32px;font-weight:700;color:#667eea}
        .stat .lbl{font-size:13px;color:#888;margin-top:5px}
        .stat.good .val{color:#10b981}
        .stat.warning .val{color:#f59e0b}
        .stat.bad .val{color:#ef4444}
        table{width:100%;border-collapse:collapse;font-size:12px}
        table th{background:#f8f9fa;padding:8px;text-align:left;font-weight:600;border-bottom:2px solid #dee2e6}
        table td{padding:8px;border-bottom:1px solid #f0f0f0}
        .query-text{font-family:monospace;font-size:11px;max-width:500px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.stats{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-speedometer"></i> Performance Monitor</h3>
                <button class="btn btn-primary" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>

            <div class="stats">
                <div class="stat <?= $metrics['cache_hit_rate'] >= 80 ? 'good' : ($metrics['cache_hit_rate'] >= 50 ? 'warning' : 'bad') ?>">
                    <div class="val"><?= number_format($metrics['cache_hit_rate'], 1) ?>%</div>
                    <div class="lbl">Cache Hit Rate</div>
                </div>
                
                <div class="stat">
                    <div class="val"><?= formatBytes($metrics['cache_size']) ?></div>
                    <div class="lbl">Cache Size</div>
                </div>
                
                <div class="stat <?= $metrics['queue_pending'] > 100 ? 'warning' : 'good' ?>">
                    <div class="val"><?= number_format($metrics['queue_pending']) ?></div>
                    <div class="lbl">Pending Jobs</div>
                </div>
                
                <div class="stat <?= $metrics['queue_failed'] > 10 ? 'bad' : 'good' ?>">
                    <div class="val"><?= number_format($metrics['queue_failed']) ?></div>
                    <div class="lbl">Failed Jobs</div>
                </div>
                
                <div class="stat <?= $metrics['avg_response_time'] > 1000 ? 'bad' : ($metrics['avg_response_time'] > 500 ? 'warning' : 'good') ?>">
                    <div class="val"><?= number_format($metrics['avg_response_time']) ?>ms</div>
                    <div class="lbl">Avg Response Time</div>
                </div>
                
                <div class="stat <?= $metrics['slow_queries'] > 50 ? 'bad' : ($metrics['slow_queries'] > 20 ? 'warning' : 'good') ?>">
                    <div class="val"><?= number_format($metrics['slow_queries']) ?></div>
                    <div class="lbl">Slow Queries (1h)</div>
                </div>
            </div>

            <div class="card">
                <h5 class="mb-4">Slow Query Log (Last 24 Hours)</h5>
                
                <?php if (empty($slowQueries)): ?>
                    <p class="text-muted text-center py-4">No slow queries detected</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Query Time</th>
                                    <th>Query</th>
                                    <th>Rows Examined</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($slowQueries as $query): ?>
                                    <tr>
                                        <td><?= date('H:i:s', strtotime($query['created_at'])) ?></td>
                                        <td><strong><?= number_format($query['query_time'], 3) ?>s</strong></td>
                                        <td><div class="query-text"><?= escape($query['sql_text']) ?></div></td>
                                        <td><?= number_format($query['rows_examined'] ?? 0) ?></td>
                                        <td><?= escape($query['user_host'] ?? 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <h6 class="mb-3">System Resources</h6>
                        <div class="mb-2">
                            <small>Memory Usage</small>
                            <div class="progress" style="height:20px">
                                <div class="progress-bar" style="width:<?= memory_get_usage(true) / memory_get_peak_usage(true) * 100 ?>%">
                                    <?= formatBytes(memory_get_usage(true)) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <h6 class="mb-3">Quick Actions</h6>
                        <button class="btn btn-sm btn-outline-danger w-100 mb-2" onclick="clearCache()">
                            <i class="bi bi-trash"></i> Clear Cache
                        </button>
                        <button class="btn btn-sm btn-outline-warning w-100" onclick="retryFailedJobs()">
                            <i class="bi bi-arrow-clockwise"></i> Retry Failed Jobs
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function clearCache() {
            if (!confirm('Clear all cache?')) return;
            alert('Cache clearing - implement endpoint');
        }
        
        function retryFailedJobs() {
            if (!confirm('Retry all failed jobs?')) return;
            alert('Job retry - implement endpoint');
        }
        
        // Auto-refresh every 30 seconds
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
<?php
function formatBytes($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>
