<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/task-management-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_dependency') {
        $taskId = (int)$_POST['task_id'];
        $dependsOn = (int)$_POST['depends_on_task_id'];
        $type = sanitize($_POST['dependency_type'] ?? 'finish_to_start');
        
        if (createTaskDependency($taskId, $dependsOn, $type)) {
            $success = 'Dependency created successfully!';
        } else {
            $error = 'Failed to create dependency (possible circular dependency)';
        }
    }
}

// Get all task dependencies
$dependencies = [];
try {
    $stmt = $pdo->query("
        SELECT td.*, t1.task_title as task_name, t2.task_title as depends_on_name
        FROM task_dependencies td
        JOIN tasks t1 ON td.task_id = t1.id
        JOIN tasks t2 ON td.depends_on_task_id = t2.id
        ORDER BY td.created_at DESC
    ");
    $dependencies = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Task dependencies error: " . $e->getMessage());
}

$csrf_token = generateCSRFToken();
$current_page = 'task-dependencies';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Dependencies - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        table th{background:#f8f9fa;padding:10px;text-align:left;font-weight:600;border-bottom:2px solid #dee2e6}
        table td{padding:10px;border-bottom:1px solid #f0f0f0}
        .badge{padding:4px 10px;border-radius:10px;font-size:11px;font-weight:600}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-diagram-3"></i> Task Dependencies</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-plus-circle"></i> Create Dependency
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Dependency Type</th>
                            <th>Depends On</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dependencies as $dep): ?>
                            <tr>
                                <td><?= escape($dep['task_name']) ?></td>
                                <td><span class="badge bg-primary"><?= escape($dep['dependency_type']) ?></span></td>
                                <td><?= escape($dep['depends_on_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($dep['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="deleteDependency(<?= $dep['id'] ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="create_dependency">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Task Dependency</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Task ID</label>
                            <input type="number" name="task_id" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Depends On Task ID</label>
                            <input type="number" name="depends_on_task_id" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dependency Type</label>
                            <select name="dependency_type" class="form-select">
                                <option value="finish_to_start">Finish to Start</option>
                                <option value="start_to_start">Start to Start</option>
                                <option value="finish_to_finish">Finish to Finish</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
