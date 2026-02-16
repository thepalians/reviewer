<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/referral-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get or generate referral code
$referral_code = getReferralCode($user_id);
$referral_bonus = (float)getSetting('referral_bonus', 50);

// Get referral stats
try {
    // Total referrals
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND level = 1");
    $stmt->execute([$user_id]);
    $total_referrals = (int)$stmt->fetchColumn();
    
    // Completed referrals (bonus received)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND level = 1 AND status = 'active'");
    $stmt->execute([$user_id]);
    $completed_referrals = (int)$stmt->fetchColumn();
    
    // Pending referrals
    $pending_referrals = $total_referrals - $completed_referrals;
    
    // Total earnings from referrals
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM referral_earnings WHERE user_id = ? AND status = 'credited'");
    $stmt->execute([$user_id]);
    $total_earnings = (float)$stmt->fetchColumn();
    
    // Get level-wise stats
    $level_stats = getLevelWiseReferralStats($pdo, $user_id);
    
    // Get network size
    $network_size = getNetworkSize($pdo, $user_id);
    
    // Get referral tree
    $referral_tree = getReferralTree($pdo, $user_id, 3);
    
    // Get milestone rewards
    $milestone_rewards = getReferralMilestoneRewards($pdo, $user_id);
    
    // Check and award milestones
    checkReferralMilestones($pdo, $user_id);
    
    // Get referred users list (Level 1 only for display)
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as referred_name, u.email as referred_email, u.created_at as joined_at
        FROM referrals r
        JOIN users u ON r.referred_id = u.id
        WHERE r.referrer_id = ? AND r.level = 1
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $referred_users = $stmt->fetchAll();
    
    // Get referral transactions
    $stmt = $pdo->prepare("
        SELECT * FROM wallet_transactions 
        WHERE user_id = ? AND (type = 'referral' OR description LIKE '%referral%') 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $referral_transactions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Referral Error: " . $e->getMessage());
    $total_referrals = 0;
    $completed_referrals = 0;
    $pending_referrals = 0;
    $total_earnings = 0;
    $referred_users = [];
    $referral_transactions = [];
    $level_stats = [];
    $network_size = 0;
    $referral_tree = [];
    $milestone_rewards = [];
}

// Referral link
$referral_link = APP_URL . "/index.php?ref=" . $referral_code;

// Share messages
$whatsapp_message = "🎁 Join " . APP_NAME . " and start earning money!\n\n✅ Complete simple tasks\n✅ Earn rewards\n✅ Instant withdrawals\n\n👉 Use my referral code: " . $referral_code . "\n\n🔗 " . $referral_link;
$telegram_message = "Join " . APP_NAME . "! Use code: " . $referral_code . " " . $referral_link;
$twitter_message = "I'm earning money on " . APP_NAME . "! Join using my code: " . $referral_code . " " . $referral_link;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refer & Earn - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px}
        
        .container{max-width:900px;margin:0 auto}
        
        .back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fff;color:#333;text-decoration:none;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:14px;transition:transform 0.2s;box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        .back-btn:hover{transform:translateY(-2px)}
        
        /* Hero Card */
        .hero-card{background:linear-gradient(135deg,#f39c12 0%,#e67e22 100%);border-radius:20px;padding:35px;color:#fff;margin-bottom:25px;text-align:center;position:relative;overflow:hidden;box-shadow:0 10px 40px rgba(243,156,18,0.4)}
        .hero-card::before{content:'';position:absolute;top:-50%;right:-50%;width:100%;height:100%;background:radial-gradient(circle,rgba(255,255,255,0.2) 0%,transparent 70%);pointer-events:none}
        .hero-card::after{content:'🎁';position:absolute;top:20px;right:30px;font-size:60px;opacity:0.2}
        .hero-title{font-size:28px;font-weight:700;margin-bottom:10px}
        .hero-subtitle{font-size:16px;opacity:0.9;margin-bottom:25px}
        .hero-amount{font-size:52px;font-weight:800;margin-bottom:5px;text-shadow:0 3px 15px rgba(0,0,0,0.2)}
        .hero-label{font-size:14px;opacity:0.9}
        
        /* Referral Code Box */
        .code-box{background:#fff;border-radius:15px;padding:25px;margin-bottom:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .code-title{font-size:16px;font-weight:600;color:#333;margin-bottom:15px;text-align:center}
        .code-display{background:linear-gradient(135deg,#f8f9fa,#e9ecef);border:2px dashed #f39c12;border-radius:12px;padding:20px;text-align:center;margin-bottom:20px}
        .code-value{font-size:32px;font-weight:800;letter-spacing:4px;color:#333;margin-bottom:10px}
        .code-copy{display:inline-flex;align-items:center;gap:8px;padding:10px 25px;background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;border:none;border-radius:25px;font-weight:600;cursor:pointer;font-size:14px;transition:all 0.2s}
        .code-copy:hover{transform:scale(1.05);box-shadow:0 5px 20px rgba(243,156,18,0.4)}
        
        .link-box{background:#f8f9fa;border-radius:10px;padding:12px 15px;display:flex;align-items:center;gap:10px;margin-bottom:20px}
        .link-box input{flex:1;background:none;border:none;font-size:13px;color:#666;outline:none}
        .link-copy{padding:8px 15px;background:#667eea;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer}
        
        .share-section{text-align:center}
        .share-title{font-size:14px;color:#666;margin-bottom:15px}
        .share-buttons{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
        .share-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 20px;border-radius:25px;text-decoration:none;color:#fff;font-weight:600;font-size:13px;transition:all 0.2s}
        .share-btn:hover{transform:translateY(-3px);box-shadow:0 5px 20px rgba(0,0,0,0.2)}
        .share-btn.whatsapp{background:#25d366}
        .share-btn.telegram{background:#0088cc}
        .share-btn.twitter{background:#1da1f2}
        .share-btn.facebook{background:#1877f2}
        .share-btn.copy{background:#333}
        
        /* Sticky Share Button (Mobile) */
        .sticky-share-btn{position:fixed;bottom:20px;right:20px;width:60px;height:60px;background:linear-gradient(135deg,#f39c12,#e67e22);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:24px;box-shadow:0 5px 25px rgba(243,156,18,0.5);cursor:pointer;z-index:1000;animation:sharePulse 2s ease-in-out infinite;display:none}
        
        @keyframes sharePulse{0%,100%{transform:scale(1);box-shadow:0 5px 25px rgba(243,156,18,0.5)}50%{transform:scale(1.1);box-shadow:0 8px 35px rgba(243,156,18,0.7)}}
        
        @media (max-width:768px){.sticky-share-btn{display:flex}}
        
        /* Stats Grid */
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px}
        .stat-card{background:#fff;border-radius:12px;padding:20px;text-align:center;box-shadow:0 3px 15px rgba(0,0,0,0.08)}
        .stat-icon{font-size:28px;margin-bottom:8px}
        .stat-value{font-size:26px;font-weight:700;color:#333}
        .stat-label{font-size:12px;color:#888;margin-top:3px}
        
        /* How It Works */
        .how-it-works{background:#fff;border-radius:15px;padding:25px;margin-bottom:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .section-title{font-size:18px;font-weight:600;color:#333;margin-bottom:20px;display:flex;align-items:center;gap:10px}
        .steps{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
        .step{text-align:center;padding:20px}
        .step-number{width:50px;height:50px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;margin:0 auto 15px}
        .step-title{font-weight:600;color:#333;margin-bottom:8px}
        .step-desc{font-size:13px;color:#666;line-height:1.5}
        
        /* Referred Users */
        .card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1);margin-bottom:25px}
        .card-title{font-size:18px;font-weight:600;color:#333;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
        
        .user-list{max-height:400px;overflow-y:auto}
        .user-item{display:flex;align-items:center;padding:15px 0;border-bottom:1px solid #f5f5f5}
        .user-item:last-child{border-bottom:none}
        .user-avatar{width:45px;height:45px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:16px;margin-right:15px}
        .user-info{flex:1}
        .user-name{font-weight:600;color:#333;font-size:14px}
        .user-date{font-size:12px;color:#888;margin-top:2px}
        .user-status{padding:5px 12px;border-radius:15px;font-size:11px;font-weight:600}
        .user-status.completed{background:#d4edda;color:#155724}
        .user-status.pending{background:#fff3cd;color:#856404}
        .user-bonus{font-weight:600;color:#27ae60;font-size:14px;margin-left:10px}
        
        /* Transactions */
        .transaction-item{display:flex;align-items:center;padding:12px 0;border-bottom:1px solid #f5f5f5}
        .transaction-item:last-child{border-bottom:none}
        .txn-icon{width:40px;height:40px;background:#e8f5e9;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-right:12px}
        .txn-info{flex:1}
        .txn-desc{font-weight:600;color:#333;font-size:14px}
        .txn-date{font-size:12px;color:#888;margin-top:2px}
        .txn-amount{font-weight:700;color:#27ae60;font-size:15px}
        
        /* Empty State */
        .empty-state{text-align:center;padding:40px 20px;color:#999}
        .empty-state .icon{font-size:50px;margin-bottom:15px;opacity:0.5}
        .empty-state h4{color:#666;margin-bottom:8px}
        .empty-state p{font-size:13px}
        
        /* Level-wise Earnings Cards */
        .level-earnings-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:25px}
        .level-card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1);text-align:center;border-top:4px solid;transition:transform 0.3s}
        .level-card:hover{transform:translateY(-5px)}
        .level-card.level-1{border-color:#27ae60}
        .level-card.level-2{border-color:#3498db}
        .level-card.level-3{border-color:#9b59b6}
        .level-badge{display:inline-block;padding:5px 15px;border-radius:20px;font-size:12px;font-weight:700;color:#fff;margin-bottom:15px}
        .level-badge.level-1{background:linear-gradient(135deg,#27ae60,#229954)}
        .level-badge.level-2{background:linear-gradient(135deg,#3498db,#2980b9)}
        .level-badge.level-3{background:linear-gradient(135deg,#9b59b6,#8e44ad)}
        .level-count{font-size:32px;font-weight:800;color:#333;margin:10px 0}
        .level-commission{font-size:20px;font-weight:700;color:#f39c12;margin-bottom:5px}
        .level-earnings{font-size:24px;font-weight:800;color:#27ae60}
        .level-label{font-size:13px;color:#888;margin-top:5px}
        
        /* Milestone Badges */
        .milestone-badges{display:flex;gap:15px;flex-wrap:wrap;margin:20px 0}
        .milestone-badge{background:#fff;border-radius:12px;padding:20px;text-align:center;flex:1;min-width:150px;box-shadow:0 4px 15px rgba(0,0,0,0.08);transition:all 0.3s}
        .milestone-badge:hover{transform:scale(1.05)}
        .milestone-badge.achieved{border:2px solid #27ae60;box-shadow:0 6px 20px rgba(39,174,96,0.3)}
        .milestone-badge:not(.achieved){opacity:0.5;filter:grayscale(1)}
        .milestone-icon{font-size:40px;margin-bottom:10px}
        .milestone-badge.achieved .milestone-icon{animation:bounce 2s ease-in-out infinite}
        .milestone-count{font-size:24px;font-weight:800;color:#333;margin-bottom:5px}
        .milestone-reward{font-size:18px;font-weight:700;color:#27ae60;margin-bottom:5px}
        .milestone-status{font-size:12px;color:#666}
        
        /* Network Visualization */
        .network-section{background:#fff;border-radius:15px;padding:25px;margin-bottom:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .network-stats{display:flex;justify-content:space-around;margin-bottom:25px;padding:20px;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:12px}
        .network-stat{text-align:center}
        .network-stat-value{font-size:36px;font-weight:800;color:#27ae60}
        .network-stat-label{font-size:14px;color:#666;margin-top:5px}
        
        /* Referral Tree */
        .referral-tree{margin:20px 0}
        .tree-level{margin-bottom:25px}
        .tree-level-header{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:12px 20px;border-radius:10px;font-weight:700;margin-bottom:15px;display:flex;justify-content:space-between;align-items:center}
        .tree-items{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px}
        .tree-item{background:#f8f9fa;border-radius:10px;padding:15px;border-left:4px solid;transition:all 0.3s}
        .tree-item:hover{background:#e9ecef;transform:translateX(5px)}
        .tree-item.level-1{border-color:#27ae60}
        .tree-item.level-2{border-color:#3498db}
        .tree-item.level-3{border-color:#9b59b6}
        .tree-user-name{font-weight:700;color:#333;margin-bottom:5px}
        .tree-user-stats{font-size:12px;color:#666}
        
        /* Progress to Next Milestone */
        .milestone-progress-card{background:linear-gradient(135deg,#fff5e6,#ffe8cc);border-radius:15px;padding:25px;margin-bottom:25px;border-left:4px solid #f39c12}
        .progress-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
        .progress-title{font-size:18px;font-weight:700;color:#333}
        .progress-value{font-size:24px;font-weight:800;color:#f39c12}
        .progress-bar-container{background:rgba(255,255,255,0.5);height:30px;border-radius:15px;overflow:hidden;margin:15px 0}
        .progress-bar-fill{height:100%;background:linear-gradient(90deg,#f39c12,#e67e22);border-radius:15px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;transition:width 1s ease;box-shadow:0 2px 10px rgba(243,156,18,0.3)}
        .progress-info{font-size:14px;color:#666;text-align:center}
        
        /* Responsive */
        /* Network Growth Chart */
        .growth-chart-card{background:#fff;border-radius:15px;padding:25px;margin-bottom:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .chart-container{display:flex;align-items:flex-end;justify-content:space-between;height:200px;padding:20px 0;gap:8px}
        .chart-bar{flex:1;background:linear-gradient(180deg,#667eea 0%,#764ba2 100%);border-radius:8px 8px 0 0;position:relative;transition:all 0.3s;cursor:pointer;min-height:20px}
        .chart-bar:hover{transform:translateY(-5px);opacity:0.8}
        .chart-bar-label{position:absolute;bottom:-25px;left:50%;transform:translateX(-50%);font-size:11px;color:#888;white-space:nowrap}
        .chart-bar-value{position:absolute;top:-25px;left:50%;transform:translateX(-50%);font-size:12px;font-weight:700;color:#667eea;background:#fff;padding:3px 8px;border-radius:5px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
        .chart-legend{display:flex;justify-content:center;gap:20px;margin-top:35px;font-size:13px;color:#666}
        .legend-item{display:flex;align-items:center;gap:6px}
        .legend-dot{width:12px;height:12px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2)}
        
        /* Chain Bonus Explainer */
        .chain-bonus-card{background:linear-gradient(135deg,#e8f5e9 0%,#c8e6c9 100%);border-radius:15px;padding:30px;margin-bottom:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1);overflow:hidden}
        .chain-flow{display:flex;align-items:center;justify-content:space-around;gap:15px;margin:25px 0}
        .chain-level{flex:1;text-align:center;background:#fff;border-radius:15px;padding:25px 15px;box-shadow:0 3px 15px rgba(0,0,0,0.1);position:relative;transition:transform 0.3s}
        .chain-level:hover{transform:scale(1.05)}
        .chain-icon{font-size:40px;margin-bottom:10px}
        .chain-title{font-weight:700;color:#333;font-size:16px;margin-bottom:8px}
        .chain-percent{font-size:32px;font-weight:800;background:linear-gradient(135deg,#27ae60,#229954);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .chain-desc{font-size:12px;color:#666;margin-top:8px}
        .chain-arrow{font-size:32px;color:#27ae60;animation:arrowSlide 1.5s ease-in-out infinite}
        @keyframes arrowSlide{0%,100%{transform:translateX(0);opacity:0.6}50%{transform:translateX(5px);opacity:1}}
        .chain-info{background:#fff;border-radius:10px;padding:15px;margin-top:20px;font-size:13px;color:#666;line-height:1.6}
        
        /* Earning Calculator */
        .calculator-card{background:linear-gradient(135deg,#fff5e6 0%,#ffe8cc 100%);border-radius:15px;padding:30px;margin-bottom:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .calc-input-group{background:#fff;border-radius:12px;padding:20px;margin:20px 0}
        .calc-label{font-size:14px;font-weight:600;color:#333;margin-bottom:10px}
        .calc-slider-container{display:flex;align-items:center;gap:15px}
        .calc-slider{flex:1;-webkit-appearance:none;appearance:none;height:8px;border-radius:5px;background:#e0e0e0;outline:none}
        .calc-slider::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#f39c12,#e67e22);cursor:pointer;box-shadow:0 2px 8px rgba(243,156,18,0.4)}
        .calc-slider::-moz-range-thumb{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#f39c12,#e67e22);cursor:pointer;border:none;box-shadow:0 2px 8px rgba(243,156,18,0.4)}
        .calc-value{min-width:60px;text-align:center;font-size:20px;font-weight:700;color:#f39c12;background:#fff;padding:8px 15px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
        .calc-results{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-top:20px}
        .calc-result-item{background:#fff;border-radius:12px;padding:20px;text-align:center;box-shadow:0 3px 12px rgba(0,0,0,0.08)}
        .calc-result-label{font-size:12px;color:#888;margin-bottom:5px}
        .calc-result-value{font-size:28px;font-weight:800;color:#27ae60}
        .calc-result-breakdown{font-size:11px;color:#999;margin-top:5px}
        
        /* Top Referrers Leaderboard */
        .leaderboard-card{background:#fff;border-radius:15px;padding:25px;margin-bottom:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .leaderboard-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
        .leaderboard-title{display:flex;align-items:center;gap:10px;font-size:18px;font-weight:700;color:#333}
        .leaderboard-trophy{font-size:28px;animation:bounce 2s ease-in-out infinite}
        @keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
        .leaderboard-item{display:flex;align-items:center;padding:15px;background:linear-gradient(135deg,#f8f9fa,#e9ecef);border-radius:12px;margin-bottom:10px;transition:all 0.3s}
        .leaderboard-item:hover{transform:translateX(5px);box-shadow:0 4px 15px rgba(0,0,0,0.1)}
        .leaderboard-rank{width:40px;height:40px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px}
        .leaderboard-item:nth-child(1) .leaderboard-rank{background:linear-gradient(135deg,#ffd700,#ffed4e);color:#333}
        .leaderboard-item:nth-child(2) .leaderboard-rank{background:linear-gradient(135deg,#c0c0c0,#d3d3d3);color:#333}
        .leaderboard-item:nth-child(3) .leaderboard-rank{background:linear-gradient(135deg,#cd7f32,#a0522d);color:#fff}
        .leaderboard-user{flex:1;margin-left:15px}
        .leaderboard-name{font-weight:700;color:#333;font-size:15px}
        .leaderboard-count{font-size:12px;color:#888;margin-top:2px}
        .leaderboard-badge{padding:8px 15px;background:linear-gradient(135deg,#27ae60,#229954);color:#fff;border-radius:20px;font-weight:700;font-size:14px}
        .leaderboard-coming-soon{text-align:center;padding:30px;background:linear-gradient(135deg,#f8f9fa,#e9ecef);border-radius:12px;color:#999}
        .leaderboard-coming-soon .icon{font-size:50px;margin-bottom:10px;opacity:0.5}
        
        @media(max-width:768px){
            .stats-grid{grid-template-columns:repeat(2,1fr)}
            .steps{grid-template-columns:1fr}
            .hero-amount{font-size:40px}
            .code-value{font-size:24px}
            .share-buttons{flex-direction:column}
            .share-btn{justify-content:center}
            .level-earnings-grid{grid-template-columns:1fr}
            .network-stats{flex-direction:column;gap:15px}
            .tree-items{grid-template-columns:1fr}
            .milestone-badges{flex-direction:column}
            .chain-flow{flex-direction:column;gap:20px}
            .chain-arrow{transform:rotate(90deg)}
            .calc-results{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
<div class="container">
    <a href="<?php echo APP_URL; ?>/user/" class="back-btn">← Back to Dashboard</a>
    
    <!-- Hero Card -->
    <div class="hero-card">
        <div class="hero-title">🎁 Refer Friends & Earn Rewards!</div>
        <div class="hero-subtitle">Share your referral code and earn for every friend who joins</div>
        <div class="hero-amount">₹<?php echo number_format($referral_bonus, 0); ?></div>
        <div class="hero-label">Per Successful Referral</div>
    </div>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo $total_referrals; ?></div>
            <div class="stat-label">Total Referrals</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $completed_referrals; ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?php echo $pending_referrals; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value">₹<?php echo number_format($total_earnings, 0); ?></div>
            <div class="stat-label">Total Earned</div>
        </div>
    </div>
    
    <!-- Network Growth Chart -->
    <?php
    // Get last 7 days referral data
    $growth_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_label = date('D', strtotime("-$i days"));
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ? AND DATE(created_at) = ?");
            $stmt->execute([$user_id, $date]);
            $count = (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            $count = 0;
        }
        $growth_data[] = ['label' => $day_label, 'count' => $count];
    }
    $max_count = max(array_column($growth_data, 'count')) ?: 1;
    ?>
    <div class="growth-chart-card">
        <div class="section-title">📈 7-Day Referral Growth</div>
        <div class="chart-container">
            <?php foreach ($growth_data as $day): ?>
                <?php 
                $height = ($day['count'] / $max_count) * 100;
                $height = max($height, 10);
                ?>
                <div class="chart-bar" style="height: <?php echo $height; ?>%" title="<?php echo $day['count']; ?> referrals">
                    <?php if ($day['count'] > 0): ?>
                        <div class="chart-bar-value"><?php echo $day['count']; ?></div>
                    <?php endif; ?>
                    <div class="chart-bar-label"><?php echo $day['label']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-legend">
            <div class="legend-item">
                <div class="legend-dot"></div>
                <span>Daily Referrals</span>
            </div>
        </div>
    </div>
    
    <!-- Referral Code Box -->
    <div class="code-box">
        <div class="code-title">Your Referral Code</div>
        <div class="code-display">
            <div class="code-value" id="referralCode"><?php echo $referral_code; ?></div>
            <button class="code-copy" onclick="copyCode()">📋 Copy Code</button>
        </div>
        
        <div class="link-box">
            <input type="text" value="<?php echo $referral_link; ?>" id="referralLink" readonly>
            <button class="link-copy" onclick="copyLink()">Copy Link</button>
        </div>
        
        <div class="share-section">
            <div class="share-title">Share via</div>
            <div class="share-buttons">
                <a href="https://wa.me/?text=<?php echo urlencode($whatsapp_message); ?>" target="_blank" class="share-btn whatsapp">
                    📱 WhatsApp
                </a>
                <a href="https://t.me/share/url?url=<?php echo urlencode($referral_link); ?>&text=<?php echo urlencode($telegram_message); ?>" target="_blank" class="share-btn telegram">
                    ✈️ Telegram
                </a>
                <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($twitter_message); ?>" target="_blank" class="share-btn twitter">
                    🐦 Twitter
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_link); ?>" target="_blank" class="share-btn facebook">
                    📘 Facebook
                </a>
                <button class="share-btn copy" onclick="copyLink()">📋 Copy Link</button>
            </div>
        </div>
    </div>
    
    <!-- How It Works -->
    <div class="how-it-works">
        <div class="section-title">📖 How It Works</div>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-title">Share Your Code</div>
                <div class="step-desc">Share your unique referral code with friends via WhatsApp, Telegram, or any social media</div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-title">Friend Joins & Works</div>
                <div class="step-desc">Your friend signs up using your code and completes their first task successfully</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-title">You Earn Bonus!</div>
                <div class="step-desc">Once your friend completes their first task, you get ₹<?php echo number_format($referral_bonus, 0); ?> bonus in your wallet</div>
            </div>
        </div>
    </div>
    
    <!-- Chain Bonus Explainer -->
    <div class="chain-bonus-card">
        <div class="section-title">💰 3-Level Chain Commission System</div>
        <div class="chain-flow">
            <div class="chain-level">
                <div class="chain-icon">👤</div>
                <div class="chain-title">Level 1</div>
                <div class="chain-percent">10%</div>
                <div class="chain-desc">Direct Referrals</div>
            </div>
            <div class="chain-arrow">→</div>
            <div class="chain-level">
                <div class="chain-icon">👥</div>
                <div class="chain-title">Level 2</div>
                <div class="chain-percent">5%</div>
                <div class="chain-desc">Their Referrals</div>
            </div>
            <div class="chain-arrow">→</div>
            <div class="chain-level">
                <div class="chain-icon">👨‍👩‍👧‍👦</div>
                <div class="chain-title">Level 3</div>
                <div class="chain-percent">2%</div>
                <div class="chain-desc">Extended Network</div>
            </div>
        </div>
        <div class="chain-info">
            <strong>💡 How It Works:</strong> When your direct referral (Level 1) completes a task, you earn <strong>10% commission</strong>. When someone they referred (Level 2) completes a task, you earn <strong>5%</strong>. Even when Level 2's referrals (Level 3) complete tasks, you earn <strong>2%</strong>! This creates a powerful passive income stream as your network grows.
        </div>
    </div>
    
    <!-- Network Overview -->
    <div class="network-section">
        <div class="section-title">🌐 Your Referral Network</div>
        <div class="network-stats">
            <div class="network-stat">
                <div class="network-stat-value"><?php echo $network_size; ?></div>
                <div class="network-stat-label">Total Network Size</div>
            </div>
            <div class="network-stat">
                <div class="network-stat-value">₹<?php echo number_format($total_earnings, 0); ?></div>
                <div class="network-stat-label">Total Earnings</div>
            </div>
            <div class="network-stat">
                <div class="network-stat-value"><?php echo count($level_stats); ?></div>
                <div class="network-stat-label">Active Levels</div>
            </div>
        </div>
    </div>
    
    <!-- Level-wise Earnings Breakdown -->
    <div class="card">
        <div class="section-title">📊 Level-wise Earnings Breakdown</div>
        <div class="level-earnings-grid">
            <?php foreach ($level_stats as $level): ?>
            <div class="level-card level-<?php echo $level['level']; ?>">
                <div class="level-badge level-<?php echo $level['level']; ?>">
                    Level <?php echo $level['level']; ?>
                </div>
                <div class="level-count"><?php echo $level['count']; ?></div>
                <div class="level-label">Referrals</div>
                <div class="level-commission"><?php echo number_format($level['commission_percent'], 1); ?>%</div>
                <div class="level-label">Commission</div>
                <div class="level-earnings">₹<?php echo number_format($level['earnings'], 0); ?></div>
                <div class="level-label">Total Earned</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Earning Calculator -->
    <div class="calculator-card">
        <div class="section-title">🧮 Earnings Calculator</div>
        <p style="text-align:center;color:#666;font-size:14px;margin-bottom:20px">See how much you can earn with your referral network</p>
        
        <div class="calc-input-group">
            <div class="calc-label">Number of Direct Referrals (Level 1)</div>
            <div class="calc-slider-container">
                <input type="range" min="0" max="100" value="10" class="calc-slider" id="calcReferrals">
                <div class="calc-value" id="calcReferralsValue">10</div>
            </div>
        </div>
        
        <div class="calc-input-group">
            <div class="calc-label">Average Tasks Completed per Referral per Month</div>
            <div class="calc-slider-container">
                <input type="range" min="0" max="50" value="10" class="calc-slider" id="calcTasks">
                <div class="calc-value" id="calcTasksValue">10</div>
            </div>
        </div>
        
        <div class="calc-input-group">
            <div class="calc-label">Average Task Value (₹)</div>
            <div class="calc-slider-container">
                <input type="range" min="10" max="500" value="50" step="10" class="calc-slider" id="calcTaskValue">
                <div class="calc-value" id="calcTaskValueDisplay">₹50</div>
            </div>
        </div>
        
        <div class="calc-results">
            <div class="calc-result-item">
                <div class="calc-result-label">Level 1 (10%)</div>
                <div class="calc-result-value" id="calcLevel1">₹500</div>
                <div class="calc-result-breakdown">Monthly Earnings</div>
            </div>
            <div class="calc-result-item">
                <div class="calc-result-label">Level 2 (5%)</div>
                <div class="calc-result-value" id="calcLevel2">₹250</div>
                <div class="calc-result-breakdown">Estimated</div>
            </div>
            <div class="calc-result-item">
                <div class="calc-result-label">Level 3 (2%)</div>
                <div class="calc-result-value" id="calcLevel3">₹100</div>
                <div class="calc-result-breakdown">Estimated</div>
            </div>
        </div>
        
        <div style="text-align:center;margin-top:20px;padding:15px;background:#fff;border-radius:10px">
            <div style="font-size:14px;color:#888;margin-bottom:5px">Total Monthly Potential</div>
            <div style="font-size:36px;font-weight:800;color:#27ae60" id="calcTotal">₹850</div>
            <div style="font-size:12px;color:#999;margin-top:5px">* Estimates based on average network growth</div>
        </div>
    </div>
    
    <!-- Referral Milestones -->
    <?php
    $milestones = [
        5 => ['icon' => '🌟', 'reward' => 50],
        10 => ['icon' => '⭐', 'reward' => 100],
        25 => ['icon' => '🏆', 'reward' => 300],
        50 => ['icon' => '👑', 'reward' => 750],
        100 => ['icon' => '💎', 'reward' => 2000]
    ];
    $achieved_milestones = array_column($milestone_rewards, 'milestone_count');
    ?>
    <div class="card">
        <div class="section-title">🎯 Referral Milestones</div>
        <div class="milestone-badges">
            <?php foreach ($milestones as $count => $data): ?>
            <?php $is_achieved = in_array($count, $achieved_milestones); ?>
            <div class="milestone-badge <?php echo $is_achieved ? 'achieved' : ''; ?>">
                <div class="milestone-icon"><?php echo $data['icon']; ?></div>
                <div class="milestone-count"><?php echo $count; ?> Referrals</div>
                <div class="milestone-reward">₹<?php echo $data['reward']; ?></div>
                <div class="milestone-status">
                    <?php echo $is_achieved ? '✓ Achieved!' : ($total_referrals . '/' . $count); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php
        // Find next milestone
        $next_milestone = null;
        $next_reward = 0;
        foreach ($milestones as $count => $data) {
            if ($total_referrals < $count) {
                $next_milestone = $count;
                $next_reward = $data['reward'];
                break;
            }
        }
        ?>
        
        <?php if ($next_milestone): ?>
        <div class="milestone-progress-card">
            <div class="progress-header">
                <div class="progress-title">🎁 Next Milestone: <?php echo $next_milestone; ?> Referrals</div>
                <div class="progress-value">₹<?php echo $next_reward; ?></div>
            </div>
            <?php 
            $progress_percent = ($total_referrals / $next_milestone) * 100;
            $remaining = $next_milestone - $total_referrals;
            ?>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?php echo $progress_percent; ?>%">
                    <?php echo number_format($progress_percent, 1); ?>%
                </div>
            </div>
            <div class="progress-info">
                <?php echo $remaining; ?> more referral<?php echo $remaining != 1 ? 's' : ''; ?> to unlock ₹<?php echo $next_reward; ?> bonus!
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Referral Tree -->
    <?php if (!empty($referral_tree)): ?>
    <div class="card">
        <div class="section-title">🌳 Your Referral Tree</div>
        <div class="referral-tree">
            <?php foreach ($referral_tree as $level => $users): ?>
            <?php if (!empty($users)): ?>
            <div class="tree-level">
                <div class="tree-level-header">
                    <span>Level <?php echo $level; ?> (<?php echo count($users); ?> referral<?php echo count($users) != 1 ? 's' : ''; ?>)</span>
                    <span><?php echo [1 => '10%', 2 => '5%', 3 => '2%'][$level] ?? '0%'; ?> Commission</span>
                </div>
                <div class="tree-items">
                    <?php foreach ($users as $user): ?>
                    <div class="tree-item level-<?php echo $level; ?>">
                        <div class="tree-user-name">
                            <?php echo escape($user['username']); ?>
                        </div>
                        <div class="tree-user-stats">
                            📅 Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?><br>
                            ✅ Tasks: <?php echo $user['completed_tasks']; ?><br>
                            🔖 Status: <?php echo ucfirst($user['status']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Referred Users -->
    <div class="card">
        <div class="card-title">
            <span>👥 Your Referrals</span>
            <span style="font-size:14px;color:#888"><?php echo $total_referrals; ?> total</span>
        </div>
        
        <?php if (empty($referred_users)): ?>
            <div class="empty-state">
                <div class="icon">👥</div>
                <h4>No Referrals Yet</h4>
                <p>Share your referral code to start earning bonus rewards!</p>
            </div>
        <?php else: ?>
            <div class="user-list">
                <?php foreach ($referred_users as $user): ?>
                    <div class="user-item">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['referred_name'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo escape($user['referred_name']); ?></div>
                            <div class="user-date">Joined: <?php echo date('d M Y', strtotime($user['joined_at'])); ?></div>
                        </div>
                        <?php if ($user['status'] === 'completed'): ?>
                            <span class="user-status completed">✓ Completed</span>
                            <span class="user-bonus">+₹<?php echo number_format($user['bonus_amount'], 0); ?></span>
                        <?php else: ?>
                            <span class="user-status pending">⏳ Pending</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Referral Earnings History -->
    <div class="card">
        <div class="card-title">
            <span>💰 Referral Earnings</span>
        </div>
        
        <?php if (empty($referral_transactions)): ?>
            <div class="empty-state">
                <div class="icon">💰</div>
                <h4>No Earnings Yet</h4>
                <p>Referral bonuses will appear here once your friends complete tasks</p>
            </div>
        <?php else: ?>
            <?php foreach ($referral_transactions as $txn): ?>
                <div class="transaction-item">
                    <div class="txn-icon">🎁</div>
                    <div class="txn-info">
                        <div class="txn-desc"><?php echo escape($txn['description']); ?></div>
                        <div class="txn-date"><?php echo date('d M Y, H:i', strtotime($txn['created_at'])); ?></div>
                    </div>
                    <div class="txn-amount">+₹<?php echo number_format($txn['amount'], 2); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Top Referrers Leaderboard -->
    <div class="leaderboard-card">
        <div class="leaderboard-header">
            <div class="leaderboard-title">
                <span class="leaderboard-trophy">🏆</span>
                <span>Top Referrers This Month</span>
            </div>
        </div>
        
        <div class="leaderboard-coming-soon">
            <div class="icon">🚀</div>
            <h4 style="color:#666;font-size:16px;margin-bottom:8px">Coming Soon!</h4>
            <p style="font-size:13px;line-height:1.6">
                Compete with other top referrers and see where you rank! <br>
                Keep referring to unlock your position on the leaderboard.
            </p>
            <div style="margin-top:20px;padding:15px;background:#fff;border-radius:10px;display:inline-block">
                <div style="font-size:12px;color:#888">Your Current Rank</div>
                <div style="font-size:32px;font-weight:800;color:#667eea;margin:5px 0">-</div>
                <div style="font-size:11px;color:#999">Keep referring to rank up!</div>
            </div>
        </div>
        
        <!-- Placeholder for when leaderboard is active -->
        <div style="display:none">
            <div class="leaderboard-item">
                <div class="leaderboard-rank">1</div>
                <div class="leaderboard-user">
                    <div class="leaderboard-name">Top Referrer</div>
                    <div class="leaderboard-count">🎯 150 referrals</div>
                </div>
                <div class="leaderboard-badge">₹15,000</div>
            </div>
            <div class="leaderboard-item">
                <div class="leaderboard-rank">2</div>
                <div class="leaderboard-user">
                    <div class="leaderboard-name">Second Place</div>
                    <div class="leaderboard-count">🎯 120 referrals</div>
                </div>
                <div class="leaderboard-badge">₹12,000</div>
            </div>
            <div class="leaderboard-item">
                <div class="leaderboard-rank">3</div>
                <div class="leaderboard-user">
                    <div class="leaderboard-name">Third Place</div>
                    <div class="leaderboard-count">🎯 95 referrals</div>
                </div>
                <div class="leaderboard-badge">₹9,500</div>
            </div>
        </div>
    </div>
    
    <!-- Terms -->
    <div class="card" style="background:#f8f9fa">
        <div class="section-title">📋 Referral Terms & Conditions</div>
        <ul style="padding-left:20px;color:#666;font-size:13px;line-height:1.8">
            <li>Referral bonus of ₹<?php echo number_format($referral_bonus, 0); ?> is credited when your referred friend completes their first task</li>
            <li>The referred friend must use your referral code during registration</li>
            <li>Self-referrals or fake accounts are not allowed and will result in account suspension</li>
            <li>Bonus amount is subject to change without prior notice</li>
            <li>Admin reserves the right to modify or terminate the referral program at any time</li>
            <li>Referral earnings can be withdrawn as per normal withdrawal rules</li>
        </ul>
    </div>
</div>

<script>
// Copy referral code
function copyCode() {
    const code = document.getElementById('referralCode').innerText;
    copyToClipboard(code);
    showToast('✓ Referral code copied: ' + code);
}

// Copy referral link
function copyLink() {
    const link = document.getElementById('referralLink').value;
    copyToClipboard(link);
    showToast('✓ Referral link copied!');
}

// Copy to clipboard function
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text);
    } else {
        // Fallback
        const input = document.createElement('textarea');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    }
}

// Toast notification
function showToast(message) {
    // Remove existing toast
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();
    
    // Create toast
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: #fff;
        padding: 15px 30px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        z-index: 9999;
        animation: fadeInUp 0.3s ease;
    `;
    document.body.appendChild(toast);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeInUp {
        from { opacity: 0; transform: translate(-50%, 20px); }
        to { opacity: 1; transform: translate(-50%, 0); }
    }
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
`;
document.head.appendChild(style);

// Web Share API (for mobile)
if (navigator.share) {
    document.querySelectorAll('.share-btn.copy').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await navigator.share({
                    title: '<?php echo APP_NAME; ?> Referral',
                    text: 'Join <?php echo APP_NAME; ?> using my referral code: <?php echo $referral_code; ?>',
                    url: '<?php echo $referral_link; ?>'
                });
            } catch (err) {
                copyLink();
            }
        });
    });
}

// Earning Calculator
const calcReferrals = document.getElementById('calcReferrals');
const calcTasks = document.getElementById('calcTasks');
const calcTaskValue = document.getElementById('calcTaskValue');
const calcReferralsValue = document.getElementById('calcReferralsValue');
const calcTasksValue = document.getElementById('calcTasksValue');
const calcTaskValueDisplay = document.getElementById('calcTaskValueDisplay');
const calcLevel1 = document.getElementById('calcLevel1');
const calcLevel2 = document.getElementById('calcLevel2');
const calcLevel3 = document.getElementById('calcLevel3');
const calcTotal = document.getElementById('calcTotal');

function updateCalculator() {
    const referrals = parseInt(calcReferrals.value);
    const tasks = parseInt(calcTasks.value);
    const taskValue = parseInt(calcTaskValue.value);
    
    // Update display values
    calcReferralsValue.textContent = referrals;
    calcTasksValue.textContent = tasks;
    calcTaskValueDisplay.textContent = '₹' + taskValue;
    
    // Calculate earnings
    // Level 1: Direct referrals (10% commission)
    const level1Earnings = referrals * tasks * taskValue * 0.10;
    
    // Level 2: Conservative estimate - assume each L1 refers 2 people on average (5% commission)
    // This 2x multiplier represents typical network growth patterns
    const level2Referrals = referrals * 2;
    const level2Earnings = level2Referrals * tasks * taskValue * 0.05;
    
    // Level 3: Same 2x growth assumption for extended network (2% commission)
    const level3Referrals = level2Referrals * 2;
    const level3Earnings = level3Referrals * tasks * taskValue * 0.02;
    
    const total = level1Earnings + level2Earnings + level3Earnings;
    
    // Update display
    calcLevel1.textContent = '₹' + Math.round(level1Earnings).toLocaleString('en-IN');
    calcLevel2.textContent = '₹' + Math.round(level2Earnings).toLocaleString('en-IN');
    calcLevel3.textContent = '₹' + Math.round(level3Earnings).toLocaleString('en-IN');
    calcTotal.textContent = '₹' + Math.round(total).toLocaleString('en-IN');
}

if (calcReferrals) {
    calcReferrals.addEventListener('input', updateCalculator);
    calcTasks.addEventListener('input', updateCalculator);
    calcTaskValue.addEventListener('input', updateCalculator);
    updateCalculator(); // Initial calculation
}
</script>
</body>
</html>
