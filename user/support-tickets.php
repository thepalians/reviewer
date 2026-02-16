<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/ticket-functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$current_page = 'support-tickets';

// Get filter and pagination
$status_filter = $_GET['status'] ?? 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = ['user_id = :user_id'];
$params = [':user_id' => $user_id];

if ($status_filter !== 'all') {
    $where_conditions[] = 'status = :status';
    $params[':status'] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) FROM support_tickets WHERE $where_clause";
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_tickets = $stmt->fetchColumn();
$total_pages = ceil($total_tickets / $per_page);

// Get tickets
$query = "
    SELECT st.*,
           (SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = st.id) as reply_count,
           (SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = st.id AND is_admin = 1 AND created_at > st.last_user_view) as unread_replies
    FROM support_tickets st
    WHERE $where_clause
    ORDER BY 
        CASE WHEN status = 'open' THEN 0
             WHEN status = 'pending' THEN 1
             WHEN status = 'resolved' THEN 2
             WHEN status = 'closed' THEN 3
        END,
        created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get ticket stats
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM support_tickets
    WHERE user_id = :user_id
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([':user_id' => $user_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { width: 260px; position: fixed; left: 0; top: 60px; height: calc(100vh - 60px); background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%); box-shadow: 2px 0 10px rgba(0,0,0,0.1); overflow-y: auto; transition: all 0.3s ease; z-index: 999; }
        .sidebar-header { padding: 20px; background: rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h2 { color: #fff; font-size: 18px; margin: 0; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .sidebar-menu li a { display: block; padding: 15px 20px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; font-size: 14px; }
        .sidebar-menu li a:hover { background: rgba(255,255,255,0.1); color: #fff; padding-left: 25px; }
        .sidebar-menu li a.active { background: linear-gradient(90deg, rgba(66,153,225,0.2) 0%, transparent 100%); color: #4299e1; border-left: 3px solid #4299e1; }
        .main-content { margin-left: 260px; padding: 20px; min-height: calc(100vh - 60px); }
        .filter-tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-tab { padding: 10px 20px; border: none; background: #f0f0f0; color: #333; border-radius: 5px; cursor: pointer; text-decoration: none; transition: all 0.3s; }
        .filter-tab:hover { background: #e0e0e0; }
        .filter-tab.active { background: #4299e1; color: white; }
        .ticket-card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.3s; border-left: 4px solid #ddd; }
        .ticket-card:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.15); transform: translateY(-2px); }
        .ticket-card.priority-low { border-left-color: #4299e1; }
        .ticket-card.priority-medium { border-left-color: #f59e0b; }
        .ticket-card.priority-high { border-left-color: #ef4444; }
        .ticket-card.priority-urgent { border-left-color: #dc2626; }
        .ticket-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .ticket-number { font-size: 18px; font-weight: 600; color: #333; }
        .ticket-status { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .ticket-status.open { background: #dcfce7; color: #166534; }
        .ticket-status.pending { background: #fef3c7; color: #92400e; }
        .ticket-status.resolved { background: #dbeafe; color: #1e40af; }
        .ticket-status.closed { background: #f3f4f6; color: #6b7280; }
        .ticket-subject { font-size: 16px; font-weight: 500; color: #111; margin-bottom: 8px; }
        .ticket-meta { display: flex; gap: 20px; font-size: 13px; color: #666; margin-bottom: 10px; }
        .ticket-description { color: #555; font-size: 14px; line-height: 1.6; margin-bottom: 10px; }
        .ticket-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid #eee; }
        .unread-badge { background: #ef4444; color: white; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .pagination a { padding: 8px 12px; background: white; border: 1px solid #ddd; border-radius: 5px; color: #333; text-decoration: none; }
        .pagination a:hover { background: #f0f0f0; }
        .pagination a.active { background: #4299e1; color: white; border-color: #4299e1; }
        @media (max-width: 768px) { .sidebar { left: -260px; } .sidebar.active { left: 0; } .main-content { margin-left: 0; } }
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
                <h2><i class="fas fa-ticket-alt"></i> Support Tickets</h2>
                <a href="create-ticket.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Ticket
                </a>
            </div>
            
            <!-- Stats -->
            <div class="stats-grid" style="margin-bottom: 20px;">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #4361ee;">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Tickets</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2ecc71;">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['open']; ?></h3>
                        <p>Open</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f39c12;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #3498db;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['resolved']; ?></h3>
                        <p>Resolved</p>
                    </div>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All (<?php echo $stats['total']; ?>)
                </a>
                <a href="?status=open" class="filter-tab <?php echo $status_filter === 'open' ? 'active' : ''; ?>">
                    Open (<?php echo $stats['open']; ?>)
                </a>
                <a href="?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending (<?php echo $stats['pending']; ?>)
                </a>
                <a href="?status=resolved" class="filter-tab <?php echo $status_filter === 'resolved' ? 'active' : ''; ?>">
                    Resolved (<?php echo $stats['resolved']; ?>)
                </a>
                <a href="?status=closed" class="filter-tab <?php echo $status_filter === 'closed' ? 'active' : ''; ?>">
                    Closed (<?php echo $stats['closed']; ?>)
                </a>
            </div>
            
            <!-- Tickets List -->
            <?php if (count($tickets) > 0): ?>
                <?php foreach($tickets as $ticket): ?>
                <div class="ticket-card priority-<?php echo htmlspecialchars($ticket['priority']); ?>">
                    <div class="ticket-header">
                        <div class="ticket-number"><?php echo htmlspecialchars($ticket['ticket_number']); ?></div>
                        <span class="ticket-status <?php echo htmlspecialchars($ticket['status']); ?>">
                            <?php echo ucfirst($ticket['status']); ?>
                        </span>
                    </div>
                    
                    <div class="ticket-subject"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                    
                    <div class="ticket-meta">
                        <span><i class="fas fa-folder"></i> <?php echo ucfirst($ticket['category']); ?></span>
                        <span><i class="fas fa-flag"></i> <?php echo ucfirst($ticket['priority']); ?> Priority</span>
                        <span><i class="fas fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($ticket['created_at'])); ?></span>
                    </div>
                    
                    <div class="ticket-description">
                        <?php echo htmlspecialchars(substr($ticket['description'], 0, 150)) . (strlen($ticket['description']) > 150 ? '...' : ''); ?>
                    </div>
                    
                    <div class="ticket-footer">
                        <div>
                            <i class="fas fa-comments"></i> <?php echo $ticket['reply_count']; ?> Replies
                            <?php if ($ticket['unread_replies'] > 0): ?>
                                <span class="unread-badge"><?php echo $ticket['unread_replies']; ?> New</span>
                            <?php endif; ?>
                        </div>
                        <a href="view-ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-primary btn-small">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $i; ?>" 
                           class="<?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?status=<?php echo $status_filter; ?>&page=<?php echo $page + 1; ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No tickets found. 
                    <?php if ($status_filter !== 'all'): ?>
                        <a href="?status=all">Show all tickets</a>
                    <?php else: ?>
                        <a href="create-ticket.php">Create your first ticket</a>
                    <?php endif; ?>
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
