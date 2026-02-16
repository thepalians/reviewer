<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/webhook-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

// Handle test webhook before any output
if (isset($_GET['action']) && $_GET['action'] === 'test' && isset($_GET['id'])) {
    testWebhook($pdo, intval($_GET['id']));
    header('Location: webhooks.php');
    exit;
}

$admin_name = $_SESSION['admin_name'];
$message = '';

// Handle add webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_webhook'])) {
    $webhook_data = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'url' => sanitizeInput($_POST['url'] ?? ''),
        'events' => $_POST['events'] ?? [],
        'retry_count' => intval($_POST['retry_count'] ?? 3),
        'timeout' => intval($_POST['timeout'] ?? 30),
        'created_by' => $_SESSION['admin_id'] ?? 1
    ];
    
    if (registerWebhook($pdo, $webhook_data)) {
        $message = "Webhook registered successfully!";
    }
}

// Get webhooks and logs
$webhooks = getAllWebhooks($pdo);
$logs = getWebhookLogs($pdo, null, 50);
$available_events = getAvailableWebhookEvents();

$current_page = 'webhooks';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhooks - Admin Panel</title>
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
        <h2 class="mb-4"><i class="bi bi-broadcast"></i> Webhook Management</h2>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Webhook -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-plus-circle"></i> Register New Webhook</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Webhook Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Webhook URL *</label>
                            <input type="url" class="form-control" name="url" placeholder="https://..." required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Events to Subscribe *</label>
                            <div class="row">
                                <?php foreach ($available_events as $event => $label): ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="events[]" value="<?php echo $event; ?>" id="event_<?php echo $event; ?>">
                                        <label class="form-check-label" for="event_<?php echo $event; ?>">
                                            <?php echo $label; ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Retry Count</label>
                            <input type="number" class="form-control" name="retry_count" value="3" min="0" max="10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Timeout (seconds)</label>
                            <input type="number" class="form-control" name="timeout" value="30" min="5" max="120">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="add_webhook" class="btn btn-primary">
                                <i class="bi bi-plus"></i> Register Webhook
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Webhooks List -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-list"></i> Registered Webhooks</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>URL</th>
                                <th>Events</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($webhooks as $webhook): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($webhook['name']); ?></td>
                                <td><small><?php echo htmlspecialchars(substr($webhook['url'], 0, 50)); ?></small></td>
                                <td>
                                    <?php 
                                    $events = json_decode($webhook['events'], true);
                                    echo count($events) . ' events';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($webhook['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($webhook['created_at'])); ?></td>
                                <td>
                                    <a href="?action=test&id=<?php echo $webhook['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-play"></i> Test
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Logs -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-clock-history"></i> Recent Webhook Logs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Webhook</th>
                                <th>Event</th>
                                <th>Status</th>
                                <th>Response Code</th>
                                <th>Attempts</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['webhook_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($log['event_type']); ?></td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'success' => 'success',
                                        'failed' => 'danger',
                                        'pending' => 'warning'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$log['status']]; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $log['response_code'] ?? '-'; ?></td>
                                <td><?php echo $log['attempts']; ?></td>
                                <td><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
