<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gamification-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name   = $_SESSION['admin_name'];
$current_page = 'monthly-bonus';
$errors       = [];
$success      = '';

// Fetch monthly leaderboard top 10
$leaderboard = [];
try {
    $leaderboard = getLeaderboard($pdo, 'monthly', 10);
} catch (Throwable $e) {
    error_log("Monthly Bonus - Leaderboard fetch error: " . $e->getMessage());
}

// Fetch all users for manual selection dropdown
$users = [];
try {
    $stmt = $pdo->query("SELECT id, name, email FROM users WHERE user_type = 'user' AND status = 'active' ORDER BY name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Monthly Bonus - Users fetch error: " . $e->getMessage());
}

// Fetch bonus history
$bonus_history = [];
try {
    $stmt = $pdo->query("
        SELECT wt.*, u.name AS user_name, u.email AS user_email
        FROM wallet_transactions wt
        JOIN users u ON wt.user_id = u.id
        WHERE wt.type = 'admin_bonus'
        ORDER BY wt.created_at DESC
        LIMIT 50
    ");
    $bonus_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Monthly Bonus - History fetch error: " . $e->getMessage());
}

// Handle POST: award bonus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $user_id      = (int)($_POST['user_id'] ?? 0);
    $bonus_amount = (float)($_POST['bonus_amount'] ?? 0);
    $bonus_type   = trim($_POST['bonus_type'] ?? '');

    if ($user_id <= 0) {
        $errors[] = 'Please select a valid user.';
    }
    if ($bonus_amount <= 0) {
        $errors[] = 'Bonus amount must be greater than 0.';
    }
    if (empty($bonus_type)) {
        $errors[] = 'Please specify the bonus type / reason.';
    }

    if (empty($errors)) {
        $description = 'Monthly Bonus: ' . $bonus_type . ' (awarded by ' . $admin_name . ')';
        $wallet_ok   = addToWallet($user_id, $bonus_amount, 'admin_bonus', $description);
        if ($wallet_ok) {
            // Award bonus points (10 pts per ₹1)
            $points = (int)($bonus_amount * 10);
            try {
                awardPoints($pdo, $user_id, $points, 'admin_bonus', $description);
            } catch (Throwable $e) {
                error_log("Monthly Bonus - awardPoints error: " . $e->getMessage());
            }
            $success = '✅ ₹' . number_format($bonus_amount, 2) . ' bonus awarded successfully!';
            // Refresh bonus history
            try {
                $stmt = $pdo->query("
                    SELECT wt.*, u.name AS user_name, u.email AS user_email
                    FROM wallet_transactions wt
                    JOIN users u ON wt.user_id = u.id
                    WHERE wt.type = 'admin_bonus'
                    ORDER BY wt.created_at DESC
                    LIMIT 50
                ");
                $bonus_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) { /* ignore */ }
        } else {
            $errors[] = 'Failed to award bonus. Please try again.';
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏅 Monthly Bonus — <?php echo htmlspecialchars(APP_NAME); ?> Admin</title>
    <?php require_once __DIR__ . '/includes/styles.php'; ?>
    <style>
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:25px; }
        @media(max-width:768px) { .two-col { grid-template-columns:1fr; } }
        .card { background:#fff; border-radius:12px; padding:25px; box-shadow:0 2px 10px rgba(0,0,0,0.04); margin-bottom:25px; }
        .card-title { font-size:18px; font-weight:700; color:#1e293b; margin-bottom:20px; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-weight:600; margin-bottom:6px; color:#1e293b; font-size:14px; }
        .form-control { width:100%; padding:10px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; }
        .form-control:focus { border-color:#667eea; outline:none; box-shadow:0 0 0 3px rgba(102,126,234,0.1); }
        .btn { padding:10px 20px; border:none; border-radius:8px; font-weight:600; cursor:pointer; font-size:14px; }
        .btn-primary { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; }
        .leaderboard-table { width:100%; border-collapse:collapse; }
        .leaderboard-table th { background:#f8fafc; padding:10px 14px; text-align:left; font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; }
        .leaderboard-table td { padding:11px 14px; border-bottom:1px solid #f1f5f9; font-size:14px; }
        .leaderboard-table tr:last-child td { border-bottom:none; }
        .rank-1 { color:#f59e0b; font-size:18px; }
        .alert { padding:14px 18px; border-radius:8px; margin-bottom:20px; font-size:14px; }
        .alert-success { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
        .alert-danger { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
        .empty-state { text-align:center; padding:40px; color:#94a3b8; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">

        <div class="page-header">
            <div class="page-title">🏅 Monthly Bonus Awards</div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger">❌ <?php echo htmlspecialchars($err); ?></div>
        <?php endforeach; ?>

        <div class="two-col">
            <!-- Leaderboard -->
            <div class="card">
                <div class="card-title">🏆 Monthly Leaderboard — Top 10</div>
                <?php if (empty($leaderboard)): ?>
                    <div class="empty-state">No leaderboard data available yet.</div>
                <?php else: ?>
                    <table class="leaderboard-table">
                        <thead>
                            <tr><th>#</th><th>User</th><th>Points</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard as $i => $row): ?>
                            <tr>
                                <td><?php if ($i === 0): ?><span class="rank-1">👑</span><?php else: ?><?php echo $i + 1; ?><?php endif; ?></td>
                                <td><?php echo htmlspecialchars($row['name'] ?? $row['username'] ?? 'User #' . $row['user_id']); ?></td>
                                <td><?php echo number_format((int)($row['total_points'] ?? $row['points'] ?? 0)); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Award Bonus Form -->
            <div class="card">
                <div class="card-title">🎁 Award Bonus</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-group">
                        <label for="user_id">Select User *</label>
                        <select name="user_id" id="user_id" class="form-control" required>
                            <option value="">— Choose a user —</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo (isset($_POST['user_id']) && (int)$_POST['user_id'] === (int)$u['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="bonus_amount">Bonus Amount (₹) *</label>
                        <input type="number" name="bonus_amount" id="bonus_amount" class="form-control" min="1" step="0.01" placeholder="e.g. 500" value="<?php echo htmlspecialchars($_POST['bonus_amount'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="bonus_type">Bonus Type / Reason *</label>
                        <input type="text" name="bonus_type" id="bonus_type" class="form-control" placeholder="e.g. Monthly Rank #1 — March 2026" value="<?php echo htmlspecialchars($_POST['bonus_type'] ?? ''); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">🏅 Award Bonus</button>
                </form>
            </div>
        </div>

        <!-- Bonus History -->
        <div class="card">
            <div class="card-title">📋 Bonus History</div>
            <?php if (empty($bonus_history)): ?>
                <div class="empty-state">No monthly bonuses have been awarded yet.</div>
            <?php else: ?>
                <table class="leaderboard-table">
                    <thead>
                        <tr><th>Date</th><th>User</th><th>Amount</th><th>Description</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bonus_history as $h): ?>
                        <tr>
                            <td><?php echo date('d M Y, h:i A', strtotime($h['created_at'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($h['user_name']); ?></strong><br>
                                <small style="color:#64748b"><?php echo htmlspecialchars($h['user_email']); ?></small>
                            </td>
                            <td style="font-weight:700;color:#059669">₹<?php echo number_format((float)$h['amount'], 2); ?></td>
                            <td style="color:#64748b;font-size:13px"><?php echo htmlspecialchars($h['description']); ?></td>
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
