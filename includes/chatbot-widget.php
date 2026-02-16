<?php
/**
 * ReviewFlow AI Chatbot Widget - Version 2.0
 * Self-learning chatbot with FAQ integration
 * Include this file on any dashboard page
 */

// Ensure this is included from a valid session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get user context
$user_type = 'guest';
$user_id = 0;
$user_name = 'Guest';

// Debug logging (only in development - remove or disable in production)
if (defined('DEBUG') && DEBUG === true) {
    error_log('Chatbot Widget - Session Debug: ' . json_encode([
        'has_admin' => isset($_SESSION['admin_name']),
        'has_seller_id' => isset($_SESSION['seller_id']),
        'has_seller_name' => isset($_SESSION['seller_name']),
        'has_user_id' => isset($_SESSION['user_id'])
    ]));
}

if (isset($_SESSION['admin_name'])) {
    $user_type = 'admin';
    $user_name = $_SESSION['admin_name'];
} elseif (isset($_SESSION['seller_id'])) {
    $user_type = 'seller';
    $user_id = $_SESSION['seller_id'];
    $user_name = $_SESSION['seller_name'] ?? 'Seller';
    if (defined('DEBUG') && DEBUG === true) {
        error_log('Chatbot Widget - Seller detected: ID=' . $user_id . ', Name=' . $user_name);
    }
} elseif (isset($_SESSION['user_id'])) {
    $user_type = 'user';
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['user_name'] ?? 'User';
}
?>

<!-- AI Chatbot Widget -->
<div id="chatbot-widget" class="chatbot-widget">
    <div class="chatbot-header">
        <div class="chatbot-title">
            <i class="bi bi-robot"></i>
            <span>AI Assistant</span>
        </div>
        <div class="chatbot-actions">
            <button class="chatbot-minimize" title="Minimize">
                <i class="bi bi-dash"></i>
            </button>
            <button class="chatbot-close" title="Close">
                <i class="bi bi-x"></i>
            </button>
        </div>
    </div>
    
    <div class="chatbot-body">
        <div class="chatbot-messages" id="chatbot-messages">
            <div class="chatbot-message bot-message">
                <div class="message-avatar">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="message-content">
                    <div class="message-text">
                        Hello <strong><?php echo htmlspecialchars($user_name); ?></strong>! 👋<br>
                        I'm your AI assistant. How can I help you today?
                    </div>
                    <div class="message-time"><?php echo date('h:i A'); ?></div>
                </div>
            </div>
            
            <!-- Quick actions for different user types -->
            <div class="chatbot-quick-actions">
                <?php if ($user_type === 'admin'): ?>
                    <button class="quick-action-btn" data-question="How do I approve a review request?">
                        <i class="bi bi-check-circle"></i> Approve Requests
                    </button>
                    <button class="quick-action-btn" data-question="How do I assign tasks to users?">
                        <i class="bi bi-clipboard-check"></i> Assign Tasks
                    </button>
                    <button class="quick-action-btn" data-question="How do I export data?">
                        <i class="bi bi-download"></i> Export Data
                    </button>
                <?php elseif ($user_type === 'seller'): ?>
                    <button class="quick-action-btn" data-question="How do I request reviews?">
                        <i class="bi bi-star"></i> Request Reviews
                    </button>
                    <button class="quick-action-btn" data-question="How do I recharge my wallet?">
                        <i class="bi bi-wallet2"></i> Wallet Recharge
                    </button>
                    <button class="quick-action-btn" data-question="How do I view my invoices?">
                        <i class="bi bi-receipt"></i> View Invoices
                    </button>
                <?php else: ?>
                    <button class="quick-action-btn" data-question="How do I complete a task?">
                        <i class="bi bi-check-square"></i> Complete Task
                    </button>
                    <button class="quick-action-btn" data-question="How do I withdraw money?">
                        <i class="bi bi-cash-coin"></i> Withdraw
                    </button>
                    <button class="quick-action-btn" data-question="How do I refer friends?">
                        <i class="bi bi-people"></i> Refer Friends
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="chatbot-footer">
        <form id="chatbot-form" class="chatbot-input-form">
            <input 
                type="text" 
                id="chatbot-input" 
                class="chatbot-input" 
                placeholder="Type your message..."
                autocomplete="off"
            >
            <button type="submit" class="chatbot-send-btn">
                <i class="bi bi-send-fill"></i>
            </button>
        </form>
        <div class="chatbot-typing" id="chatbot-typing" style="display: none;">
            <span></span><span></span><span></span>
        </div>
    </div>
</div>

<!-- Chatbot Trigger Button -->
<button id="chatbot-trigger" class="chatbot-trigger" title="Chat with AI Assistant">
    <i class="bi bi-chat-dots-fill"></i>
    <span class="chatbot-badge" id="chatbot-badge" style="display: none;">1</span>
</button>

<!-- Chatbot Styles -->
<style>
.chatbot-widget {
    position: fixed;
    bottom: 90px;
    right: 20px;
    width: 380px;
    max-width: calc(100vw - 40px);
    height: 600px;
    max-height: calc(100vh - 120px);
    background: var(--bg-secondary, #ffffff);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    display: none;
    flex-direction: column;
    z-index: 9998;
    animation: slideUp 0.3s ease-out;
}

.chatbot-widget.active {
    display: flex;
}

@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.chatbot-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 16px 20px;
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chatbot-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 16px;
}

.chatbot-actions {
    display: flex;
    gap: 8px;
}

.chatbot-minimize,
.chatbot-close {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.chatbot-minimize:hover,
.chatbot-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.chatbot-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: var(--bg-primary, #f5f6fa);
}

.chatbot-messages {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.chatbot-message {
    display: flex;
    gap: 10px;
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.bot-message .message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.user-message {
    flex-direction: row-reverse;
}

.user-message .message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #10b981;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.message-content {
    flex: 1;
    max-width: 75%;
}

.message-text {
    background: var(--bg-secondary, #ffffff);
    padding: 12px 16px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    line-height: 1.5;
    font-size: 14px;
}

.user-message .message-text {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.message-time {
    font-size: 11px;
    color: var(--text-muted, #94a3b8);
    margin-top: 4px;
    padding: 0 4px;
}

.chatbot-quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
}

.quick-action-btn {
    background: var(--bg-secondary, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.quick-action-btn:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: transparent;
    transform: translateY(-1px);
}

.chatbot-footer {
    padding: 16px;
    background: var(--bg-secondary, #ffffff);
    border-top: 1px solid var(--border-color, #e2e8f0);
    border-radius: 0 0 16px 16px;
}

.chatbot-input-form {
    display: flex;
    gap: 8px;
}

.chatbot-input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 24px;
    font-size: 14px;
    background: var(--bg-primary, #f5f6fa);
    color: var(--text-primary, #1e293b);
}

.chatbot-input:focus {
    outline: none;
    border-color: #667eea;
}

.chatbot-send-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s;
}

.chatbot-send-btn:hover {
    transform: scale(1.05);
}

.chatbot-typing {
    display: flex;
    gap: 4px;
    padding: 8px 0;
    justify-content: center;
}

.chatbot-typing span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #667eea;
    animation: typing 1.4s infinite;
}

.chatbot-typing span:nth-child(2) {
    animation-delay: 0.2s;
}

.chatbot-typing span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
    30% { transform: translateY(-10px); opacity: 1; }
}

.chatbot-trigger {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
    z-index: 9997;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.chatbot-trigger:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 32px rgba(102, 126, 234, 0.5);
}

.chatbot-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #ef4444;
    color: white;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 768px) {
    .chatbot-widget {
        width: calc(100vw - 20px);
        right: 10px;
        bottom: 80px;
    }
    
    .chatbot-trigger {
        width: 48px;
        height: 48px;
        font-size: 20px;
    }
}
</style>

<script>
// Chatbot Widget Script
(function() {
    const widget = document.getElementById('chatbot-widget');
    const trigger = document.getElementById('chatbot-trigger');
    const closeBtn = document.querySelector('.chatbot-close');
    const minimizeBtn = document.querySelector('.chatbot-minimize');
    const form = document.getElementById('chatbot-form');
    const input = document.getElementById('chatbot-input');
    const messages = document.getElementById('chatbot-messages');
    const typingIndicator = document.getElementById('chatbot-typing');
    
    const userType = '<?php echo $user_type; ?>';
    const userId = <?php echo $user_id; ?>;
    
    // Toggle chatbot
    trigger.addEventListener('click', () => {
        widget.classList.toggle('active');
        if (widget.classList.contains('active')) {
            input.focus();
        }
    });
    
    closeBtn.addEventListener('click', () => {
        widget.classList.remove('active');
    });
    
    minimizeBtn.addEventListener('click', () => {
        widget.classList.remove('active');
    });
    
    // Handle form submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = input.value.trim();
        if (!message) return;
        
        // Add user message
        addMessage(message, 'user');
        input.value = '';
        
        // Show typing indicator
        typingIndicator.style.display = 'flex';
        
        // Send to chatbot API
        try {
            const apiUrl = '<?php echo htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8'); ?>/chatbot/process.php';
            
            // Debug logging (can be disabled in production)
            if (typeof console !== 'undefined' && console.log) {
                console.log('Chatbot: Sending message to API', apiUrl);
            }
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message, userType, userId })
            });
            
            if (typeof console !== 'undefined' && console.log) {
                console.log('Chatbot: Response status', response.status);
            }
            
            if (!response.ok) {
                const errorText = await response.text();
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Chatbot: Server error response:', errorText);
                }
                throw new Error('Server returned error status: ' + response.status);
            }
            
            const data = await response.json();
            
            if (typeof console !== 'undefined' && console.log) {
                console.log('Chatbot: Received response', data);
            }
            
            typingIndicator.style.display = 'none';
            
            // Validate response structure
            if (!data || !data.success) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Chatbot: Invalid response format:', data);
                }
                throw new Error(data.error || 'Invalid response format from server');
            }
            
            if (typeof data.response !== 'string' || data.response.trim() === '') {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('Chatbot: Empty or invalid response:', data);
                }
                throw new Error('Received empty response from server');
            }
            
            addMessage(data.response, 'bot');
        } catch (error) {
            typingIndicator.style.display = 'none';
            if (typeof console !== 'undefined' && console.error) {
                console.error('Chatbot error:', error);
            }
            addMessage('I\'m having trouble connecting. Please check back later. Error: ' + error.message, 'bot');
        }
    });
    
    // Quick action buttons
    document.querySelectorAll('.quick-action-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const question = btn.dataset.question;
            input.value = question;
            form.dispatchEvent(new Event('submit'));
        });
    });
    
    // Add message to chat
    function addMessage(text, type) {
        const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        const messageDiv = document.createElement('div');
        messageDiv.className = `chatbot-message ${type}-message`;
        
        const avatar = type === 'bot' 
            ? '<div class="message-avatar"><i class="bi bi-robot"></i></div>'
            : '<div class="message-avatar"><i class="bi bi-person-fill"></i></div>';
        
        messageDiv.innerHTML = `
            ${avatar}
            <div class="message-content">
                <div class="message-text">${escapeHtml(text)}</div>
                <div class="message-time">${time}</div>
            </div>
        `;
        
        messages.appendChild(messageDiv);
        messages.scrollTop = messages.scrollHeight;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
</script>
<?php
// End of chatbot widget
?>
