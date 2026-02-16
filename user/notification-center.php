<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/notification-center-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$current_page = 'notification-center';

// Get filter parameters
$category_filter = $_GET['category'] ?? 'all';
$read_filter = $_GET['read'] ?? 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get notification categories
$categories = getNotificationCategories($pdo);

// Build query
$where_conditions = ['user_id = :user_id'];
$params = [':user_id' => $user_id];

if ($category_filter !== 'all') {
    $where_conditions[] = 'category_id = :category_id';
    $params[':category_id'] = $category_filter;
}

if ($read_filter === 'unread') {
    $where_conditions[] = 'is_read = 0';
} elseif ($read_filter === 'read') {
    $where_conditions[] = 'is_read = 1';
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM user_notifications WHERE $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_notifications = $stmt->fetchColumn();
$total_pages = ceil($total_notifications / $per_page);

// Get notifications
$query = "
    SELECT un.*, nc.name as category_name, nc.icon, nc.color
    FROM user_notifications un
    LEFT JOIN notification_categories nc ON un.category_id = nc.id
    WHERE $where_clause
    ORDER BY un.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read
    FROM user_notifications
    WHERE user_id = :user_id
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([':user_id' => $user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $notification_id = intval($_POST['notification_id']);
    $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $notification_id, ':user_id' => $user_id]);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
    $stmt->execute([':user_id' => $user_id]);
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y h:i A', $time);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Center - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { width: 260px; position: fixed; left: 0; top: 60px; height: calc(100vh - 60px); background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%); box-shadow: 2px 0 10px rgba(0,0,0,0.1); overflow-y: auto; transition: all 0.3s ease; z-index: 999; }
        .main-content { margin-left: 260px; padding: 20px; min-height: calc(100vh - 60px); }
        .filter-section { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-tabs { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-tab { padding: 8px 16px; border: none; background: #f0f0f0; color: #333; border-radius: 5px; cursor: pointer; text-decoration: none; font-size: 14px; transition: all 0.3s; }
        .filter-tab:hover { background: #e0e0e0; }
        .filter-tab.active { background: #4299e1; color: white; }
        .notification-card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.3s; display: flex; gap: 15px; border-left: 4px solid #ddd; }
        .notification-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
        .notification-card.unread { background: #f0f9ff; border-left-color: #3b82f6; }
        .notification-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; color: white; }
        .notification-content { flex: 1; min-width: 0; }
        .notification-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
        .notification-title { font-weight: 600; color: #333; font-size: 15px; }
        .notification-time { font-size: 12px; color: #666; white-space: nowrap; }
        .notification-message { color: #555; font-size: 14px; line-height: 1.5; margin-bottom: 8px; }
        .notification-actions { display: flex; gap: 10px; align-items: center; }
        .notification-actions button { padding: 5px 12px; border: none; background: #e5e7eb; color: #333; border-radius: 5px; cursor: pointer; font-size: 12px; transition: all 0.3s; }
        .notification-actions button:hover { background: #d1d5db; }
        .category-badge { padding: 4px 10px; background: #e5e7eb; color: #333; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .pagination a { padding: 8px 12px; background: white; border: 1px solid #ddd; border-radius: 5px; color: #333; text-decoration: none; }
        .pagination a:hover { background: #f0f0f0; }
        .pagination a.active { background: #4299e1; color: white; border-color: #4299e1; }
        @media (max-width: 768px) { .sidebar { left: -260px; } .main-content { margin-left: 0; } .notification-card { flex-direction: column; } }
    </style>
</head>
<body class="light-mode">
    <?php 
    include '../includes/header.php'; 
    require_once __DIR__ . '/includes/sidebar.php';
    ?>
    
    <div class="main-content">
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-bell"></i> Notification Center</h2>
                <?php if ($stats['unread'] > 0): ?>
                <form method="POST" action="" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="btn btn-primary">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <!-- Stats -->
            <div class="stats-grid" style="margin-bottom: 20px;">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #4361ee;">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Notifications</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #3b82f6;">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['unread']; ?></h3>
                        <p>Unread</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2ecc71;">
                        <i class="fas fa-envelope-open"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['read']; ?></h3>
                        <p>Read</p>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <div>
                    <strong>Filter by Status:</strong>
                    <div class="filter-tabs" style="margin-top: 8px;">
                        <a href="?category=<?php echo $category_filter; ?>&read=all" 
                           class="filter-tab <?php echo $read_filter === 'all' ? 'active' : ''; ?>">
                            All (<?php echo $stats['total']; ?>)
                        </a>
                        <a href="?category=<?php echo $category_filter; ?>&read=unread" 
                           class="filter-tab <?php echo $read_filter === 'unread' ? 'active' : ''; ?>">
                            Unread (<?php echo $stats['unread']; ?>)
                        </a>
                        <a href="?category=<?php echo $category_filter; ?>&read=read" 
                           class="filter-tab <?php echo $read_filter === 'read' ? 'active' : ''; ?>">
                            Read (<?php echo $stats['read']; ?>)
                        </a>
                    </div>
                </div>
                
                <div>
                    <strong>Filter by Category:</strong>
                    <div class="filter-tabs" style="margin-top: 8px;">
                        <a href="?category=all&read=<?php echo $read_filter; ?>" 
                           class="filter-tab <?php echo $category_filter === 'all' ? 'active' : ''; ?>">
                            All Categories
                        </a>
                        <?php foreach($categories as $cat): ?>
                        <a href="?category=<?php echo $cat['id']; ?>&read=<?php echo $read_filter; ?>" 
                           class="filter-tab <?php echo $category_filter == $cat['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Notifications List -->
            <?php if (count($notifications) > 0): ?>
                <?php foreach($notifications as $notification): ?>
                <div class="notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                    <div class="notification-icon" style="background-color: <?php echo htmlspecialchars($notification['color'] ?? '#4299e1'); ?>;">
                        <i class="fas fa-<?php echo htmlspecialchars($notification['icon'] ?? 'bell'); ?>"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-header">
                            <div class="notification-title">
                                <?php echo htmlspecialchars($notification['title']); ?>
                                <?php if (!$notification['is_read']): ?>
                                    <span style="display: inline-block; width: 8px; height: 8px; background: #3b82f6; border-radius: 50%; margin-left: 5px;"></span>
                                <?php endif; ?>
                            </div>
                            <div class="notification-time">
                                <?php echo timeAgo($notification['created_at']); ?>
                            </div>
                        </div>
                        <div class="notification-message">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>
                        <div class="notification-actions">
                            <span class="category-badge">
                                <i class="fas fa-<?php echo htmlspecialchars($notification['icon'] ?? 'bell'); ?>"></i>
                                <?php echo htmlspecialchars($notification['category_name'] ?? 'General'); ?>
                            </span>
                            <?php if (!$notification['is_read']): ?>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" name="mark_read">
                                    <i class="fas fa-check"></i> Mark as Read
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($notification['action_url']): ?>
                            <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" 
                               style="padding: 5px 12px; background: #4299e1; color: white; border-radius: 5px; text-decoration: none; font-size: 12px;">
                                <i class="fas fa-external-link-alt"></i> View
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?category=<?php echo $category_filter; ?>&read=<?php echo $read_filter; ?>&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?category=<?php echo $category_filter; ?>&read=<?php echo $read_filter; ?>&page=<?php echo $i; ?>" 
                           class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?category=<?php echo $category_filter; ?>&read=<?php echo $read_filter; ?>&page=<?php echo $page + 1; ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No notifications found.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/../includes/version-display.php'; ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/themes.css">
    <script src="<?= APP_URL ?>/assets/js/theme.js"></script>
    <?php require_once __DIR__ . '/../includes/chatbot-widget.php'; ?>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
