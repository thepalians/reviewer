<?php
/**
 * User — Social Media Hub
 * Browse and filter social campaigns, earn by watching content
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/social-functions.php';

if (!isUser()) {
    redirect(APP_URL . '/index.php');
}

$user_id      = (int)$_SESSION['user_id'];
$current_page = 'social-hub';

// Active platform filter
$filter_platform = isset($_GET['platform']) ? (int)$_GET['platform'] : 0;

// Fetch all active platforms with campaign counts
try {
    $stmt = $pdo->query("
        SELECT sp.*,
               COUNT(sc.id) AS campaign_count,
               COALESCE(SUM(sc.reward_per_task * (sc.total_tasks_needed - sc.tasks_completed)), 0) AS potential_earnings
        FROM social_platforms sp
        LEFT JOIN social_campaigns sc
               ON sc.platform_id = sp.id
              AND sc.status = 'active'
              AND sc.admin_approved = 1
              AND sc.tasks_completed < sc.total_tasks_needed
        WHERE sp.is_active = 1
        GROUP BY sp.id
        ORDER BY sp.sort_order
    ");
    $platforms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("social-hub platforms error: " . $e->getMessage());
    $platforms = [];
}

// Fetch campaigns (exclude already completed by this user)
try {
    $params = [];
    $where  = "sc.status = 'active' AND sc.admin_approved = 1 AND sc.tasks_completed < sc.total_tasks_needed";

    if ($filter_platform) {
        $where .= " AND sc.platform_id = ?";
        $params[] = $filter_platform;
    }

    $where .= " AND NOT EXISTS (
        SELECT 1 FROM social_task_completions stc
        WHERE stc.campaign_id = sc.id AND stc.user_id = ?
          AND stc.status IN ('completed','verified')
    )";
    $params[] = $user_id;

    $stmt = $pdo->prepare("
        SELECT sc.*, sp.name AS platform_name, sp.slug AS platform_slug,
               sp.icon AS platform_icon, sp.color AS platform_color,
               s.name AS seller_name
        FROM social_campaigns sc
        JOIN social_platforms sp ON sp.id = sc.platform_id
        JOIN sellers s ON s.id = sc.seller_id
        WHERE $where
        ORDER BY sc.created_at DESC
    ");
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("social-hub campaigns error: " . $e->getMessage());
    $campaigns = [];
}

// Fetch user's completed campaigns for "Completed" badge
try {
    $stmt = $pdo->prepare("SELECT campaign_id FROM social_task_completions WHERE user_id = ? AND status IN ('completed','verified')");
    $stmt->execute([$user_id]);
    $completed_ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'campaign_id');
} catch (PDOException $e) {
    $completed_ids = [];
}

// User social stats
$social_stats = getUserSocialStats($pdo, $user_id);

include '../includes/security.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📱 Social Hub — <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .platform-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .platform-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem 1rem;
            text-align: center;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .platform-card:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0,0,0,0.15); }
        .platform-card.active { border-color: #667eea; background: #f0f0ff; }
        .platform-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .platform-name { font-weight: 600; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .platform-count { font-size: 0.78rem; color: #888; }

        .campaign-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        .campaign-card {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s;
            border: 1px solid #eee;
        }
        .campaign-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .campaign-thumbnail {
            width: 100%; height: 170px; object-fit: cover;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex; align-items: center; justify-content: center;
        }
        .campaign-thumbnail img { width: 100%; height: 170px; object-fit: cover; }
        .campaign-thumbnail-placeholder { font-size: 3rem; color: white; }
        .campaign-body { padding: 1.1rem; }
        .platform-badge {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.2rem 0.6rem; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600; color: white; margin-bottom: 0.6rem;
        }
        .campaign-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.3rem; }
        .campaign-meta { font-size: 0.8rem; color: #888; margin-bottom: 0.75rem; }
        .reward-badge {
            background: #d4edda; color: #155724;
            padding: 0.3rem 0.75rem; border-radius: 20px;
            font-weight: 700; font-size: 0.88rem;
        }
        .progress-bar-wrap { background: #f0f0f0; border-radius: 6px; height: 6px; margin: 0.75rem 0; }
        .progress-bar-fill { height: 6px; border-radius: 6px; background: linear-gradient(90deg, #667eea, #764ba2); }
        .campaign-footer { display: flex; justify-content: space-between; align-items: center; }
        .btn-watch {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border: none; border-radius: 8px;
            padding: 0.5rem 1rem; font-weight: 600; font-size: 0.85rem;
            cursor: pointer; text-decoration: none; display: inline-block;
            transition: opacity 0.2s;
        }
        .btn-watch:hover { opacity: 0.9; color: white; }
        .btn-completed {
            background: #28a745; color: white; border: none; border-radius: 8px;
            padding: 0.5rem 1rem; font-weight: 600; font-size: 0.85rem;
            cursor: default; display: inline-block;
        }
        .stat-chips { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .stat-chip {
            background: white; border-radius: 10px; padding: 0.75rem 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07); text-align: center;
        }
        .stat-chip .value { font-size: 1.4rem; font-weight: 700; color: #667eea; }
        .stat-chip .label { font-size: 0.78rem; color: #888; }
        .section-title { font-size: 1.3rem; font-weight: 700; margin-bottom: 1rem; }
        .empty-state { text-align: center; padding: 3rem; color: #888; }
        @media(max-width: 600px) {
            .campaign-grid { grid-template-columns: 1fr; }
            .platform-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">

    <div class="page-header" style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.8rem;font-weight:700;">📱 Social Media Hub</h1>
        <p style="color:#888;margin:0;">Watch content, engage genuinely &amp; earn rewards</p>
    </div>

    <!-- Stats -->
    <div class="stat-chips">
        <div class="stat-chip">
            <div class="value"><?php echo (int)$social_stats['total_completed']; ?></div>
            <div class="label">Tasks Done</div>
        </div>
        <div class="stat-chip">
            <div class="value">₹<?php echo number_format((float)$social_stats['total_earned'], 2); ?></div>
            <div class="label">Total Earned</div>
        </div>
        <div class="stat-chip">
            <div class="value"><?php echo count($campaigns); ?></div>
            <div class="label">Available Now</div>
        </div>
    </div>

    <!-- Platform Filter -->
    <div class="section-title">🌐 Platforms</div>
    <div class="platform-grid">
        <a href="<?php echo APP_URL; ?>/user/social-hub.php" class="platform-card <?php echo !$filter_platform ? 'active' : ''; ?>">
            <div class="platform-icon">🌐</div>
            <div class="platform-name">All</div>
            <div class="platform-count"><?php echo count($campaigns); ?> available</div>
        </a>
        <?php foreach ($platforms as $p): ?>
        <a href="<?php echo APP_URL; ?>/user/social-hub.php?platform=<?php echo $p['id']; ?>"
           class="platform-card <?php echo $filter_platform === (int)$p['id'] ? 'active' : ''; ?>">
            <div class="platform-icon">
                <i class="<?php echo htmlspecialchars($p['icon']); ?>" style="color:<?php echo htmlspecialchars($p['color']); ?>"></i>
            </div>
            <div class="platform-name"><?php echo htmlspecialchars($p['name']); ?></div>
            <div class="platform-count"><?php echo (int)$p['campaign_count']; ?> campaigns</div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Campaign List -->
    <div class="section-title">🎯 Available Campaigns</div>
    <?php if (empty($campaigns)): ?>
    <div class="empty-state">
        <div style="font-size:3rem;">📭</div>
        <p>No campaigns available right now. Check back soon!</p>
    </div>
    <?php else: ?>
    <div class="campaign-grid">
        <?php foreach ($campaigns as $c):
            $pct = $c['total_tasks_needed'] > 0 ? round($c['tasks_completed'] / $c['total_tasks_needed'] * 100) : 0;
            $is_done = in_array((int)$c['id'], $completed_ids);
        ?>
        <div class="campaign-card">
            <div class="campaign-thumbnail">
                <?php if ($c['thumbnail_url']): ?>
                <img src="<?php echo htmlspecialchars($c['thumbnail_url']); ?>" alt="thumbnail">
                <?php else: ?>
                <div class="campaign-thumbnail-placeholder">
                    <i class="<?php echo htmlspecialchars($c['platform_icon']); ?>"></i>
                </div>
                <?php endif; ?>
            </div>
            <div class="campaign-body">
                <span class="platform-badge" style="background:<?php echo htmlspecialchars($c['platform_color']); ?>">
                    <i class="<?php echo htmlspecialchars($c['platform_icon']); ?>"></i>
                    <?php echo htmlspecialchars($c['platform_name']); ?>
                </span>
                <div class="campaign-title"><?php echo htmlspecialchars($c['title']); ?></div>
                <div class="campaign-meta">By <?php echo htmlspecialchars($c['seller_name']); ?> &bull; Watch <?php echo (int)$c['watch_percent_required']; ?>%+</div>
                <div class="progress-bar-wrap">
                    <div class="progress-bar-fill" style="width:<?php echo $pct; ?>%"></div>
                </div>
                <div style="font-size:0.75rem;color:#888;margin-bottom:0.75rem;"><?php echo $c['tasks_completed']; ?>/<?php echo $c['total_tasks_needed']; ?> tasks done</div>
                <div class="campaign-footer">
                    <span class="reward-badge">₹<?php echo number_format((float)$c['reward_per_task'], 2); ?> reward</span>
                    <?php if ($is_done): ?>
                    <span class="btn-completed">✅ Completed</span>
                    <?php else: ?>
                    <a href="<?php echo APP_URL; ?>/user/social-watch.php?id=<?php echo (int)$c['id']; ?>" class="btn-watch">▶️ Watch &amp; Earn</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
