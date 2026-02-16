<?php
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Only regular logged-in users
if (!isLoggedIn() || isAdmin()) {
    redirect('../index.php');
}

$user_id = $_SESSION['user_id'];
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : '';

// Build query based on filter
$query = "SELECT * FROM tasks WHERE user_id = ?";
$params = [$user_id];

switch ($filter) {
    case 'pending':
        $query .= " AND status NOT IN ('completed')";
        break;
    case 'completed':
        $query .= " AND status = 'completed'";
        break;
    case 'active':
        $query .= " AND status IN ('assigned', 'step1_completed', 'step2_completed', 'step3_completed', 'refund_requested')";
        break;
}

$query .= " ORDER BY FIELD(status, 'assigned', 'step1_completed', 'step2_completed', 'step3_completed', 'refund_requested', 'completed'), deadline ASC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Count tasks by status
$counts = [
    'total' => 0,
    'pending' => 0,
    'completed' => 0,
    'active' => 0
];

foreach ($tasks as $task) {
    $counts['total']++;
    if ($task['status'] == 'completed') {
        $counts['completed']++;
    } else {
        $counts['pending']++;
    }
    if ($task['status'] != 'completed') {
        $counts['active']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks - User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .task-card { transition: all 0.3s; }
        .task-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .status-badge { font-size: 0.8rem; }
        .progress-step { width: 25px; height: 25px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-right: 5px; }
        .step-active { background-color: #0d6efd; color: white; }
        .step-completed { background-color: #198754; color: white; }
        .step-pending { background-color: #6c757d; color: white; }
        .deadline-warning { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">📋 My Tasks</h2>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h5>Total</h5>
                        <h2><?php echo $counts['total']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h5>Pending</h5>
                        <h2><?php echo $counts['pending']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h5>Completed</h5>
                        <h2><?php echo $counts['completed']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h5>Active</h5>
                        <h2><?php echo $counts['active']; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tasks List -->
        <div class="row">
            <?php if (empty($tasks)): ?>
                <div class="col-12">
                    <p class="text-muted">No tasks found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card task-card">
                            <div class="card-body">
                                <h5><?php echo htmlspecialchars($task['product_name'] ?? 'Task'); ?></h5>
                                <p><?php echo htmlspecialchars($task['platform'] ?? ''); ?></p>
                                <p><?php echo statusBadge($task['status']); ?></p>
                                <a href="view_entry.php?order_id=<?php echo urlencode($task['order_id'] ?? ''); ?>" class="btn btn-sm btn-outline-primary">View</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
