<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/fraud-detection-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

// Handle batch scanning before any output
if (isset($_GET['action']) && $_GET['action'] === 'scan') {
    runBatchFraudDetection($pdo, 50);
    header('Location: fraud-detection.php');
    exit;
}

$admin_name = $_SESSION['admin_name'];

// Get high-risk users
$high_risk_users = getHighRiskUsers($pdo, 'high');

// Get recent fraud alerts
$fraud_alerts = getFraudAlerts($pdo, 'new', 50);

// Handle alert status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_alert'])) {
    $alert_id = intval($_POST['alert_id']);
    $status = sanitizeInput($_POST['status']);
    updateFraudAlertStatus($pdo, $alert_id, $status, $_SESSION['admin_id'] ?? 1);
    header('Location: fraud-detection.php');
    exit;
}

$current_page = 'fraud-detection';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fraud Detection - Admin Panel</title>
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
        <h2 class="mb-4"><i class="bi bi-shield-exclamation"></i> Fraud Detection Dashboard</h2>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h3><?php echo count($high_risk_users); ?></h3>
                        <p class="mb-0">High Risk Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h3><?php echo count($fraud_alerts); ?></h3>
                        <p class="mb-0">New Alerts</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h3>
                            <a href="?action=scan" class="text-white text-decoration-none">
                                <i class="bi bi-play-circle"></i> Run Scan
                            </a>
                        </h3>
                        <p class="mb-0">Batch Fraud Detection</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- High Risk Users -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-exclamation-triangle"></i> High Risk Users</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Risk Score</th>
                                <th>Risk Level</th>
                                <th>Flags</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($high_risk_users as $user): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($user['name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                </td>
                                <td><?php echo number_format($user['overall_score'], 2); ?></td>
                                <td>
                                    <?php
                                    $badge_class = $user['risk_level'] == 'critical' ? 'danger' : 'warning';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($user['risk_level']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $flags = json_decode($user['flags'] ?? '[]', true);
                                    foreach ($flags as $flag): 
                                    ?>
                                        <span class="badge bg-secondary"><?php echo $flag; ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['last_calculated'])); ?></td>
                                <td>
                                    <a href="users.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Fraud Alerts -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-bell"></i> Recent Fraud Alerts</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Alert Type</th>
                                <th>Severity</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fraud_alerts as $alert): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($alert['name']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?></td>
                                <td>
                                    <?php
                                    $severity_colors = [
                                        'critical' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'info',
                                        'low' => 'secondary'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $severity_colors[$alert['severity']]; ?>">
                                        <?php echo ucfirst($alert['severity']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(substr($alert['description'], 0, 50)); ?></td>
                                <td><?php echo date('M d, H:i', strtotime($alert['created_at'])); ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                        <select name="status" class="form-select form-select-sm d-inline w-auto" onchange="this.form.submit()">
                                            <option value="new" selected>New</option>
                                            <option value="investigating">Investigating</option>
                                            <option value="confirmed">Confirmed</option>
                                            <option value="dismissed">Dismissed</option>
                                        </select>
                                        <input type="hidden" name="update_alert" value="1">
                                    </form>
                                </td>
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
