<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/spin-functions.php';
require_once __DIR__ . '/../includes/gamification-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

// Process spin request
$spin_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spin_wheel'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $spin_result = processSpinResult($pdo, $user_id);
    } else {
        $spin_result = ['success' => false, 'message' => 'Invalid security token'];
    }
}

// Check if user can spin
$can_spin = canUserSpin($pdo, $user_id);
$time_until_next = getTimeUntilNextSpin();
$spin_history = getSpinHistory($pdo, $user_id, 10);
$spin_stats = getSpinStats($pdo, $user_id);

// Get user streak for bonus badge
$user_points = getUserPoints($pdo, $user_id);
$streak_days = $user_points['streak_days'] ?? 0;

$current_page = 'spin-wheel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Spin Wheel - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 999;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 40px 20px;
            min-height: 100vh;
        }
        
        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        /* Spin Wheel Card */
        .spin-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }
        
        .wheel-container {
            position: relative;
            width: 400px;
            height: 400px;
            margin: 0 auto 30px;
        }
        
        .wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 15px solid #f39c12;
            background: conic-gradient(
                from 0deg,
                #e74c3c 0deg 45deg,
                #f39c12 45deg 90deg,
                #27ae60 90deg 135deg,
                #3498db 135deg 180deg,
                #9b59b6 180deg 225deg,
                #e67e22 225deg 270deg,
                #1abc9c 270deg 315deg,
                #34495e 315deg 360deg
            );
            position: relative;
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            box-shadow: 0 10px 40px rgba(0,0,0,0.3), inset 0 0 40px rgba(255,255,255,0.2);
        }
        
        .wheel.spinning {
            border-color: #f39c12;
            animation: wheelGlow 0.3s ease-in-out infinite;
        }
        
        @keyframes wheelGlow {
            0%, 100% { 
                box-shadow: 0 10px 40px rgba(0,0,0,0.3), 
                            inset 0 0 40px rgba(255,255,255,0.2),
                            0 0 30px rgba(243, 156, 18, 0.8); 
            }
            50% { 
                box-shadow: 0 15px 50px rgba(0,0,0,0.4), 
                            inset 0 0 50px rgba(255,255,255,0.3),
                            0 0 60px rgba(243, 156, 18, 1); 
            }
        }
        
        /* Segment dividers */
        .wheel::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            transform: translate(-50%, -50%);
            background: 
                linear-gradient(0deg, transparent 49.5%, rgba(255,255,255,0.3) 49.5%, rgba(255,255,255,0.3) 50.5%, transparent 50.5%),
                linear-gradient(45deg, transparent 49.5%, rgba(255,255,255,0.3) 49.5%, rgba(255,255,255,0.3) 50.5%, transparent 50.5%),
                linear-gradient(90deg, transparent 49.5%, rgba(255,255,255,0.3) 49.5%, rgba(255,255,255,0.3) 50.5%, transparent 50.5%),
                linear-gradient(135deg, transparent 49.5%, rgba(255,255,255,0.3) 49.5%, rgba(255,255,255,0.3) 50.5%, transparent 50.5%);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .wheel-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            font-weight: 800;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            z-index: 10;
            border: 5px solid white;
        }
        
        .wheel-pointer {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 20px solid transparent;
            border-right: 20px solid transparent;
            border-top: 40px solid #e74c3c;
            z-index: 20;
            filter: drop-shadow(0 3px 10px rgba(0,0,0,0.3));
        }
        
        .wheel-segments {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
        }
        
        .segment-label {
            position: absolute;
            color: white;
            font-weight: 800;
            font-size: 1rem;
            text-shadow: 0 2px 8px rgba(0,0,0,0.8);
            transform-origin: 200px 200px;
            left: 50%;
            top: 50%;
            width: 80px;
            text-align: center;
            pointer-events: none;
            z-index: 5;
        }
        
        /* Position labels for 8 segments */
        .label-0 { transform: translate(-50%, -50%) rotate(22.5deg) translateY(-140px) rotate(-22.5deg); }
        .label-1 { transform: translate(-50%, -50%) rotate(67.5deg) translateY(-140px) rotate(-67.5deg); }
        .label-2 { transform: translate(-50%, -50%) rotate(112.5deg) translateY(-140px) rotate(-112.5deg); }
        .label-3 { transform: translate(-50%, -50%) rotate(157.5deg) translateY(-140px) rotate(-157.5deg); }
        .label-4 { transform: translate(-50%, -50%) rotate(202.5deg) translateY(-140px) rotate(-202.5deg); }
        .label-5 { transform: translate(-50%, -50%) rotate(247.5deg) translateY(-140px) rotate(-247.5deg); }
        .label-6 { transform: translate(-50%, -50%) rotate(292.5deg) translateY(-140px) rotate(-292.5deg); }
        .label-7 { transform: translate(-50%, -50%) rotate(337.5deg) translateY(-140px) rotate(-337.5deg); }
        
        .spin-button {
            display: block;
            width: 300px;
            margin: 0 auto;
            padding: 20px 40px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.3rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(243, 156, 18, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .spin-button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(243, 156, 18, 0.5);
        }
        
        .spin-button:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        .countdown-timer {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border-radius: 16px;
            color: white;
        }
        
        .countdown-timer h3 {
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .timer-display {
            font-size: 2.5rem;
            font-weight: 800;
            font-family: 'Courier New', monospace;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #888;
            font-size: 0.9rem;
        }
        
        /* History Table */
        .history-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .history-card h3 {
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
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
        
        .reward-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-block;
        }
        
        .reward-money {
            background: #d4edda;
            color: #155724;
        }
        
        .reward-points {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .reward-nothing {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Confetti Animation */
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            animation: confetti-fall 3s linear forwards;
            z-index: 9999;
            pointer-events: none;
        }
        
        .confetti.circle {
            border-radius: 50%;
        }
        
        .confetti.triangle {
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-bottom: 10px solid;
        }
        
        .confetti.star {
            clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
        }
        
        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
        }
        
        /* Result Modal */
        .result-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeIn 0.3s;
        }
        
        .result-overlay.show {
            display: flex;
        }
        
        .result-modal {
            background: white;
            border-radius: 24px;
            padding: 50px;
            text-align: center;
            max-width: 500px;
            animation: scaleIn 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes scaleIn {
            from { transform: scale(0.5); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        .result-icon {
            font-size: 5rem;
            margin-bottom: 20px;
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        .result-message {
            font-size: 1.8rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 15px;
        }
        
        .result-prize {
            font-size: 3rem;
            font-weight: 800;
            color: #f39c12;
            margin-bottom: 30px;
        }
        
        .close-modal-btn {
            padding: 15px 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .close-modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        /* Streak Bonus Badge */
        .streak-bonus-badge {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 5px 20px rgba(243, 156, 18, 0.4);
            margin: 15px 0;
            animation: badgePulse 2s ease-in-out infinite;
        }
        
        @keyframes badgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        /* Prize Pool Section */
        .prize-pool {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
        }
        
        .prize-pool h3 {
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
        }
        
        .prize-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .prize-item {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 2px solid #dee2e6;
            transition: all 0.3s;
        }
        
        .prize-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .prize-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .prize-label {
            font-weight: 700;
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 5px;
        }
        
        .prize-chance {
            font-size: 0.85rem;
            color: #888;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                left: -260px;
            }
            .main-content {
                margin-left: 0;
                padding: 20px 10px;
            }
            .wheel-container {
                width: 300px;
                height: 300px;
            }
            .spin-card {
                padding: 20px;
            }
            .page-header h1 {
                font-size: 1.8rem;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main-content">
    <div class="container-custom">
        <div class="page-header">
            <h1>🎰 Daily Spin Wheel</h1>
            <p>Spin once per day and win exciting rewards!</p>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🎰</div>
                <div class="stat-value"><?php echo $spin_stats['total_spins']; ?></div>
                <div class="stat-label">Total Spins</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">₹<?php echo number_format($spin_stats['total_money_won'], 0); ?></div>
                <div class="stat-label">Money Won</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-value"><?php echo $spin_stats['total_points_won']; ?></div>
                <div class="stat-label">Points Won</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-value"><?php echo $spin_stats['total_spins'] - $spin_stats['nothing_count']; ?></div>
                <div class="stat-label">Successful Spins</div>
            </div>
        </div>
        
        <!-- Spin Wheel Card -->
        <div class="spin-card">
            <?php if ($streak_days >= 7): ?>
                <div class="text-center">
                    <div class="streak-bonus-badge">
                        🔥 Streak Bonus: Extra spin coming soon!
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($can_spin): ?>
                <div class="wheel-container">
                    <div class="wheel-pointer"></div>
                    <div class="wheel" id="spinWheel">
                        <!-- Prize labels on segments -->
                        <div class="segment-label label-0">₹5</div>
                        <div class="segment-label label-1">10<br>Pts</div>
                        <div class="segment-label label-2">₹10</div>
                        <div class="segment-label label-3">25<br>Pts</div>
                        <div class="segment-label label-4">₹25</div>
                        <div class="segment-label label-5">₹50</div>
                        <div class="segment-label label-6">₹100</div>
                        <div class="segment-label label-7">Better<br>Luck</div>
                        <div class="wheel-center">SPIN</div>
                    </div>
                </div>
                
                <form method="POST" id="spinForm">
                    <?php echo Security::csrfField(); ?>
                    <button type="submit" name="spin_wheel" class="spin-button" id="spinButton">
                        🎰 SPIN THE WHEEL
                    </button>
                </form>
            <?php else: ?>
                <div class="countdown-timer">
                    <h3>⏰ Come Back Tomorrow!</h3>
                    <p>Next spin available in:</p>
                    <div class="timer-display" id="countdownTimer">
                        <span id="hours">00</span>:<span id="minutes">00</span>:<span id="seconds">00</span>
                    </div>
                </div>
                
                <div class="wheel-container">
                    <div class="wheel-pointer"></div>
                    <div class="wheel" style="opacity: 0.5;">
                        <!-- Prize labels on segments -->
                        <div class="segment-label label-0">₹5</div>
                        <div class="segment-label label-1">10<br>Pts</div>
                        <div class="segment-label label-2">₹10</div>
                        <div class="segment-label label-3">25<br>Pts</div>
                        <div class="segment-label label-4">₹25</div>
                        <div class="segment-label label-5">₹50</div>
                        <div class="segment-label label-6">₹100</div>
                        <div class="segment-label label-7">Better<br>Luck</div>
                        <div class="wheel-center">SPIN</div>
                    </div>
                </div>
                
                <button class="spin-button" disabled>
                    🔒 ALREADY SPUN TODAY
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Prize Pool Section -->
        <div class="prize-pool">
            <h3><i class="bi bi-gift-fill"></i> Available Prizes</h3>
            <div class="prize-grid">
                <div class="prize-item">
                    <span class="prize-icon">💰</span>
                    <div class="prize-label">₹5</div>
                    <div class="prize-chance">15% chance</div>
                </div>
                <div class="prize-item">
                    <span class="prize-icon">⭐</span>
                    <div class="prize-label">10 Points</div>
                    <div class="prize-chance">20% chance</div>
                </div>
                <div class="prize-item">
                    <span class="prize-icon">💰</span>
                    <div class="prize-label">₹10</div>
                    <div class="prize-chance">12% chance</div>
                </div>
                <div class="prize-item">
                    <span class="prize-icon">⭐</span>
                    <div class="prize-label">25 Points</div>
                    <div class="prize-chance">15% chance</div>
                </div>
                <div class="prize-item">
                    <span class="prize-icon">💰</span>
                    <div class="prize-label">₹25</div>
                    <div class="prize-chance">8% chance</div>
                </div>
                <div class="prize-item">
                    <span class="prize-icon">💰</span>
                    <div class="prize-label">₹50</div>
                    <div class="prize-chance">5% chance</div>
                </div>
                <div class="prize-item">
                    <span class="prize-icon">💸</span>
                    <div class="prize-label">₹100</div>
                    <div class="prize-chance">2% chance</div>
                </div>
                <div class="prize-item">
                    <span class="prize-icon">😊</span>
                    <div class="prize-label">Better Luck</div>
                    <div class="prize-chance">23% chance</div>
                </div>
            </div>
        </div>
        
        <!-- Spin History -->
        <?php if (!empty($spin_history)): ?>
        <div class="history-card">
            <h3><i class="bi bi-clock-history"></i> Recent Spin History</h3>
            <div class="table-responsive">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reward Type</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($spin_history as $spin): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($spin['spin_date'])); ?></td>
                            <td>
                                <?php
                                $badge_class = 'reward-nothing';
                                $label = 'Better Luck';
                                if ($spin['reward_type'] === 'money') {
                                    $badge_class = 'reward-money';
                                    $label = '💰 Money';
                                } elseif ($spin['reward_type'] === 'points') {
                                    $badge_class = 'reward-points';
                                    $label = '⭐ Points';
                                }
                                ?>
                                <span class="reward-badge <?php echo $badge_class; ?>"><?php echo $label; ?></span>
                            </td>
                            <td>
                                <strong>
                                    <?php 
                                    if ($spin['reward_type'] === 'money') {
                                        echo '₹' . number_format($spin['reward_amount'], 0);
                                    } elseif ($spin['reward_type'] === 'points') {
                                        echo $spin['reward_amount'] . ' Points';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Result Modal -->
<div class="result-overlay" id="resultOverlay">
    <div class="result-modal">
        <div class="result-icon" id="resultIcon">🎉</div>
        <div class="result-message" id="resultMessage">Congratulations!</div>
        <div class="result-prize" id="resultPrize">You Won!</div>
        <button class="close-modal-btn" onclick="closeModal()">Awesome!</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Countdown timer
<?php if (!$can_spin): ?>
let totalSeconds = <?php echo $time_until_next['total_seconds']; ?>;

function updateCountdown() {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    
    document.getElementById('hours').textContent = String(hours).padStart(2, '0');
    document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
    document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
    
    if (totalSeconds > 0) {
        totalSeconds--;
        setTimeout(updateCountdown, 1000);
    } else {
        location.reload();
    }
}

updateCountdown();
<?php endif; ?>

// Handle spin
<?php if ($can_spin && $spin_result): ?>
document.addEventListener('DOMContentLoaded', function() {
    const spinResult = <?php echo json_encode($spin_result); ?>;
    
    if (spinResult.success) {
        // Calculate rotation angle based on prize index
        const prizeIndex = spinResult.prize_index || 0;
        const SPIN_ROTATIONS = 5; // Number of full rotations before stopping
        const RANDOM_VARIATION_RANGE = 10; // Degrees of random variation for natural feel
        const segmentAngle = 360 / 8; // 8 segments = 45 degrees each
        const targetAngle = (prizeIndex * segmentAngle) + (segmentAngle / 2); // Center of segment
        const randomVariation = Math.random() * RANDOM_VARIATION_RANGE - (RANDOM_VARIATION_RANGE / 2);
        const finalRotation = (360 * SPIN_ROTATIONS) + (360 - targetAngle) + randomVariation;
        
        // Animate wheel with calculated rotation
        const wheel = document.getElementById('spinWheel');
        wheel.style.transform = `rotate(${finalRotation}deg)`;
        wheel.classList.add('spinning');
        
        // Show result after animation
        setTimeout(() => {
            showResult(spinResult);
            createConfetti();
        }, 4000);
    } else {
        alert(spinResult.message);
    }
});
<?php endif; ?>

function showResult(result) {
    const overlay = document.getElementById('resultOverlay');
    const icon = document.getElementById('resultIcon');
    const message = document.getElementById('resultMessage');
    const prize = document.getElementById('resultPrize');
    
    if (result.prize.type === 'money') {
        icon.textContent = '💰';
        message.textContent = 'Congratulations!';
        prize.textContent = '₹' + result.prize.amount;
    } else if (result.prize.type === 'points') {
        icon.textContent = '⭐';
        message.textContent = 'You Won!';
        prize.textContent = result.prize.amount + ' Points';
    } else {
        icon.textContent = '😊';
        message.textContent = 'Better Luck Next Time!';
        prize.textContent = 'Try Again Tomorrow';
    }
    
    overlay.classList.add('show');
}

function closeModal() {
    location.reload();
}

function createConfetti() {
    const colors = ['#f39c12', '#e74c3c', '#27ae60', '#3498db', '#9b59b6', '#e67e22', '#1abc9c'];
    const shapes = ['square', 'circle', 'triangle', 'star'];
    const confettiCount = 60; // Optimized for performance
    
    for (let i = 0; i < confettiCount; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            
            // Random shape
            const shape = shapes[Math.floor(Math.random() * shapes.length)];
            confetti.classList.add(shape);
            
            // Random color
            const color = colors[Math.floor(Math.random() * colors.length)];
            if (shape === 'triangle') {
                confetti.style.borderBottomColor = color;
            } else {
                confetti.style.background = color;
            }
            
            // Random position and animation
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.animationDelay = Math.random() * 0.5 + 's';
            confetti.style.animationDuration = (2 + Math.random() * 2) + 's';
            
            document.body.appendChild(confetti);
            
            setTimeout(() => confetti.remove(), 4000);
        }, i * 30);
    }
}

// Prevent multiple submissions
const spinForm = document.getElementById('spinForm');
if (spinForm) {
    spinForm.addEventListener('submit', function(e) {
        const button = document.getElementById('spinButton');
        button.disabled = true;
        button.textContent = '🎰 SPINNING...';
    });
}
</script>
</body>
</html>
