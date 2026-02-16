<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/chat-functions.php';

if (!isset($_SESSION['admin_name'])) {
    header('Location: ' . ADMIN_URL);
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$admin_name = $_SESSION['admin_name'];

// Get filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'open';

// Get conversations
try {
    $conversations = getAllConversations($pdo, $status_filter, 100);
    $stats = getChatStatistics($pdo, null, true);
} catch (PDOException $e) {
    $conversations = [];
    $stats = ['total_conversations' => 0, 'open_conversations' => 0, 'pending_conversations' => 0, 'unread_messages' => 0];
}

// Set current page for sidebar
$current_page = 'support-chat';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Chat Dashboard - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
/* Admin Layout */
.admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}

/* Sidebar styles */
.sidebar{background:linear-gradient(180deg,#2c3e50 0%,#1a252f 100%);color:#fff;padding:0;position:sticky;top:0;height:100vh;overflow-y:auto}
.sidebar-header{padding:25px 20px;border-bottom:1px solid rgba(255,255,255,0.1)}
.sidebar-header h2{font-size:20px;display:flex;align-items:center;gap:10px}
.sidebar-menu{list-style:none;padding:15px 0}
.sidebar-menu li{margin-bottom:5px}
.sidebar-menu a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#94a3b8;text-decoration:none;transition:all 0.2s;border-left:3px solid transparent}
.sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(255,255,255,0.05);color:#fff;border-left-color:#667eea}
.sidebar-menu .badge{background:#e74c3c;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto}
.sidebar-divider{height:1px;background:rgba(255,255,255,0.1);margin:15px 20px}
.menu-section-label{padding:8px 20px;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px}
.sidebar-menu a.logout{color:#e74c3c}

/* Main Content */
.main-content{padding:25px;overflow-x:hidden}

.conversation-item {
    cursor: pointer;
    transition: background-color 0.2s;
}
.conversation-item:hover {
    background-color: #f8f9fa;
}
.conversation-item.unread {
    background-color: #e3f2fd;
    font-weight: bold;
}
</style>
</head>
<body>

<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>
    
    <div class="main-content">
            <h2 class="mb-4"><i class="bi bi-chat-dots-fill"></i> Support Chat Dashboard</h2>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h3><?php echo $stats['total_conversations']; ?></h3>
                            <p class="mb-0">Total Conversations</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h3><?php echo $stats['open_conversations']; ?></h3>
                            <p class="mb-0">Open Conversations</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h3><?php echo $stats['pending_conversations']; ?></h3>
                            <p class="mb-0">Pending Response</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h3><?php echo $stats['unread_messages']; ?></h3>
                            <p class="mb-0">Unread Messages</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="btn-group w-100" role="group">
                        <a href="?status=open" class="btn btn-<?php echo $status_filter == 'open' ? 'primary' : 'outline-primary'; ?>">
                            <i class="bi bi-chat"></i> Open
                        </a>
                        <a href="?status=pending" class="btn btn-<?php echo $status_filter == 'pending' ? 'primary' : 'outline-primary'; ?>">
                            <i class="bi bi-hourglass"></i> Pending
                        </a>
                        <a href="?status=closed" class="btn btn-<?php echo $status_filter == 'closed' ? 'primary' : 'outline-primary'; ?>">
                            <i class="bi bi-check-circle"></i> Closed
                        </a>
                        <a href="?status=" class="btn btn-<?php echo $status_filter == '' ? 'primary' : 'outline-primary'; ?>">
                            <i class="bi bi-list"></i> All
                        </a>
                    </div>
                </div>
            </div>

            <!-- Conversations List -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-inbox"></i> Conversations</h5>
                </div>
                <div class="card-body">
                    <?php if (count($conversations) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($conversations as $conv): ?>
                        <a href="javascript:void(0)" 
                           onclick="openConversation(<?php echo $conv['id']; ?>)"
                           class="list-group-item list-group-item-action conversation-item <?php echo $conv['unread_count'] > 0 ? 'unread' : ''; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="bi bi-person-circle"></i>
                                        <?php echo htmlspecialchars($conv['user_name']); ?>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="badge bg-danger"><?php echo $conv['unread_count']; ?> new</span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="mb-1 text-muted small">
                                        <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 80)); ?>...
                                    </p>
                                    <small class="text-muted"><?php echo htmlspecialchars($conv['user_email']); ?></small>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">
                                        <?php echo $conv['last_message_at'] ? date('M d, H:i', strtotime($conv['last_message_at'])) : '-'; ?>
                                    </small>
                                    <br>
                                    <span class="badge bg-<?php 
                                        echo $conv['status'] == 'open' ? 'success' : 
                                            ($conv['status'] == 'pending' ? 'warning' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($conv['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                        <h5 class="mt-3">No Conversations</h5>
                        <p class="text-muted">There are no <?php echo $status_filter; ?> conversations at the moment</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Conversation Modal -->
<div class="modal fade" id="conversationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="conversationModalTitle">Chat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="conversationModalBody" style="height: 500px; overflow-y: auto;">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <form id="adminReplyForm" class="w-100">
                    <input type="hidden" id="modalConversationId" value="">
                    <div class="input-group">
                        <input type="text" class="form-control" id="adminMessageInput" 
                               placeholder="Type your reply..." required>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let currentConversationId = null;
let refreshInterval = null;

function openConversation(conversationId) {
    currentConversationId = conversationId;
    document.getElementById('modalConversationId').value = conversationId;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('conversationModal'));
    modal.show();
    
    // Load messages
    loadConversationMessages(conversationId);
    
    // Auto-refresh
    clearInterval(refreshInterval);
    refreshInterval = setInterval(() => loadConversationMessages(conversationId), 5000);
}

function loadConversationMessages(conversationId) {
    fetch(`../api/chat.php?action=get_messages&conversation_id=${conversationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayMessages(data.messages);
            }
        });
}

function displayMessages(messages) {
    const container = document.getElementById('conversationModalBody');
    
    if (!messages || messages.length === 0) {
        container.innerHTML = '<div class="text-center py-5 text-muted">No messages yet</div>';
        return;
    }
    
    let html = '';
    messages.forEach(msg => {
        const isAdmin = msg.sender_type === 'admin';
        const alignClass = isAdmin ? 'text-end' : 'text-start';
        const bgClass = isAdmin ? 'bg-primary text-white' : 'bg-light';
        
        html += `
            <div class="${alignClass} mb-3">
                <div class="d-inline-block ${bgClass} p-2 rounded" style="max-width: 70%;">
                    <div>${msg.message.replace(/\n/g, '<br>')}</div>
                    <small class="opacity-75">${formatDate(msg.created_at)}</small>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Handle reply form
document.getElementById('adminReplyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const conversationId = document.getElementById('modalConversationId').value;
    const message = document.getElementById('adminMessageInput').value;
    
    const formData = new FormData();
    formData.append('send_message', '1');
    formData.append('conversation_id', conversationId);
    formData.append('message', message);
    
    fetch('../api/chat.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('adminMessageInput').value = '';
            loadConversationMessages(conversationId);
        } else {
            alert('Error sending message');
        }
    });
});

// Clean up interval on modal close
document.getElementById('conversationModal').addEventListener('hidden.bs.modal', function() {
    clearInterval(refreshInterval);
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
