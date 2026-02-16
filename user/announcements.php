<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/announcement-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = escape($_SESSION['user_name'] ?? 'User');

// Get active announcements for users
try {
    $announcements = getActiveAnnouncementsForUser($pdo, 'users');
    
    // Get viewed status for each announcement
    $viewed_announcements = [];
    if (!empty($announcements)) {
        $announcement_ids = array_column($announcements, 'id');
        $placeholders = str_repeat('?,', count($announcement_ids) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT announcement_id 
            FROM announcement_views 
            WHERE user_id = ? AND announcement_id IN ($placeholders)
        ");
        $stmt->execute(array_merge([$user_id], $announcement_ids));
        $viewed_announcements = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'announcement_id');
    }
    
    // Mark as viewed if viewing specific announcement
    if (isset($_GET['view']) && !empty($_GET['view'])) {
        $announcement_id = (int)$_GET['view'];
        markAnnouncementViewed($pdo, $announcement_id, $user_id);
        header('Location: ' . APP_URL . '/user/announcements.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Announcements Error: " . $e->getMessage());
    $error = 'Unable to load announcements';
    $announcements = [];
    $viewed_announcements = [];
}

$csrf_token = generateCSRFToken();

// Count unviewed announcements
$unviewed_count = 0;
foreach ($announcements as $announcement) {
    if (!in_array($announcement['id'], $viewed_announcements)) {
        $unviewed_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px}
        
        .container{max-width:900px;margin:0 auto}
        
        .back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fff;color:#333;text-decoration:none;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:14px;transition:transform 0.2s;box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        .back-btn:hover{transform:translateY(-2px)}
        
        /* Header */
        .page-header{background:#fff;border-radius:15px;padding:25px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .page-title{font-size:24px;font-weight:700;color:#333;display:flex;align-items:center;gap:10px}
        .page-title .count{background:#e74c3c;color:#fff;font-size:14px;padding:3px 10px;border-radius:15px}
        
        /* Announcement Cards */
        .announcements-grid{display:grid;gap:20px;margin-bottom:20px}
        
        .announcement-card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 5px 20px rgba(0,0,0,0.1);transition:all 0.3s;position:relative;overflow:hidden}
        .announcement-card:hover{transform:translateY(-5px);box-shadow:0 8px 25px rgba(0,0,0,0.15)}
        
        .announcement-card.unviewed{border-left:4px solid #667eea}
        .announcement-card.unviewed::before{content:'NEW';position:absolute;top:15px;right:15px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:5px 12px;border-radius:15px;font-size:11px;font-weight:700}
        
        .announcement-header{display:flex;justify-content:space-between;align-items:start;margin-bottom:15px;gap:15px}
        
        .announcement-title{font-size:20px;font-weight:700;color:#333;margin-bottom:8px;display:flex;align-items:center;gap:10px}
        .announcement-title .icon{font-size:24px}
        
        .announcement-meta{display:flex;gap:15px;flex-wrap:wrap;font-size:13px;color:#999;margin-bottom:15px}
        .announcement-meta .meta-item{display:flex;align-items:center;gap:5px}
        .announcement-meta .meta-icon{font-size:14px}
        
        .announcement-message{color:#666;font-size:15px;line-height:1.7;margin-bottom:15px}
        
        .announcement-dates{background:#f8f9ff;border-radius:10px;padding:12px 15px;display:flex;gap:20px;flex-wrap:wrap;font-size:13px}
        .announcement-dates .date-item{display:flex;align-items:center;gap:8px}
        .announcement-dates .date-label{color:#999;font-weight:600}
        .announcement-dates .date-value{color:#667eea;font-weight:700}
        
        .announcement-footer{display:flex;justify-content:space-between;align-items:center;margin-top:15px;padding-top:15px;border-top:1px solid #f5f5f5}
        
        .view-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;transition:all 0.2s}
        .view-btn:hover{transform:translateY(-2px);box-shadow:0 4px 15px rgba(102,126,234,0.4)}
        
        .viewed-badge{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;background:#e8f5e9;color:#27ae60;border-radius:8px;font-size:13px;font-weight:600}
        
        /* Target Audience Badge */
        .audience-badge{display:inline-block;padding:5px 12px;border-radius:15px;font-size:12px;font-weight:600}
        .audience-badge.all{background:#e3f2fd;color:#2196f3}
        .audience-badge.users{background:#e8f5e9;color:#4caf50}
        .audience-badge.sellers{background:#fff8e1;color:#ff9800}
        
        /* Empty State */
        .empty-state{background:#fff;border-radius:15px;text-align:center;padding:60px 20px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .empty-state .icon{font-size:80px;margin-bottom:20px;opacity:0.5}
        .empty-state h3{color:#666;margin-bottom:10px;font-size:20px}
        .empty-state p{font-size:14px;color:#999}
        
        /* Error Message */
        .error-message{background:#ffebee;border:1px solid #ffcdd2;color:#c62828;padding:15px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
        
        /* Stats */
        .stats-bar{display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:20px}
        .stat-item{background:#fff;border-radius:12px;padding:20px;text-align:center;box-shadow:0 3px 15px rgba(0,0,0,0.08)}
        .stat-item .value{font-size:28px;font-weight:700;color:#667eea;margin-bottom:5px}
        .stat-item .label{font-size:13px;color:#888;font-weight:600}
        
        /* Responsive */
        @media(max-width:768px){
            .page-header{flex-direction:column;text-align:center}
            .announcement-header{flex-direction:column}
            .announcement-dates{flex-direction:column;gap:10px}
            .announcement-footer{flex-direction:column;gap:10px}
            .stats-bar{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
<div class="container">
    <a href="<?php echo APP_URL; ?>/user/" class="back-btn">← Back to Dashboard</a>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            📢 Announcements
            <?php if ($unviewed_count > 0): ?>
                <span class="count"><?php echo $unviewed_count; ?> new</span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="value"><?php echo count($announcements); ?></div>
            <div class="label">Total Announcements</div>
        </div>
        <div class="stat-item">
            <div class="value" style="color:#e74c3c"><?php echo $unviewed_count; ?></div>
            <div class="label">Unread</div>
        </div>
        <div class="stat-item">
            <div class="value" style="color:#27ae60"><?php echo count($announcements) - $unviewed_count; ?></div>
            <div class="label">Read</div>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="error-message">
            <span style="font-size:20px">⚠️</span>
            <span><?php echo escape($error); ?></span>
        </div>
    <?php endif; ?>
    
    <!-- Announcements List -->
    <?php if (empty($announcements)): ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <h3>No Announcements Yet</h3>
            <p>Check back later for important updates and announcements from the system.</p>
        </div>
    <?php else: ?>
        <div class="announcements-grid">
            <?php foreach ($announcements as $announcement): 
                $is_viewed = in_array($announcement['id'], $viewed_announcements);
                $start_date = 'Not set';
                $end_date = 'No end date';
                $created_at = 'Unknown';
                
                if ($announcement['start_date']) {
                    $start_timestamp = strtotime($announcement['start_date']);
                    $start_date = $start_timestamp !== false ? date('M d, Y', $start_timestamp) : 'Invalid date';
                }
                if ($announcement['end_date']) {
                    $end_timestamp = strtotime($announcement['end_date']);
                    $end_date = $end_timestamp !== false ? date('M d, Y', $end_timestamp) : 'Invalid date';
                }
                if ($announcement['created_at']) {
                    $created_timestamp = strtotime($announcement['created_at']);
                    $created_at = $created_timestamp !== false ? date('M d, Y \a\t g:i A', $created_timestamp) : 'Unknown';
                }
            ?>
                <div class="announcement-card <?php echo !$is_viewed ? 'unviewed' : ''; ?>">
                    <div class="announcement-header">
                        <div>
                            <div class="announcement-title">
                                <span class="icon">📢</span>
                                <?php echo escape($announcement['title']); ?>
                            </div>
                            <div class="announcement-meta">
                                <span class="meta-item">
                                    <span class="meta-icon">📅</span>
                                    Posted: <?php echo $created_at; ?>
                                </span>
                                <span class="meta-item">
                                    <span class="audience-badge <?php echo escape($announcement['target_audience']); ?>">
                                        <?php 
                                        echo $announcement['target_audience'] === 'all' ? '🌐 All Users' : 
                                             ($announcement['target_audience'] === 'users' ? '👤 Users' : '🏪 Sellers'); 
                                        ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="announcement-message">
                        <?php echo nl2br(escape($announcement['message'])); ?>
                    </div>
                    
                    <?php if ($announcement['start_date'] || $announcement['end_date']): ?>
                        <div class="announcement-dates">
                            <?php if ($announcement['start_date']): ?>
                                <div class="date-item">
                                    <span class="date-label">📆 Start:</span>
                                    <span class="date-value"><?php echo $start_date; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($announcement['end_date']): ?>
                                <div class="date-item">
                                    <span class="date-label">🏁 End:</span>
                                    <span class="date-value"><?php echo $end_date; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="announcement-footer">
                        <?php if (!$is_viewed): ?>
                            <a href="?view=<?php echo $announcement['id']; ?>" class="view-btn">
                                ✓ Mark as Read
                            </a>
                        <?php else: ?>
                            <span class="viewed-badge">
                                ✓ Already Read
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
