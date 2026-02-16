<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/whatsapp-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$message = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $settings = [
        'enabled' => isset($_POST['enabled']) ? 1 : 0,
        'api_url' => sanitizeInput($_POST['api_url'] ?? ''),
        'api_key' => sanitizeInput($_POST['api_key'] ?? ''),
        'phone_number_id' => sanitizeInput($_POST['phone_number_id'] ?? '')
    ];
    
    if (updateWhatsAppSettings($pdo, $settings)) {
        $message = "Settings updated successfully!";
    }
}

// Get current settings
$settings = getWhatsAppSettings($pdo);

// Get templates
$templates = getWhatsAppTemplates($pdo);

// Get statistics
$stats = getWhatsAppStatistics($pdo, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));

$current_page = 'whatsapp-settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Integration - Admin Panel</title>
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
        <h2 class="mb-4"><i class="bi bi-whatsapp"></i> WhatsApp Integration</h2>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <?php foreach ($stats as $stat): ?>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5><?php echo ucfirst($stat['status']); ?></h5>
                        <h3><?php echo number_format($stat['count']); ?></h3>
                        <small><?php echo ucfirst($stat['message_type']); ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Settings -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-gear"></i> WhatsApp API Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="enabled" name="enabled" 
                               <?php echo $settings['enabled'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enabled">
                            Enable WhatsApp Integration
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API URL</label>
                        <input type="url" class="form-control" name="api_url" 
                               value="<?php echo htmlspecialchars($settings['api_url'] ?? ''); ?>"
                               placeholder="https://api.whatsapp.com/...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Key</label>
                        <input type="password" class="form-control" name="api_key" 
                               value="<?php echo htmlspecialchars($settings['api_key'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number ID</label>
                        <input type="text" class="form-control" name="phone_number_id" 
                               value="<?php echo htmlspecialchars($settings['phone_number_id'] ?? ''); ?>">
                    </div>
                    <button type="submit" name="update_settings" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- Templates -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-file-text"></i> Message Templates</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Language</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($template['name']); ?></td>
                                <td><?php echo htmlspecialchars($template['category']); ?></td>
                                <td><?php echo strtoupper($template['language']); ?></td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'approved' => 'success',
                                        'pending' => 'warning',
                                        'rejected' => 'danger'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$template['status']]; ?>">
                                        <?php echo ucfirst($template['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($template['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
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
