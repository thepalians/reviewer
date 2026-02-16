<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/competition-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

// Handle join competition with CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_competition'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $competition_id = intval($_POST['competition_id']);
        $result = joinCompetition($pdo, $competition_id, $user_id);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'danger';
    } else {
        $message = 'Invalid security token. Please try again.';
        $message_type = 'danger';
    }
}

// Get active competitions
$active_competitions = getActiveCompetitions($pdo);

// Get user's competitions
$user_competitions = getUserCompetitionHistory($pdo, $user_id);

$current_page = 'competitions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competitions - ReviewFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .main-container {
            margin-left: 260px;
            padding: 40px 20px;
            min-height: 100vh;
        }
        
        .page-header {
            color: white;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        /* Competition Card Styles */
        .competition-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            backdrop-filter: blur(10px);
            border: none;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .competition-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #f39c12, #e67e22, #e74c3c);
        }
        
        .competition-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
        }
        
        .competition-card.hot::before {
            background: linear-gradient(90deg, #e74c3c, #c0392b);
            animation: hotGlow 1.5s ease-in-out infinite;
        }
        
        @keyframes hotGlow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .competition-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .competition-type-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-right: 10px;
        }
        
        .type-tasks {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .type-earnings {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
        }
        
        .type-quality {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white;
        }
        
        .type-speed {
            background: linear-gradient(135deg, #e67e22, #d35400);
            color: white;
        }
        
        .type-referral {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .hot-badge {
            display: inline-block;
            padding: 8px 16px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            position: relative;
            overflow: hidden;
        }
        
        .hot-badge::before {
            content: '🔥';
            position: absolute;
            left: -20px;
            animation: flameMove 2s linear infinite;
        }
        
        @keyframes flameMove {
            0% { left: -20px; }
            100% { left: 100%; }
        }
        
        .hot-badge::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: hotShine 2s linear infinite;
        }
        
        @keyframes hotShine {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .competition-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 10px;
        }
        
        .competition-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .info-item {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px 20px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: #333;
        }
        
        .info-value.prize {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 2s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0%, 100% { filter: brightness(1); }
            50% { filter: brightness(1.3); }
        }
        
        /* Countdown Timer */
        .countdown-timer {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .countdown-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .countdown-display {
            display: flex;
            justify-content: center;
            gap: 15px;
            font-family: 'Courier New', monospace;
        }
        
        .countdown-unit {
            text-align: center;
        }
        
        .countdown-number {
            font-size: 2.5rem;
            font-weight: 800;
            display: block;
            line-height: 1;
        }
        
        .countdown-text {
            font-size: 0.75rem;
            opacity: 0.8;
            text-transform: uppercase;
            margin-top: 5px;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn-compete {
            flex: 1;
            min-width: 200px;
            padding: 15px 30px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-compete:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(243, 156, 18, 0.4);
        }
        
        .btn-leaderboard {
            flex: 1;
            min-width: 200px;
            padding: 15px 30px;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-leaderboard:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
        }
        
        .participating-badge {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        /* Leaderboard Styles */
        .leaderboard-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .leaderboard-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .leaderboard-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        
        .leaderboard-table tr {
            background: #f8f9fa;
            transition: all 0.3s;
        }
        
        .leaderboard-table tr:hover {
            background: #e9ecef;
            transform: scale(1.02);
        }
        
        .leaderboard-table tr.current-user {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            font-weight: 700;
        }
        
        .leaderboard-table td {
            padding: 15px;
        }
        
        .leaderboard-table tr td:first-child {
            border-radius: 12px 0 0 12px;
        }
        
        .leaderboard-table tr td:last-child {
            border-radius: 0 12px 12px 0;
        }
        
        .rank-cell {
            font-weight: 800;
            font-size: 1.3rem;
            color: #333;
        }
        
        .medal {
            font-size: 2rem;
            animation: rotate 3s ease-in-out infinite;
        }
        
        @keyframes rotate {
            0%, 100% { transform: rotate(-10deg); }
            50% { transform: rotate(10deg); }
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            margin-right: 15px;
        }
        
        .score-cell {
            font-size: 1.3rem;
            font-weight: 800;
            color: #27ae60;
        }
        
        /* Live Countdown Timer */
        .countdown-timer {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 15px 0;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        
        .timer-icon {
            font-size: 1.8rem;
        }
        
        .timer-display {
            font-size: 1.5rem;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
        }
        
        .timer-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        /* Position Progress Bar */
        .position-progress {
            margin: 20px 0;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }
        
        .position-progress h6 {
            color: #333;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .position-bar {
            height: 30px;
            background: rgba(255,255,255,0.7);
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        
        .position-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            transition: width 1s ease;
        }
        
        /* Prize Breakdown */
        .prize-breakdown {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .prize-item {
            flex: 1;
            min-width: 150px;
            background: linear-gradient(135deg, #fff9e6, #ffffff);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #f39c12;
        }
        
        .prize-medal {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .prize-position {
            font-weight: 700;
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .prize-amount {
            font-size: 1.3rem;
            font-weight: 800;
            color: #f39c12;
        }
        
        /* Competition ending soon pulse */
        .competition-card.ending-soon {
            animation: endingSoonPulse 2s ease-in-out infinite;
        }
        
        @keyframes endingSoonPulse {
            0%, 100% { 
                box-shadow: 0 10px 40px rgba(0,0,0,0.15);
                transform: translateY(0);
            }
            50% { 
                box-shadow: 0 15px 60px rgba(231, 76, 60, 0.4);
                transform: translateY(-5px);
            }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            margin: 30px 0;
        }
        
        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 15px;
        }
        
        .empty-state-message {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .empty-state-cta {
            display: inline-block;
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 50px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .empty-state-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        /* History Table */
        .history-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .history-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 20px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 700;
            color: #666;
            border-bottom: 2px solid #dee2e6;
        }
        
        .history-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .history-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            display: inline-block;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-ended {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .prize-won {
            font-weight: 800;
            color: #27ae60;
            font-size: 1.1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                margin-left: 0;
                padding: 20px 10px;
            }
            .page-header h2 {
                font-size: 1.8rem;
            }
            .competition-card {
                padding: 20px;
            }
            .competition-title {
                font-size: 1.4rem;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .countdown-number {
                font-size: 2rem;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn-compete, .btn-leaderboard {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-container">
    <div class="page-header">
        <h2>🏆 Competitions & Leaderboards</h2>
        <p>Compete with other users and win exciting prizes!</p>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" style="border-radius: 16px;">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Active Competitions -->
    <h4 style="color: white; margin-bottom: 20px; font-size: 1.5rem; font-weight: 800;">🔥 Active Competitions</h4>
    
    <?php if (empty($active_competitions)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🏆</div>
            <div class="empty-state-title">No Active Competitions</div>
            <p class="empty-state-message">
                New competitions are coming soon! Stay tuned for exciting challenges and amazing prizes.
            </p>
            <a href="dashboard.php" class="empty-state-cta">
                <i class="bi bi-house-fill"></i> Go to Dashboard
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($active_competitions as $comp): ?>
        <?php
        // Check if user has joined
        $joined_stmt = $pdo->prepare("
            SELECT * FROM competition_participants 
            WHERE competition_id = ? AND user_id = ?
        ");
        $joined_stmt->execute([$comp['id'], $user_id]);
        $has_joined = $joined_stmt->fetch();
        
        // Check if ending soon (within 24 hours)
        $end_time = strtotime($comp['end_date']);
        $is_hot = ($end_time - time()) < 86400 && $end_time > time();
        $is_ending_very_soon = ($end_time - time()) < 21600; // 6 hours
        
        // Competition type icon
        $type_icons = [
            'tasks' => '📋',
            'earnings' => '💰',
            'quality' => '⭐',
            'speed' => '⚡',
            'referral' => '🤝'
        ];
        $type_icon = $type_icons[$comp['competition_type']] ?? '🏆';
        
        // Get participant count for position calculation
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM competition_participants WHERE competition_id = ?");
        $count_stmt->execute([$comp['id']]);
        $total_participants = (int)$count_stmt->fetchColumn();
        ?>
        
        <div class="competition-card <?php echo $is_hot ? 'hot' : ''; ?> <?php echo $is_ending_very_soon ? 'ending-soon' : ''; ?>">
            <div class="competition-header">
                <div>
                    <span class="competition-type-badge type-<?php echo $comp['competition_type']; ?>">
                        <?php echo $type_icon; ?> <?php echo ucfirst(str_replace('_', ' ', $comp['competition_type'])); ?>
                    </span>
                    <?php if ($is_hot): ?>
                        <span class="hot-badge">🔥 ENDING SOON</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <h3 class="competition-title"><?php echo htmlspecialchars($comp['name']); ?></h3>
            <p class="competition-description"><?php echo htmlspecialchars($comp['description']); ?></p>
            
            <!-- Prize Breakdown -->
            <?php
            $first_prize = $comp['prize_pool'] * 0.5; // 50%
            $second_prize = $comp['prize_pool'] * 0.3; // 30%
            $third_prize = $comp['prize_pool'] * 0.2; // 20%
            ?>
            <div class="prize-breakdown">
                <div class="prize-item">
                    <span class="prize-medal">🥇</span>
                    <div class="prize-position">1st Place</div>
                    <div class="prize-amount">₹<?php echo number_format($first_prize, 0); ?></div>
                </div>
                <div class="prize-item">
                    <span class="prize-medal">🥈</span>
                    <div class="prize-position">2nd Place</div>
                    <div class="prize-amount">₹<?php echo number_format($second_prize, 0); ?></div>
                </div>
                <div class="prize-item">
                    <span class="prize-medal">🥉</span>
                    <div class="prize-position">3rd Place</div>
                    <div class="prize-amount">₹<?php echo number_format($third_prize, 0); ?></div>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">📅 Duration</div>
                    <div class="info-value">
                        <?php echo date('M d', strtotime($comp['start_date'])); ?> - 
                        <?php echo date('M d', strtotime($comp['end_date'])); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">💰 Prize Pool</div>
                    <div class="info-value prize">₹<?php echo number_format($comp['prize_pool'], 0); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">👥 Participants</div>
                    <div class="info-value"><?php echo $total_participants; ?></div>
                </div>
                <?php if ($has_joined && $has_joined['rank']): ?>
                <div class="info-item">
                    <div class="info-label">🏅 Your Rank</div>
                    <div class="info-value">#<?php echo $has_joined['rank']; ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Countdown Timer -->
            <?php if ($comp['status'] === 'active'): ?>
            <div class="countdown-timer" data-end-time="<?php echo $end_time; ?>">
                <div class="timer-icon">⏰</div>
                <div style="flex: 1;">
                    <div class="timer-label">Time Remaining</div>
                    <div class="timer-display">
                        <span class="days">0</span>d 
                        <span class="hours">00</span>h 
                        <span class="minutes">00</span>m 
                        <span class="seconds">00</span>s
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Your Position Progress Bar -->
            <?php if ($has_joined && $has_joined['rank'] && $total_participants > 0): ?>
            <div class="position-progress">
                <h6>Your Position in Competition</h6>
                <?php 
                $position_percentage = (($total_participants - $has_joined['rank'] + 1) / $total_participants) * 100;
                ?>
                <div class="position-bar">
                    <div class="position-fill" style="width: <?php echo $position_percentage; ?>%">
                        #<?php echo $has_joined['rank']; ?> out of <?php echo $total_participants; ?>
                    </div>
                </div>
                <p style="text-align: center; margin-top: 10px; color: #666; font-size: 0.9rem;">
                    <?php
                    if ($has_joined['rank'] <= 3) {
                        echo "🏆 You're in the prize zone! Keep it up!";
                    } else if ($has_joined['rank'] <= 10) {
                        echo "🎯 You're in the top 10! Push harder!";
                    } else {
                        echo "💪 Keep competing to climb the ranks!";
                    }
                    ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if ($has_joined): ?>
                <div class="participating-badge">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>You're Participating!</span>
                    <?php if ($has_joined['rank']): ?>
                        <span>Current Rank: #<?php echo $has_joined['rank']; ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <?php if (!$has_joined): ?>
                    <form method="POST" style="flex: 1;">
                        <?php echo Security::csrfField(); ?>
                        <input type="hidden" name="competition_id" value="<?php echo $comp['id']; ?>">
                        <button type="submit" name="join_competition" class="btn-compete">
                            <i class="bi bi-plus-circle"></i> JOIN COMPETITION
                        </button>
                    </form>
                <?php endif; ?>
                <a href="?view=<?php echo $comp['id']; ?>" class="btn-leaderboard">
                    <i class="bi bi-list-ol"></i> VIEW LEADERBOARD
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- User's Competition History -->
    <?php if (!empty($user_competitions)): ?>
    <div class="history-card">
        <h4 class="history-title">📊 Your Competition History</h4>
        <div class="table-responsive">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Competition</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Your Rank</th>
                        <th>Score</th>
                        <th>Prize Won</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_competitions as $comp): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($comp['name']); ?></strong></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $comp['competition_type'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $comp['status'] == 'ended' ? 'ended' : 'active'; ?>">
                                <?php echo ucfirst($comp['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($comp['rank']): ?>
                                <strong style="font-size: 1.2rem;">#<?php echo $comp['rank']; ?></strong>
                                <?php if ($comp['rank'] <= 3): ?>
                                    <?php echo [1 => '🥇', 2 => '🥈', 3 => '🥉'][$comp['rank']]; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($comp['score'], 2); ?></td>
                        <td>
                            <?php if ($comp['prize_won'] > 0): ?>
                                <span class="prize-won">₹<?php echo number_format($comp['prize_won'], 2); ?></span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- View specific leaderboard -->
    <?php if (isset($_GET['view'])): ?>
    <?php
    $comp_id = intval($_GET['view']);
    $leaderboard = getCompetitionLeaderboard($pdo, $comp_id, 100);
    
    if (!empty($leaderboard)):
    ?>
    <div class="leaderboard-card">
        <h3 class="leaderboard-title">🏆 Competition Leaderboard</h3>
        <div class="table-responsive">
            <table class="leaderboard-table">
                <tbody>
                    <?php foreach ($leaderboard as $entry): ?>
                    <tr class="<?php echo $entry['user_id'] == $user_id ? 'current-user' : ''; ?>">
                        <td class="rank-cell">
                            <?php if ($entry['rank'] <= 3): ?>
                                <span class="medal"><?php echo [1 => '🥇', 2 => '🥈', 3 => '🥉'][$entry['rank']]; ?></span>
                            <?php else: ?>
                                #<?php echo $entry['rank']; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center;">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($entry['name'], 0, 1)); ?>
                                </div>
                                <strong><?php echo htmlspecialchars($entry['name']); ?></strong>
                                <?php if ($entry['user_id'] == $user_id): ?>
                                    <span class="badge bg-success ms-2">You</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="score-cell"><?php echo number_format($entry['metric_value'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Countdown Timer Function
function updateCountdown(timerElement) {
    const endTime = parseInt(timerElement.dataset.endTime) * 1000;
    const now = Date.now();
    const distance = endTime - now;
    
    if (distance < 0) {
        timerElement.innerHTML = '<div class="timer-icon">🏁</div><div style="flex: 1;"><div class="timer-display">Competition Ended!</div></div>';
        return;
    }
    
    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
    
    const daysEl = timerElement.querySelector('.days');
    const hoursEl = timerElement.querySelector('.hours');
    const minutesEl = timerElement.querySelector('.minutes');
    const secondsEl = timerElement.querySelector('.seconds');
    
    if (daysEl) daysEl.textContent = days;
    if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
    if (minutesEl) minutesEl.textContent = String(minutes).padStart(2, '0');
    if (secondsEl) secondsEl.textContent = String(seconds).padStart(2, '0');
    
    setTimeout(() => updateCountdown(timerElement), 1000);
}

// Initialize all countdown timers
document.addEventListener('DOMContentLoaded', function() {
    const timers = document.querySelectorAll('.countdown-timer[data-end-time]');
    timers.forEach(timer => updateCountdown(timer));
});
</script>
</body>
</html>
