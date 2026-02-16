<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/referral-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');

$message = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        foreach ($_POST['levels'] as $level => $data) {
            $commission = floatval($data['commission']);
            $is_active = isset($data['active']) ? 1 : 0;
            updateReferralSettings($pdo, $level, $commission, $is_active);
        }
        $message = 'Referral settings updated successfully!';
    } else {
        $error = 'Invalid security token';
    }
}

// Get current settings (with fallback)
try {
    $settings = getReferralSettings($pdo);
} catch (PDOException $e) {
    $error = 'Database error: Could not load referral settings - ' . $e->getMessage();
    $settings = [];
}

// Get referral statistics (safe)
$total_referrals = 0;
$active_referrals = 0;
$total_earnings = 0;
$pending_earnings = 0;

try {
    $total_referrals_stmt = $pdo->query("SELECT COUNT(*) FROM referrals WHERE level = 1");
    $total_referrals = $total_referrals_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Referral stats error (total): " . $e->getMessage());
}

try {
    $active_referrals_stmt = $pdo->query("SELECT COUNT(*) FROM referrals WHERE level = 1 AND status = 'active'");
    $active_referrals = $active_referrals_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Referral stats error (active): " . $e->getMessage());
}

try {
    $total_earnings_stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM referral_earnings WHERE status = 'credited'");
    $total_earnings = $total_earnings_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Referral stats error (earnings): " . $e->getMessage());
}

try {
    $pending_earnings_stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM referral_earnings WHERE status = 'pending'");
    $pending_earnings = $pending_earnings_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Referral stats error (pending): " . $e->getMessage());
}

// Get recent referrals (safe)
$recent_referrals = [];
try {
    // Check if 'username' column exists, fallback to 'name'
    $columns_stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
    $has_username = $columns_stmt->rowCount() > 0;
    $name_col = $has_username ? 'username' : 'name';

    $recent_stmt = $pdo->query("
        SELECT 
            r.*,
            u1.{$name_col} as referrer_name,
            u2.{$name_col} as referee_name,
            u2.email as referee_email
        FROM referrals r
        JOIN users u1 ON r.referrer_id = u1.id
        JOIN users u2 ON r.referee_id = u2.id
        WHERE r.level = 1
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $recent_referrals = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent referrals error: " . $e->getMessage());
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
/* Admin Layout */
.admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}

/* Sidebar styles */
.sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
.sidebar-menu{list-style:none;padding:15px 0}
.sidebar-menu li{margin-bottom:5px}
.sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
.sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
.sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
.sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
.menu-section-label{padding:8px 20px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px}
.sidebar-menu a.logout{color:#e74c3c}

/* Main Content */
.main-content{padding:25px;overflow-x:hidden}
</style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
            <h2 class="mb-4"><i class="bi bi-gear-fill"></i> Referral System Settings</h2>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h3><?php echo (int)$total_referrals; ?></h3>
                            <p class="mb-0">Total Referrals</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h3><?php echo (int)$active_referrals; ?></h3>
                            <p class="mb-0">Active Referrals</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h3>₹<?php echo number_format((float)$total_earnings, 2); ?></h3>
                            <p class="mb-0">Total Earnings Paid</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h3>₹<?php echo number_format((float)$pending_earnings, 2); ?></h3>
                            <p class="mb-0">Pending Earnings</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Commission Settings Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-percent"></i> Commission Rate Settings</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($settings)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> No referral levels found. Please check that the <code>referral_settings</code> table has data.
                        </div>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Level</th>
                                        <th>Commission (%)</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($settings as $setting): ?>
                                    <tr>
                                        <td><strong>Level <?php echo (int)$setting['level']; ?></strong></td>
                                        <td>
                                            <div class="input-group" style="width: 150px;">
                                                <input type="number" step="0.01" min="0" max="100"
                                                       class="form-control" 
                                                       name="levels[<?php echo (int)$setting['level']; ?>][commission]"
                                                       value="<?php echo htmlspecialchars($setting['commission_percent']); ?>" required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($setting['level'] == 1): ?>
                                                Direct referrals (users directly referred by referrer)
                                            <?php elseif ($setting['level'] == 2): ?>
                                                Second level (referrals from your direct referrals)
                                            <?php else: ?>
                                                Third level (referrals from second level referrals)
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="levels[<?php echo (int)$setting['level']; ?>][active]"
                                                       <?php echo $setting['is_active'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Active</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>How it works:</strong>
                            <ul class="mb-0 mt-2">
                                <li>When a user completes a task, their referrers earn commission based on these rates</li>
                                <li>Commission is calculated as a percentage of the task amount</li>
                                <li>Commissions are automatically credited to referrer's wallet</li>
                                <li>You can disable any level by unchecking the "Active" status</li>
                            </ul>
                        </div>

                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Settings
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Referrals -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-clock-history"></i> Recent Referrals</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recent_referrals) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Referrer</th>
                                    <th>Referee</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_referrals as $ref): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ref['referrer_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($ref['referee_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($ref['referee_email'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (($ref['status'] ?? '') == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php elseif (($ref['status'] ?? '') == 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo isset($ref['created_at']) ? date('M d, Y', strtotime($ref['created_at'])) : 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No referrals yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
