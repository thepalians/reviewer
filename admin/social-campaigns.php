<?php
/**
 * Admin — Social Campaigns Management
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/social-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$current_page = 'social-campaigns';

// Handle approve / reject / force-pause
$action_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action      = $_POST['action'] ?? '';
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);

    if ($campaign_id) {
        try {
            switch ($action) {
                case 'approve':
                    $pdo->prepare("UPDATE social_campaigns SET admin_approved = 1, status = 'active' WHERE id = ?")->execute([$campaign_id]);
                    $action_msg = '✅ Campaign approved and set to active.';
                    break;
                case 'reject':
                    $pdo->prepare("UPDATE social_campaigns SET admin_approved = 0, status = 'rejected' WHERE id = ?")->execute([$campaign_id]);
                    $action_msg = '❌ Campaign rejected.';
                    break;
                case 'pause':
                    $pdo->prepare("UPDATE social_campaigns SET status = 'paused' WHERE id = ?")->execute([$campaign_id]);
                    $action_msg = '⏸ Campaign force-paused.';
                    break;
            }
        } catch (PDOException $e) {
            error_log("admin social-campaigns action error: " . $e->getMessage());
            $action_msg = 'Action failed.';
        }
    }
}

// Filters
$filter_status   = $_GET['status'] ?? '';
$filter_platform = (int)($_GET['platform'] ?? 0);

// Build query
$where  = '1=1';
$params = [];
if ($filter_status) {
    $where .= ' AND sc.status = ?';
    $params[] = $filter_status;
}
if ($filter_platform) {
    $where .= ' AND sc.platform_id = ?';
    $params[] = $filter_platform;
}

try {
    $stmt = $pdo->prepare("
        SELECT sc.*, sp.name AS platform_name, sp.icon AS platform_icon, sp.color AS platform_color,
               s.name AS seller_name, s.email AS seller_email,
               (SELECT COUNT(*) FROM social_task_completions stc WHERE stc.campaign_id = sc.id AND stc.status IN ('completed','verified')) AS completions
        FROM social_campaigns sc
        JOIN social_platforms sp ON sp.id = sc.platform_id
        JOIN sellers s ON s.id = sc.seller_id
        WHERE $where
        ORDER BY sc.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("admin social-campaigns list error: " . $e->getMessage());
    $campaigns = [];
}

// Fetch platforms for filter dropdown
try {
    $platforms = $pdo->query("SELECT id, name FROM social_platforms WHERE is_active = 1 ORDER BY sort_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $platforms = [];
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📱 Social Campaigns — <?php echo htmlspecialchars(APP_NAME); ?> Admin</title>
<?php require_once __DIR__ . '/includes/styles.php'; ?>
<style>
        /* Status / platform badges */
        .status-badge { padding:0.3rem 0.75rem;border-radius:20px;font-size:0.78rem;font-weight:600; }
        .status-active    { background:#d4edda;color:#155724; }
        .status-pending   { background:#fff3cd;color:#856404; }
        .status-paused    { background:#f8d7da;color:#721c24; }
        .status-completed { background:#d1ecf1;color:#0c5460; }
        .status-rejected  { background:#f8d7da;color:#721c24; }
        .platform-badge { display:inline-flex;align-items:center;gap:0.3rem;padding:0.2rem 0.6rem;border-radius:20px;font-size:0.75rem;font-weight:600;color:white; }
        /* Alert */
        .alert { padding:0.75rem 1rem;border-radius:6px;margin-bottom:1rem; }
        .alert-info { background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb; }
        /* Form controls */
        .form-select,.form-control { display:inline-block;padding:0.375rem 0.75rem;font-size:0.875rem;border:1px solid #ced4da;border-radius:0.375rem;background:#fff;color:#1a1a1a;cursor:pointer; }
        /* Buttons */
        .btn { display:inline-block;padding:0.375rem 0.75rem;font-size:0.875rem;border-radius:0.375rem;border:1px solid transparent;cursor:pointer;font-weight:500;text-decoration:none; }
        .btn-sm { padding:0.25rem 0.5rem;font-size:0.8rem; }
        .btn-primary { background:#4f46e5;color:#fff;border-color:#4f46e5; }
        .btn-primary:hover { background:#4338ca; }
        .btn-success { background:#198754;color:#fff;border-color:#198754; }
        .btn-success:hover { background:#157347; }
        .btn-danger { background:#dc3545;color:#fff;border-color:#dc3545; }
        .btn-danger:hover { background:#bb2d3b; }
        .btn-warning { background:#ffc107;color:#000;border-color:#ffc107; }
        .btn-warning:hover { background:#e0a800; }
        .btn-outline-secondary { background:transparent;color:#6c757d;border-color:#6c757d; }
        .btn-outline-secondary:hover { background:#6c757d;color:#fff; }
        /* Table */
        .table-responsive { overflow-x:auto; }
        .table { width:100%;border-collapse:collapse;font-size:0.9rem; }
        .table th,.table td { padding:0.75rem;vertical-align:middle;border-bottom:1px solid #dee2e6; }
        .table thead th { background:#f8f9fa;font-weight:600; }
        .table tbody tr:hover { background:rgba(0,0,0,0.025); }
        /* Progress */
        .progress { background:#e9ecef;border-radius:0.375rem;overflow:hidden;height:6px;min-width:80px; }
        .progress-bar { height:100%;border-radius:0.375rem; }
        /* Utilities */
        .text-center { text-align:center; }
        .text-muted { color:#6c757d; }
        .py-5 { padding-top:3rem;padding-bottom:3rem; }
        .small,.small * { font-size:0.82rem; }
    </style>
</head>
<body>
<div class="admin-layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">

    <h2 style="margin-bottom:1.5rem;font-size:1.5rem;font-weight:700;">📱 Social Campaigns</h2>

    <?php if ($action_msg): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($action_msg); ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
        <select name="status" class="form-select" style="width:auto;">
            <option value="">All Statuses</option>
            <?php foreach (['pending','active','paused','completed','rejected'] as $s): ?>
            <option value="<?php echo $s; ?>" <?php echo $filter_status === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="platform" class="form-select" style="width:auto;">
            <option value="0">All Platforms</option>
            <?php foreach ($platforms as $p): ?>
            <option value="<?php echo (int)$p['id']; ?>" <?php echo $filter_platform === (int)$p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="social-campaigns.php" class="btn btn-outline-secondary btn-sm">Reset</a>
    </form>

    <?php if (empty($campaigns)): ?>
    <div class="text-center text-muted py-5">No campaigns found.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Campaign</th>
                    <th>Platform</th>
                    <th>Seller</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Budget</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $c):
                    $pct = $c['total_tasks_needed'] > 0 ? round($c['tasks_completed'] / $c['total_tasks_needed'] * 100) : 0;
                ?>
                <tr>
                    <td><?php echo (int)$c['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($c['title']); ?></strong><br>
                        <small class="text-muted">₹<?php echo number_format((float)$c['reward_per_task'], 2); ?>/task &bull; <?php echo (int)$c['watch_percent_required']; ?>% required</small>
                    </td>
                    <td>
                        <span class="platform-badge" style="background:<?php echo htmlspecialchars($c['platform_color']); ?>">
                            <i class="<?php echo htmlspecialchars($c['platform_icon']); ?>"></i>
                            <?php echo htmlspecialchars($c['platform_name']); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($c['seller_name']); ?><br>
                        <small class="text-muted"><?php echo htmlspecialchars($c['seller_email']); ?></small>
                    </td>
                    <td><span class="status-badge status-<?php echo htmlspecialchars($c['status']); ?>"><?php echo ucfirst($c['status']); ?></span></td>
                    <td>
                        <div class="progress" style="height:6px;min-width:80px;">
                            <div class="progress-bar" style="width:<?php echo $pct; ?>%;background:linear-gradient(90deg,#4f46e5,#7c3aed);"></div>
                        </div>
                        <small class="text-muted"><?php echo $c['tasks_completed']; ?>/<?php echo $c['total_tasks_needed']; ?></small>
                    </td>
                    <td>₹<?php echo number_format((float)$c['budget'], 2); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="campaign_id" value="<?php echo (int)$c['id']; ?>">
                            <?php if ($c['status'] === 'pending'): ?>
                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">✅ Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">❌ Reject</button>
                            <?php elseif ($c['status'] === 'active'): ?>
                            <button type="submit" name="action" value="pause" class="btn btn-sm btn-warning">⏸ Pause</button>
                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">❌ Reject</button>
                            <?php elseif ($c['status'] === 'paused'): ?>
                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">▶️ Reactivate</button>
                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger">❌ Reject</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
</div>
</body>
</html>
