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

$admin_name = $_SESSION['admin_name'];
$current_page = 'add-bonus';
$errors = [];
$success = '';

// Get all active users for dropdown
$users = [];
try {
    $stmt = $pdo->query("SELECT id, name, email FROM users WHERE user_type = 'user' AND status = 'active' ORDER BY name");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Add Bonus - Users fetch error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bonus'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($user_id <= 0) {
        $errors[] = 'Please select a valid user.';
    }
    if ($amount <= 0) {
        $errors[] = 'Amount must be greater than 0.';
    }
    if (empty($reason)) {
        $errors[] = 'Please provide a reason for the bonus.';
    }

    if (empty($errors)) {
        $description = 'Admin Bonus: ' . $reason . ' (by ' . $admin_name . ')';
        if (addToWallet($user_id, $amount, 'bonus', $description)) {
            $success = "₹" . number_format($amount, 2) . " bonus successfully added to user's wallet.";
        } else {
            $errors[] = 'Failed to add bonus. Please try again.';
        }
    }
}

// Fetch bonus transaction history (admin bonuses)
$bonus_history = [];
try {
    $stmt = $pdo->query("
        SELECT wt.*, u.name as user_name, u.email as user_email
        FROM wallet_transactions wt
        JOIN users u ON wt.user_id = u.id
        WHERE wt.type = 'bonus' AND wt.description LIKE 'Admin Bonus:%'
        ORDER BY wt.created_at DESC
        LIMIT 50
    ");
    $bonus_history = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Add Bonus - History fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User Bonus - Admin - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
        .sidebar-menu{list-style:none;padding:15px 0}
        .sidebar-menu li{margin-bottom:5px}
        .sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
        .sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
        .sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
        .sidebar-menu a.logout{color:#e74c3c}
        .main-content{padding:25px;overflow-x:hidden}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:15px}
        .page-title{font-size:28px;font-weight:700;color:#1e293b}
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px;display:flex;align-items:center;gap:10px}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        .card{background:#fff;border-radius:12px;padding:25px;box-shadow:0 2px 10px rgba(0,0,0,0.04);margin-bottom:25px}
        .card-title{font-size:20px;font-weight:600;color:#1e293b;margin-bottom:20px}
        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#1e293b;font-size:14px}
        .form-control{width:100%;padding:12px 15px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px}
        .form-control:focus{border-color:#667eea;outline:none;box-shadow:0 0 0 3px rgba(102,126,234,0.1)}
        select.form-control{cursor:pointer}
        .input-prefix{display:flex;align-items:center}
        .input-prefix span{background:#f1f5f9;border:1px solid #e2e8f0;border-right:none;padding:12px 15px;border-radius:10px 0 0 10px;font-weight:600;color:#64748b}
        .input-prefix .form-control{border-radius:0 10px 10px 0}
        .btn{padding:12px 24px;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px;transition:all 0.2s;display:inline-flex;align-items:center;gap:8px}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .btn-primary:hover{transform:translateY(-1px);box-shadow:0 5px 15px rgba(102,126,234,0.4)}
        table{width:100%;border-collapse:collapse}
        th{background:#f8fafc;padding:12px 15px;text-align:left;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase}
        td{padding:13px 15px;border-bottom:1px solid #f1f5f9;font-size:14px}
        tr:last-child td{border-bottom:none}
        tr:hover td{background:#f8fafc}
        .amount-cell{font-weight:700;color:#059669}
        .empty-state{text-align:center;padding:40px;color:#94a3b8}
        @media(max-width:992px){.admin-layout{grid-template-columns:1fr}.sidebar{display:none}}
    </style>
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div class="page-title">🎁 Add User Bonus</div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo escape($success); ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger">❌ <?php echo escape($err); ?></div>
        <?php endforeach; ?>
        
        <!-- Bonus Form -->
        <div class="card">
            <div class="card-title">💰 Credit Bonus to User Wallet</div>
            <form method="POST">
                <div class="form-group">
                    <label for="user_id">Select User *</label>
                    <select name="user_id" id="user_id" class="form-control" required>
                        <option value="">— Choose a user —</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>" <?php echo (isset($_POST['user_id']) && (int)$_POST['user_id'] === (int)$u['id']) ? 'selected' : ''; ?>>
                                <?php echo escape($u['name']); ?> (<?php echo escape($u['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="amount">Bonus Amount (₹) *</label>
                    <div class="input-prefix">
                        <span>₹</span>
                        <input type="number" name="amount" id="amount" class="form-control" min="1" step="0.01" placeholder="e.g. 500" value="<?php echo escape($_POST['amount'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="reason">Reason / Description *</label>
                    <input type="text" name="reason" id="reason" class="form-control" placeholder="e.g. Monthly Rank #1 Bonus - February 2026" value="<?php echo escape($_POST['reason'] ?? ''); ?>" required>
                </div>
                
                <button type="submit" name="add_bonus" class="btn btn-primary">🎁 Add Bonus to Wallet</button>
            </form>
        </div>
        
        <!-- Bonus History -->
        <div class="card">
            <div class="card-title">📋 Admin Bonus History</div>
            <?php if (empty($bonus_history)): ?>
                <div class="empty-state">No admin bonuses have been given yet.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bonus_history as $h): ?>
                        <tr>
                            <td><?php echo date('d M Y, h:i A', strtotime($h['created_at'])); ?></td>
                            <td>
                                <strong><?php echo escape($h['user_name']); ?></strong><br>
                                <small style="color:#64748b"><?php echo escape($h['user_email']); ?></small>
                            </td>
                            <td class="amount-cell">₹<?php echo number_format((float)$h['amount'], 2); ?></td>
                            <td style="color:#64748b;font-size:13px"><?php echo escape($h['description']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
