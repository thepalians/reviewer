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
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($subject) || empty($body)) {
                $error = 'All fields are required';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO email_templates (name, subject, content, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$name, $subject, $body, $is_active, $admin_id]);
                    $success = 'Template created successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to create template';
                }
            }
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $body = trim($_POST['body'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id <= 0 || empty($name) || empty($subject) || empty($body)) {
                $error = 'Invalid data';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE email_templates SET name = ?, subject = ?, content = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $subject, $body, $is_active, $id]);
                    $success = 'Template updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update template';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    // Check if template is used in campaigns
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_campaigns WHERE template_id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Cannot delete template: it is used in campaigns';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id = ?");
                        $stmt->execute([$id]);
                        $success = 'Template deleted successfully';
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to delete template';
                }
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("UPDATE email_templates SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Status updated successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to update status';
                }
            }
        }
    }
}

// Get templates
$templates = [];
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0];
try {
    $stmt = $pdo->query("
        SELECT t.*, u.username as creator_name,
               (SELECT COUNT(*) FROM email_campaigns WHERE template_id = t.id) as usage_count
        FROM email_templates t
        LEFT JOIN users u ON t.created_by = u.id
        ORDER BY t.created_at DESC
    ");
    $templates = $stmt->fetchAll();
    
    // Get stats
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM email_templates");
    $stats_row = $stmt->fetch();
    $stats['total'] = (int)$stats_row['total'];
    $stats['active'] = (int)$stats_row['active'];
    $stats['inactive'] = $stats['total'] - $stats['active'];
} catch (PDOException $e) {
    $error = 'Database error';
}

$csrf_token = generateCSRFToken();
$current_page = 'email-templates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Templates - Admin Panel</title>
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
        .stat.total .val{color:#667eea}.stat.active .val{color:#27ae60}.stat.inactive .val{color:#e74c3c}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .card-header h4{margin:0;font-size:20px}
        .btn{padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-weight:600;transition:all 0.3s}
        .btn-primary{background:#667eea;color:#fff}.btn-primary:hover{background:#5568d3}
        .btn-success{background:#27ae60;color:#fff}.btn-success:hover{background:#229954}
        .btn-danger{background:#e74c3c;color:#fff}.btn-danger:hover{background:#c0392b}
        .btn-warning{background:#f39c12;color:#fff}.btn-warning:hover{background:#e67e22}
        .btn-secondary{background:#6c757d;color:#fff}.btn-secondary:hover{background:#5a6268}
        .btn-sm{padding:6px 12px;font-size:13px}
        table{width:100%;border-collapse:collapse}
        table th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;font-size:13px;color:#555;border-bottom:2px solid #dee2e6}
        table td{padding:12px;border-bottom:1px solid #f0f0f0;font-size:14px}
        table tr:hover{background:#f8f9fa}
        .badge{padding:5px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .badge.success{background:#d4edda;color:#155724}
        .badge.danger{background:#f8d7da;color:#721c24}
        .badge.info{background:#d1ecf1;color:#0c5460}
        .alert{padding:15px;border-radius:8px;margin-bottom:20px}
        .alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .alert-danger{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:12px;max-width:800px;width:90%;max-height:90vh;overflow-y:auto}
        .modal-header{padding:20px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center}
        .modal-body{padding:20px}
        .modal-footer{padding:20px;border-top:1px solid #f0f0f0;display:flex;gap:10px;justify-content:flex-end}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:5px;font-weight:600;font-size:14px}
        .form-control{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px}
        textarea.form-control{min-height:200px;resize:vertical;font-family:monospace}
        .close{background:none;border:none;font-size:24px;cursor:pointer;color:#999}
        .template-preview{background:#f8f9fa;padding:15px;border-radius:6px;margin-top:10px;font-size:13px}
    </style>
</head>
<body>
<div class="wrapper">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="content">
        <div class="card-header">
            <h4>📧 Email Templates</h4>
            <button class="btn btn-primary" onclick="openCreateModal()">+ Create Template</button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats">
            <div class="stat total">
                <div class="val"><?php echo number_format($stats['total']); ?></div>
                <div class="lbl">Total Templates</div>
            </div>
            <div class="stat active">
                <div class="val"><?php echo number_format($stats['active']); ?></div>
                <div class="lbl">Active</div>
            </div>
            <div class="stat inactive">
                <div class="val"><?php echo number_format($stats['inactive']); ?></div>
                <div class="lbl">Inactive</div>
            </div>
        </div>

        <!-- Templates Table -->
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Used In</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($templates)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:30px;color:#999">No templates found</td></tr>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td>#<?php echo $template['id']; ?></td>
                                <td><?php echo htmlspecialchars($template['name']); ?></td>
                                <td><?php echo htmlspecialchars($template['subject']); ?></td>
                                <td>
                                    <span class="badge <?php echo $template['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo $template['usage_count']; ?> campaign(s)</td>
                                <td><?php echo date('M j, Y', strtotime($template['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick='openEditModal(<?php echo json_encode($template); ?>)'>Edit</button>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Toggle status?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                        <button type="submit" class="btn btn-warning btn-sm">Toggle</button>
                                    </form>
                                    <?php if ($template['usage_count'] == 0): ?>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this template?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h5>Available Template Variables</h5>
            <div class="template-preview">
                <p><strong>{{user_name}}</strong> - User's full name</p>
                <p><strong>{{user_email}}</strong> - User's email</p>
                <p><strong>{{site_name}}</strong> - Website name</p>
                <p><strong>{{site_url}}</strong> - Website URL</p>
                <p><strong>{{current_date}}</strong> - Current date</p>
                <p><strong>{{wallet_balance}}</strong> - User's wallet balance</p>
            </div>
        </div>
    </div>
</div>

<!-- Create Template Modal -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5>Create Email Template</h5>
            <button class="close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label>Template Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Subject *</label>
                    <input type="text" name="subject" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Body (HTML) *</label>
                    <textarea name="body" class="form-control" required></textarea>
                    <small>Use template variables like {{user_name}}, {{site_name}}, etc.</small>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" checked> Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Template</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Template Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5>Edit Email Template</h5>
            <button class="close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Template Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Subject *</label>
                    <input type="text" name="subject" id="edit_subject" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Body (HTML) *</label>
                    <textarea name="body" id="edit_body" class="form-control" required></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="edit_is_active"> Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Template</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.add('show');
}

function openEditModal(template) {
    document.getElementById('edit_id').value = template.id;
    document.getElementById('edit_name').value = template.name;
    document.getElementById('edit_subject').value = template.subject;
    document.getElementById('edit_body').value = template.content;
    document.getElementById('edit_is_active').checked = template.is_active == 1;
    document.getElementById('editModal').classList.add('show');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}
</script>
</body>
</html>
