<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/analytics-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$current_page = 'analytics';

// Get user analytics data
$analytics = getUserAnalytics($pdo, $user_id);

// Prepare monthly earnings data
$monthlyLabels = [];
$monthlyValues = [];
foreach ($analytics['monthly_earnings'] as $row) {
    $monthlyLabels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthlyValues[] = $row['earnings'];
}

// Get earnings trends for last 30 days
$stmt = $pdo->prepare("
    SELECT 
        DATE(completed_at) as date,
        COALESCE(SUM(reward_amount), 0) as earnings
    FROM tasks
    WHERE user_id = ? 
    AND task_status = 'completed'
    AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(completed_at)
    ORDER BY date ASC
");
$stmt->execute([$user_id]);
$trendData = $stmt->fetchAll();

$trendDates = [];
$trendValues = [];
foreach ($trendData as $row) {
    $trendDates[] = date('M d', strtotime($row['date']));
    $trendValues[] = $row['earnings'];
}

// Task distribution data
$taskDistribution = [
    'Completed' => $analytics['completed_tasks'] ?? 0,
    'Pending' => $analytics['pending_tasks'] ?? 0,
    'Rejected' => $analytics['rejected_tasks'] ?? 0
];
$distLabels = array_keys($taskDistribution);
$distValues = array_values($taskDistribution);
$distColors = ['#059669', '#f59e0b', '#dc2626'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings Analytics - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.5rem;
        }
        .navbar a {
            color: rgba(255,255,255,0.9) !important;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 1.5rem;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 1.5rem;
        }
        .card-title {
            font-weight: 600;
            font-size: 1.1rem;
        }
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .metric-card.success {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }
        .metric-card.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        .metric-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .metric-card p {
            opacity: 0.9;
            margin: 0;
        }
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-speedometer2"></i> <?= APP_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tasks.php">Tasks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="analytics.php">Analytics</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="wallet.php">Wallet</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-graph-up"></i> Earnings Analytics</h2>
                <p class="text-muted mb-0">Track your performance and earnings</p>
            </div>
        </div>

        <!-- Overview Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="metric-card">
                    <h3>₹<?= number_format($analytics['total_earnings'] ?? 0, 2) ?></h3>
                    <p>Total Earnings</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card success">
                    <h3>₹<?= number_format($analytics['wallet_balance'] ?? 0, 2) ?></h3>
                    <p>Current Balance</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card warning">
                    <h3>₹<?= number_format($analytics['total_withdrawn'] ?? 0, 2) ?></h3>
                    <p>Total Withdrawn</p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Total Tasks</div>
                            <h3 class="mb-0"><?= number_format($analytics['total_tasks'] ?? 0) ?></h3>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-list-task"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Completed</div>
                            <h3 class="mb-0"><?= number_format($analytics['completed_tasks'] ?? 0) ?></h3>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Pending</div>
                            <h3 class="mb-0"><?= number_format($analytics['pending_tasks'] ?? 0) ?></h3>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small mb-1">Success Rate</div>
                            <h3 class="mb-0"><?= $analytics['success_rate'] ?? 0 ?>%</h3>
                        </div>
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row g-3 mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4"><i class="bi bi-graph-up"></i> Earnings Trends (Last 30 Days)</h5>
                        <div style="height: 300px;">
                            <canvas id="earningsTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4"><i class="bi bi-pie-chart"></i> Task Distribution</h5>
                        <div style="height: 300px;">
                            <canvas id="taskDistChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4"><i class="bi bi-bar-chart"></i> Monthly Earnings (Last 6 Months)</h5>
                        <div style="height: 300px;">
                            <canvas id="monthlyEarningsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        // Earnings Trends Chart
        const trendCtx = document.getElementById('earningsTrendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendDates) ?>,
                datasets: [{
                    label: 'Daily Earnings (₹)',
                    data: <?= json_encode($trendValues) ?>,
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

        // Task Distribution Pie Chart
        const distCtx = document.getElementById('taskDistChart').getContext('2d');
        new Chart(distCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($distLabels) ?>,
                datasets: [{
                    data: <?= json_encode($distValues) ?>,
                    backgroundColor: <?= json_encode($distColors) ?>
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

        // Monthly Earnings Chart
        const monthlyCtx = document.getElementById('monthlyEarningsChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthlyLabels) ?>,
                datasets: [{
                    label: 'Monthly Earnings (₹)',
                    data: <?= json_encode($monthlyValues) ?>,
                    backgroundColor: '#059669',
                    borderRadius: 8
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
    </script>
</body>
</html>
