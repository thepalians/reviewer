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
$admin_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

// Handle template creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $id = $_POST['template_id'] ?? null;
        $name = $_POST['name'];
        $platform = $_POST['platform'];
        $category = $_POST['category'];
        $template_text = $_POST['template_text'];
        $rating_default = $_POST['rating_default'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE review_templates SET name=?, platform=?, category=?, template_text=?, rating_default=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $platform, $category, $template_text, $rating_default, $is_active, $id]);
                $message = 'Template updated successfully!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO review_templates (name, platform, category, template_text, rating_default, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $platform, $category, $template_text, $rating_default, $is_active, $admin_id]);
                $message = 'Template created successfully!';
            }
        } catch (PDOException $e) {
            $error = 'Failed to save template';
        }
    }
}

// Handle template deletion
if (isset($_GET['delete']) && $_GET['delete']) {
    try {
        $stmt = $pdo->prepare("DELETE FROM review_templates WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $message = 'Template deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to delete template';
    }
}

// Get all templates
try {
    $templates = $pdo->query("SELECT rt.*, u.username as creator_name FROM review_templates rt LEFT JOIN users u ON rt.created_by = u.id ORDER BY rt.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $templates = [];
}

// Get template being edited
$edit_template = null;
if (isset($_GET['edit']) && $_GET['edit']) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM review_templates WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_template = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $edit_template = null;
    }
}

$current_page = 'review-templates';
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Templates - Admin Panel</title>
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
        <h2 class="mb-4"><i class="bi bi-file-text"></i> Review Templates</h2>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Template Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><?php echo $edit_template ? 'Edit' : 'Create New'; ?> Template</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <?php if ($edit_template): ?>
                        <input type="hidden" name="template_id" value="<?php echo $edit_template['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Name *</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($edit_template['name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Platform *</label>
                            <select class="form-select" name="platform" required>
                                <option value="">Select Platform</option>
                                <option value="Amazon" <?php echo ($edit_template['platform'] ?? '') == 'Amazon' ? 'selected' : ''; ?>>Amazon</option>
                                <option value="Flipkart" <?php echo ($edit_template['platform'] ?? '') == 'Flipkart' ? 'selected' : ''; ?>>Flipkart</option>
                                <option value="Google" <?php echo ($edit_template['platform'] ?? '') == 'Google' ? 'selected' : ''; ?>>Google</option>
                                <option value="Zomato" <?php echo ($edit_template['platform'] ?? '') == 'Zomato' ? 'selected' : ''; ?>>Zomato</option>
                                <option value="Swiggy" <?php echo ($edit_template['platform'] ?? '') == 'Swiggy' ? 'selected' : ''; ?>>Swiggy</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($edit_template['category'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Template Text *</label>
                        <textarea class="form-control" name="template_text" rows="5" required><?php echo htmlspecialchars($edit_template['template_text'] ?? ''); ?></textarea>
                        <small class="text-muted">Write a generic review template that can be customized for different products.</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Default Rating</label>
                            <select class="form-select" name="rating_default">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($edit_template['rating_default'] ?? 5) == $i ? 'selected' : ''; ?>><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" <?php echo ($edit_template['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="save_template" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Template
                    </button>
                    <?php if ($edit_template): ?>
                        <a href="review-templates.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Templates List -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list"></i> All Templates</h5>
            </div>
            <div class="card-body">
                <?php if (count($templates) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Platform</th>
                                <th>Category</th>
                                <th>Rating</th>
                                <th>Usage Count</th>
                                <th>Status</th>
                                <th>Created By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($template['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($template['platform']); ?></td>
                                <td><?php echo htmlspecialchars($template['category'] ?? '-'); ?></td>
                                <td><?php echo $template['rating_default']; ?> ⭐</td>
                                <td><?php echo $template['usage_count']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $template['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($template['creator_name'] ?? 'System'); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $template['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="?delete=<?php echo $template['id']; ?>" class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Delete this template?')">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <p class="text-muted">No templates created yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
