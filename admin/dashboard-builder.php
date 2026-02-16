<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bi-dashboard-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$error = '';
$success = '';

// Check if editing existing widget
$editWidgetId = (int)($_GET['edit'] ?? 0);
$editWidget = null;
if ($editWidgetId > 0) {
    $editWidget = getWidgetById($editWidgetId, $admin_id);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $widgetData = [
            'id' => $editWidgetId,
            'user_id' => $admin_id,
            'title' => sanitize($_POST['title'] ?? ''),
            'widget_type' => sanitize($_POST['widget_type'] ?? ''),
            'data_source' => sanitize($_POST['data_source'] ?? ''),
            'config' => [
                'refresh_interval' => (int)($_POST['refresh_interval'] ?? 60),
                'chart_type' => sanitize($_POST['chart_type'] ?? ''),
                'metric' => sanitize($_POST['metric'] ?? ''),
                'date_range' => sanitize($_POST['date_range'] ?? '30'),
                'filters' => $_POST['filters'] ?? []
            ],
            'width' => (int)($_POST['width'] ?? 4),
            'height' => (int)($_POST['height'] ?? 2),
            'position_x' => (int)($_POST['position_x'] ?? 0),
            'position_y' => (int)($_POST['position_y'] ?? 0)
        ];

        if (saveWidget($widgetData)) {
            $success = 'Widget saved successfully!';
            if (!$editWidgetId) {
                header('Location: bi-dashboard.php');
                exit;
            }
        } else {
            $error = 'Failed to save widget';
        }
    }
}

$csrf_token = generateCSRFToken();
$current_page = 'bi-dashboard';

// Widget type options
$widgetTypes = [
    'revenue_chart' => 'Revenue Chart',
    'user_stats' => 'User Statistics',
    'task_overview' => 'Task Overview',
    'top_performers' => 'Top Performers',
    'recent_activity' => 'Recent Activity',
    'conversion_funnel' => 'Conversion Funnel',
    'kpi_card' => 'KPI Card',
    'table_view' => 'Data Table'
];

$dataSources = [
    'users' => 'Users',
    'tasks' => 'Tasks',
    'revenue' => 'Revenue',
    'sellers' => 'Sellers',
    'transactions' => 'Transactions',
    'analytics' => 'Analytics'
];

$chartTypes = [
    'line' => 'Line Chart',
    'bar' => 'Bar Chart',
    'pie' => 'Pie Chart',
    'doughnut' => 'Doughnut Chart',
    'area' => 'Area Chart'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Builder - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f0f0}
        .form-label{font-weight:600;margin-bottom:8px;font-size:14px}
        .form-control,.form-select{border-radius:8px;border:1px solid #ddd;padding:10px 15px}
        .btn-primary{background:#667eea;border:none;padding:12px 25px;border-radius:8px;font-weight:600}
        .btn-secondary{background:#6c757d;border:none;padding:12px 25px;border-radius:8px;font-weight:600}
        .preview-box{background:#f8f9fa;border:2px dashed #ddd;border-radius:12px;padding:40px;text-align:center;min-height:200px}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h4><i class="bi bi-tools"></i> <?= $editWidget ? 'Edit' : 'Create' ?> Widget</h4>
                    <a href="bi-dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= escape($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= escape($success) ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Widget Title</label>
                            <input type="text" name="title" class="form-control" 
                                   value="<?= escape($editWidget['title'] ?? '') ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Widget Type</label>
                            <select name="widget_type" class="form-select" required onchange="updateOptions()">
                                <option value="">Select Type</option>
                                <?php foreach ($widgetTypes as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($editWidget['widget_type'] ?? '') === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Source</label>
                            <select name="data_source" class="form-select" required>
                                <option value="">Select Source</option>
                                <?php foreach ($dataSources as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($editWidget['data_source'] ?? '') === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Chart Type</label>
                            <select name="chart_type" class="form-select">
                                <option value="">Select Chart</option>
                                <?php foreach ($chartTypes as $key => $label): 
                                    $config = json_decode($editWidget['config'] ?? '{}', true);
                                    $selected = ($config['chart_type'] ?? '') === $key ? 'selected' : '';
                                ?>
                                    <option value="<?= $key ?>" <?= $selected ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Width (1-12)</label>
                            <input type="number" name="width" class="form-control" min="1" max="12" 
                                   value="<?= $editWidget['width'] ?? 4 ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Height (1-6)</label>
                            <input type="number" name="height" class="form-control" min="1" max="6" 
                                   value="<?= $editWidget['height'] ?? 2 ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Refresh Interval (seconds)</label>
                            <input type="number" name="refresh_interval" class="form-control" min="30" 
                                   value="<?= $config['refresh_interval'] ?? 60 ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date Range (days)</label>
                            <select name="date_range" class="form-select">
                                <option value="7" <?= ($config['date_range'] ?? '30') === '7' ? 'selected' : '' ?>>Last 7 Days</option>
                                <option value="30" <?= ($config['date_range'] ?? '30') === '30' ? 'selected' : '' ?>>Last 30 Days</option>
                                <option value="90" <?= ($config['date_range'] ?? '30') === '90' ? 'selected' : '' ?>>Last 90 Days</option>
                                <option value="365" <?= ($config['date_range'] ?? '30') === '365' ? 'selected' : '' ?>>Last Year</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Metric</label>
                            <input type="text" name="metric" class="form-control" 
                                   value="<?= escape($config['metric'] ?? '') ?>" 
                                   placeholder="e.g., total_revenue, user_count">
                        </div>
                    </div>

                    <div class="mb-3">
                        <h5>Preview</h5>
                        <div class="preview-box">
                            <i class="bi bi-eye" style="font-size:48px;color:#ccc"></i>
                            <p class="mt-3 text-muted">Widget preview will appear here</p>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="bi-dashboard.php" class="btn btn-secondary me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Widget
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateOptions() {
            // Update available options based on widget type
            const widgetType = document.querySelector('[name="widget_type"]').value;
            console.log('Widget type changed to:', widgetType);
        }
    </script>
</body>
</html>
