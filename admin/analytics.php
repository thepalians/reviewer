<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/analytics-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$current_page = 'analytics';

// Get analytics data
$summary = getDashboardSummary($pdo);
$revenueData = getRevenueStats($pdo, 30);
$userGrowthData = getUserGrowthStats($pdo, 30);
$taskStats = getTaskCompletionStats($pdo);
$topPerformers = getTopPerformers($pdo, 10);

// Prepare chart data
$revenueDates = [];
$revenueValues = [];
foreach ($revenueData as $row) {
    $revenueDates[] = date('M d', strtotime($row['date']));
    $revenueValues[] = $row['revenue'];
}

$userGrowthDates = [];
$userGrowthValues = [];
foreach ($userGrowthData as $row) {
    $userGrowthDates[] = date('M d', strtotime($row['date']));
    $userGrowthValues[] = $row['count'];
}

$taskLabels = [];
$taskValues = [];
$taskColors = [
    'completed' => '#059669',
    'pending' => '#f59e0b',
    'rejected' => '#dc2626',
    'in_progress' => '#3b82f6'
];
$chartColors = [];
foreach ($taskStats as $row) {
    $status = ucfirst(str_replace('_', ' ', $row['task_status']));
    $taskLabels[] = $status;
    $taskValues[] = $row['count'];
    $chartColors[] = $taskColors[$row['task_status']] ?? '#64748b';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php include __DIR__ . '/includes/styles.php'; ?>
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .stat-card.info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        .stat-card h3 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-card p {
            opacity: 0.9;
            font-size: 14px;
            margin: 0;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        @media (max-width: 768px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }
            .sidebar {
                display: none;
            }
            .chart-container {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="bi bi-graph-up"></i> Analytics Dashboard</h1>
                    <p class="page-subtitle">Comprehensive analytics and insights</p>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card">
                        <h3><?= number_format($summary['total_users'] ?? 0) ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card success">
                        <h3>₹<?= number_format($summary['total_revenue'] ?? 0, 2) ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card warning">
                        <h3><?= number_format($summary['total_tasks'] ?? 0) ?></h3>
                        <p>Total Tasks</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card info">
                        <h3><?= number_format($summary['completed_tasks'] ?? 0) ?></h3>
                        <p>Completed Tasks</p>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-graph-up"></i> Revenue Trends (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-pie-chart"></i> Task Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="taskChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row g-3 mb-4">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title"><i class="bi bi-bar-chart"></i> User Growth (Last 30 Days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="userGrowthChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performers Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title"><i class="bi bi-trophy"></i> Top 10 Performers</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Completed Tasks</th>
                                    <th>Total Earned</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($topPerformers)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No data available</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($topPerformers as $index => $performer): ?>
                                        <tr>
                                            <td>
                                                <?php if ($index === 0): ?>
                                                    <i class="bi bi-trophy-fill text-warning"></i> <?= $index + 1 ?>
                                                <?php elseif ($index === 1): ?>
                                                    <i class="bi bi-trophy-fill" style="color: #c0c0c0;"></i> <?= $index + 1 ?>
                                                <?php elseif ($index === 2): ?>
                                                    <i class="bi bi-trophy-fill" style="color: #cd7f32;"></i> <?= $index + 1 ?>
                                                <?php else: ?>
                                                    <?= $index + 1 ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($performer['username']) ?></td>
                                            <td><?= htmlspecialchars($performer['email']) ?></td>
                                            <td><span class="badge bg-primary"><?= $performer['completed_tasks'] ?></span></td>
                                            <td><strong>₹<?= number_format($performer['total_earned'], 2) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($revenueDates) ?>,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?= json_encode($revenueValues) ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Task Pie Chart
        const taskCtx = document.getElementById('taskChart').getContext('2d');
        new Chart(taskCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($taskLabels) ?>,
                datasets: [{
                    data: <?= json_encode($taskValues) ?>,
                    backgroundColor: <?= json_encode($chartColors) ?>
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // User Growth Bar Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(userGrowthCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($userGrowthDates) ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?= json_encode($userGrowthValues) ?>,
                    backgroundColor: '#059669',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
