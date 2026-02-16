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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'create_kpi') {
                $stmt = $pdo->prepare("
                    INSERT INTO kpis (name, description, metric_type, target_value, 
                                      current_value, unit, category, calculation_method)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    sanitize($_POST['name']),
                    sanitize($_POST['description']),
                    sanitize($_POST['metric_type']),
                    floatval($_POST['target_value']),
                    floatval($_POST['current_value'] ?? 0),
                    sanitize($_POST['unit']),
                    sanitize($_POST['category']),
                    sanitize($_POST['calculation_method'])
                ]);
                $success = 'KPI created successfully!';
            } elseif ($action === 'update_kpi') {
                $stmt = $pdo->prepare("
                    UPDATE kpis 
                    SET name = ?, description = ?, target_value = ?, unit = ?, 
                        category = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    sanitize($_POST['name']),
                    sanitize($_POST['description']),
                    floatval($_POST['target_value']),
                    sanitize($_POST['unit']),
                    sanitize($_POST['category']),
                    (int)($_POST['is_active'] ?? 1),
                    (int)$_POST['kpi_id']
                ]);
                $success = 'KPI updated successfully!';
            } elseif ($action === 'delete_kpi') {
                $stmt = $pdo->prepare("DELETE FROM kpis WHERE id = ?");
                $stmt->execute([(int)$_POST['kpi_id']]);
                $success = 'KPI deleted successfully!';
            } elseif ($action === 'update_value') {
                $stmt = $pdo->prepare("
                    UPDATE kpis SET current_value = ?, last_updated = NOW() WHERE id = ?
                ");
                $stmt->execute([
                    floatval($_POST['current_value']),
                    (int)$_POST['kpi_id']
                ]);
                
                // Log history
                $stmt = $pdo->prepare("
                    INSERT INTO kpi_history (kpi_id, value, recorded_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([
                    (int)$_POST['kpi_id'],
                    floatval($_POST['current_value'])
                ]);
                $success = 'KPI value updated!';
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred';
            error_log("KPI Error: " . $e->getMessage());
        }
    }
}

// Get all KPIs
$kpis = [];
try {
    $stmt = $pdo->query("
        SELECT k.*, 
               ROUND((k.current_value / NULLIF(k.target_value, 0)) * 100, 2) as achievement_percentage,
               (SELECT COUNT(*) FROM kpi_history WHERE kpi_id = k.id) as data_points
        FROM kpis k
        ORDER BY k.category, k.name
    ");
    $kpis = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to load KPIs';
}

$csrf_token = generateCSRFToken();
$current_page = 'kpi-tracking';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI Tracking - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        .kpi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;margin-bottom:30px}
        .kpi-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:20px;position:relative;transition:transform 0.3s}
        .kpi-card:hover{transform:translateY(-5px);box-shadow:0 4px 20px rgba(0,0,0,0.1)}
        .kpi-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px}
        .kpi-title{font-size:16px;font-weight:600;color:#333;margin:0}
        .kpi-category{font-size:11px;text-transform:uppercase;color:#999;letter-spacing:0.5px}
        .kpi-value{font-size:36px;font-weight:700;color:#667eea;margin:10px 0}
        .kpi-target{font-size:14px;color:#666}
        .kpi-progress{height:8px;background:#e9ecef;border-radius:10px;overflow:hidden;margin:15px 0}
        .kpi-progress-bar{height:100%;transition:width 0.5s}
        .kpi-progress-bar.success{background:linear-gradient(90deg,#10b981,#059669)}
        .kpi-progress-bar.warning{background:linear-gradient(90deg,#f59e0b,#d97706)}
        .kpi-progress-bar.danger{background:linear-gradient(90deg,#ef4444,#dc2626)}
        .kpi-actions{display:flex;gap:5px;margin-top:15px}
        .btn-sm{padding:5px 10px;font-size:12px;border-radius:6px}
        table{width:100%;border-collapse:collapse;font-size:14px}
        table th{background:#f8f9fa;padding:12px;text-align:left;font-weight:600;border-bottom:2px solid #dee2e6}
        table td{padding:12px;border-bottom:1px solid #f0f0f0}
        .badge{padding:4px 10px;border-radius:10px;font-size:11px;font-weight:600}
        .badge.success{background:#d1fae5;color:#065f46}
        .badge.warning{background:#fef3c7;color:#92400e}
        .badge.danger{background:#fee2e2;color:#991b1b}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}.kpi-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3><i class="bi bi-speedometer2"></i> KPI Tracking Dashboard</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createKpiModal">
                    <i class="bi bi-plus-circle"></i> Create KPI
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>

            <div class="kpi-grid">
                <?php foreach ($kpis as $kpi): 
                    $percentage = min($kpi['achievement_percentage'], 100);
                    $progressClass = $percentage >= 90 ? 'success' : ($percentage >= 70 ? 'warning' : 'danger');
                    $badgeClass = $percentage >= 90 ? 'success' : ($percentage >= 70 ? 'warning' : 'danger');
                ?>
                    <div class="kpi-card">
                        <div class="kpi-header">
                            <div>
                                <div class="kpi-category"><?= escape($kpi['category']) ?></div>
                                <h5 class="kpi-title"><?= escape($kpi['name']) ?></h5>
                            </div>
                            <span class="badge <?= $badgeClass ?>"><?= number_format($percentage, 1) ?>%</span>
                        </div>
                        
                        <div class="kpi-value">
                            <?= number_format($kpi['current_value'], 2) ?>
                            <small style="font-size:16px;color:#999"><?= escape($kpi['unit']) ?></small>
                        </div>
                        
                        <div class="kpi-target">
                            Target: <?= number_format($kpi['target_value'], 2) ?> <?= escape($kpi['unit']) ?>
                        </div>
                        
                        <div class="kpi-progress">
                            <div class="kpi-progress-bar <?= $progressClass ?>" 
                                 style="width:<?= $percentage ?>%"></div>
                        </div>
                        
                        <div class="kpi-actions">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="updateValue(<?= $kpi['id'] ?>, '<?= escape($kpi['name']) ?>')">
                                <i class="bi bi-pencil"></i> Update
                            </button>
                            <button class="btn btn-sm btn-outline-info" 
                                    onclick="viewHistory(<?= $kpi['id'] ?>)">
                                <i class="bi bi-graph-up"></i> History
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteKPI(<?= $kpi['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($kpis)): ?>
                <div class="card text-center py-5">
                    <i class="bi bi-speedometer" style="font-size:64px;color:#ccc"></i>
                    <h4 class="mt-3">No KPIs Defined</h4>
                    <p class="text-muted">Start tracking your key performance indicators</p>
                    <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createKpiModal">
                        Create Your First KPI
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create KPI Modal -->
    <div class="modal fade" id="createKpiModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="create_kpi">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Create New KPI</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">KPI Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="Revenue">Revenue</option>
                                    <option value="Users">Users</option>
                                    <option value="Tasks">Tasks</option>
                                    <option value="Performance">Performance</option>
                                    <option value="Quality">Quality</option>
                                    <option value="Operations">Operations</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Metric Type</label>
                                <select name="metric_type" class="form-select" required>
                                    <option value="count">Count</option>
                                    <option value="percentage">Percentage</option>
                                    <option value="currency">Currency</option>
                                    <option value="duration">Duration</option>
                                    <option value="ratio">Ratio</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Target Value</label>
                                <input type="number" step="0.01" name="target_value" class="form-control" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Current Value</label>
                                <input type="number" step="0.01" name="current_value" class="form-control" value="0">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Unit</label>
                                <input type="text" name="unit" class="form-control" placeholder="e.g., ₹, %, users">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Calculation Method</label>
                            <input type="text" name="calculation_method" class="form-control" 
                                   placeholder="e.g., SUM(revenue) / COUNT(users)">
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create KPI</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Value Modal -->
    <div class="modal fade" id="updateValueModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="update_value">
                    <input type="hidden" name="kpi_id" id="update_kpi_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Update KPI Value</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    
                    <div class="modal-body">
                        <p id="update_kpi_name"></p>
                        <div class="mb-3">
                            <label class="form-label">Current Value</label>
                            <input type="number" step="0.01" name="current_value" 
                                   id="update_current_value" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Value</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateValue(kpiId, kpiName) {
            document.getElementById('update_kpi_id').value = kpiId;
            document.getElementById('update_kpi_name').textContent = kpiName;
            new bootstrap.Modal(document.getElementById('updateValueModal')).show();
        }

        function viewHistory(kpiId) {
            window.location.href = `kpi-history.php?id=${kpiId}`;
        }

        function deleteKPI(kpiId) {
            if (!confirm('Are you sure you want to delete this KPI?')) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="delete_kpi">
                <input type="hidden" name="kpi_id" value="${kpiId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
