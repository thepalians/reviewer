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
$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Get ticket details
$stmt = $pdo->prepare("
    SELECT * FROM support_tickets 
    WHERE id = :id AND user_id = :user_id
");
$stmt->execute([':id' => $ticket_id, ':user_id' => $user_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    $_SESSION['error_message'] = 'Ticket not found.';
    header('Location: support-tickets.php');
    exit;
}

// Get ticket replies
$stmt = $pdo->prepare("
    SELECT tr.*, 
           CASE WHEN tr.is_admin = 1 THEN 'Support Team' ELSE u.name END as sender_name
    FROM ticket_replies tr
    LEFT JOIN users u ON tr.user_id = u.id
    WHERE tr.ticket_id = :ticket_id
    ORDER BY tr.created_at ASC
");
$stmt->execute([':ticket_id' => $ticket_id]);
$replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Update last user view
$stmt = $pdo->prepare("UPDATE support_tickets SET last_user_view = NOW() WHERE id = :id");
$stmt->execute([':id' => $ticket_id]);

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $reply_text = trim($_POST['reply']);
    
    if (!empty($reply_text)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ticket_replies (ticket_id, user_id, is_admin, message)
                VALUES (:ticket_id, :user_id, 0, :message)
            ");
            $stmt->execute([
                ':ticket_id' => $ticket_id,
                ':user_id' => $user_id,
                ':message' => $reply_text
            ]);
            
            // Update ticket status to pending if it was resolved
            if ($ticket['status'] === 'resolved' || $ticket['status'] === 'closed') {
                $stmt = $pdo->prepare("UPDATE support_tickets SET status = 'pending' WHERE id = :id");
                $stmt->execute([':id' => $ticket_id]);
            }
            
            $_SESSION['success_message'] = 'Reply posted successfully!';
            header('Location: view-ticket.php?id=' . $ticket_id);
            exit;
        } catch (Exception $e) {
            error_log('Reply error: ' . $e->getMessage());
            $success_message = '';
        }
    }
}

// Calculate time ago
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
    <title>Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { width: 260px; position: fixed; left: 0; top: 60px; height: calc(100vh - 60px); background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%); box-shadow: 2px 0 10px rgba(0,0,0,0.1); overflow-y: auto; transition: all 0.3s ease; z-index: 999; }
        .main-content { margin-left: 260px; padding: 20px; min-height: calc(100vh - 60px); }
        .ticket-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 12px; color: #666; margin-bottom: 5px; text-transform: uppercase; font-weight: 600; }
        .info-value { font-size: 14px; color: #333; font-weight: 500; }
        .ticket-status { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .ticket-status.open { background: #dcfce7; color: #166534; }
        .ticket-status.pending { background: #fef3c7; color: #92400e; }
        .ticket-status.resolved { background: #dbeafe; color: #1e40af; }
        .ticket-status.closed { background: #f3f4f6; color: #6b7280; }
        .priority-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .priority-badge.low { background: #dbeafe; color: #1e40af; }
        .priority-badge.medium { background: #fef3c7; color: #92400e; }
        .priority-badge.high { background: #fee2e2; color: #991b1b; }
        .priority-badge.urgent { background: #dc2626; color: white; }
        .ticket-description { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #4299e1; }
        .replies-section { margin-top: 30px; }
        .reply-item { display: flex; gap: 15px; margin-bottom: 20px; padding: 15px; background: white; border-radius: 8px; }
        .reply-item.admin-reply { background: #f0f9ff; border-left: 4px solid #3b82f6; }
        .reply-item.user-reply { background: white; border-left: 4px solid #e5e7eb; }
        .reply-avatar { width: 40px; height: 40px; border-radius: 50%; background: #4299e1; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
        .reply-avatar.admin { background: #3b82f6; }
        .reply-content { flex: 1; }
        .reply-header { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .reply-sender { font-weight: 600; color: #333; }
        .reply-time { font-size: 12px; color: #666; }
        .reply-message { color: #555; line-height: 1.6; white-space: pre-wrap; }
        .reply-form { margin-top: 20px; }
        .reply-form textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; min-height: 100px; resize: vertical; font-size: 14px; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
        @media (max-width: 768px) { .sidebar { left: -260px; } .main-content { margin-left: 0; } }
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
                <h2>
                    <i class="fas fa-ticket-alt"></i> 
                    Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?>
                </h2>
                <a href="support-tickets.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Tickets
                </a>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Ticket Info -->
            <div class="ticket-info">
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <span class="ticket-status <?php echo htmlspecialchars($ticket['status']); ?>">
                            <?php echo ucfirst($ticket['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Priority</div>
                    <div class="info-value">
                        <span class="priority-badge <?php echo htmlspecialchars($ticket['priority']); ?>">
                            <?php echo ucfirst($ticket['priority']); ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Category</div>
                    <div class="info-value"><?php echo ucfirst($ticket['category']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value"><?php echo date('M d, Y h:i A', strtotime($ticket['created_at'])); ?></div>
                </div>
                <?php if ($ticket['sla_deadline']): ?>
                <div class="info-item">
                    <div class="info-label">SLA Deadline</div>
                    <div class="info-value"><?php echo date('M d, Y h:i A', strtotime($ticket['sla_deadline'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Ticket Subject & Description -->
            <div class="ticket-description">
                <h3 style="margin-bottom: 15px; color: #333;">
                    <?php echo htmlspecialchars($ticket['subject']); ?>
                </h3>
                <div style="color: #555; line-height: 1.7; white-space: pre-wrap;">
                    <?php echo htmlspecialchars($ticket['description']); ?>
                </div>
            </div>
            
            <!-- Replies Section -->
            <div class="replies-section">
                <h3 style="margin-bottom: 20px;">
                    <i class="fas fa-comments"></i> Conversation (<?php echo count($replies); ?>)
                </h3>
                
                <?php if (count($replies) > 0): ?>
                    <?php foreach($replies as $reply): ?>
                    <div class="reply-item <?php echo $reply['is_admin'] ? 'admin-reply' : 'user-reply'; ?>">
                        <div class="reply-avatar <?php echo $reply['is_admin'] ? 'admin' : ''; ?>">
                            <?php echo strtoupper(substr($reply['sender_name'], 0, 1)); ?>
                        </div>
                        <div class="reply-content">
                            <div class="reply-header">
                                <div class="reply-sender">
                                    <?php echo htmlspecialchars($reply['sender_name']); ?>
                                    <?php if ($reply['is_admin']): ?>
                                        <span style="font-size: 11px; color: #3b82f6; font-weight: normal;">
                                            <i class="fas fa-shield-alt"></i> Official Response
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="reply-time">
                                    <?php echo timeAgo($reply['created_at']); ?>
                                </div>
                            </div>
                            <div class="reply-message">
                                <?php echo htmlspecialchars($reply['message']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No replies yet. Our support team will respond shortly.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Reply Form -->
            <?php if ($ticket['status'] !== 'closed'): ?>
            <div class="reply-form">
                <h4 style="margin-bottom: 15px;">
                    <i class="fas fa-reply"></i> Add Reply
                </h4>
                <form method="POST" action="">
                    <textarea 
                        name="reply" 
                        placeholder="Type your message here..."
                        required
                    ></textarea>
                    <div style="margin-top: 10px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Reply
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-lock"></i> This ticket is closed. Please create a new ticket if you need further assistance.
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
