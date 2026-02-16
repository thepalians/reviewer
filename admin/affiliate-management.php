<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/affiliate-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'];
        $affiliateId = (int)($_POST['affiliate_id'] ?? 0);
        
        try {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE affiliates SET status = 'active', approved_at = NOW() WHERE id = ?");
                $stmt->execute([$affiliateId]);
                $success = 'Affiliate approved!';
            } elseif ($action === 'suspend') {
                $stmt = $pdo->prepare("UPDATE affiliates SET status = 'suspended' WHERE id = ?");
                $stmt->execute([$affiliateId]);
                $success = 'Affiliate suspended!';
            } elseif ($action === 'update_commission') {
                $stmt = $pdo->prepare("UPDATE affiliates SET commission_rate = ? WHERE id = ?");
                $stmt->execute([floatval($_POST['commission_rate']), $affiliateId]);
                $success = 'Commission rate updated!';
            }
        } catch (PDOException $e) {
            $error = 'Database error';
            error_log("Affiliate error: " . $e->getMessage());
        }
    }
}

// Get affiliates with stats
$affiliates = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, u.name, u.email,
               (SELECT COUNT(*) FROM affiliate_referrals WHERE affiliate_id = a.id) as total_referrals,
               (SELECT SUM(commission_amount) FROM affiliate_commissions WHERE affiliate_id = a.id AND status = 'paid') as total_earned,
               (SELECT SUM(commission_amount) FROM affiliate_commissions WHERE affiliate_id = a.id AND status = 'pending') as pending_commission
        FROM affiliates a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
    ");
    $affiliates = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to load affiliates';
}

$csrf_token = generateCSRFToken();
$current_page = 'affiliate-management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliate Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f5f5f5;font-family:"Segoe UI",sans-serif}
        .wrapper{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(135deg,#2c3e50,#1a252f);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow-y:auto}
        .content{padding:30px}
        .card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:25px;margin-bottom:20px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        table th{background:#f8f9fa;padding:10px;text-align:left;font-weight:600;border-bottom:2px solid #dee2e6}
        table td{padding:10px;border-bottom:1px solid #f0f0f0}
        .badge{padding:4px 10px;border-radius:10px;font-size:11px;font-weight:600}
        .badge.active{background:#d1fae5;color:#065f46}
        .badge.pending{background:#fef3c7;color:#92400e}
        .badge.suspended{background:#fee2e2;color:#991b1b}
        @media(max-width:768px){.wrapper{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="content">
            <h3 class="mb-4"><i class="bi bi-people"></i> Affiliate Management</h3>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= escape($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?= escape($success) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Affiliate</th>
                                <th>Code</th>
                                <th>Status</th>
                                <th>Referrals</th>
                                <th>Earned</th>
                                <th>Pending</th>
                                <th>Commission %</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($affiliates as $aff): ?>
                                <tr>
                                    <td>
                                        <strong><?= escape($aff['name']) ?></strong><br>
                                        <small class="text-muted"><?= escape($aff['email']) ?></small>
                                    </td>
                                    <td><code><?= escape($aff['affiliate_code']) ?></code></td>
                                    <td><span class="badge <?= $aff['status'] ?>"><?= ucfirst($aff['status']) ?></span></td>
                                    <td><?= $aff['total_referrals'] ?></td>
                                    <td>₹<?= number_format($aff['total_earned'] ?? 0, 2) ?></td>
                                    <td>₹<?= number_format($aff['pending_commission'] ?? 0, 2) ?></td>
                                    <td><?= number_format($aff['commission_rate'], 2) ?>%</td>
                                    <td><?= date('M d, Y', strtotime($aff['created_at'])) ?></td>
                                    <td>
                                        <?php if ($aff['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="affiliate_id" value="<?= $aff['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                            </form>
                                        <?php elseif ($aff['status'] === 'active'): ?>
                                            <form method="POST" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <input type="hidden" name="affiliate_id" value="<?= $aff['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-warning">Suspend</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="affiliate-stats.php?id=<?= $aff['id'] ?>" class="btn btn-sm btn-info">Stats</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
