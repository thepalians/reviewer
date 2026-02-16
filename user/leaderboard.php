<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/gamification-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get period filter
$period = isset($_GET['period']) ? $_GET['period'] : 'all_time';
$valid_periods = ['daily', 'weekly', 'monthly', 'all_time'];
if (!in_array($period, $valid_periods)) {
    $period = 'all_time';
}

// Helper: initials
function getInitials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $first = $parts[0] ?? '';
    $second = $parts[1] ?? '';
    $initials = mb_substr($first, 0, 1) . mb_substr($second, 0, 1);
    return strtoupper($initials ?: mb_substr($name, 0, 1));
}

// Get leaderboard
try {
    $leaderboard = getLeaderboard($pdo, $period, 1000);
    $user_rank = getUserRank($pdo, $user_id);
    $user_points = getUserPoints($pdo, $user_id);
} catch (PDOException $e) {
    $leaderboard = [];
    $user_rank = 0;
    $user_points = ['points' => 0, 'level' => 'Bronze', 'streak_days' => 0];
}

// Set current page for sidebar
$current_page = 'leaderboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - User Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
    :root {
        --bg: #f4f7ff;
        --card: #ffffff;
        --primary: #4f46e5;
        --secondary: #7c3aed;
        --accent: #22c55e;
        --text: #0f172a;
        --muted: #64748b;
        --shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    }
    body {
        background: var(--bg);
        color: var(--text);
    }

    /* Sidebar Styles */
    .sidebar {
        width: 260px;
        position: fixed;
        left: 0;
        top: 60px;
        height: calc(100vh - 60px);
        background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        overflow-y: auto;
        transition: all 0.3s ease;
        z-index: 999;
    }
    .sidebar-header {
        padding: 20px;
        background: rgba(255,255,255,0.05);
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar-header h2 {
        color: #fff;
        font-size: 18px;
        margin: 0;
    }
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .sidebar-menu li {
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .sidebar-menu li a {
        display: block;
        padding: 14px 20px;
        color: rgba(255,255,255,0.78);
        text-decoration: none;
        transition: all 0.3s;
        font-size: 14px;
    }
    .sidebar-menu li a:hover {
        background: rgba(255,255,255,0.08);
        color: #fff;
        padding-left: 26px;
    }
    .sidebar-menu li a.active {
        background: linear-gradient(90deg, rgba(99,102,241,0.25) 0%, transparent 100%);
        color: #a5b4fc;
        border-left: 3px solid #818cf8;
    }
    .sidebar-menu li a.logout {
        color: #f87171;
    }
    .sidebar-divider {
        height: 1px;
        background: rgba(255,255,255,0.1);
        margin: 10px 0;
    }
    .menu-section-label {
        padding: 14px 20px 6px;
        color: rgba(255,255,255,0.45);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .sidebar .badge {
        background: #ef4444;
        color: white;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        margin-left: 8px;
    }

    .admin-layout {
        margin-left: 260px;
        padding: 26px;
        min-height: calc(100vh - 60px);
    }

    .page-title {
        font-weight: 800;
        color: var(--text);
        letter-spacing: .3px;
    }

    .hero-card {
        background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
        color: #fff;
        border: 0;
        border-radius: 20px;
        box-shadow: var(--shadow);
        position: relative;
        overflow: hidden;
    }
    .hero-card::after {
        content: "";
        position: absolute;
        right: -60px;
        top: -60px;
        width: 220px;
        height: 220px;
        background: rgba(255,255,255,0.12);
        border-radius: 50%;
    }
    .hero-icon {
        font-size: 5.5rem;
        opacity: 0.25;
    }

    .stat-card {
        border: 0;
        border-radius: 16px;
        box-shadow: var(--shadow);
        background: var(--card);
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
    }
    .stat-card .icon {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: #eef2ff;
        color: #4f46e5;
        font-size: 20px;
    }

    .period-card {
        border-radius: 16px;
        border: 0;
        box-shadow: var(--shadow);
        background: var(--card);
    }
    .btn-period {
        border-radius: 12px !important;
        padding: 10px 14px;
        font-weight: 700;
    }

    .podium-card {
        border: 0;
        border-radius: 18px;
        box-shadow: var(--shadow);
        background: var(--card);
    }
    .podium-tile {
        border-radius: 16px;
        padding: 18px;
        background: #f8fafc;
        box-shadow: inset 0 0 0 1px #eef2f7;
        height: 100%;
    }
    .podium-gold {
        background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
        color: #fff;
    }
    .podium-rank {
        font-size: 1.25rem;
        font-weight: 800;
    }
    .podium-name {
        font-weight: 800;
    }
    .podium-points {
        font-size: 1.1rem;
        font-weight: 700;
    }

    .leaderboard-card {
        border: 0;
        border-radius: 18px;
        box-shadow: var(--shadow);
        background: var(--card);
    }
    .table thead th {
        background: #f8fafc;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .6px;
        color: var(--muted);
        border-bottom: 0;
    }
    .table tbody tr {
        border-bottom: 1px solid #eef2f7;
    }
    .table tbody tr:hover {
        background: #f7f8ff;
    }
    .you-badge {
        font-size: 11px;
    }
    .level-badge {
        color: #fff;
        font-weight: 700;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 11px;
    }
    .rank-chip {
        font-weight: 800;
    }
    .avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #e0e7ff;
        color: #4338ca;
        font-weight: 800;
        font-size: 12px;
        margin-right: 10px;
    }

    @media (max-width: 768px) {
        .sidebar {
            left: -260px;
        }
        .sidebar.active {
            left: 0;
        }
        .admin-layout {
            margin-left: 0;
        }
    }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-layout">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h2 class="page-title mb-0"><i class="bi bi-bar-chart-fill"></i> Leaderboard</h2>
        </div>

        <div class="card hero-card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-1">Your Current Rank</h4>
                        <div class="display-4 fw-bold">#<?php echo $user_rank; ?></div>
                        <p class="mb-0">Keep completing tasks to climb the leaderboard.</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="bi bi-trophy-fill hero-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="icon"><i class="bi bi-star-fill"></i></div>
                        <div>
                            <div class="text-muted">Your Points</div>
                            <div class="fs-4 fw-bold"><?php echo number_format($user_points['points']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="icon"><i class="bi bi-award-fill"></i></div>
                        <div>
                            <div class="text-muted">Current Level</div>
                            <div class="fs-4 fw-bold"><?php echo htmlspecialchars($user_points['level']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="icon"><i class="bi bi-fire"></i></div>
                        <div>
                            <div class="text-muted">Streak Days</div>
                            <div class="fs-4 fw-bold"><?php echo (int)$user_points['streak_days']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card period-card mb-4">
            <div class="card-body">
                <div class="btn-group w-100" role="group">
                    <a href="?period=daily" class="btn btn-<?php echo $period == 'daily' ? 'primary' : 'outline-primary'; ?> btn-period">
                        <i class="bi bi-calendar-day"></i> Daily
                    </a>
                    <a href="?period=weekly" class="btn btn-<?php echo $period == 'weekly' ? 'primary' : 'outline-primary'; ?> btn-period">
                        <i class="bi bi-calendar-week"></i> Weekly
                    </a>
                    <a href="?period=monthly" class="btn btn-<?php echo $period == 'monthly' ? 'primary' : 'outline-primary'; ?> btn-period">
                        <i class="bi bi-calendar-month"></i> Monthly
                    </a>
                    <a href="?period=all_time" class="btn btn-<?php echo $period == 'all_time' ? 'primary' : 'outline-primary'; ?> btn-period">
                        <i class="bi bi-clock-history"></i> All Time
                    </a>
                </div>
            </div>
        </div>

        <?php if (count($leaderboard) >= 3): ?>
        <div class="card podium-card mb-4">
            <div class="card-body">
                <h5 class="text-center mb-4">Top Performers</h5>
                <div class="row align-items-end text-center g-3">
                    <div class="col-md-4">
                        <div class="podium-tile">
                            <div class="text-secondary display-5"><i class="bi bi-trophy-fill"></i></div>
                            <div class="podium-rank">#2</div>
                            <div class="podium-name"><?php echo htmlspecialchars($leaderboard[1]['username']); ?></div>
                            <div class="podium-points"><?php echo number_format($leaderboard[1]['points']); ?> pts</div>
                            <div class="text-muted small mt-1">
                                <i class="bi bi-award text-warning"></i> <?php echo $leaderboard[1]['badge_count']; ?> badges
                                <i class="bi bi-fire text-danger ms-2"></i> <?php echo $leaderboard[1]['streak_days']; ?> days
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="podium-tile podium-gold">
                            <div class="display-4"><i class="bi bi-trophy-fill"></i></div>
                            <div class="podium-rank">#1</div>
                            <div class="podium-name"><?php echo htmlspecialchars($leaderboard[0]['username']); ?></div>
                            <div class="podium-points"><?php echo number_format($leaderboard[0]['points']); ?> pts</div>
                            <div class="small mt-1">
                                <i class="bi bi-award"></i> <?php echo $leaderboard[0]['badge_count']; ?> badges
                                <i class="bi bi-fire ms-2"></i> <?php echo $leaderboard[0]['streak_days']; ?> days
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="podium-tile">
                            <div style="color:#cd7f32;" class="display-5"><i class="bi bi-trophy-fill"></i></div>
                            <div class="podium-rank">#3</div>
                            <div class="podium-name"><?php echo htmlspecialchars($leaderboard[2]['username']); ?></div>
                            <div class="podium-points"><?php echo number_format($leaderboard[2]['points']); ?> pts</div>
                            <div class="text-muted small mt-1">
                                <i class="bi bi-award text-warning"></i> <?php echo $leaderboard[2]['badge_count']; ?> badges
                                <i class="bi bi-fire text-danger ms-2"></i> <?php echo $leaderboard[2]['streak_days']; ?> days
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card leaderboard-card">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0"><i class="bi bi-list-ol"></i> Complete Leaderboard</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>User</th>
                                <th>Level</th>
                                <th>Points</th>
                                <th>Badges</th>
                                <th>Streak</th>
                                <?php if ($period == 'daily'): ?>
                                <th>Today's Points</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($leaderboard as $user): 
                                $is_current_user = ($user['id'] == $user_id);
                                $row_class = $is_current_user ? 'table-primary' : '';

                                $medal = '';
                                if ($rank == 1) $medal = '<i class="bi bi-trophy-fill text-warning"></i>';
                                elseif ($rank == 2) $medal = '<i class="bi bi-trophy-fill" style="color:#c0c0c0;"></i>';
                                elseif ($rank == 3) $medal = '<i class="bi bi-trophy-fill" style="color:#cd7f32;"></i>';
                                $initials = getInitials($user['username']);
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td class="rank-chip"><?php echo $medal; ?> #<?php echo $rank; ?></td>
                                <td>
                                    <span class="avatar"><?php echo $initials; ?></span>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($is_current_user): ?>
                                        <span class="badge bg-primary you-badge">You</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $colors = [
                                            'Bronze' => '#CD7F32',
                                            'Silver' => '#C0C0C0',
                                            'Gold' => '#FFD700',
                                            'Platinum' => '#E5E4E2',
                                            'Diamond' => '#B9F2FF'
                                        ];
                                        $levelColor = $colors[$user['level']] ?? '#6c757d';
                                    ?>
                                    <span class="level-badge" style="background-color: <?php echo $levelColor; ?>;">
                                        <?php echo $user['level']; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo number_format($user['points']); ?></strong></td>
                                <td><i class="bi bi-award text-warning"></i> <?php echo $user['badge_count']; ?></td>
                                <td><i class="bi bi-fire text-danger"></i> <?php echo $user['streak_days']; ?> days</td>
                                <?php if ($period == 'daily'): ?>
                                <td><span class="badge bg-success">+<?php echo number_format($user['points_today']); ?></span></td>
                                <?php endif; ?>
                            </tr>
                            <?php 
                            $rank++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>

                <?php if (count($leaderboard) == 0): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3">No Rankings Yet</h4>
                    <p class="text-muted">Be the first to appear on the leaderboard!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
