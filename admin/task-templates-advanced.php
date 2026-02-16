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
    
    if ($action === 'create_template') {
        try {
            $templateData = [
                'name' => sanitize($_POST['template_name']),
                'description' => sanitize($_POST['description']),
                'category' => sanitize($_POST['category']),
                'steps' => json_decode($_POST['steps'], true),
                'default_reward' => floatval($_POST['default_reward']),
                'estimated_time' => (int)$_POST['estimated_time']
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO task_templates (template_name, description, category, steps, 
                                           default_reward, estimated_time, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $templateData['name'],
                $templateData['description'],
                $templateData['category'],
                json_encode($templateData['steps']),
                $templateData['default_reward'],
                $templateData['estimated_time'],
                $admin_name
            ]);
            $success = 'Template created successfully!';
        } catch (PDOException $e) {
            $error = 'Database error';
            error_log("Template error: " . $e->getMessage());
        }
    } elseif ($action === 'clone_template') {
        $templateId = (int)$_POST['template_id'];
        try {
            $stmt = $pdo->prepare("
                INSERT INTO task_templates (template_name, description, category, steps, 
                                           default_reward, estimated_time, created_by)
                SELECT CONCAT(template_name, ' (Copy)'), description, category, steps,
                       default_reward, estimated_time, ?
                FROM task_templates WHERE id = ?
            ");
            $stmt->execute([$admin_name, $templateId]);
            $success = 'Template cloned successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to clone template';
        }
    }
}

// Get all templates
$templates = [];
try {
    $stmt = $pdo->query("
        SELECT tt.*, 
               (SELECT COUNT(*) FROM tasks WHERE template_id = tt.id) as usage_count
        FROM task_templates tt
        ORDER BY tt.created_at DESC
    ");
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Templates error: " . $e->getMessage());
}

$csrf_token = generateCSRFToken();
$current_page = 'task-templates-advanced';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Task Templates - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .template-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px}
        .template-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:20px;transition:transform 0.3s}
        .template-card:hover{transform:translateY(-5px);box-shadow:0 4px 20px rgba(0,0,0,0.1)}
        .badge{padding:4px 10px;border-radius:10px;font-size:11px;font-weight:600}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.template-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-file-earmark-text"></i> Advanced Task Templates</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="bi bi-plus-circle"></i> Create Template
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>

            <div class="template-grid">
                <?php foreach ($templates as $template): ?>
                    <div class="template-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5><?= escape($template['template_name']) ?></h5>
                                <span class="badge bg-primary"><?= escape($template['category']) ?></span>
                            </div>
                            <span class="badge bg-info"><?= $template['usage_count'] ?> uses</span>
                        </div>
                        
                        <p class="text-muted mb-3" style="font-size:13px">
                            <?= escape($template['description']) ?>
                        </p>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3" style="font-size:12px;color:#666">
                            <span><i class="bi bi-currency-rupee"></i> ₹<?= number_format($template['default_reward'], 2) ?></span>
                            <span><i class="bi bi-clock"></i> <?= $template['estimated_time'] ?> min</span>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="useTemplate(<?= $template['id'] ?>)">
                                <i class="bi bi-play"></i> Use
                            </button>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                <input type="hidden" name="action" value="clone_template">
                                <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-files"></i> Clone
                                </button>
                            </form>
                            <button class="btn btn-sm btn-outline-info" onclick="editTemplate(<?= $template['id'] ?>)">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="create_template">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Task Template</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Template Name</label>
                            <input type="text" name="template_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="Review">Review</option>
                                    <option value="Survey">Survey</option>
                                    <option value="Social Media">Social Media</option>
                                    <option value="Testing">Testing</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Default Reward (₹)</label>
                                <input type="number" step="0.01" name="default_reward" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Est. Time (min)</label>
                                <input type="number" name="estimated_time" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Steps (JSON)</label>
                            <textarea name="steps" class="form-control" rows="5" 
                                      placeholder='[{"step": 1, "title": "Step Title", "description": "Description"}]'>[]</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function useTemplate(id) { window.location.href = 'assign-task.php?template=' + id; }
        function editTemplate(id) { alert('Edit template: ' + id); }
    </script>
</body>
</html>
