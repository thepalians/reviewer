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
    
    if ($action === 'create_milestone') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO milestones (title, description, target_date, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                sanitize($_POST['title']),
                sanitize($_POST['description']),
                $_POST['target_date'],
                $admin_name
            ]);
            $success = 'Milestone created successfully!';
        } catch (PDOException $e) {
            $error = 'Database error';
            error_log("Milestone error: " . $e->getMessage());
        }
    }
}

// Get all milestones
$milestones = [];
try {
    $stmt = $pdo->query("
        SELECT m.*, 
               (SELECT COUNT(*) FROM milestone_tasks WHERE milestone_id = m.id) as task_count,
               (SELECT COUNT(*) FROM milestone_tasks WHERE milestone_id = m.id AND status = 'completed') as completed_count
        FROM milestones m
        ORDER BY m.target_date ASC
    ");
    $milestones = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Milestones error: " . $e->getMessage());
}

$csrf_token = generateCSRFToken();
$current_page = 'milestone-tasks';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Milestone Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .milestone-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:20px;margin-bottom:20px;border-left:4px solid #667eea}
        .milestone-card.completed{border-left-color:#10b981}
        .milestone-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px}
        .progress{height:20px;border-radius:10px}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-flag"></i> Milestone Management</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-plus-circle"></i> Create Milestone
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>

            <?php foreach ($milestones as $milestone): 
                $progress = $milestone['task_count'] > 0 ? 
                           ($milestone['completed_count'] / $milestone['task_count']) * 100 : 0;
                $isCompleted = $progress >= 100;
            ?>
                <div class="milestone-card <?= $isCompleted ? 'completed' : '' ?>">
                    <div class="milestone-header">
                        <div>
                            <h5><?= escape($milestone['title']) ?></h5>
                            <p class="text-muted mb-0"><?= escape($milestone['description']) ?></p>
                        </div>
                        <div>
                            <span class="badge <?= $isCompleted ? 'bg-success' : 'bg-primary' ?>">
                                <?= $isCompleted ? 'Completed' : 'In Progress' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <small><?= $milestone['completed_count'] ?> / <?= $milestone['task_count'] ?> tasks completed</small>
                            <small><?= number_format($progress, 1) ?>%</small>
                        </div>
                        <div class="progress">
                            <div class="progress-bar <?= $isCompleted ? 'bg-success' : '' ?>" 
                                 style="width:<?= $progress ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-calendar"></i> Target: <?= date('M d, Y', strtotime($milestone['target_date'])) ?>
                        </small>
                        <div>
                            <a href="milestone-detail.php?id=<?= $milestone['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="create_milestone">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Milestone</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Date</label>
                            <input type="date" name="target_date" class="form-control" required>
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
