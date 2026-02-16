<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auto-assignment-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$message = '';
$errors = [];

// Handle Add Assignment Rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_rule'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $rule_type = sanitizeInput($_POST['rule_type'] ?? '');
    $conditions = $_POST['conditions'] ?? [];
    $priority = intval($_POST['priority'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name) || empty($rule_type)) {
        $errors[] = "Rule name and type are required";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO auto_assignment_rules 
                (name, description, rule_type, conditions, priority, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, 
                $description, 
                $rule_type, 
                json_encode($conditions), 
                $priority, 
                $is_active,
                $_SESSION['admin_id'] ?? 1
            ]);
            $message = "Assignment rule added successfully!";
        } catch (PDOException $e) {
            $errors[] = "Failed to add rule: " . $e->getMessage();
        }
    }
}

// Handle Update Rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rule'])) {
    $id = intval($_POST['rule_id']);
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $priority = intval($_POST['priority'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE auto_assignment_rules 
            SET name = ?, description = ?, priority = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $priority, $is_active, $id]);
        $message = "Rule updated successfully!";
    } catch (PDOException $e) {
        $errors[] = "Failed to update rule";
    }
}

// Handle Delete Rule
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM auto_assignment_rules WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Rule deleted successfully!";
    } catch (PDOException $e) {
        $errors[] = "Failed to delete rule";
    }
}

// Get all rules
try {
    $rules = $pdo->query("
        SELECT * FROM auto_assignment_rules 
        ORDER BY priority DESC, created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rules = [];
}

// Get statistics
try {
    $stats = getAssignmentStatistics($pdo, 7);
} catch (Exception $e) {
    $stats = [];
}

$current_page = 'auto-assignment';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Task Assignment - Admin Panel</title>
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
        <h2 class="mb-4"><i class="bi bi-robot"></i> Auto Task Assignment System</h2>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <?php foreach ($stats as $stat): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo ucfirst(str_replace('_', ' ', $stat['assignment_type'])); ?></h5>
                        <h3><?php echo number_format($stat['count']); ?></h3>
                        <p class="mb-0">Avg Score: <?php echo number_format($stat['avg_score'], 2); ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Add New Rule -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-plus-circle"></i> Add New Assignment Rule</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Rule Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rule Type *</label>
                            <select class="form-select" name="rule_type" required>
                                <option value="">Select Type</option>
                                <option value="level">Level-Based</option>
                                <option value="performance">Performance-Based</option>
                                <option value="category">Category Expertise</option>
                                <option value="location">Location-Based</option>
                                <option value="round_robin">Round Robin</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <input type="number" class="form-control" name="priority" value="0">
                            <small class="text-muted">Higher priority rules are evaluated first</small>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="add_rule" class="btn btn-primary">
                                <i class="bi bi-plus"></i> Add Rule
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rules List -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list-ul"></i> Assignment Rules</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Priority</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $rule): ?>
                            <tr>
                                <td><span class="badge bg-info"><?php echo $rule['priority']; ?></span></td>
                                <td><?php echo htmlspecialchars($rule['name']); ?></td>
                                <td><?php echo ucfirst($rule['rule_type']); ?></td>
                                <td><?php echo htmlspecialchars(substr($rule['description'], 0, 50)); ?></td>
                                <td>
                                    <?php if ($rule['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($rule['created_at'])); ?></td>
                                <td>
                                    <a href="?action=delete&id=<?php echo $rule['id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Delete this rule?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($rules)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No rules found</td>
                            </tr>
                            <?php endif; ?>
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
