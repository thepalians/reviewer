<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_name = $_SESSION['admin_name'];
$errors = [];
$success = '';

// Handle send reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $task_id = intval($_POST['task_id'] ?? 0) ?: null;
    $message = sanitizeInput($_POST['message'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? 'Admin Reply');
    
    if (empty($message)) {
        $errors[] = "Message cannot be empty";
    } elseif ($user_id <= 0) {
        $errors[] = "Invalid user";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, task_id, subject, message, created_at)
                VALUES ('admin', NULL, 'user', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $task_id, $subject, $message]);
            
            // Create notification for user
            createNotification($user_id, 'info', '💬 New Message from Admin', substr($message, 0, 100) . '...');
            
            $success = "Reply sent successfully!";
            
        } catch (PDOException $e) {
            $errors[] = "Failed to send reply";
            error_log("Message Error: " . $e->getMessage());
        }
    }
}

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $message_id = intval($_POST['message_id'] ?? 0);
    if ($message_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_type = 'admin'");
            $stmt->execute([$message_id]);
        } catch (PDOException $e) {}
    }
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_type = 'admin' AND is_read = 0");
        $stmt->execute();
        $success = "All messages marked as read";
    } catch (PDOException $e) {}
}

// Handle delete conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_conversation'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    $task_id = $_POST['task_id'] ?? null;
    
    if ($user_id > 0) {
        try {
            if ($task_id === '0' || $task_id === null) {
                $stmt = $pdo->prepare("DELETE FROM messages WHERE ((sender_type = 'user' AND sender_id = ?) OR (receiver_type = 'user' AND receiver_id = ?)) AND task_id IS NULL");
                $stmt->execute([$user_id, $user_id]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM messages WHERE ((sender_type = 'user' AND sender_id = ?) OR (receiver_type = 'user' AND receiver_id = ?)) AND task_id = ?");
                $stmt->execute([$user_id, $user_id, intval($task_id)]);
            }
            $success = "Conversation deleted";
        } catch (PDOException $e) {}
    }
}

// Filters
$filter = $_GET['filter'] ?? 'unread';
$selected_user = intval($_GET['user_id'] ?? 0);
$selected_task = isset($_GET['task_id']) ? $_GET['task_id'] : null;

// Get conversations (grouped by user and task)
try {
    $where_filter = "";
    if ($filter === 'unread') {
        $where_filter = "HAVING unread_count > 0";
    }
    
  $stmt = $pdo->query("
        SELECT 
            u.id as user_id,
            u.name as user_name,
            u.email as user_email,
            u.mobile as user_mobile,
            COALESCE(m.task_id, 0) as task_id,
            COUNT(m.id) as message_count,
            SUM(CASE WHEN m.receiver_type = 'admin' AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_count,
            MAX(m.created_at) as last_message_time,
            MAX(m.message) as last_message
        FROM messages m
        JOIN users u ON m.sender_id = u.id AND m.sender_type = 'user'
        GROUP BY u.id, u.name, u.email, u.mobile, COALESCE(m.task_id, 0)
        $where_filter
        ORDER BY unread_count DESC, last_message_time DESC
    ");
    $conversations = $stmt->fetchAll();
    
    // Get total unread count
    $stmt = $pdo->query("SELECT COUNT(*) FROM messages WHERE receiver_type = 'admin' AND is_read = 0");
    $total_unread = (int)$stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Messages Error: " . $e->getMessage());
    $conversations = [];
    $total_unread = 0;
}

// Get selected conversation messages
$conversation_messages = [];
$selected_user_data = null;
if ($selected_user > 0) {
    try {
        // Get user data
        $stmt = $pdo->prepare("SELECT id, name, email, mobile, created_at FROM users WHERE id = ?");
        $stmt->execute([$selected_user]);
        $selected_user_data = $stmt->fetch();
        
        // Get messages
        $task_condition = ($selected_task === '0' || $selected_task === null) ? "m.task_id IS NULL" : "m.task_id = ?";
        $params = ($selected_task === '0' || $selected_task === null) ? [$selected_user, $selected_user] : [$selected_user, $selected_user, intval($selected_task)];
        
        $stmt = $pdo->prepare("
            SELECT m.*
            FROM messages m
            WHERE ((m.sender_type = 'user' AND m.sender_id = ?) OR (m.receiver_type = 'user' AND m.receiver_id = ?))
            AND $task_condition
            ORDER BY m.created_at ASC
        ");
        $stmt->execute($params);
        $conversation_messages = $stmt->fetchAll();
        
        // Mark messages as read
        $stmt = $pdo->prepare("
            UPDATE messages SET is_read = 1 
            WHERE receiver_type = 'admin' AND sender_type = 'user' AND sender_id = ? 
            AND $task_condition AND is_read = 0
        ");
        $stmt->execute($params);
        
    } catch (PDOException $e) {
        error_log("Conversation Error: " . $e->getMessage());
    }
}

// Get all users for new message dropdown
try {
    $stmt = $pdo->query("SELECT id, name, email FROM users WHERE user_type = 'user' AND status = 'active' ORDER BY name ASC");
    $all_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_users = [];
}

// Get sidebar badge counts
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status = 'pending'");
    $pending_withdrawals = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_unanswered WHERE is_resolved = 0");
    $unanswered_questions = (int)$stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE task_status = 'pending' AND refund_requested = 1");
    $pending_tasks = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $pending_withdrawals = $unanswered_questions = $pending_tasks = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Admin - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f6fa;min-height:100vh}
        
        .admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
        
        /* Sidebar */
        .sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
        .sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
        .sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
        .sidebar-menu{list-style:none;padding:15px 0}
        .sidebar-menu li{margin-bottom:5px}
        .sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
        .sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
        .sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
        .sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
        .sidebar-menu a.logout{color:#e74c3c}
        
        .main-content{padding:0;display:flex;flex-direction:column;height:100vh;overflow:hidden}
        
        /* Messages Layout */
        .messages-layout{display:grid;grid-template-columns:350px 1fr;flex:1;overflow:hidden}
        
        /* Conversations List */
        .conversations-panel{background:#fff;border-right:1px solid #e2e8f0;display:flex;flex-direction:column;overflow:hidden}
        .conversations-header{padding:20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center}
        .conversations-header h3{font-size:18px;color:#1e293b;display:flex;align-items:center;gap:10px}
        .conversations-header .badge{background:#ef4444;color:#fff;padding:3px 10px;border-radius:10px;font-size:12px}
        .header-actions{display:flex;gap:8px}
        .header-btn{padding:8px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all 0.2s}
        .header-btn.primary{background:#667eea;color:#fff}
        .header-btn.secondary{background:#f1f5f9;color:#64748b}
        
        .conversations-filters{padding:15px 20px;border-bottom:1px solid #e2e8f0;display:flex;gap:8px}
        .filter-btn{padding:8px 15px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid #e2e8f0;background:#fff;color:#64748b;transition:all 0.2s}
        .filter-btn:hover{border-color:#667eea;color:#667eea}
        .filter-btn.active{background:#667eea;color:#fff;border-color:#667eea}
        
        .conversations-list{flex:1;overflow-y:auto}
        .conversation-item{display:flex;align-items:flex-start;padding:15px 20px;border-bottom:1px solid #f1f5f9;cursor:pointer;transition:background 0.2s;text-decoration:none}
        .conversation-item:hover{background:#f8fafc}
        .conversation-item.active{background:#eff6ff;border-left:3px solid #667eea}
        .conversation-item.unread{background:#fffbeb}
        .conversation-item.unread .conv-name{font-weight:700}
        
        .conv-avatar{width:45px;height:45px;border-radius:12px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:16px;margin-right:12px;flex-shrink:0}
        .conv-content{flex:1;min-width:0}
        .conv-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
        .conv-name{font-weight:600;color:#1e293b;font-size:14px}
        .conv-time{font-size:11px;color:#94a3b8}
        .conv-preview{font-size:13px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .conv-meta{display:flex;align-items:center;gap:8px;margin-top:5px}
        .conv-task{font-size:11px;background:#f1f5f9;color:#64748b;padding:2px 8px;border-radius:10px}
        .conv-unread{width:20px;height:20px;background:#ef4444;color:#fff;border-radius:50%;font-size:11px;display:flex;align-items:center;justify-content:center;font-weight:600}
        
        /* Chat Panel */
        .chat-panel{display:flex;flex-direction:column;background:#f8fafc;overflow:hidden}
        
        .chat-header{padding:20px;background:#fff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:15px}
        .chat-avatar{width:50px;height:50px;border-radius:12px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:18px}
        .chat-info{flex:1}
        .chat-name{font-size:16px;font-weight:600;color:#1e293b}
        .chat-details{font-size:13px;color:#64748b;margin-top:2px}
        .chat-actions{display:flex;gap:8px}
        .chat-action{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#f1f5f9;color:#64748b;cursor:pointer;border:none;font-size:16px;transition:all 0.2s}
        .chat-action:hover{background:#e2e8f0}
        .chat-action.danger:hover{background:#fee2e2;color:#dc2626}
        
        .chat-messages{flex:1;overflow-y:auto;padding:20px}
        
        .message-date{text-align:center;margin:20px 0}
        .message-date span{background:#e2e8f0;padding:5px 15px;border-radius:15px;font-size:12px;color:#64748b}
        
        .message-item{display:flex;margin-bottom:15px}
        .message-item.sent{justify-content:flex-end}
        .message-item.received{justify-content:flex-start}
        
        .message-bubble{max-width:70%;position:relative}
        .message-content{padding:12px 16px;border-radius:18px;font-size:14px;line-height:1.5}
        .message-item.sent .message-content{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-bottom-right-radius:5px}
        .message-item.received .message-content{background:#fff;color:#1e293b;border-bottom-left-radius:5px;box-shadow:0 1px 3px rgba(0,0,0,0.1)}
        .message-time{font-size:11px;color:#94a3b8;margin-top:5px;text-align:right}
        .message-item.received .message-time{text-align:left}
        
        .chat-compose{padding:20px;background:#fff;border-top:1px solid #e2e8f0}
        .compose-form{display:flex;gap:12px;align-items:flex-end}
        .compose-input{flex:1}
        .compose-input textarea{width:100%;padding:12px 15px;border:1px solid #e2e8f0;border-radius:12px;font-size:14px;resize:none;min-height:50px;max-height:120px;font-family:inherit}
        .compose-input textarea:focus{border-color:#667eea;outline:none}
        .compose-btn{padding:12px 25px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:12px;font-weight:600;cursor:pointer;font-size:14px;display:flex;align-items:center;gap:8px;transition:all 0.2s}
        .compose-btn:hover{transform:translateY(-2px);box-shadow:0 5px 15px rgba(102,126,234,0.3)}
        
        /* Empty State */
        .empty-state{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;text-align:center;color:#94a3b8}
        .empty-state .icon{font-size:60px;margin-bottom:20px;opacity:0.5}
        .empty-state h3{color:#64748b;margin-bottom:10px}
        .empty-state p{font-size:14px;margin-bottom:20px}
        
        /* New Message Modal */
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;padding:20px}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:15px;padding:30px;max-width:500px;width:100%;max-height:90vh;overflow-y:auto}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .modal-title{font-size:20px;font-weight:600;color:#1e293b}
        .modal-close{width:35px;height:35px;border-radius:50%;background:#f1f5f9;border:none;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center}
        
        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#1e293b;font-size:14px}
        .form-control{width:100%;padding:12px 15px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px}
        .form-control:focus{border-color:#667eea;outline:none}
        select.form-control{cursor:pointer}
        textarea.form-control{min-height:120px;resize:vertical;font-family:inherit}
        
        .btn{padding:12px 25px;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;width:100%}
        
        /* Alerts */
        .alert{padding:12px 20px;border-radius:10px;margin:15px 20px;font-size:14px}
        .alert-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
        .alert-danger{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        
        /* Responsive */
        @media(max-width:1200px){
            .messages-layout{grid-template-columns:300px 1fr}
        }
        @media(max-width:992px){
            .admin-layout{grid-template-columns:1fr}
            .sidebar{display:none}
            .messages-layout{grid-template-columns:1fr}
            .conversations-panel{display:<?php echo $selected_user > 0 ? 'none' : 'flex'; ?>}
            .chat-panel{display:<?php echo $selected_user > 0 ? 'flex' : 'none'; ?>}
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <!-- Sidebar -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
        <?php endforeach; ?>
        
        <div class="messages-layout">
            <!-- Conversations Panel -->
            <div class="conversations-panel">
                <div class="conversations-header">
                    <h3>
                        💬 Messages
                        <?php if ($total_unread > 0): ?>
                            <span class="badge"><?php echo $total_unread; ?></span>
                        <?php endif; ?>
                    </h3>
                    <div class="header-actions">
                        <button class="header-btn primary" onclick="showNewMessageModal()">+ New</button>
                        <?php if ($total_unread > 0): ?>
                            <form method="POST" style="display:inline">
                                <button type="submit" name="mark_all_read" class="header-btn secondary">✓ Read All</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="conversations-filters">
                    <a href="?filter=unread" class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>">Unread</a>
                    <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                </div>
                
                <div class="conversations-list">
                    <?php if (empty($conversations)): ?>
                        <div class="empty-state" style="padding:40px">
                            <div class="icon">💬</div>
                            <h3>No Messages</h3>
                            <p><?php echo $filter === 'unread' ? 'No unread messages' : 'No conversations yet'; ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): 
                            $is_active = $selected_user == $conv['user_id'] && (string)$selected_task === (string)$conv['task_id'];
                        ?>
                            <a href="?user_id=<?php echo $conv['user_id']; ?>&task_id=<?php echo $conv['task_id']; ?>&filter=<?php echo $filter; ?>" 
                               class="conversation-item <?php echo $is_active ? 'active' : ''; ?> <?php echo $conv['unread_count'] > 0 ? 'unread' : ''; ?>">
                                <div class="conv-avatar"><?php echo strtoupper(substr($conv['user_name'], 0, 1)); ?></div>
                                <div class="conv-content">
                                    <div class="conv-header">
                                        <span class="conv-name"><?php echo escape($conv['user_name']); ?></span>
                                        <span class="conv-time"><?php echo getTimeAgo($conv['last_message_time']); ?></span>
                                    </div>
                                    <div class="conv-preview"><?php echo escape(substr($conv['last_message'] ?? '', 0, 50)); ?>...</div>
                                    <div class="conv-meta">
                                        <?php if ($conv['task_id'] > 0): ?>
                                            <span class="conv-task">Task #<?php echo $conv['task_id']; ?></span>
                                        <?php else: ?>
                                            <span class="conv-task">General</span>
                                        <?php endif; ?>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="conv-unread"><?php echo $conv['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Panel -->
            <div class="chat-panel">
                <?php if ($selected_user > 0 && $selected_user_data): ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <a href="?filter=<?php echo $filter; ?>" class="chat-action" style="display:none" id="backBtn">←</a>
                        <div class="chat-avatar"><?php echo strtoupper(substr($selected_user_data['name'], 0, 1)); ?></div>
                        <div class="chat-info">
                            <div class="chat-name"><?php echo escape($selected_user_data['name']); ?></div>
                            <div class="chat-details">
                                <?php echo escape($selected_user_data['email']); ?> • <?php echo escape($selected_user_data['mobile']); ?>
                                <?php if ($selected_task > 0): ?> • Task #<?php echo $selected_task; ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="chat-actions">
                            <a href="<?php echo ADMIN_URL; ?>/reviewers.php?search=<?php echo urlencode($selected_user_data['email']); ?>" class="chat-action" title="View User">👤</a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this conversation?')">
                                <input type="hidden" name="user_id" value="<?php echo $selected_user; ?>">
                                <input type="hidden" name="task_id" value="<?php echo $selected_task; ?>">
                                <button type="submit" name="delete_conversation" class="chat-action danger" title="Delete">🗑️</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Chat Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <?php if (empty($conversation_messages)): ?>
                            <div class="empty-state">
                                <p>No messages in this conversation yet.</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $last_date = '';
                            foreach ($conversation_messages as $msg): 
                                $msg_date = date('Y-m-d', strtotime($msg['created_at']));
                                if ($msg_date !== $last_date):
                                    $last_date = $msg_date;
                            ?>
                                <div class="message-date">
                                    <span><?php echo date('d M Y', strtotime($msg['created_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                                <div class="message-item <?php echo $msg['sender_type'] === 'admin' ? 'sent' : 'received'; ?>">
                                    <div class="message-bubble">
                                        <div class="message-content"><?php echo nl2br(escape($msg['message'])); ?></div>
                                        <div class="message-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Compose -->
                    <div class="chat-compose">
                        <form method="POST" class="compose-form" id="replyForm">
                            <input type="hidden" name="user_id" value="<?php echo $selected_user; ?>">
                            <input type="hidden" name="task_id" value="<?php echo $selected_task ?: ''; ?>">
                            <input type="hidden" name="subject" value="<?php echo $selected_task > 0 ? "Task #$selected_task" : 'Support'; ?>">
                            <div class="compose-input">
                                <textarea name="message" placeholder="Type your reply..." required maxlength="2000" rows="1" id="replyInput"></textarea>
                            </div>
                            <button type="submit" name="send_reply" class="compose-btn">Send ➤</button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- No Conversation Selected -->
                    <div class="empty-state">
                        <div class="icon">💬</div>
                        <h3>Select a Conversation</h3>
                        <p>Choose a conversation from the list or start a new message.</p>
                        <button class="btn btn-primary" onclick="showNewMessageModal()" style="width:auto;padding:12px 30px;margin-top:10px">+ New Message</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div class="modal" id="newMessageModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">✉️ New Message</div>
            <button class="modal-close" onclick="hideModal()">×</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Select User <span style="color:#ef4444">*</span></label>
                <select name="user_id" class="form-control" required>
                    <option value="">Choose a user...</option>
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo escape($user['name']); ?> (<?php echo escape($user['email']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" class="form-control" placeholder="Message subject" value="Admin Message">
            </div>
            <div class="form-group">
                <label>Message <span style="color:#ef4444">*</span></label>
                <textarea name="message" class="form-control" placeholder="Type your message..." required maxlength="2000"></textarea>
            </div>
            <button type="submit" name="send_reply" class="btn btn-primary">📤 Send Message</button>
        </form>
    </div>
</div>

<?php
function getTimeAgo($datetime) {
    if (!$datetime) return '';
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'now';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'd';
    return date('d/m', $time);
}
?>

<script>
// Scroll to bottom
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Auto-resize textarea
document.querySelectorAll('textarea').forEach(textarea => {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
});

// Enter to send (Shift+Enter for new line)
document.getElementById('replyInput')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('replyForm').submit();
    }
});

// Modal functions
function showNewMessageModal() {
    document.getElementById('newMessageModal').classList.add('show');
}

function hideModal() {
    document.getElementById('newMessageModal').classList.remove('show');
}

document.getElementById('newMessageModal')?.addEventListener('click', function(e) {
    if (e.target === this) hideModal();
});

// Show back button on mobile
if (window.innerWidth <= 992) {
    const backBtn = document.getElementById('backBtn');
    if (backBtn) backBtn.style.display = 'flex';
}

// Auto-refresh every 30 seconds
setInterval(() => {
    <?php if ($selected_user > 0): ?>
    // Could implement AJAX refresh here
    <?php endif; ?>
}, 30000);
</script>
</body>
</html>
