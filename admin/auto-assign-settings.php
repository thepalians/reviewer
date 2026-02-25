<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$errors = [];
$success = '';

// Helper to upsert a setting
function saveSetting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
    ");
    $stmt->execute([$key, $value]);
}

// Handle toggle auto-assign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_auto_assign'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        try {
            $enabled = isset($_POST['auto_assign_enabled']) ? '1' : '0';
            $min = max(1, min(10, (int)($_POST['auto_assign_min_tasks'] ?? 1)));
            $max = max(1, min(10, (int)($_POST['auto_assign_max_tasks'] ?? 3)));
            if ($min > $max) $max = $min;
            saveSetting($pdo, 'auto_assign_enabled', $enabled);
            saveSetting($pdo, 'auto_assign_min_tasks', (string)$min);
            saveSetting($pdo, 'auto_assign_max_tasks', (string)$max);
            $success = 'Auto-assign settings updated.';
            logActivity('Auto-assign settings updated by admin');
        } catch (PDOException $e) {
            $errors[] = 'Failed to save settings.';
            error_log('Auto-assign settings error: ' . $e->getMessage());
        }
    }
}

// Handle add task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $product_link   = sanitizeInput($_POST['product_link'] ?? '');
        $brand_name     = sanitizeInput($_POST['brand_name'] ?? '');
        $commission     = max(0, (float)($_POST['commission'] ?? 0));
        $priority       = in_array($_POST['priority'] ?? '', ['low','medium','high','urgent']) ? $_POST['priority'] : 'medium';
        $deadline_days  = max(1, (int)($_POST['deadline_days'] ?? 7));
        $max_assign     = max(0, (int)($_POST['max_assignments'] ?? 0));
        $admin_notes    = sanitizeInput($_POST['admin_notes'] ?? '');

        if (empty($product_link)) {
            $errors[] = 'Product link is required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO auto_assign_tasks (product_link, brand_name, commission, priority, deadline_days, max_assignments, admin_notes, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$product_link, $brand_name, $commission, $priority, $deadline_days, $max_assign, $admin_notes, $admin_name]);
                $success = 'Task added to auto-assign pool.';
                logActivity('Auto-assign task added by admin');
            } catch (PDOException $e) {
                $errors[] = 'Failed to add task.';
                error_log('Auto-assign add task error: ' . $e->getMessage());
            }
        }
    }
}

// Handle edit task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $task_id        = (int)($_POST['task_id'] ?? 0);
        $product_link   = sanitizeInput($_POST['product_link'] ?? '');
        $brand_name     = sanitizeInput($_POST['brand_name'] ?? '');
        $commission     = max(0, (float)($_POST['commission'] ?? 0));
        $priority       = in_array($_POST['priority'] ?? '', ['low','medium','high','urgent']) ? $_POST['priority'] : 'medium';
        $deadline_days  = max(1, (int)($_POST['deadline_days'] ?? 7));
        $max_assign     = max(0, (int)($_POST['max_assignments'] ?? 0));
        $admin_notes    = sanitizeInput($_POST['admin_notes'] ?? '');

        if ($task_id <= 0 || empty($product_link)) {
            $errors[] = 'Invalid task data.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE auto_assign_tasks SET product_link=?, brand_name=?, commission=?, priority=?, deadline_days=?, max_assignments=?, admin_notes=?, updated_at=NOW()
                    WHERE id=?
                ");
                $stmt->execute([$product_link, $brand_name, $commission, $priority, $deadline_days, $max_assign, $admin_notes, $task_id]);
                $success = 'Task updated.';
            } catch (PDOException $e) {
                $errors[] = 'Failed to update task.';
                error_log('Auto-assign edit task error: ' . $e->getMessage());
            }
        }
    }
}

// Handle delete task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $task_id = (int)($_POST['task_id'] ?? 0);
        if ($task_id > 0) {
            try {
                $pdo->prepare("DELETE FROM auto_assign_tasks WHERE id = ?")->execute([$task_id]);
                $success = 'Task deleted.';
            } catch (PDOException $e) {
                $errors[] = 'Failed to delete task.';
                error_log('Auto-assign delete task error: ' . $e->getMessage());
            }
        }
    }
}

// Handle toggle task active status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_task'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $task_id = (int)($_POST['task_id'] ?? 0);
        if ($task_id > 0) {
            try {
                $pdo->prepare("UPDATE auto_assign_tasks SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ?")->execute([$task_id]);
                $success = 'Task status updated.';
            } catch (PDOException $e) {
                $errors[] = 'Failed to toggle task.';
                error_log('Auto-assign toggle task error: ' . $e->getMessage());
            }
        }
    }
}

// Load current settings
$auto_assign_enabled    = getSetting('auto_assign_enabled', '0');
$auto_assign_min_tasks  = (int)getSetting('auto_assign_min_tasks', '1');
$auto_assign_max_tasks  = (int)getSetting('auto_assign_max_tasks', '3');

// Load task pool
$task_pool = [];
try {
    $stmt = $pdo->query("SELECT * FROM auto_assign_tasks ORDER BY created_at DESC");
    $task_pool = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = 'Could not load task pool (table may not exist yet — run migrations/auto_assign_settings.sql).';
}

// Load recent auto-assignments
$recent_assignments = [];
try {
    $stmt = $pdo->query("
        SELECT t.id, t.brand_name, t.commission, t.created_at, u.name AS user_name
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        WHERE t.assigned_by = 'Auto-System'
        ORDER BY t.created_at DESC
        LIMIT 20
    ");
    $recent_assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    // silently ignore if table structure differs
}

$current_page = 'auto-assign-settings';
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Assign Tasks - Admin - <?php echo escape(APP_NAME); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar-header h2{font-size:20px}
        .sidebar-menu{list-style:none;padding:15px 0}
        .sidebar-menu li{margin-bottom:5px}
        .sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#0ea5e9}
        .sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
        .sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
        .sidebar-menu .menu-section-label{padding:8px 20px;font-size:11px;text-transform:uppercase;color:#64748b;letter-spacing:1px}
        .main-content{padding:25px}
        .page-header{margin-bottom:25px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .page-subtitle{color:#64748b;font-size:14px;margin-top:5px}
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        .card{background:#fff;border-radius:12px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:25px}
        .card-title{font-size:18px;font-weight:600;color:#1e293b;margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid #f1f5f9}
        .form-label{display:block;font-size:14px;font-weight:500;color:#374151;margin-bottom:6px}
        .form-control,.form-select{width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;transition:border 0.2s}
        .form-control:focus,.form-select:focus{outline:none;border-color:#0ea5e9}
        .btn{padding:10px 20px;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;border:none;transition:all 0.2s}
        .btn-primary{background:linear-gradient(135deg,#0ea5e9,#06b6d4);color:#fff}
        .btn-primary:hover{opacity:0.9}
        .btn-success{background:#10b981;color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-warning{background:#f59e0b;color:#fff}
        .btn-sm{padding:6px 14px;font-size:13px}
        .table{width:100%;border-collapse:collapse;font-size:14px}
        .table th{background:#f8fafc;padding:12px;text-align:left;font-weight:600;color:#374151;border-bottom:2px solid #e2e8f0}
        .table td{padding:12px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        .table tr:hover td{background:#f8fafc}
        .badge-active{background:#ecfdf5;color:#065f46;padding:3px 10px;border-radius:20px;font-size:12px}
        .badge-inactive{background:#fef2f2;color:#991b1b;padding:3px 10px;border-radius:20px;font-size:12px}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px}
        .form-check{display:flex;align-items:center;gap:10px}
        .form-check-input{width:18px;height:18px;cursor:pointer}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal.show{display:flex}
        .modal-box{background:#fff;border-radius:12px;padding:30px;width:100%;max-width:540px;max-height:90vh;overflow-y:auto}
        .modal-title{font-size:20px;font-weight:600;margin-bottom:20px}
        @media(max-width:768px){.admin-layout{grid-template-columns:1fr}.sidebar{display:none}.grid-2,.grid-3{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">⚡ Auto Assign Tasks</h1>
            <p class="page-subtitle">Configure automatic task assignment for new users on registration.</p>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?php echo escape($err); ?></div>
        <?php endforeach; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?php echo escape($success); ?></div>
        <?php endif; ?>

        <!-- Settings Card -->
        <div class="card">
            <div class="card-title">⚙️ Auto-Assign Settings</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
                <input type="hidden" name="toggle_auto_assign" value="1">
                <div class="grid-3" style="margin-bottom:20px">
                    <div>
                        <label class="form-label">Auto-Assign Status</label>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="auto_assign_enabled" id="auto_assign_enabled" value="1" <?php echo $auto_assign_enabled === '1' ? 'checked' : ''; ?>>
                            <label for="auto_assign_enabled" class="form-label mb-0">Enable for new registrations</label>
                        </div>
                    </div>
                    <div>
                        <label class="form-label" for="auto_assign_min_tasks">Min Tasks (1–10)</label>
                        <input type="number" class="form-control" id="auto_assign_min_tasks" name="auto_assign_min_tasks" min="1" max="10" value="<?php echo escape((string)$auto_assign_min_tasks); ?>">
                    </div>
                    <div>
                        <label class="form-label" for="auto_assign_max_tasks">Max Tasks (1–10)</label>
                        <input type="number" class="form-control" id="auto_assign_max_tasks" name="auto_assign_max_tasks" min="1" max="10" value="<?php echo escape((string)$auto_assign_max_tasks); ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Settings</button>
            </form>
        </div>

        <!-- Task Pool Card -->
        <div class="card">
            <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
                <span>📋 Auto-Assign Task Pool</span>
                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('addTaskModal').classList.add('show')">
                    <i class="bi bi-plus-lg me-1"></i> Add Task
                </button>
            </div>
            <?php if (empty($task_pool)): ?>
                <p style="color:#64748b;text-align:center;padding:20px">No tasks in the pool yet. Add tasks above.</p>
            <?php else: ?>
            <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Brand / Product</th>
                            <th>Commission</th>
                            <th>Priority</th>
                            <th>Deadline</th>
                            <th>Assigned</th>
                            <th>Max</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($task_pool as $task): ?>
                        <tr>
                            <td><?php echo (int)$task['id']; ?></td>
                            <td>
                                <strong><?php echo escape($task['brand_name'] ?: '—'); ?></strong><br>
                                <small style="color:#64748b"><a href="<?php echo escape($task['product_link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo escape(mb_substr($task['product_link'], 0, 50)) . (mb_strlen($task['product_link']) > 50 ? '…' : ''); ?></a></small>
                            </td>
                            <td>₹<?php echo number_format((float)$task['commission'], 2); ?></td>
                            <td><?php echo escape(ucfirst($task['priority'])); ?></td>
                            <td><?php echo (int)$task['deadline_days']; ?> days</td>
                            <td><?php echo (int)$task['total_assigned']; ?></td>
                            <td><?php echo (int)$task['max_assignments'] === 0 ? '∞' : (int)$task['max_assignments']; ?></td>
                            <td>
                                <?php if ($task['is_active']): ?>
                                    <span class="badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-warning btn-sm"
                                    onclick="openEditModal(<?php echo (int)$task['id']; ?>, <?php echo htmlspecialchars(json_encode($task), ENT_QUOTES); ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Toggle active status?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
                                    <input type="hidden" name="toggle_task" value="1">
                                    <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                    <button type="submit" class="btn btn-sm" style="background:<?php echo $task['is_active'] ? '#6b7280' : '#10b981'; ?>;color:#fff" title="Toggle">
                                        <i class="bi bi-<?php echo $task['is_active'] ? 'pause' : 'play'; ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this task?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
                                    <input type="hidden" name="delete_task" value="1">
                                    <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Auto-Assignments -->
        <?php if (!empty($recent_assignments)): ?>
        <div class="card">
            <div class="card-title">🕐 Recent Auto-Assignments (Last 20)</div>
            <div style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr><th>Task #</th><th>User</th><th>Brand</th><th>Commission</th><th>Assigned At</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_assignments as $ra): ?>
                        <tr>
                            <td><a href="<?php echo escape(ADMIN_URL); ?>/task-detail.php?id=<?php echo (int)$ra['id']; ?>">#<?php echo (int)$ra['id']; ?></a></td>
                            <td><?php echo escape($ra['user_name']); ?></td>
                            <td><?php echo escape($ra['brand_name'] ?: '—'); ?></td>
                            <td>₹<?php echo number_format((float)$ra['commission'], 2); ?></td>
                            <td><?php echo escape($ra['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal" id="addTaskModal">
    <div class="modal-box">
        <div class="modal-title">➕ Add Task to Pool</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
            <input type="hidden" name="add_task" value="1">
            <div style="margin-bottom:15px">
                <label class="form-label">Task Link *</label>
                <input type="url" class="form-control" name="product_link" required placeholder="https://amazon.in/...">
            </div>
            <div class="grid-2" style="margin-bottom:15px">
                <div>
                    <label class="form-label">Brand Name</label>
                    <input type="text" class="form-control" name="brand_name" placeholder="e.g. Nike">
                </div>
                <div>
                    <label class="form-label">Commission (₹)</label>
                    <input type="number" class="form-control" name="commission" min="0" step="0.01" value="0">
                </div>
            </div>
            <div class="grid-3" style="margin-bottom:15px">
                <div>
                    <label class="form-label">Priority</label>
                    <select class="form-select" name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Deadline (days)</label>
                    <input type="number" class="form-control" name="deadline_days" min="1" value="7">
                </div>
                <div>
                    <label class="form-label">Max Assignments (0=∞)</label>
                    <input type="number" class="form-control" name="max_assignments" min="0" value="0">
                </div>
            </div>
            <div style="margin-bottom:20px">
                <label class="form-label">Admin Notes</label>
                <textarea class="form-control" name="admin_notes" rows="2" placeholder="Optional notes..."></textarea>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Task</button>
                <button type="button" class="btn" style="background:#e2e8f0;color:#374151" onclick="document.getElementById('addTaskModal').classList.remove('show')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal" id="editTaskModal">
    <div class="modal-box">
        <div class="modal-title">✏️ Edit Task</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo escape($csrf_token); ?>">
            <input type="hidden" name="edit_task" value="1">
            <input type="hidden" name="task_id" id="edit_task_id">
            <div style="margin-bottom:15px">
                <label class="form-label">Task Link *</label>
                <input type="url" class="form-control" name="product_link" id="edit_product_link" required>
            </div>
            <div class="grid-2" style="margin-bottom:15px">
                <div>
                    <label class="form-label">Brand Name</label>
                    <input type="text" class="form-control" name="brand_name" id="edit_brand_name">
                </div>
                <div>
                    <label class="form-label">Commission (₹)</label>
                    <input type="number" class="form-control" name="commission" id="edit_commission" min="0" step="0.01">
                </div>
            </div>
            <div class="grid-3" style="margin-bottom:15px">
                <div>
                    <label class="form-label">Priority</label>
                    <select class="form-select" name="priority" id="edit_priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Deadline (days)</label>
                    <input type="number" class="form-control" name="deadline_days" id="edit_deadline_days" min="1">
                </div>
                <div>
                    <label class="form-label">Max Assignments (0=∞)</label>
                    <input type="number" class="form-control" name="max_assignments" id="edit_max_assignments" min="0">
                </div>
            </div>
            <div style="margin-bottom:20px">
                <label class="form-label">Admin Notes</label>
                <textarea class="form-control" name="admin_notes" id="edit_admin_notes" rows="2"></textarea>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Changes</button>
                <button type="button" class="btn" style="background:#e2e8f0;color:#374151" onclick="document.getElementById('editTaskModal').classList.remove('show')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, task) {
    document.getElementById('edit_task_id').value = id;
    document.getElementById('edit_product_link').value = task.product_link;
    document.getElementById('edit_brand_name').value = task.brand_name;
    document.getElementById('edit_commission').value = task.commission;
    document.getElementById('edit_priority').value = task.priority;
    document.getElementById('edit_deadline_days').value = task.deadline_days;
    document.getElementById('edit_max_assignments').value = task.max_assignments;
    document.getElementById('edit_admin_notes').value = task.admin_notes || '';
    document.getElementById('editTaskModal').classList.add('show');
}
// Close modal on backdrop click
document.querySelectorAll('.modal').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.classList.remove('show');
    });
});
</script>
</body>
</html>
