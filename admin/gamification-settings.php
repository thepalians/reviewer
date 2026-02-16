<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gamification-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');

$message = '';
$error = '';

// Safe stats
$total_users = 0;
$total_points = 0;
$total_badges = 0;
$level_dist = [];
$level_settings = [];
$all_badges = [];
$recent_transactions = [];

// Check if 'username' or 'name' column exists in users
$name_col = 'name';
try {
    $col_check = $pdo->query("SHOW COLUMNS FROM users LIKE 'username'");
    if ($col_check->rowCount() > 0) {
        $name_col = 'username';
    }
} catch (PDOException $e) {}

// 1. Total users in gamification
try {
    $total_users_stmt = $pdo->query("SELECT COUNT(*) FROM user_points");
    $total_users = (int)$total_users_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Gamification stats error (users): " . $e->getMessage());
}

// 2. Total points earned
try {
    $total_points_stmt = $pdo->query("SELECT COALESCE(SUM(total_earned), 0) FROM user_points");
    $total_points = (int)$total_points_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Gamification stats error (points): " . $e->getMessage());
}

// 3. Total badges awarded
try {
    $total_badges_stmt = $pdo->query("SELECT COUNT(*) FROM user_badges");
    $total_badges = (int)$total_badges_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Gamification stats error (badges): " . $e->getMessage());
}

// 4. Level distribution
try {
    $level_dist = $pdo->query("
        SELECT level, COUNT(*) as count 
        FROM user_points 
        GROUP BY level 
        ORDER BY 
            CASE level
                WHEN 'Diamond' THEN 5
                WHEN 'Platinum' THEN 4
                WHEN 'Gold' THEN 3
                WHEN 'Silver' THEN 2
                ELSE 1
            END DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Gamification stats error (level dist): " . $e->getMessage());
}

// 5. Level settings
try {
    $level_settings = getLevelSettings($pdo);
} catch (PDOException $e) {
    error_log("Gamification stats error (level settings): " . $e->getMessage());
}

// 6. All badges - detect column names
try {
    $badge_col_check = $pdo->query("SHOW COLUMNS FROM badges LIKE 'badge_name'");
    $badge_name_col = ($badge_col_check->rowCount() > 0) ? 'badge_name' : 'name';

    $badge_pts_check = $pdo->query("SHOW COLUMNS FROM badges LIKE 'points_required'");
    $has_points_required = $badge_pts_check->rowCount() > 0;

    if ($has_points_required) {
        $all_badges = $pdo->query("SELECT id, {$badge_name_col} as name, description, icon FROM badges WHERE is_active = 1 ORDER BY points_required ASC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $all_badges = $pdo->query("SELECT id, {$badge_name_col} as name, description, icon FROM badges WHERE is_active = 1 ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Gamification stats error (badges): " . $e->getMessage());
}

// 7. Recent point transactions
try {
    $recent_transactions = $pdo->query("
        SELECT pt.*, u.{$name_col} as username
        FROM point_transactions pt
        JOIN users u ON pt.user_id = u.id
        ORDER BY pt.created_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Gamification stats error (transactions): " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gamification Settings - Admin Panel</title>
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
        <h2 class="mb-4"><i class="bi bi-trophy-fill"></i> Gamification System</h2>

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
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h3><?php echo number_format($total_users); ?></h3>
                            <p class="mb-0">Total Users in System</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h3><?php echo number_format($total_points); ?></h3>
                            <p class="mb-0">Total Points Earned</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h3><?php echo number_format($total_badges); ?></h3>
                            <p class="mb-0">Total Badges Awarded</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Level Distribution -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-bar-chart"></i> User Level Distribution</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($level_settings)): ?>
                        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No level settings found. Check <code>level_settings</code> table.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Level</th>
                                    <th>Users</th>
                                    <th>Point Range</th>
                                    <th>Perks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($level_settings as $level): ?>
                                <tr>
                                    <td>
                                        <strong style="color: <?php echo htmlspecialchars($level['badge_color'] ?? '#333'); ?>">
                                            <?php echo htmlspecialchars($level['level_name'] ?? ''); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php
                                        $count = 0;
                                        foreach ($level_dist as $dist) {
                                            if ($dist['level'] == ($level['level_name'] ?? '')) {
                                                $count = $dist['count'];
                                                break;
                                            }
                                        }
                                        echo (int)$count;
                                        ?>
                                    </td>
                                    <td><?php echo number_format((int)($level['min_points'] ?? 0)); ?> - <?php echo number_format((int)($level['max_points'] ?? 0)); ?></td>
                                    <td><?php echo htmlspecialchars($level['perks'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Badges Overview -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="bi bi-award"></i> Badge System</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($all_badges)): ?>
                        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No badges found. Check <code>badges</code> table.</div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($all_badges as $badge): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card text-center h-100">
                                <div class="card-body">
                                    <i class="<?php echo htmlspecialchars($badge['icon'] ?? 'bi bi-award'); ?>" 
                                       style="font-size: 2.5rem; color: #fbbf24;"></i>
                                    <h6 class="mt-2"><?php echo htmlspecialchars($badge['name'] ?? ''); ?></h6>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($badge['description'] ?? ''); ?></p>
                                    <div class="mt-2">
                                        <?php
                                        $count = 0;
                                        try {
                                            $earned_count = $pdo->prepare("SELECT COUNT(*) FROM user_badges WHERE badge_id = ?");
                                            $earned_count->execute([$badge['id']]);
                                            $count = $earned_count->fetchColumn();
                                        } catch (PDOException $e) {}
                                        ?>
                                        <span class="badge bg-success"><?php echo (int)$count; ?> users</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-clock-history"></i> Recent Point Transactions</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_transactions)): ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No point transactions yet</p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Points</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $trans): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($trans['username'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $trans['type'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($trans['description'] ?? ''); ?></td>
                                    <td>
                                        <strong class="<?php echo ($trans['points'] ?? 0) > 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo ($trans['points'] ?? 0) > 0 ? '+' : ''; ?><?php echo (int)($trans['points'] ?? 0); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo isset($trans['created_at']) ? date('M d, Y H:i', strtotime($trans['created_at'])) : 'N/A'; ?></td>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
