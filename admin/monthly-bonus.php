<?php
/**
 * Admin — Monthly Leaderboard Bonus
 */
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

$current_page = 'monthly-bonus';
$admin_name = escape($_SESSION['admin_name'] ?? 'Admin');

$message = '';
$error   = '';

// Monthly leaderboard top 10
$leaderboard = [];
try {
    $leaderboard = getLeaderboard($pdo, 'monthly', 10);
} catch (PDOException $e) {
    error_log("monthly-bonus leaderboard error: " . $e->getMessage());
}

// All users for manual selection
$users = [];
try {
    $users = $pdo->query("SELECT id, name, email FROM users WHERE user_type = 'user' ORDER BY name ASC")
                 ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("monthly-bonus users error: " . $e->getMessage());
}

// Bonus history
$bonus_history = [];
try {
    $bonus_history = $pdo->query("
        SELECT wt.*, u.name AS user_name, u.email AS user_email
        FROM wallet_transactions wt
        JOIN users u ON u.id = wt.user_id
        WHERE wt.type = 'admin_bonus'
        ORDER BY wt.created_at DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("monthly-bonus history error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $recipient_id  = (int)($_POST['user_id'] ?? 0);
    $bonus_amount  = (float)($_POST['bonus_amount'] ?? 0);
    $bonus_type    = $_POST['bonus_type'] ?? 'Monthly Rank #1 Bonus';
    $remarks       = trim($_POST['remarks'] ?? '');

    $allowed_types = ['Monthly Rank #1 Bonus', 'Special Achievement Bonus', 'Contest Winner Bonus', 'Custom Bonus'];
    if (!in_array($bonus_type, $allowed_types, true)) {
        $bonus_type = 'Custom Bonus';
    }

    if ($recipient_id <= 0 || $bonus_amount <= 0) {
        $error = 'Please select a valid user and enter a positive bonus amount.';
    } else {
        // Verify the user exists
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND user_type = 'user'");
        $stmt->execute([$recipient_id]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipient) {
            $error = 'Selected user not found.';
        } else {
            $month_label = date('F Y');
            $description = $bonus_type . ' — ' . $month_label;
            if ($remarks !== '') {
                $description .= ': ' . $remarks;
            }

            $credited = addToWallet($recipient_id, $bonus_amount, 'admin_bonus', $description);
            if ($credited) {
                // Award gamification points for receiving a bonus
                try {
                    awardPoints($pdo, $recipient_id, 50, 'bonus', $description);
                } catch (Throwable $e) {
                    error_log("monthly-bonus awardPoints error: " . $e->getMessage());
                }
                $message = '✅ ₹' . number_format($bonus_amount, 2) . ' bonus awarded to ' . htmlspecialchars($recipient['name']) . '.';
                // Refresh history
                try {
                    $bonus_history = $pdo->query("
                        SELECT wt.*, u.name AS user_name, u.email AS user_email
                        FROM wallet_transactions wt
                        JOIN users u ON u.id = wt.user_id
                        WHERE wt.type = 'admin_bonus'
                        ORDER BY wt.created_at DESC
                        LIMIT 50
                    ")->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    // ignore refresh error
                }
            } else {
                $error = 'Failed to credit wallet. Please try again.';
            }
        }
    }
}

$csrf_token      = generateCSRFToken();
$top_user_id     = !empty($leaderboard) ? (int)$leaderboard[0]['id'] : 0;
$current_month   = date('F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏅 Monthly Bonus — <?php echo htmlspecialchars(APP_NAME); ?> Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php require_once __DIR__ . '/includes/styles.php'; ?>
    <style>
        .gold-rank { background: linear-gradient(135deg, #f6d365, #fda085); color: #7a4500; font-weight: 700; border-radius: 50%; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; }
        .rank-num  { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; color: #475569; font-size: 13px; font-weight: 600; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">

        <div class="page-header">
            <div>
                <h1 class="page-title">🏅 Monthly Leaderboard Bonus</h1>
                <p class="page-subtitle">Award bonus rewards to top-ranked users — <?php echo htmlspecialchars($current_month); ?></p>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:25px;align-items:start;">

            <!-- Section A: Monthly Leaderboard Top 10 -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">🏆 <?php echo htmlspecialchars($current_month); ?> Leaderboard — Top 10</span>
                </div>
                <div class="card-body" style="padding:0;">
                    <?php if (empty($leaderboard)): ?>
                    <p class="text-muted text-center py-4">No leaderboard data yet.</p>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>User</th>
                                <th>Points</th>
                                <th>Badges</th>
                                <th>Streak</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($leaderboard as $i => $entry): ?>
                        <tr <?php echo $i === 0 ? 'style="background:#fffbeb;"' : ''; ?>>
                            <td>
                                <?php if ($i === 0): ?>
                                    <span class="gold-rank">👑</span>
                                <?php else: ?>
                                    <span class="rank-num"><?php echo $i + 1; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($entry['username'] ?? 'User #' . $entry['id']); ?></strong>
                            </td>
                            <td><?php echo number_format((int)$entry['points']); ?></td>
                            <td><?php echo (int)($entry['badge_count'] ?? 0); ?></td>
                            <td><?php echo (int)($entry['streak_days'] ?? 0); ?>d</td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section B: Award Bonus Form -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">💰 Award Bonus</span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Recipient User *</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Select user…</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>"
                                    <?php echo (int)$u['id'] === $top_user_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($top_user_id): ?>
                            <div class="form-text">👑 #1 ranked user pre-selected.</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bonus Amount (₹) *</label>
                            <input type="number" name="bonus_amount" class="form-control" min="1" step="0.01" placeholder="e.g. 500" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bonus Type</label>
                            <select name="bonus_type" class="form-select">
                                <option>Monthly Rank #1 Bonus</option>
                                <option>Special Achievement Bonus</option>
                                <option>Contest Winner Bonus</option>
                                <option>Custom Bonus</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Remarks / Reason</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Optional: Add a note…"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            🏅 Award Bonus
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Section C: Bonus History -->
        <div class="card" style="margin-top:25px;">
            <div class="card-header">
                <span class="card-title">📋 Bonus Award History</span>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($bonus_history)): ?>
                <p class="text-muted text-center py-4">No bonuses awarded yet.</p>
                <?php else: ?>
                <div class="table-responsive">
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
                            <td><?php echo date('d M Y, H:i', strtotime($h['created_at'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($h['user_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($h['user_email']); ?></small>
                            </td>
                            <td><strong>₹<?php echo number_format((float)$h['amount'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars($h['description']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- .main-content -->
</div><!-- .admin-layout -->
</body>
</html>
