<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Notifications.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$current_page = 'notification-templates';
$notifications = new Notifications($pdo);

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        if ($_POST['action'] === 'update_template') {
            $id = (int)$_POST['template_id'];
            $data = [
                'subject' => sanitizeInput($_POST['subject']),
                'body' => $_POST['body'], // Keep HTML
                'sms_body' => sanitizeInput($_POST['sms_body'] ?? ''),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            if ($notifications->updateTemplate($id, $data)) {
                $success_message = 'Template updated successfully!';
            } else {
                $error_message = 'Failed to update template.';
            }
        }
    }
}

// Get all templates
$templates = $notifications->getTemplates();
$queue_stats = $notifications->getQueueStats();

// Get badge counts for sidebar
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM wallet_recharge_requests WHERE status = 'pending'");
    $pending_wallet_recharges = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_questions = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM review_requests WHERE admin_status = 'pending'");
    $pending_review_requests = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_tasks = $pending_withdrawals = $pending_wallet_recharges = $unanswered_questions = $pending_review_requests = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Templates - <?php echo htmlspecialchars(APP_NAME); ?> Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <?php include __DIR__ . '/includes/styles.php'; ?>
    <style>
        .template-card {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            transition: box-shadow 0.3s;
        }
        .template-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .template-header {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .template-body {
            padding: 15px;
        }
        .preview-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            max-height: 300px;
            overflow-y: auto;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .stat-item {
            text-align: center;
            padding: 10px;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="container-fluid">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h1 class="mb-3"><i class="bi bi-envelope"></i> Notification Templates</h1>
                        <p class="text-muted">Manage email and SMS notification templates</p>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Queue Statistics -->
                <div class="stats-card">
                    <div class="row">
                        <div class="col-md-3 stat-item">
                            <div class="stat-value"><?php echo number_format($queue_stats['total'] ?? 0); ?></div>
                            <div class="stat-label">Total Queued</div>
                        </div>
                        <div class="col-md-3 stat-item">
                            <div class="stat-value"><?php echo number_format($queue_stats['pending'] ?? 0); ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="col-md-3 stat-item">
                            <div class="stat-value"><?php echo number_format($queue_stats['sent'] ?? 0); ?></div>
                            <div class="stat-label">Sent</div>
                        </div>
                        <div class="col-md-3 stat-item">
                            <div class="stat-value"><?php echo number_format($queue_stats['failed'] ?? 0); ?></div>
                            <div class="stat-label">Failed</div>
                        </div>
                    </div>
                </div>

                <!-- Template List -->
                <div class="row">
                    <div class="col-md-12">
                        <?php foreach ($templates as $template): ?>
                            <div class="template-card">
                                <div class="template-header">
                                    <div>
                                        <h5 class="mb-0">
                                            <i class="bi bi-envelope-fill text-primary"></i>
                                            <?php echo htmlspecialchars($template['type']); ?>
                                        </h5>
                                        <small class="text-muted">
                                            <?php echo $template['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?>
                                        </small>
                                    </div>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $template['id']; ?>">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                </div>
                                <div class="template-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Email Subject:</strong>
                                            <div class="preview-box">
                                                <?php echo htmlspecialchars($template['subject']); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Email Body Preview:</strong>
                                            <div class="preview-box">
                                                <?php echo substr(strip_tags($template['body']), 0, 200) . '...'; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($template['sms_body']): ?>
                                        <div class="row mt-3">
                                            <div class="col-md-12">
                                                <strong>SMS Body:</strong>
                                                <div class="preview-box">
                                                    <?php echo htmlspecialchars($template['sms_body']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?php echo $template['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Template: <?php echo htmlspecialchars($template['type']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="update_template">
                                                <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Email Subject *</label>
                                                    <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($template['subject']); ?>" required>
                                                    <small class="text-muted">Use {{variable_name}} for dynamic content</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Email Body (HTML) *</label>
                                                    <textarea name="body" class="form-control" rows="10" required><?php echo htmlspecialchars($template['body']); ?></textarea>
                                                    <small class="text-muted">HTML content for email. Use {{variable_name}} for dynamic content</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">SMS Body (Optional)</label>
                                                    <textarea name="sms_body" class="form-control" rows="3" maxlength="500"><?php echo htmlspecialchars($template['sms_body'] ?? ''); ?></textarea>
                                                    <small class="text-muted">Max 500 characters. Use {{variable_name}} for dynamic content</small>
                                                </div>
                                                
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="is_active" id="active<?php echo $template['id']; ?>" <?php echo $template['is_active'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="active<?php echo $template['id']; ?>">
                                                        Active
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-save"></i> Save Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($templates)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No notification templates found. Please run the migrations to create default templates.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
