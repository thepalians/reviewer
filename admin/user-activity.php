<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/activity-logger.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];

// Get filters
$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';

// Build query
$where = [];
$params = [];

if ($user_filter) {
    $where[] = "ual.user_id = ?";
    $params[] = $user_filter;
}

if ($action_filter) {
    $where[] = "ual.action = ?";
    $params[] = $action_filter;
}

$where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Get activities
try {
    $stmt = $pdo->prepare("
        SELECT ual.*, u.username, u.email
        FROM user_activity_logs ual
        JOIN users u ON ual.user_id = u.id
        {$where_clause}
        ORDER BY ual.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $activities = [];
}

// Get unique actions for filter
try {
    $actions = $pdo->query("SELECT DISTINCT action FROM user_activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $actions = [];
}

// Get stats
try {
    $total_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_activities,
            COUNT(DISTINCT user_id) as active_users,
            COUNT(DISTINCT DATE(created_at)) as active_days
        FROM user_activity_logs
    ")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $total_stats = ['total_activities' => 0, 'active_users' => 0, 'active_days' => 0];
}

$current_page = 'user-activity';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Logs - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
.admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
.sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
.main-content{padding:25px;overflow-x:hidden}
</style>
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
        <h2 class="mb-4"><i class="bi bi-activity"></i> User Activity Logs</h2>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h3><?php echo number_format($total_stats['total_activities']); ?></h3>
                        <p class="mb-0">Total Activities</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h3><?php echo number_format($total_stats['active_users']); ?></h3>
                        <p class="mb-0">Active Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h3><?php echo $total_stats['active_days']; ?></h3>
                        <p class="mb-0">Active Days</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">User ID</label>
                        <input type="number" class="form-control" name="user_id" value="<?php echo htmlspecialchars($user_filter); ?>" placeholder="Filter by user ID">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Action Type</label>
                        <select class="form-select" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $action_filter == $action ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-filter"></i> Filter
                        </button>
                        <a href="user-activity.php" class="btn btn-secondary">
                            <i class="bi bi-x"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Activity Logs -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list"></i> Activity Logs</h5>
            </div>
            <div class="card-body">
                <?php if (count($activities) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i:s', strtotime($activity['created_at'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($activity['username']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($activity['email']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                <td><code><?php echo htmlspecialchars($activity['ip_address']); ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No activity logs found</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
