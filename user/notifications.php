<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notification_id = intval($_POST['notification_id'] ?? 0);
    if ($notification_id > 0) {
        markNotificationRead($notification_id, $user_id);
    }
    header('Location: ' . APP_URL . '/user/notifications.php');
    exit;
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    markAllNotificationsRead($user_id);
    header('Location: ' . APP_URL . '/user/notifications.php');
    exit;
}

// Handle delete notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notification'])) {
    $notification_id = intval($_POST['notification_id'] ?? 0);
    if ($notification_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
        } catch (PDOException $e) {}
    }
    header('Location: ' . APP_URL . '/user/notifications.php');
    exit;
}

// Handle clear all notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {}
    header('Location: ' . APP_URL . '/user/notifications.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get notifications
try {
    $where = "user_id = ?";
    $params = [$user_id];
    
    if ($filter === 'unread') {
        $where .= " AND is_read = 0";
    } elseif ($filter === 'read') {
        $where .= " AND is_read = 1";
    } elseif (in_array($filter, ['task', 'wallet', 'success', 'warning', 'info', 'system'])) {
        $where .= " AND type = ?";
        $params[] = $filter;
    }
    
    // Count total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $total_pages = ceil($total / $per_page);
    
    // Get notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    // Get counts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_all = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $total_unread = (int)$stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Notifications Error: " . $e->getMessage());
    $notifications = [];
    $total = 0;
    $total_pages = 0;
    $total_all = 0;
    $total_unread = 0;
}

// Get notification icon and color based on type
function getNotificationStyle($type) {
    $styles = [
        'info' => ['icon' => 'ℹ️', 'color' => '#3498db', 'bg' => '#e3f2fd'],
        'success' => ['icon' => '✅', 'color' => '#27ae60', 'bg' => '#e8f5e9'],
        'warning' => ['icon' => '⚠️', 'color' => '#f39c12', 'bg' => '#fff8e1'],
        'error' => ['icon' => '❌', 'color' => '#e74c3c', 'bg' => '#ffebee'],
        'task' => ['icon' => '📋', 'color' => '#9b59b6', 'bg' => '#f3e5f5'],
        'refund' => ['icon' => '💰', 'color' => '#27ae60', 'bg' => '#e8f5e9'],
        'wallet' => ['icon' => '💳', 'color' => '#3498db', 'bg' => '#e3f2fd'],
        'system' => ['icon' => '🔔', 'color' => '#666', 'bg' => '#f5f5f5'],
    ];
    return $styles[$type] ?? $styles['info'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px}
        
        .container{max-width:800px;margin:0 auto}
        
        .back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fff;color:#333;text-decoration:none;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:14px;transition:transform 0.2s;box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        .back-btn:hover{transform:translateY(-2px)}
        
        /* Header */
        .page-header{background:#fff;border-radius:15px;padding:25px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .page-title{font-size:24px;font-weight:700;color:#333;display:flex;align-items:center;gap:10px}
        .page-title .count{background:#e74c3c;color:#fff;font-size:14px;padding:3px 10px;border-radius:15px}
        .header-actions{display:flex;gap:10px}
        .header-btn{padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s}
        .header-btn.primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .header-btn.secondary{background:#f5f5f5;color:#666}
        .header-btn.danger{background:#fee;color:#e74c3c}
        .header-btn:hover{transform:translateY(-2px)}
        
        /* Filters */
        .filters{background:#fff;border-radius:12px;padding:15px;margin-bottom:20px;display:flex;gap:8px;flex-wrap:wrap;box-shadow:0 3px 15px rgba(0,0,0,0.08)}
        .filter-btn{padding:8px 16px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;border:2px solid #eee;background:#fff;color:#666;transition:all 0.2s;text-decoration:none}
        .filter-btn:hover{border-color:#667eea;color:#667eea}
        .filter-btn.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-color:transparent}
        .filter-btn .badge{background:rgba(255,255,255,0.3);padding:2px 8px;border-radius:10px;font-size:11px;margin-left:5px}
        
        /* Notification List */
        .notification-list{background:#fff;border-radius:15px;overflow:hidden;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        
        .notification-item{display:flex;padding:20px;border-bottom:1px solid #f5f5f5;transition:all 0.2s;position:relative}
        .notification-item:last-child{border-bottom:none}
        .notification-item:hover{background:#fafafa}
        .notification-item.unread{background:#f8f9ff}
        .notification-item.unread::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(135deg,#667eea,#764ba2)}
        
        .notif-icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-right:15px;flex-shrink:0}
        
        .notif-content{flex:1;min-width:0}
        .notif-title{font-weight:600;color:#333;font-size:15px;margin-bottom:5px;display:flex;align-items:center;gap:8px}
        .notif-title .new-badge{background:#e74c3c;color:#fff;font-size:10px;padding:2px 8px;border-radius:10px;font-weight:600}
        .notif-message{color:#666;font-size:14px;line-height:1.5;margin-bottom:8px}
        .notif-time{color:#999;font-size:12px;display:flex;align-items:center;gap:5px}
        
        .notif-actions{display:flex;flex-direction:column;gap:8px;margin-left:15px}
        .notif-action{width:35px;height:35px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;border:none;font-size:14px;transition:all 0.2s}
        .notif-action.read{background:#e8f5e9;color:#27ae60}
        .notif-action.read:hover{background:#c8e6c9}
        .notif-action.delete{background:#ffebee;color:#e74c3c}
        .notif-action.delete:hover{background:#ffcdd2}
        .notif-action.link{background:#e3f2fd;color:#3498db;text-decoration:none}
        .notif-action.link:hover{background:#bbdefb}
        
        /* Empty State */
        .empty-state{text-align:center;padding:60px 20px;color:#999}
        .empty-state .icon{font-size:60px;margin-bottom:20px;opacity:0.5}
        .empty-state h3{color:#666;margin-bottom:10px;font-size:18px}
        .empty-state p{font-size:14px}
        
        /* Pagination */
        .pagination{display:flex;justify-content:center;gap:8px;margin-top:20px}
        .page-btn{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#fff;color:#666;text-decoration:none;font-weight:600;font-size:14px;transition:all 0.2s;box-shadow:0 2px 10px rgba(0,0,0,0.08)}
        .page-btn:hover{background:#667eea;color:#fff}
        .page-btn.active{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
        .page-btn.disabled{opacity:0.5;cursor:not-allowed;pointer-events:none}
        
        /* Stats */
        .stats-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px}
        .stat-item{background:#fff;border-radius:10px;padding:15px;text-align:center;box-shadow:0 3px 10px rgba(0,0,0,0.08)}
        .stat-item .value{font-size:24px;font-weight:700;color:#667eea}
        .stat-item .label{font-size:11px;color:#888;margin-top:3px}
        
        /* Responsive */
        @media(max-width:768px){
            .page-header{flex-direction:column;text-align:center}
            .header-actions{width:100%;justify-content:center}
            .filters{justify-content:center}
            .notification-item{flex-direction:column}
            .notif-icon{margin-bottom:10px}
            .notif-actions{flex-direction:row;margin-left:0;margin-top:10px}
            .stats-bar{grid-template-columns:repeat(2,1fr)}
        }
    </style>
</head>
<body>
<div class="container">
    <a href="<?php echo APP_URL; ?>/user/" class="back-btn">← Back to Dashboard</a>
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-title">
            🔔 Notifications
            <?php if ($total_unread > 0): ?>
                <span class="count"><?php echo $total_unread; ?> new</span>
            <?php endif; ?>
        </div>
        <div class="header-actions">
            <?php if ($total_unread > 0): ?>
                <form method="POST" style="display:inline">
                    <button type="submit" name="mark_all_read" class="header-btn primary">✓ Mark All Read</button>
                </form>
            <?php endif; ?>
            <?php if ($total_all > 0): ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Clear all notifications?')">
                    <button type="submit" name="clear_all" class="header-btn danger">🗑️ Clear All</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="value"><?php echo $total_all; ?></div>
            <div class="label">Total</div>
        </div>
        <div class="stat-item">
            <div class="value" style="color:#e74c3c"><?php echo $total_unread; ?></div>
            <div class="label">Unread</div>
        </div>
        <div class="stat-item">
            <div class="value" style="color:#27ae60"><?php echo $total_all - $total_unread; ?></div>
            <div class="label">Read</div>
        </div>
        <div class="stat-item">
            <div class="value" style="color:#f39c12"><?php echo $total_pages; ?></div>
            <div class="label">Pages</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters">
        <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
        <a href="?filter=unread" class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>">
            Unread
            <?php if ($total_unread > 0): ?>
                <span class="badge"><?php echo $total_unread; ?></span>
            <?php endif; ?>
        </a>
        <a href="?filter=task" class="filter-btn <?php echo $filter === 'task' ? 'active' : ''; ?>">📋 Tasks</a>
        <a href="?filter=wallet" class="filter-btn <?php echo $filter === 'wallet' ? 'active' : ''; ?>">💳 Wallet</a>
        <a href="?filter=success" class="filter-btn <?php echo $filter === 'success' ? 'active' : ''; ?>">✅ Success</a>
        <a href="?filter=warning" class="filter-btn <?php echo $filter === 'warning' ? 'active' : ''; ?>">⚠️ Warning</a>
        <a href="?filter=system" class="filter-btn <?php echo $filter === 'system' ? 'active' : ''; ?>">🔔 System</a>
    </div>
    
    <!-- Notification List -->
    <div class="notification-list">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <div class="icon">🔔</div>
                <h3>No Notifications</h3>
                <p>
                    <?php if ($filter !== 'all'): ?>
                        No <?php echo $filter; ?> notifications found. <a href="?filter=all" style="color:#667eea">View all</a>
                    <?php else: ?>
                        You're all caught up! Check back later for updates.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): 
                $style = getNotificationStyle($notif['type']);
            ?>
                <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                    <div class="notif-icon" style="background:<?php echo $style['bg']; ?>;color:<?php echo $style['color']; ?>">
                        <?php echo $style['icon']; ?>
                    </div>
                    <div class="notif-content">
                        <div class="notif-title">
                            <?php echo escape($notif['title']); ?>
                            <?php if (!$notif['is_read']): ?>
                                <span class="new-badge">NEW</span>
                            <?php endif; ?>
                        </div>
                        <div class="notif-message"><?php echo nl2br(escape($notif['message'])); ?></div>
                        <div class="notif-time">
                            🕐 <?php echo getTimeAgo($notif['created_at']); ?>
                            <span style="margin-left:10px;color:#ccc">•</span>
                            <span style="margin-left:10px"><?php echo date('d M Y, H:i', strtotime($notif['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="notif-actions">
                        <?php if (!empty($notif['link'])): ?>
                            <a href="<?php echo escape($notif['link']); ?>" class="notif-action link" title="View">👁️</a>
                        <?php endif; ?>
                        <?php if (!$notif['is_read']): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                <button type="submit" name="mark_read" class="notif-action read" title="Mark as Read">✓</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this notification?')">
                            <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                            <button type="submit" name="delete_notification" class="notif-action delete" title="Delete">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <a href="?filter=<?php echo $filter; ?>&page=1" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">«</a>
            <a href="?filter=<?php echo $filter; ?>&page=<?php echo max(1, $page - 1); ?>" class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">‹</a>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
                <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <a href="?filter=<?php echo $filter; ?>&page=<?php echo min($total_pages, $page + 1); ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">›</a>
            <a href="?filter=<?php echo $filter; ?>&page=<?php echo $total_pages; ?>" class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">»</a>
        </div>
    <?php endif; ?>
</div>

<?php
// Helper function for time ago
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    return date('d M Y', $time);
}
?>

<script>
// Auto-refresh every 30 seconds if on unread filter
<?php if ($filter === 'unread'): ?>
setTimeout(() => location.reload(), 30000);
<?php endif; ?>

// Mark as read when clicking on notification
document.querySelectorAll('.notification-item.unread').forEach(item => {
    item.addEventListener('click', function(e) {
        if (e.target.closest('.notif-actions')) return;
        const form = this.querySelector('form[action*="mark_read"]');
        if (form) form.submit();
    });
});
</script>
</body>
</html>
