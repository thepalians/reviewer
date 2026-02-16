<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/commission-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$message = '';
$errors = [];

// Handle Add Commission Tier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tier'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $min_tasks = intval($_POST['min_tasks'] ?? 0);
    $max_tasks = !empty($_POST['max_tasks']) ? intval($_POST['max_tasks']) : null;
    $base_multiplier = floatval($_POST['base_multiplier'] ?? 1.00);
    $bonus_percentage = floatval($_POST['bonus_percentage'] ?? 0);
    
    if (empty($name)) {
        $errors[] = "Tier name is required";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO commission_tiers 
                (name, min_tasks, max_tasks, base_multiplier, bonus_percentage, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$name, $min_tasks, $max_tasks, $base_multiplier, $bonus_percentage]);
            $message = "Commission tier added successfully!";
        } catch (PDOException $e) {
            $errors[] = "Failed to add tier: " . $e->getMessage();
        }
    }
}

// Handle Add Bonus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bonus'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $bonus_type = sanitizeInput($_POST['bonus_type'] ?? '');
    $bonus_amount = !empty($_POST['bonus_amount']) ? floatval($_POST['bonus_amount']) : null;
    $bonus_percentage = !empty($_POST['bonus_percentage']) ? floatval($_POST['bonus_percentage']) : null;
    $valid_from = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
    $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
    
    if (empty($name) || empty($bonus_type)) {
        $errors[] = "Bonus name and type are required";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO commission_bonuses 
                (name, bonus_type, bonus_amount, bonus_percentage, valid_from, valid_until, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$name, $bonus_type, $bonus_amount, $bonus_percentage, $valid_from, $valid_until]);
            $message = "Commission bonus added successfully!";
        } catch (PDOException $e) {
            $errors[] = "Failed to add bonus: " . $e->getMessage();
        }
    }
}

// Get tiers and bonuses
try {
    $tiers = getActiveCommissionTiers($pdo);
    $bonuses = getActiveCommissionBonuses($pdo);
} catch (Exception $e) {
    $tiers = [];
    $bonuses = [];
}

$current_page = 'commission-rules';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Rules - Admin Panel</title>
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
        <h2 class="mb-4"><i class="bi bi-cash-coin"></i> Commission Rules Management</h2>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Commission Tier -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-plus-circle"></i> Add Commission Tier</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tier Name *</label>
                            <input type="text" class="form-control" name="name" placeholder="e.g., Bronze Tier" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Min Tasks *</label>
                            <input type="number" class="form-control" name="min_tasks" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Tasks</label>
                            <input type="number" class="form-control" name="max_tasks" placeholder="Leave empty for unlimited">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Base Multiplier *</label>
                            <input type="number" step="0.01" class="form-control" name="base_multiplier" value="1.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bonus Percentage</label>
                            <input type="number" step="0.01" class="form-control" name="bonus_percentage" value="0">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="add_tier" class="btn btn-primary">
                                <i class="bi bi-plus"></i> Add Tier
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tiers List -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-layers"></i> Commission Tiers</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Task Range</th>
                                <th>Multiplier</th>
                                <th>Bonus %</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tiers as $tier): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tier['name']); ?></td>
                                <td>
                                    <?php echo $tier['min_tasks']; ?> - 
                                    <?php echo $tier['max_tasks'] ?? '∞'; ?>
                                </td>
                                <td><?php echo number_format($tier['base_multiplier'], 2); ?>x</td>
                                <td><?php echo number_format($tier['bonus_percentage'], 2); ?>%</td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td><?php echo date('M d, Y', strtotime($tier['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Commission Bonus -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-gift"></i> Add Commission Bonus</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Bonus Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bonus Type *</label>
                            <select class="form-select" name="bonus_type" required>
                                <option value="">Select Type</option>
                                <option value="first_task">First Task of Day</option>
                                <option value="streak">Streak Bonus</option>
                                <option value="quality">Quality Bonus</option>
                                <option value="speed">Speed Bonus</option>
                                <option value="referral">Referral Bonus</option>
                                <option value="special">Special Bonus</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bonus Amount (₹)</label>
                            <input type="number" step="0.01" class="form-control" name="bonus_amount">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bonus Percentage (%)</label>
                            <input type="number" step="0.01" class="form-control" name="bonus_percentage">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valid From</label>
                            <input type="date" class="form-control" name="valid_from">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valid Until</label>
                            <input type="date" class="form-control" name="valid_until">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="add_bonus" class="btn btn-primary">
                                <i class="bi bi-plus"></i> Add Bonus
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bonuses List -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-list-stars"></i> Active Bonuses</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Percentage</th>
                                <th>Valid Period</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bonuses as $bonus): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bonus['name']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $bonus['bonus_type'])); ?></td>
                                <td>₹<?php echo number_format($bonus['bonus_amount'] ?? 0, 2); ?></td>
                                <td><?php echo number_format($bonus['bonus_percentage'] ?? 0, 2); ?>%</td>
                                <td>
                                    <?php if ($bonus['valid_from'] && $bonus['valid_until']): ?>
                                        <?php echo date('M d', strtotime($bonus['valid_from'])); ?> - 
                                        <?php echo date('M d', strtotime($bonus['valid_until'])); ?>
                                    <?php else: ?>
                                        Always
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-success">Active</span></td>
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
