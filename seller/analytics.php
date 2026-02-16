<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/analytics-functions.php';
require_once __DIR__ . '/includes/header.php';

// Get seller analytics data
$analytics = getSellerAnalytics($pdo, $seller_id);

// Prepare monthly spending data
$monthlyLabels = [];
$monthlyValues = [];
foreach ($analytics['monthly_spending'] as $row) {
    $monthlyLabels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthlyValues[] = $row['spending'];
}

// Calculate completion rate
$completionRate = 0;
if ($analytics['total_requests'] > 0) {
    $completionRate = round(($analytics['completed_requests'] / $analytics['total_requests']) * 100, 2);
}

// Get spending trends for last 30 days
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COALESCE(SUM(grand_total), 0) as spending
    FROM review_requests
    WHERE seller_id = ? 
    AND payment_status = 'paid'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$seller_id]);
$trendData = $stmt->fetchAll();

$trendDates = [];
$trendValues = [];
foreach ($trendData as $row) {
    $trendDates[] = date('M d', strtotime($row['date']));
    $trendValues[] = $row['spending'];
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h3 class="mb-0"><i class="bi bi-graph-up"></i> Analytics Dashboard</h3>
            <p class="text-muted">Track your spending and request performance</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Requests</div>
                        <h3 class="mb-0"><?= number_format($analytics['total_requests'] ?? 0) ?></h3>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-file-text"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Completed</div>
                        <h3 class="mb-0"><?= number_format($analytics['completed_requests'] ?? 0) ?></h3>
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
                        <h3 class="mb-0"><?= number_format($analytics['pending_requests'] ?? 0) ?></h3>
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
                        <div class="text-muted small mb-1">Total Spent</div>
                        <h3 class="mb-0">₹<?= number_format($analytics['total_spent'] ?? 0, 2) ?></h3>
                    </div>
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-3">Completion Rate</h6>
                    <div class="d-flex align-items-center">
                        <h2 class="mb-0 me-2"><?= $completionRate ?>%</h2>
                        <i class="bi bi-graph-up text-success fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-3">Avg Reviews Per Request</h6>
                    <div class="d-flex align-items-center">
                        <h2 class="mb-0 me-2"><?= $analytics['avg_reviews_per_request'] ?? 0 ?></h2>
                        <i class="bi bi-star text-warning fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-3">Wallet Balance</h6>
                    <div class="d-flex align-items-center">
                        <h2 class="mb-0 me-2">₹<?= number_format($analytics['wallet_balance'] ?? 0, 2) ?></h2>
                        <i class="bi bi-wallet2 text-primary fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="bi bi-graph-up"></i> Spending Trends (Last 30 Days)</h5>
                    <div style="height: 300px;">
                        <canvas id="spendingTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i class="bi bi-bar-chart"></i> Monthly Spending (Last 6 Months)</h5>
                    <div style="height: 300px;">
                        <canvas id="monthlySpendingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    // Spending Trends Chart
    const trendCtx = document.getElementById('spendingTrendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($trendDates) ?>,
            datasets: [{
                label: 'Daily Spending (₹)',
                data: <?= json_encode($trendValues) ?>,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
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

    // Monthly Spending Chart
    const monthlyCtx = document.getElementById('monthlySpendingChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthlyLabels) ?>,
            datasets: [{
                label: 'Monthly Spending (₹)',
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
