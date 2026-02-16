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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    switch ($_POST['action']) {
        case 'save_widget':
            $widgetData = [
                'id' => (int)($_POST['widget_id'] ?? 0),
                'user_id' => $admin_id,
                'title' => sanitize($_POST['title'] ?? ''),
                'widget_type' => sanitize($_POST['widget_type'] ?? ''),
                'data_source' => sanitize($_POST['data_source'] ?? ''),
                'config' => json_decode($_POST['config'] ?? '{}', true),
                'position_x' => (int)($_POST['position_x'] ?? 0),
                'position_y' => (int)($_POST['position_y'] ?? 0),
                'width' => (int)($_POST['width'] ?? 4),
                'height' => (int)($_POST['height'] ?? 2)
            ];
            
            if (saveWidget($widgetData)) {
                echo json_encode(['success' => true, 'message' => 'Widget saved successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save widget']);
            }
            exit;
            
        case 'delete_widget':
            $widgetId = (int)($_POST['widget_id'] ?? 0);
            if (deleteWidget($widgetId, $admin_id)) {
                echo json_encode(['success' => true, 'message' => 'Widget deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete widget']);
            }
            exit;
            
        case 'update_positions':
            $positions = json_decode($_POST['positions'] ?? '[]', true);
            if (updateWidgetPositions($positions, $admin_id)) {
                echo json_encode(['success' => true, 'message' => 'Positions updated successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update positions']);
            }
            exit;
            
        case 'get_widget_data':
            $widgetId = (int)($_POST['widget_id'] ?? 0);
            $data = getWidgetDataById($widgetId);
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
    }
}

// Get dashboard widgets
$widgets = getDashboardWidgets($admin_id);

// Get available widget types
$widgetTypes = [
    'revenue_chart' => ['icon' => 'bi-graph-up', 'name' => 'Revenue Chart'],
    'user_stats' => ['icon' => 'bi-people', 'name' => 'User Statistics'],
    'task_overview' => ['icon' => 'bi-list-check', 'name' => 'Task Overview'],
    'top_performers' => ['icon' => 'bi-trophy', 'name' => 'Top Performers'],
    'recent_activity' => ['icon' => 'bi-clock-history', 'name' => 'Recent Activity'],
    'conversion_funnel' => ['icon' => 'bi-funnel', 'name' => 'Conversion Funnel'],
    'geographic_map' => ['icon' => 'bi-map', 'name' => 'Geographic Map'],
    'kpi_card' => ['icon' => 'bi-speedometer2', 'name' => 'KPI Card']
];

$csrf_token = generateCSRFToken();
$current_page = 'bi-dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BI Dashboard - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridstack@8.0.0/dist/gridstack.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar h2{text-align:center;margin-bottom:30px;padding-bottom:20px;border-bottom:1px solid rgba(255,255,255,0.1);font-size:20px}
        .sidebar ul{list-style:none}
        .sidebar a{color:#bbb;text-decoration:none;padding:12px 15px;display:block;border-radius:8px;margin-bottom:5px;transition:all 0.3s}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,0.1);color:#fff}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);margin-bottom:20px}
        .card-header{background:#f8f9fa;padding:15px 20px;border-bottom:2px solid #e9ecef;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center}
        .btn-primary{background:#667eea;border:none;padding:10px 20px;border-radius:8px;font-weight:600}
        .btn-primary:hover{background:#5568d3}
        .grid-stack{min-height:600px}
        .grid-stack-item-content{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.08);padding:20px;overflow:auto}
        .widget-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;padding-bottom:10px;border-bottom:1px solid #e9ecef}
        .widget-title{font-size:16px;font-weight:600;margin:0}
        .widget-controls{display:flex;gap:5px}
        .widget-btn{background:none;border:none;color:#666;cursor:pointer;padding:5px;border-radius:4px;transition:all 0.3s}
        .widget-btn:hover{background:#f0f0f0;color:#333}
        .widget-content{font-size:14px;color:#666}
        .add-widget-btn{position:fixed;bottom:30px;right:30px;width:60px;height:60px;border-radius:50%;background:#667eea;color:#fff;border:none;font-size:24px;box-shadow:0 4px 12px rgba(102,126,234,0.4);cursor:pointer;transition:all 0.3s}
        .add-widget-btn:hover{transform:scale(1.1);box-shadow:0 6px 16px rgba(102,126,234,0.6)}
        .empty-state{text-align:center;padding:60px 20px;color:#999}
        .empty-state i{font-size:64px;margin-bottom:20px;opacity:0.3}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h4><i class="bi bi-grid-3x3"></i> Business Intelligence Dashboard</h4>
                    <div>
                        <button class="btn btn-primary" onclick="toggleEditMode()">
                            <i class="bi bi-pencil"></i> <span id="editModeText">Edit Mode</span>
                        </button>
                        <a href="dashboard-builder.php" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Add Widget
                        </a>
                    </div>
                </div>
            </div>

            <?php if (empty($widgets)): ?>
                <div class="card">
                    <div class="empty-state">
                        <i class="bi bi-grid"></i>
                        <h3>No Widgets Yet</h3>
                        <p>Start building your custom dashboard by adding widgets</p>
                        <a href="dashboard-builder.php" class="btn btn-primary mt-3">
                            <i class="bi bi-plus-circle"></i> Create Your First Widget
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid-stack">
                    <?php foreach ($widgets as $widget): 
                        $config = json_decode($widget['config'], true) ?? [];
                    ?>
                        <div class="grid-stack-item" 
                             data-gs-id="<?= $widget['id'] ?>"
                             data-gs-x="<?= $widget['position_x'] ?>" 
                             data-gs-y="<?= $widget['position_y'] ?>" 
                             data-gs-width="<?= $widget['width'] ?>" 
                             data-gs-height="<?= $widget['height'] ?>">
                            <div class="grid-stack-item-content">
                                <div class="widget-header">
                                    <h5 class="widget-title"><?= escape($widget['title']) ?></h5>
                                    <div class="widget-controls">
                                        <button class="widget-btn" onclick="refreshWidget(<?= $widget['id'] ?>)" title="Refresh">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                        <button class="widget-btn" onclick="configureWidget(<?= $widget['id'] ?>)" title="Configure">
                                            <i class="bi bi-gear"></i>
                                        </button>
                                        <button class="widget-btn text-danger" onclick="deleteWidget(<?= $widget['id'] ?>)" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="widget-content" id="widget-content-<?= $widget['id'] ?>">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gridstack@8.0.0/dist/gridstack-all.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        let grid;
        let editMode = false;

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize GridStack
            grid = GridStack.init({
                cellHeight: 100,
                margin: 15,
                disableDrag: true,
                disableResize: true
            });

            // Load widget data
            loadAllWidgets();
        });

        function toggleEditMode() {
            editMode = !editMode;
            const text = document.getElementById('editModeText');
            
            if (editMode) {
                grid.enable();
                text.textContent = 'Save Layout';
            } else {
                grid.disable();
                text.textContent = 'Edit Mode';
                saveLayout();
            }
        }

        function saveLayout() {
            const items = grid.save();
            const positions = items.map(item => ({
                id: parseInt(item.id),
                x: item.x,
                y: item.y,
                w: item.w,
                h: item.h
            }));

            fetch('bi-dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'update_positions',
                    positions: JSON.stringify(positions),
                    csrf_token: '<?= $csrf_token ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification('Layout saved successfully', 'success');
                }
            });
        }

        function loadAllWidgets() {
            document.querySelectorAll('[data-gs-id]').forEach(el => {
                const widgetId = el.dataset.gsId;
                loadWidgetData(widgetId);
            });
        }

        function loadWidgetData(widgetId) {
            fetch('bi-dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'get_widget_data',
                    widget_id: widgetId,
                    csrf_token: '<?= $csrf_token ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    renderWidget(widgetId, data.data);
                }
            });
        }

        function renderWidget(widgetId, data) {
            const container = document.getElementById(`widget-content-${widgetId}`);
            if (!container) return;

            // Render based on widget type
            container.innerHTML = `<div class="widget-data">${JSON.stringify(data)}</div>`;
        }

        function refreshWidget(widgetId) {
            loadWidgetData(widgetId);
        }

        function configureWidget(widgetId) {
            window.location.href = `dashboard-builder.php?edit=${widgetId}`;
        }

        function deleteWidget(widgetId) {
            if (!confirm('Are you sure you want to delete this widget?')) return;

            fetch('bi-dashboard.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'delete_widget',
                    widget_id: widgetId,
                    csrf_token: '<?= $csrf_token ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function showNotification(message, type) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</body>
</html>
