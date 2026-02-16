<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/calendar-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$message = '';

// Handle create recurring task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_recurring'])) {
    $seller_id = intval($_POST['seller_id']);
    $template_data = [
        'title' => sanitizeInput($_POST['title'] ?? ''),
        'description' => sanitizeInput($_POST['description'] ?? ''),
        'commission_amount' => floatval($_POST['commission_amount'] ?? 0)
    ];
    $frequency = sanitizeInput($_POST['frequency']);
    $start_date = $_POST['start_date'];
    
    if (createRecurringTask($pdo, $seller_id, $template_data, $frequency, $start_date)) {
        $message = "Recurring task created successfully!";
    }
}

// Get recurring tasks
$recurring_tasks = $pdo->query("
    SELECT rt.*, u.name as seller_name 
    FROM recurring_tasks rt
    JOIN users u ON rt.seller_id = u.id
    ORDER BY rt.next_run ASC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$current_page = 'task-scheduler';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Scheduler - Admin Panel</title>
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
        <h2 class="mb-4"><i class="bi bi-calendar-check"></i> Task Scheduler</h2>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Create Recurring Task -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-arrow-repeat"></i> Create Recurring Task</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Seller ID *</label>
                            <input type="number" class="form-control" name="seller_id" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Frequency *</label>
                            <select class="form-select" name="frequency" required>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Task Title *</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Commission Amount (₹)</label>
                            <input type="number" step="0.01" class="form-control" name="commission_amount">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="create_recurring" class="btn btn-primary">
                                <i class="bi bi-plus"></i> Create Recurring Task
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recurring Tasks List -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list-task"></i> Recurring Tasks</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Seller</th>
                                <th>Frequency</th>
                                <th>Next Run</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recurring_tasks as $task): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['seller_name']); ?></td>
                                <td><?php echo ucfirst($task['frequency']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($task['next_run'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($task['start_date'])); ?></td>
                                <td><?php echo $task['end_date'] ? date('M d, Y', strtotime($task['end_date'])) : 'N/A'; ?></td>
                                <td>
                                    <?php if ($task['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
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
