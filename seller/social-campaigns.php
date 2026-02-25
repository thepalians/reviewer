<?php
/**
 * Seller — Social Campaigns List
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/social-functions.php';

include 'includes/header.php';

$seller_id = (int)$_SESSION['seller_id'];

// Handle pause/resume actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action      = $_POST['action'] ?? '';
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);

    if ($campaign_id && in_array($action, ['pause', 'resume'])) {
        try {
            $new_status = $action === 'pause' ? 'paused' : 'active';
            // Can only resume if admin_approved = 1
            $extra = $action === 'resume' ? " AND admin_approved = 1" : "";
            $stmt = $pdo->prepare("UPDATE social_campaigns SET status = ? WHERE id = ? AND seller_id = ?$extra");
            $stmt->execute([$new_status, $campaign_id, $seller_id]);
            $msg = $action === 'pause' ? 'Campaign paused.' : 'Campaign resumed.';
            $success = $msg;
        } catch (PDOException $e) {
            error_log("social-campaigns action error: " . $e->getMessage());
            $error = 'Action failed. Please try again.';
        }
    }
}

// Fetch campaigns
try {
    $stmt = $pdo->prepare("
        SELECT sc.*, sp.name AS platform_name, sp.icon AS platform_icon, sp.color AS platform_color,
               sp.slug AS platform_slug
        FROM social_campaigns sc
        JOIN social_platforms sp ON sp.id = sc.platform_id
        WHERE sc.seller_id = ?
        ORDER BY sc.created_at DESC
    ");
    $stmt->execute([$seller_id]);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("social-campaigns list error: " . $e->getMessage());
    $campaigns = [];
}

$csrf_token = generateCSRFToken();
?>

<style>
.status-badge { padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.status-active   { background: #d4edda; color: #155724; }
.status-pending  { background: #fff3cd; color: #856404; }
.status-paused   { background: #f8d7da; color: #721c24; }
.status-completed { background: #d1ecf1; color: #0c5460; }
.status-rejected { background: #f8d7da; color: #721c24; }
.campaign-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.25rem; margin-bottom: 1rem; }
.platform-badge { display:inline-flex;align-items:center;gap:0.3rem;padding:0.2rem 0.6rem;border-radius:20px;font-size:0.75rem;font-weight:600;color:white; }
.progress-bar-wrap { background:#f0f0f0;border-radius:6px;height:8px;margin:0.5rem 0; }
.progress-bar-fill { height:8px;border-radius:6px;background:linear-gradient(90deg,#4f46e5,#7c3aed); }
</style>

<h2 style="margin-bottom:1.5rem;font-size:1.5rem;font-weight:700;">📢 My Social Campaigns</h2>

<?php if (!empty($success)): ?>
<div class="alert alert-success" style="background:#d4edda;color:#155724;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger" style="background:#f8d7da;color:#721c24;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div style="margin-bottom:1.5rem;">
    <a href="create-campaign.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Create New Campaign
    </a>
</div>

<?php if (empty($campaigns)): ?>
<div style="text-align:center;padding:3rem;color:#888;">
    <div style="font-size:3rem;">📭</div>
    <p>You have no campaigns yet. <a href="create-campaign.php">Create your first campaign!</a></p>
</div>
<?php else: ?>
<?php foreach ($campaigns as $c):
    $pct = $c['total_tasks_needed'] > 0 ? round($c['tasks_completed'] / $c['total_tasks_needed'] * 100) : 0;
    $budget_remaining = max(0, (float)$c['budget'] - ($c['tasks_completed'] * (float)$c['reward_per_task']));
?>
<div class="campaign-card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.75rem;">
        <div>
            <span class="platform-badge" style="background:<?php echo htmlspecialchars($c['platform_color']); ?>">
                <i class="<?php echo htmlspecialchars($c['platform_icon']); ?>"></i>
                <?php echo htmlspecialchars($c['platform_name']); ?>
            </span>
            <h3 style="font-size:1rem;font-weight:700;margin:0.4rem 0 0.25rem;"><?php echo htmlspecialchars($c['title']); ?></h3>
            <div style="font-size:0.82rem;color:#888;">
                Created: <?php echo date('d M Y', strtotime($c['created_at'])); ?>
                &bull; Reward: ₹<?php echo number_format((float)$c['reward_per_task'], 2); ?>/task
                &bull; Budget: ₹<?php echo number_format((float)$c['budget'], 2); ?>
            </div>
        </div>
        <div>
            <span class="status-badge status-<?php echo htmlspecialchars($c['status']); ?>">
                <?php echo ucfirst($c['status']); ?>
                <?php if ($c['status'] === 'pending'): ?>(Awaiting Approval)<?php endif; ?>
            </span>
        </div>
    </div>

    <div class="progress-bar-wrap">
        <div class="progress-bar-fill" style="width:<?php echo $pct; ?>%"></div>
    </div>
    <div style="font-size:0.8rem;color:#666;margin-bottom:0.75rem;">
        <?php echo $c['tasks_completed']; ?>/<?php echo $c['total_tasks_needed']; ?> tasks &bull;
        Budget remaining: ₹<?php echo number_format($budget_remaining, 2); ?>
    </div>

    <?php if (in_array($c['status'], ['active', 'paused'])): ?>
    <form method="POST" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="campaign_id" value="<?php echo (int)$c['id']; ?>">
        <?php if ($c['status'] === 'active'): ?>
        <input type="hidden" name="action" value="pause">
        <button type="submit" class="btn btn-sm btn-outline-secondary">⏸ Pause</button>
        <?php else: ?>
        <input type="hidden" name="action" value="resume">
        <button type="submit" class="btn btn-sm btn-outline-primary" <?php echo !$c['admin_approved'] ? 'disabled title="Needs admin approval first"' : ''; ?>>▶️ Resume</button>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

</div><!-- .main-content -->
</body>
</html>
