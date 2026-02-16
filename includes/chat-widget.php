<?php
/**
 * Floating Chat Widget
 * Include this file in footer or header to show chat widget on all pages
 */

if (!defined('DB_HOST')) {
    return; // Don't show widget if not in proper context
}

// Only show for logged-in users
if (!isLoggedIn()) {
    return;
}

require_once __DIR__ . '/chat-functions.php';

$widget_user_id = $_SESSION['user_id'];
$widget_is_admin = isAdmin();

// Get unread count
$unread_count = getUnreadMessageCount($db, $widget_user_id, $widget_is_admin ? 'admin' : 'user');
?>

<style>
#chatWidget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
}

#chatWidgetButton {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: transform 0.3s;
    position: relative;
}

#chatWidgetButton:hover {
    transform: scale(1.1);
}

#chatWidgetButton .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: #ef4444;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: bold;
}

#chatWidgetWindow {
    position: fixed;
    bottom: 90px;
    right: 20px;
    width: 350px;
    height: 500px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    display: none;
    flex-direction: column;
    z-index: 1001;
}

#chatWidgetWindow.show {
    display: flex;
}

#chatWidgetHeader {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    border-radius: 10px 10px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#chatWidgetMessages {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    background-color: #f8f9fa;
}

#chatWidgetInput {
    padding: 15px;
    background-color: white;
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 10px 10px;
}

.widget-message {
    margin-bottom: 10px;
    clear: both;
}

.widget-message.user {
    text-align: right;
}

.widget-message .message-bubble {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 15px;
    max-width: 80%;
    word-wrap: break-word;
}

.widget-message.user .message-bubble {
    background-color: #007bff;
    color: white;
}

.widget-message.admin .message-bubble {
    background-color: white;
    border: 1px solid #dee2e6;
}

.widget-message-time {
    font-size: 0.7rem;
    opacity: 0.7;
    margin-top: 3px;
}

@media (max-width: 768px) {
    #chatWidgetWindow {
        width: calc(100% - 40px);
        right: 20px;
        left: 20px;
    }
}
</style>

<div id="chatWidget">
    <!-- Chat Button -->
    <button id="chatWidgetButton" onclick="toggleChatWidget()" title="Chat with Support">
        <i class="bi bi-chat-dots-fill"></i>
        <?php if ($unread_count > 0): ?>
        <span class="badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
        <?php endif; ?>
    </button>

    <!-- Chat Window -->
    <div id="chatWidgetWindow">
        <div id="chatWidgetHeader">
            <div>
                <strong><?php echo $widget_is_admin ? 'Support Chat' : 'Help & Support'; ?></strong>
                <div><small>We're here to help!</small></div>
            </div>
            <button onclick="toggleChatWidget()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">
                <i class="bi bi-x"></i>
            </button>
        </div>

        <div id="chatWidgetMessages">
            <div class="text-center text-muted">
                <i class="bi bi-chat-dots" style="font-size: 2rem;"></i>
                <p>Loading messages...</p>
            </div>
        </div>

        <div id="chatWidgetInput">
            <form id="widgetChatForm" onsubmit="sendWidgetMessage(event)">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="widgetMessageInput" 
                           placeholder="Type a message..." required>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let widgetConversationId = null;
let widgetRefreshInterval = null;

function toggleChatWidget() {
    const window = document.getElementById('chatWidgetWindow');
    window.classList.toggle('show');
    
    if (window.classList.contains('show')) {
        initChatWidget();
    } else {
        if (widgetRefreshInterval) {
            clearInterval(widgetRefreshInterval);
        }
    }
}

function initChatWidget() {
    <?php if (!$widget_is_admin): ?>
    // For users, get or create conversation
    fetch('/reviewer/api/chat.php?action=get_conversations')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.conversations.length > 0) {
                widgetConversationId = data.conversations[0].id;
                loadWidgetMessages();
                
                // Refresh messages every 3 seconds
                widgetRefreshInterval = setInterval(loadWidgetMessages, 3000);
            } else {
                // Create new conversation
                createWidgetConversation();
            }
        });
    <?php else: ?>
    // For admins, show conversation list
    loadAdminConversations();
    <?php endif; ?>
}

function createWidgetConversation() {
    // Send initial message to create conversation
    const formData = new FormData();
    formData.append('send_message', '1');
    formData.append('conversation_id', '0');
    formData.append('message', 'Hello, I need help');
    
    fetch('/reviewer/api/chat.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            initChatWidget(); // Reload
        }
    });
}

function loadWidgetMessages() {
    if (!widgetConversationId) return;
    
    fetch(`/reviewer/api/chat.php?action=get_messages&conversation_id=${widgetConversationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayWidgetMessages(data.messages);
            }
        });
}

function displayWidgetMessages(messages) {
    const container = document.getElementById('chatWidgetMessages');
    
    if (!messages || messages.length === 0) {
        container.innerHTML = '<div class="text-center text-muted"><i class="bi bi-chat-dots" style="font-size: 2rem;"></i><p>No messages yet. Start chatting!</p></div>';
        return;
    }
    
    let html = '';
    messages.forEach(msg => {
        const senderClass = msg.sender_type === 'user' ? 'user' : 'admin';
        html += `
            <div class="widget-message ${senderClass}">
                <div class="message-bubble">
                    ${msg.message.replace(/\n/g, '<br>')}
                    <div class="widget-message-time">${formatWidgetTime(msg.created_at)}</div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    container.scrollTop = container.scrollHeight;
}

function sendWidgetMessage(e) {
    e.preventDefault();
    
    const input = document.getElementById('widgetMessageInput');
    const message = input.value.trim();
    
    if (!message || !widgetConversationId) return;
    
    const formData = new FormData();
    formData.append('send_message', '1');
    formData.append('conversation_id', widgetConversationId);
    formData.append('message', message);
    
    fetch('/reviewer/api/chat.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            loadWidgetMessages();
        }
    });
}

function formatWidgetTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`;
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// Update unread count badge
function updateUnreadBadge() {
    fetch('/reviewer/api/chat.php?action=get_unread_count')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const button = document.getElementById('chatWidgetButton');
                const existingBadge = button.querySelector('.badge');
                
                if (data.count > 0) {
                    if (existingBadge) {
                        existingBadge.textContent = data.count > 9 ? '9+' : data.count;
                    } else {
                        const badge = document.createElement('span');
                        badge.className = 'badge';
                        badge.textContent = data.count > 9 ? '9+' : data.count;
                        button.appendChild(badge);
                    }
                } else if (existingBadge) {
                    existingBadge.remove();
                }
            }
        });
}

// Update badge every 10 seconds
setInterval(updateUnreadBadge, 10000);
</script>
