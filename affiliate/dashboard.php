<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/header.php';

// Get date range for filtering
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

try {
    // Get earnings summary
    $stmt = $pdo->prepare("
        SELECT 
            SUM(amount) as total_earnings,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_earnings,
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_earnings,
            SUM(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN amount ELSE 0 END) as period_earnings
        FROM affiliate_commissions
        WHERE affiliate_id = ?
    ");
    $stmt->execute([$start_date, $end_date, $affiliate_id]);
    $earnings = $stmt->fetch();
    
    // Get referral statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_referrals,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_referrals,
            SUM(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 ELSE 0 END) as period_referrals
        FROM affiliate_referrals
        WHERE affiliate_id = ?
    ");
    $stmt->execute([$start_date, $end_date, $affiliate_id]);
    $referrals = $stmt->fetch();
    
    // Get commission breakdown by level
    $stmt = $pdo->prepare("
        SELECT 
            level,
            COUNT(*) as count,
            SUM(amount) as total_amount
        FROM affiliate_commissions
        WHERE affiliate_id = ? AND DATE(created_at) >= ? AND DATE(created_at) <= ?
        GROUP BY level
        ORDER BY level
    ");
    $stmt->execute([$affiliate_id, $start_date, $end_date]);
    $commission_breakdown = $stmt->fetchAll();
    
    // Get recent commissions
    $stmt = $pdo->prepare("
        SELECT ac.*, ar.referred_user_id as user_id, u.username
        FROM affiliate_commissions ac
        LEFT JOIN affiliate_referrals ar ON ar.id = ac.referral_id
        LEFT JOIN users u ON u.id = ar.referred_user_id
        WHERE ac.affiliate_id = ?
        ORDER BY ac.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$affiliate_id]);
    $recent_commissions = $stmt->fetchAll();
    
    // Get link performance
    $stmt = $pdo->prepare("
        SELECT 
            al.id,
            al.link_name,
            al.short_code,
            COUNT(alc.id) as clicks,
            COUNT(DISTINCT CASE WHEN alc.converted = 1 THEN alc.id END) as conversions,
            ROUND(COUNT(DISTINCT CASE WHEN alc.converted = 1 THEN alc.id END) * 100.0 / NULLIF(COUNT(alc.id), 0), 2) as conversion_rate
        FROM affiliate_links al
        LEFT JOIN affiliate_link_clicks alc ON alc.link_id = al.id
        WHERE al.affiliate_id = ?
        GROUP BY al.id
        ORDER BY clicks DESC
        LIMIT 5
    ");
    $stmt->execute([$affiliate_id]);
    $top_links = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Affiliate dashboard error: ' . $e->getMessage());
    $earnings = [
        'total_earnings' => 0,
        'pending_earnings' => 0,
        'paid_earnings' => 0,
        'period_earnings' => 0
    ];
    $referrals = [
        'total_referrals' => 0,
        'active_referrals' => 0,
        'period_referrals' => 0
    ];
    $commission_breakdown = [];
    $recent_commissions = [];
    $top_links = [];
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h3 class="mb-0">Affiliate Dashboard</h3>
            <p class="text-muted">Welcome back, <?= htmlspecialchars($affiliate['name']) ?>!</p>
        </div>
    </div>
    
    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Earnings</div>
                        <h3 class="mb-0">₹<?= number_format($earnings['total_earnings'], 2) ?></h3>
                    </div>
                    <div class="text-success">
                        <i class="bi bi-currency-rupee fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Pending</div>
                        <h3 class="mb-0">₹<?= number_format($earnings['pending_earnings'], 2) ?></h3>
                    </div>
                    <div class="text-warning">
                        <i class="bi bi-clock-history fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">This Period</div>
                        <h3 class="mb-0">₹<?= number_format($earnings['period_earnings'], 2) ?></h3>
                    </div>
                    <div class="text-primary">
                        <i class="bi bi-calendar-check fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Referrals</div>
                        <h3 class="mb-0"><?= number_format($referrals['total_referrals']) ?></h3>
                        <small class="text-success"><?= $referrals['active_referrals'] ?> active</small>
                    </div>
                    <div class="text-info">
                        <i class="bi bi-people fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Commission Breakdown -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Commission Breakdown</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($commission_breakdown)): ?>
                    <p class="text-muted text-center py-4">No commission data for this period</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th>Count</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commission_breakdown as $breakdown): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">Level <?= $breakdown['level'] ?></span>
                                    </td>
                                    <td><?= number_format($breakdown['count']) ?></td>
                                    <td>₹<?= number_format($breakdown['total_amount'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Top Performing Links -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Top Performing Links</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($top_links)): ?>
                    <p class="text-muted text-center py-4">No link data available</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Link Name</th>
                                    <th>Clicks</th>
                                    <th>Conversions</th>
                                    <th>Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_links as $link): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($link['link_name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($link['short_code']) ?></small>
                                    </td>
                                    <td><?= number_format($link['clicks']) ?></td>
                                    <td><?= number_format($link['conversions']) ?></td>
                                    <td>
                                        <span class="badge bg-success"><?= $link['conversion_rate'] ?>%</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Commissions -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Recent Commissions</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Referral</th>
                            <th>Level</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_commissions)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No commissions yet. Start sharing your links to earn!
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent_commissions as $commission): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($commission['created_at'])) ?></td>
                            <td><?= htmlspecialchars($commission['username'] ?? 'Direct') ?></td>
                            <td>
                                <span class="badge bg-primary">Level <?= $commission['level'] ?></span>
                            </td>
                            <td>₹<?= number_format($commission['amount'], 2) ?></td>
                            <td>
                                <?php if ($commission['status'] === 'pending'): ?>
                                <span class="badge bg-warning">Pending</span>
                                <?php elseif ($commission['status'] === 'paid'): ?>
                                <span class="badge bg-success">Paid</span>
                                <?php else: ?>
                                <span class="badge bg-secondary"><?= ucfirst($commission['status']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
