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
$errors = [];
$success = '';

// Handle send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    $task_id = intval($_POST['task_id'] ?? 0) ?: null;
    
    if (empty($message)) {
        $errors[] = "Message cannot be empty";
    } elseif (strlen($message) > 2000) {
        $errors[] = "Message is too long (max 2000 characters)";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, task_id, subject, message, created_at)
                VALUES ('user', ?, 'admin', NULL, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $task_id, $subject ?: 'General Inquiry', $message]);
            
            $success = "Message sent successfully! Admin will respond soon.";
            
            // Create notification for admin (we'll handle this differently)
            
        } catch (PDOException $e) {
            $errors[] = "Failed to send message";
            error_log("Message Error: " . $e->getMessage());
        }
    }
}

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $message_id = intval($_POST['message_id'] ?? 0);
    if ($message_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_type = 'user' AND receiver_id = ?");
            $stmt->execute([$message_id, $user_id]);
        } catch (PDOException $e) {}
    }
}

// Get conversation view
$view = $_GET['view'] ?? 'inbox';
$selected_task = intval($_GET['task_id'] ?? 0);

// Get messages
try {
    if ($view === 'inbox') {
        // Messages received from admin
        $stmt = $pdo->prepare("
            SELECT m.*, t.id as task_ref
            FROM messages m
            LEFT JOIN tasks t ON m.task_id = t.id
            WHERE m.receiver_type = 'user' AND m.receiver_id = ?
            ORDER BY m.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id]);
    } else {
        // Messages sent to admin
        $stmt = $pdo->prepare("
            SELECT m.*, t.id as task_ref
            FROM messages m
            LEFT JOIN tasks t ON m.task_id = t.id
            WHERE m.sender_type = 'user' AND m.sender_id = ?
            ORDER BY m.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id]);
    }
    $messages = $stmt->fetchAll();
    
    // Get unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_type = 'user' AND receiver_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = (int)$stmt->fetchColumn();
    
    // Get user's tasks for dropdown
    $stmt = $pdo->prepare("SELECT id, created_at FROM tasks WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$user_id]);
    $user_tasks = $stmt->fetchAll();
    
    // Get conversation threads (grouped by task or general)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(m.task_id, 0) as thread_id,
            COUNT(*) as message_count,
            MAX(m.created_at) as last_message,
            SUM(CASE WHEN m.receiver_type = 'user' AND m.receiver_id = ? AND m.is_read = 0 THEN 1 ELSE 0 END) as unread
        FROM messages m
        WHERE (m.sender_type = 'user' AND m.sender_id = ?) 
           OR (m.receiver_type = 'user' AND m.receiver_id = ?)
        GROUP BY COALESCE(m.task_id, 0)
        ORDER BY last_message DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $threads = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Messages Error: " . $e->getMessage());
    $messages = [];
    $unread_count = 0;
    $user_tasks = [];
    $threads = [];
}

// Get selected thread messages
$thread_messages = [];
if ($selected_task >= 0 && isset($_GET['task_id'])) {
    try {
        $task_condition = $selected_task > 0 ? "m.task_id = ?" : "m.task_id IS NULL";
        $params = $selected_task > 0 ? [$user_id, $user_id, $selected_task] : [$user_id, $user_id];
        
        $stmt = $pdo->prepare("
            SELECT m.*
            FROM messages m
            WHERE ((m.sender_type = 'user' AND m.sender_id = ?) OR (m.receiver_type = 'user' AND m.receiver_id = ?))
            AND $task_condition
            ORDER BY m.created_at ASC
        ");
        $stmt->execute($params);
        $thread_messages = $stmt->fetchAll();
        
        // Mark as read
        $stmt = $pdo->prepare("
            UPDATE messages SET is_read = 1 
            WHERE receiver_type = 'user' AND receiver_id = ? AND $task_condition AND is_read = 0
        ");
        $stmt->execute($selected_task > 0 ? [$user_id, $selected_task] : [$user_id]);
        
    } catch (PDOException $e) {
        error_log("Thread Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - <?php echo APP_NAME; ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px}
        
        .container{max-width:1100px;margin:0 auto}
        
        .back-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#fff;color:#333;text-decoration:none;border-radius:10px;margin-bottom:20px;font-weight:600;font-size:14px;transition:transform 0.2s;box-shadow:0 3px 10px rgba(0,0,0,0.1)}
        .back-btn:hover{transform:translateY(-2px)}
        
        /* Alerts */
        .alert{padding:15px 20px;border-radius:10px;margin-bottom:20px;font-size:14px}
        .alert-success{background:#d4edda;color:#155724}
        .alert-danger{background:#f8d7da;color:#721c24}
        
        /* Messages Layout */
        .messages-layout{display:grid;grid-template-columns:300px 1fr;gap:20px;height:calc(100vh - 140px);min-height:500px}
        
        /* Sidebar */
        .sidebar{background:#fff;border-radius:15px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        .sidebar-header{padding:20px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center}
        .sidebar-header h3{font-size:18px;color:#333;display:flex;align-items:center;gap:8px}
        .sidebar-header .badge{background:#e74c3c;color:#fff;padding:3px 10px;border-radius:10px;font-size:12px}
        .new-msg-btn{padding:8px 15px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer}
        
        .thread-list{flex:1;overflow-y:auto}
        .thread-item{display:flex;align-items:center;padding:15px 20px;border-bottom:1px solid #f5f5f5;cursor:pointer;transition:background 0.2s}
        .thread-item:hover{background:#f8f9fa}
        .thread-item.active{background:#f0f4ff;border-left:4px solid #667eea}
        .thread-item.unread{background:#fffbf0}
        .thread-icon{width:45px;height:45px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;margin-right:12px;flex-shrink:0}
        .thread-icon.general{background:linear-gradient(135deg,#27ae60,#2ecc71)}
        .thread-info{flex:1;min-width:0}
        .thread-title{font-weight:600;color:#333;font-size:14px;margin-bottom:3px;display:flex;align-items:center;gap:8px}
        .thread-title .unread-dot{width:8px;height:8px;background:#e74c3c;border-radius:50%}
        .thread-preview{font-size:12px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .thread-time{font-size:11px;color:#aaa;margin-left:10px;white-space:nowrap}
        
        /* Main Content */
        .main-content{background:#fff;border-radius:15px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 5px 20px rgba(0,0,0,0.1)}
        
        /* Chat Header */
        .chat-header{padding:20px;border-bottom:1px solid #eee;display:flex;align-items:center;gap:15px}
        .chat-avatar{width:50px;height:50px;background:linear-gradient(135deg,#2c3e50,#1a252f);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px}
        .chat-info h4{font-size:16px;color:#333;margin-bottom:3px}
        .chat-info p{font-size:13px;color:#888}
        
        /* Messages Area */
        .messages-area{flex:1;overflow-y:auto;padding:20px;background:#f8f9fa}
        
        .message-item{display:flex;margin-bottom:15px}
        .message-item.sent{justify-content:flex-end}
        .message-item.received{justify-content:flex-start}
        
        .message-bubble{max-width:70%;padding:12px 16px;border-radius:18px;position:relative}
        .message-item.sent .message-bubble{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-bottom-right-radius:5px}
        .message-item.received .message-bubble{background:#fff;color:#333;border-bottom-left-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.05)}
        
        .message-text{font-size:14px;line-height:1.5;white-space:pre-wrap;word-wrap:break-word}
        .message-time{font-size:10px;margin-top:5px;opacity:0.7;text-align:right}
        .message-item.received .message-time{color:#888}
        
        .message-date{text-align:center;margin:20px 0}
        .message-date span{background:#e9ecef;padding:5px 15px;border-radius:15px;font-size:12px;color:#666}
        
        /* Compose Area */
        .compose-area{padding:20px;border-top:1px solid #eee;background:#fff}
        .compose-form{display:flex;gap:10px;align-items:flex-end}
        .compose-input{flex:1}
        .compose-input textarea{width:100%;padding:12px 15px;border:2px solid #eee;border-radius:12px;font-size:14px;resize:none;min-height:50px;max-height:120px;font-family:inherit;transition:border-color 0.2s}
        .compose-input textarea:focus{border-color:#667eea;outline:none}
        .compose-btn{padding:12px 25px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:12px;font-weight:600;cursor:pointer;font-size:14px;display:flex;align-items:center;gap:8px;transition:transform 0.2s}
        .compose-btn:hover{transform:translateY(-2px)}
        
        /* Empty/Welcome State */
        .empty-state{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;text-align:center;color:#888}
        .empty-state .icon{font-size:60px;margin-bottom:20px;opacity:0.5}
        .empty-state h3{color:#666;margin-bottom:10px}
        .empty-state p{font-size:14px;margin-bottom:20px}
        
        /* New Message Modal */
        .modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;justify-content:center;align-items:center;padding:20px}
        .modal.show{display:flex}
        .modal-content{background:#fff;border-radius:15px;padding:30px;max-width:500px;width:100%;max-height:90vh;overflow-y:auto}
        .modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .modal-header h3{font-size:20px;color:#333}
        .modal-close{width:35px;height:35px;border-radius:50%;background:#f5f5f5;border:none;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center}
        
        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-weight:600;margin-bottom:8px;color:#333;font-size:14px}
        .form-control{width:100%;padding:12px 15px;border:2px solid #eee;border-radius:10px;font-size:14px}
        .form-control:focus{border-color:#667eea;outline:none}
        textarea.form-control{min-height:120px;resize:vertical;font-family:inherit}
        
        .btn{padding:12px 25px;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px}
        .btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;width:100%}
        
        /* Responsive */
        @media(max-width:768px){
            .messages-layout{grid-template-columns:1fr;height:auto}
            .sidebar{max-height:300px;margin-bottom:20px}
            .main-content{min-height:400px}
            .message-bubble{max-width:85%}
        }
    </style>
</head>
<body>
<div class="container">
    <a href="<?php echo APP_URL; ?>/user/" class="back-btn">← Back to Dashboard</a>
    
    <!-- Alerts -->
    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger">❌ <?php echo escape($error); ?></div>
    <?php endforeach; ?>
    
    <div class="messages-layout">
        <!-- Sidebar - Thread List -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>
                    💬 Messages
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </h3>
                <button class="new-msg-btn" onclick="showNewMessageModal()">+ New</button>
            </div>
            <div class="thread-list">
                <!-- General Thread -->
                <a href="?task_id=0" class="thread-item <?php echo (isset($_GET['task_id']) && $_GET['task_id'] == '0') ? 'active' : ''; ?>" style="text-decoration:none">
                    <div class="thread-icon general">💬</div>
                    <div class="thread-info">
                        <div class="thread-title">
                            General Support
                            <?php 
                            $general_unread = 0;
                            foreach ($threads as $t) {
                                if ($t['thread_id'] == 0) {
                                    $general_unread = $t['unread'];
                                    break;
                                }
                            }
                            if ($general_unread > 0): ?>
                                <span class="unread-dot"></span>
                            <?php endif; ?>
                        </div>
                        <div class="thread-preview">General inquiries & support</div>
                    </div>
                </a>
                
                <!-- Task Threads -->
                <?php foreach ($threads as $thread): 
                    if ($thread['thread_id'] == 0) continue;
                ?>
                    <a href="?task_id=<?php echo $thread['thread_id']; ?>" class="thread-item <?php echo $selected_task == $thread['thread_id'] ? 'active' : ''; ?> <?php echo $thread['unread'] > 0 ? 'unread' : ''; ?>" style="text-decoration:none">
                        <div class="thread-icon">📋</div>
                        <div class="thread-info">
                            <div class="thread-title">
                                Task #<?php echo $thread['thread_id']; ?>
                                <?php if ($thread['unread'] > 0): ?>
                                    <span class="unread-dot"></span>
                                <?php endif; ?>
                            </div>
                            <div class="thread-preview"><?php echo $thread['message_count']; ?> messages</div>
                        </div>
                        <div class="thread-time"><?php echo getTimeAgo($thread['last_message']); ?></div>
                    </a>
                <?php endforeach; ?>
                
                <?php if (empty($threads)): ?>
                    <div style="padding:30px;text-align:center;color:#888">
                        <p>No conversations yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main Content - Chat -->
        <div class="main-content">
            <?php if (isset($_GET['task_id'])): ?>
                <!-- Chat Header -->
                <div class="chat-header">
                    <div class="chat-avatar">👨‍💼</div>
                    <div class="chat-info">
                        <h4><?php echo $selected_task > 0 ? "Task #$selected_task Support" : "General Support"; ?></h4>
                        <p>Admin • Usually responds within 24 hours</p>
                    </div>
                </div>
                
                <!-- Messages -->
                <div class="messages-area" id="messagesArea">
                    <?php if (empty($thread_messages)): ?>
                        <div style="text-align:center;padding:40px;color:#888">
                            <p>No messages yet. Start the conversation!</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $last_date = '';
                        foreach ($thread_messages as $msg): 
                            $msg_date = date('Y-m-d', strtotime($msg['created_at']));
                            if ($msg_date !== $last_date):
                                $last_date = $msg_date;
                        ?>
                            <div class="message-date">
                                <span><?php echo date('d M Y', strtotime($msg['created_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                            <div class="message-item <?php echo $msg['sender_type'] === 'user' ? 'sent' : 'received'; ?>">
                                <div class="message-bubble">
                                    <div class="message-text"><?php echo nl2br(escape($msg['message'])); ?></div>
                                    <div class="message-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Compose -->
                <div class="compose-area">
                    <form method="POST" class="compose-form">
                        <input type="hidden" name="task_id" value="<?php echo $selected_task ?: ''; ?>">
                        <input type="hidden" name="subject" value="<?php echo $selected_task > 0 ? "Task #$selected_task" : 'General'; ?>">
                        <div class="compose-input">
                            <textarea name="message" placeholder="Type your message..." required maxlength="2000" rows="1" onkeydown="handleEnter(event)"></textarea>
                        </div>
                        <button type="submit" name="send_message" class="compose-btn">
                            Send ➤
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Welcome State -->
                <div class="empty-state">
                    <div class="icon">💬</div>
                    <h3>Welcome to Messages</h3>
                    <p>Select a conversation from the sidebar or start a new message to admin.</p>
                    <button class="btn btn-primary" onclick="showNewMessageModal()" style="width:auto;padding:12px 30px">+ New Message</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div class="modal" id="newMessageModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>✉️ New Message</h3>
            <button class="modal-close" onclick="hideNewMessageModal()">×</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Related Task (Optional)</label>
                <select name="task_id" class="form-control">
                    <option value="">General Inquiry</option>
                    <?php foreach ($user_tasks as $task): ?>
                        <option value="<?php echo $task['id']; ?>">Task #<?php echo $task['id']; ?> - <?php echo date('d M Y', strtotime($task['created_at'])); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" class="form-control" placeholder="What is this about?" maxlength="100">
            </div>
            <div class="form-group">
                <label>Message <span style="color:#e74c3c">*</span></label>
                <textarea name="message" class="form-control" placeholder="Type your message here..." required maxlength="2000"></textarea>
                <div style="font-size:12px;color:#888;margin-top:5px">Max 2000 characters</div>
            </div>
            <button type="submit" name="send_message" class="btn btn-primary">📤 Send Message</button>
        </form>
    </div>
</div>

<?php
function getTimeAgo($datetime) {
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
// Scroll to bottom of messages
const messagesArea = document.getElementById('messagesArea');
if (messagesArea) {
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

// Auto-resize textarea
document.querySelectorAll('textarea').forEach(textarea => {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
});

// Handle Enter key to send (Shift+Enter for new line)
function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        e.target.closest('form').submit();
    }
}

// Modal functions
function showNewMessageModal() {
    document.getElementById('newMessageModal').classList.add('show');
}

function hideNewMessageModal() {
    document.getElementById('newMessageModal').classList.remove('show');
}

// Close modal on outside click
document.getElementById('newMessageModal').addEventListener('click', function(e) {
    if (e.target === this) hideNewMessageModal();
});

// Auto-refresh messages every 10 seconds if in a thread
<?php if (isset($_GET['task_id'])): ?>
setInterval(() => {
    // Could implement AJAX refresh here
}, 10000);
<?php endif; ?>
</script>
</body>
</html>
