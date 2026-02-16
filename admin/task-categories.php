<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$error = '';
$success = '';

// Handle POST actions with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $color = trim($_POST['color'] ?? '#667eea');
            $icon = trim($_POST['icon'] ?? '📋');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name)) {
                $error = 'Category name is required';
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT MAX(sort_order) as max_order FROM task_categories");
                    $stmt->execute();
                    $max_order = (int)$stmt->fetchColumn();
                    
                    $stmt = $pdo->prepare("INSERT INTO task_categories (name, description, color, icon, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$name, $description, $color, $icon, $is_active, $max_order + 1]);
                    $success = 'Category created successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to create category';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $color = trim($_POST['color'] ?? '#667eea');
            $icon = trim($_POST['icon'] ?? '📋');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || $id <= 0) {
                $error = 'Invalid data';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE task_categories SET name = ?, description = ?, color = ?, icon = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $description, $color, $icon, $is_active, $id]);
                    $success = 'Category updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update category';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE category_id = ?");
                    $stmt->execute([$id]);
                    $task_count = (int)$stmt->fetchColumn();
                    
                    if ($task_count > 0) {
                        $error = "Cannot delete category with {$task_count} tasks";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM task_categories WHERE id = ?");
                        $stmt->execute([$id]);
                        $success = 'Category deleted successfully';
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to delete category';
                }
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE task_categories SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Status updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update status';
                }
            }
        } elseif ($action === 'reorder') {
            $order = json_decode($_POST['order'] ?? '[]', true);
            if (is_array($order)) {
                try {
                    foreach ($order as $index => $id) {
                        $stmt = $pdo->prepare("UPDATE task_categories SET sort_order = ? WHERE id = ?");
                        $stmt->execute([$index + 1, (int)$id]);
                    }
                    $success = 'Order updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update order';
                }
            }
        }
    }
}

// Get categories with try-catch
$categories = [];
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0];
try {
    $stmt = $pdo->query("
        SELECT c.*, COUNT(t.id) as task_count
        FROM task_categories c
        LEFT JOIN tasks t ON c.id = t.category_id
        GROUP BY c.id
        ORDER BY c.sort_order ASC, c.created_at DESC
    ");
    $categories = $stmt->fetchAll();
    
    // Get stats
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM task_categories");
    $stats_row = $stmt->fetch();
    $stats['total'] = (int)$stats_row['total'];
    $stats['active'] = (int)$stats_row['active'];
    $stats['inactive'] = $stats['total'] - $stats['active'];
} catch (PDOException $e) {
    $error = 'Database error';
}

$csrf_token = generateCSRFToken();
$current_page = 'task-categories';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Categories - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar h2{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);font-size:20px}
        .sidebar ul{list-style:none}
        .sidebar a{color:#bbb;text-decoration:none;padding:12px 15px;display:block;border-radius:8px;margin-bottom:5px;transition:all 0.3s}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.1);color:#fff}
        .sidebar .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:12px;font-size:11px;margin-left:5px}
        .sidebar-divider{border-top:1px solid rgba(255,255,255,0.1);margin:15px 0}
        .menu-section-label{color:#888;font-size:11px;text-transform:uppercase;padding:10px 15px;font-weight:600}
        .content{padding:30px}
        .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:25px}
        .stat{background:#fff;padding:20px;border-radius:12px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.05)}
        .stat .val{font-size:28px;font-weight:700}
        .stat .lbl{font-size:13px;color:#888;margin-top:5px}
        .stat.p .val{color:#667eea}.stat.s .val{color:#27ae60}.stat.d .val{color:#e74c3c}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h4{margin:0;font-size:20px}
        .btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;transition:all 0.3s}
        .btn-primary{background:#667eea;color:#fff}.btn-primary:hover{background:#5568d3}
        .btn-success{background:#27ae60;color:#fff}.btn-success:hover{background:#229954}
        .btn-danger{background:#e74c3c;color:#fff}.btn-danger:hover{background:#c0392b}
        .btn-warning{background:#f39c12;color:#fff}.btn-warning:hover{background:#e67e22}
        .btn-sm{padding:6px 12px;font-size:13px}
        table{width:100%;border-collapse:collapse}
        table th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;font-size:13px;color:#555;border-bottom:2px solid #dee2e6}
        table td{padding:12px;border-bottom:1px solid #f0f0f0;font-size:14px}
        table tr:hover{background:#f8f9fa}
        table tr.dragging{opacity:0.5}
        .badge{padding:5px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .badge.success{background:#d4edda;color:#155724}
        .badge.danger{background:#f8d7da;color:#721c24}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:12px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto}
        .modal-header{padding:20px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center}
        .modal-body{padding:20px}
        .modal-footer{padding:20px;border-top:1px solid #f0f0f0;display:flex;gap:10px;justify-content:flex-end}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:5px;font-weight:600;font-size:14px}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
        textarea.form-control{min-height:80px;resize:vertical}
        .close{background:none;border:none;font-size:24px;cursor:pointer;color:#999}
        .color-preview{width:30px;height:30px;border-radius:6px;display:inline-block;vertical-align:middle;border:2px solid #ddd}
        .drag-handle{cursor:move;color:#999;margin-right:10px}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>📁 Task Categories</h4>
            <button class="btn btn-primary" onclick="openCreateModal()">+ Add Category</button>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo escape($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo escape($success); ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat p">
                <div class="val"><?php echo $stats['total']; ?></div>
                <div class="lbl">Total Categories</div>
            </div>
            <div class="stat s">
                <div class="val"><?php echo $stats['active']; ?></div>
                <div class="lbl">Active</div>
            </div>
            <div class="stat d">
                <div class="val"><?php echo $stats['inactive']; ?></div>
                <div class="lbl">Inactive</div>
            </div>
        </div>
        
        <div class="card">
            <p style="color:#888;margin-bottom:15px"><i class="bi bi-info-circle"></i> Drag rows to reorder categories</p>
            <table id="categoriesTable">
                <thead>
                    <tr>
                        <th width="50">Order</th>
                        <th>Icon</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Color</th>
                        <th>Tasks</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sortableBody">
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="8" style="text-align:center;color:#999">No categories found</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr data-id="<?php echo $cat['id']; ?>" draggable="true">
                                <td><span class="drag-handle">☰</span><?php echo $cat['sort_order']; ?></td>
                                <td style="font-size:24px"><?php echo escape($cat['icon']); ?></td>
                                <td><strong><?php echo escape($cat['name']); ?></strong></td>
                                <td><?php echo escape($cat['description']); ?></td>
                                <td><span class="color-preview" style="background-color:<?php echo escape($cat['color']); ?>"></span> <?php echo escape($cat['color']); ?></td>
                                <td><?php echo number_format($cat['task_count']); ?></td>
                                <td>
                                    <?php if ($cat['is_active']): ?>
                                        <span class="badge success">Active</span>
                                    <?php else: ?>
                                        <span class="badge danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick='editCategory(<?php echo json_encode($cat); ?>)'>Edit</button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this category?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 id="modalTitle">Create Category</h5>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="categoryForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="categoryId">
                
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="description" class="form-control"></textarea>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                    <div class="form-group">
                        <label>Icon (Emoji)</label>
                        <input type="text" name="icon" id="icon" class="form-control" placeholder="📋" value="📋">
                    </div>
                    
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" id="color" class="form-control" value="#667eea">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" checked>
                        Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create Category';
    document.getElementById('formAction').value = 'create';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('is_active').checked = true;
    document.getElementById('icon').value = '📋';
    document.getElementById('color').value = '#667eea';
    document.getElementById('categoryModal').classList.add('show');
}

function editCategory(cat) {
    document.getElementById('modalTitle').textContent = 'Edit Category';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('name').value = cat.name;
    document.getElementById('description').value = cat.description || '';
    document.getElementById('icon').value = cat.icon;
    document.getElementById('color').value = cat.color;
    document.getElementById('is_active').checked = cat.is_active == 1;
    document.getElementById('categoryModal').classList.add('show');
}

function closeModal() {
    document.getElementById('categoryModal').classList.remove('show');
}

window.onclick = function(event) {
    if (event.target == document.getElementById('categoryModal')) {
        closeModal();
    }
}

// Drag and drop
let draggedRow = null;

document.getElementById('sortableBody').addEventListener('dragstart', function(e) {
    draggedRow = e.target.closest('tr');
    e.target.closest('tr').classList.add('dragging');
});

document.getElementById('sortableBody').addEventListener('dragend', function(e) {
    e.target.closest('tr').classList.remove('dragging');
});

document.getElementById('sortableBody').addEventListener('dragover', function(e) {
    e.preventDefault();
    const afterElement = getDragAfterElement(this, e.clientY);
    if (afterElement == null) {
        this.appendChild(draggedRow);
    } else {
        this.insertBefore(draggedRow, afterElement);
    }
});

document.getElementById('sortableBody').addEventListener('drop', function(e) {
    e.preventDefault();
    const rows = Array.from(this.querySelectorAll('tr[data-id]'));
    const order = rows.map(row => row.dataset.id);
    
    // Send order to server
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?php echo $csrf_token; ?>&action=reorder&order=' + JSON.stringify(order)
    }).then(() => location.reload());
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('tr[data-id]:not(.dragging)')];
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
