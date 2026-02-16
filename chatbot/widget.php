<?php
/**
 * Chatbot Widget - Embeddable chat interface
 */

require_once __DIR__ . '/../includes/config.php';

$user_name = $_SESSION['user_name'] ?? 'Guest';
?>
<div id="chatbot-widget" class="chatbot-widget">
    <!-- Chat Button -->
    <button class="chat-btn" id="chatToggle" aria-label="Open Chat">
        <span class="chat-icon">💬</span>
        <span class="chat-close">✕</span>
        <span class="chat-badge" id="chatBadge" style="display:none">1</span>
    </button>
    
    <!-- Chat Window -->
    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <div class="chat-header-info">
                <div class="bot-avatar">🤖</div>
                <div>
                    <div class="bot-name"><?php echo APP_NAME; ?> Assistant</div>
                    <div class="bot-status"><span class="status-dot"></span> Online</div>
                </div>
            </div>
            <button class="chat-minimize" onclick="toggleChat()">−</button>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <!-- Welcome message -->
            <div class="message bot">
                <div class="message-avatar">🤖</div>
                <div class="message-content">
                    <div class="message-bubble">
                        Hi <?php echo escape($user_name); ?>! 👋 I'm your assistant. How can I help you today?
                    </div>
                    <div class="message-time">Just now</div>
                </div>
            </div>
            
            <!-- Quick suggestions -->
            <div class="quick-suggestions" id="quickSuggestions">
                <button onclick="sendSuggestion('How do tasks work?')">📋 How tasks work</button>
                <button onclick="sendSuggestion('Check my wallet')">💰 My wallet</button>
                <button onclick="sendSuggestion('How to withdraw?')">💸 Withdraw</button>
                <button onclick="sendSuggestion('Referral bonus')">🎁 Referrals</button>
            </div>
        </div>
        
        <div class="chat-input">
            <input type="text" id="chatInput" placeholder="Type your message..." autocomplete="off" maxlength="500">
            <button id="chatSend" onclick="sendMessage()">
                <span>➤</span>
            </button>
        </div>
    </div>
</div>

<style>
.chatbot-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.chat-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    cursor: pointer;
    box-shadow: 0 5px 25px rgba(102, 126, 234, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    position: relative;
}

.chat-btn:hover {
    transform: scale(1.1);
}

.chat-btn .chat-icon,
.chat-btn .chat-close {
    font-size: 24px;
    color: #fff;
    transition: all 0.3s;
}

.chat-btn .chat-close {
    display: none;
    font-size: 20px;
}

.chat-btn.active .chat-icon {
    display: none;
}

.chat-btn.active .chat-close {
    display: block;
}

.chat-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 22px;
    height: 22px;
    background: #ef4444;
    color: #fff;
    border-radius: 50%;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-window {
    position: absolute;
    bottom: 75px;
    right: 0;
    width: 380px;
    height: 520px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 10px 50px rgba(0, 0, 0, 0.2);
    display: none;
    flex-direction: column;
    overflow: hidden;
    animation: slideUp 0.3s ease;
}

.chat-window.active {
    display: flex;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.chat-header {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    padding: 18px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-header-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.bot-avatar {
    width: 45px;
    height: 45px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.bot-name {
    font-weight: 600;
    font-size: 15px;
}

.bot-status {
    font-size: 12px;
    opacity: 0.9;
    display: flex;
    align-items: center;
    gap: 5px;
}

.status-dot {
    width: 8px;
    height: 8px;
    background: #4ade80;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.chat-minimize {
    width: 35px;
    height: 35px;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f8fafc;
}

.message {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.message.user {
    flex-direction: row-reverse;
}

.message-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.message.bot .message-avatar {
    background: linear-gradient(135deg, #667eea, #764ba2);
}

.message.user .message-avatar {
    background: #e2e8f0;
}

.message-content {
    max-width: 75%;
}

.message-bubble {
    padding: 12px 16px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.message.bot .message-bubble {
    background: #fff;
    color: #1e293b;
    border-bottom-left-radius: 5px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.message.user .message-bubble {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    border-bottom-right-radius: 5px;
}

.message-time {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 5px;
    padding: 0 5px;
}

.message.user .message-time {
    text-align: right;
}

.quick-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.quick-suggestions button {
    padding: 8px 14px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
    color: #475569;
}

.quick-suggestions button:hover {
    background: #667eea;
    color: #fff;
    border-color: #667eea;
}

.chat-input {
    padding: 15px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 10px;
}

.chat-input input {
    flex: 1;
    padding: 12px 18px;
    border: 2px solid #e2e8f0;
    border-radius: 25px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s;
}

.chat-input input:focus {
    border-color: #667eea;
}

.chat-input button {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s;
}

.chat-input button:hover {
    transform: scale(1.05);
}

.typing-indicator {
    display: flex;
    gap: 4px;
    padding: 12px 16px;
    background: #fff;
    border-radius: 18px;
    width: fit-content;
}

.typing-indicator span {
    width: 8px;
    height: 8px;
    background: #94a3b8;
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-5px); }
}

/* Mobile responsive */
@media (max-width: 480px) {
    .chat-window {
        width: calc(100vw - 20px);
        height: calc(100vh - 100px);
        bottom: 70px;
        right: -10px;
        border-radius: 15px;
    }
    
    .chat-btn {
        width: 55px;
        height: 55px;
    }
}
</style>

<script>
(function() {
    // Configuration constants
    const MAX_MESSAGE_LENGTH = 500;
    const MAX_SUGGESTION_LENGTH = 100;
    
    const chatToggle = document.getElementById('chatToggle');
    const chatWindow = document.getElementById('chatWindow');
    const chatMessages = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    const quickSuggestions = document.getElementById('quickSuggestions');
    
    let isTyping = false;
    
    // Toggle chat
    window.toggleChat = function() {
        chatToggle.classList.toggle('active');
        chatWindow.classList.toggle('active');
        if (chatWindow.classList.contains('active')) {
            chatInput.focus();
        }
    };
    
    chatToggle.addEventListener('click', toggleChat);
    
    // Send message
    window.sendMessage = function() {
        const message = chatInput.value.trim();
        if (!message || isTyping) return;
        
        // Validate message length (max 500 chars)
        if (message.length > MAX_MESSAGE_LENGTH) {
            addMessage(`Message is too long. Please keep it under ${MAX_MESSAGE_LENGTH} characters.`, 'bot');
            return;
        }
        
        // Add user message
        addMessage(message, 'user');
        chatInput.value = '';
        
        // Hide suggestions
        if (quickSuggestions) {
            quickSuggestions.style.display = 'none';
        }
        
        // Show typing indicator
        showTyping();
        
        // Send to API
        fetch('<?php echo APP_URL; ?>/chatbot/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: message })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Server error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            hideTyping();
            
            // Validate response structure
            if (!data || typeof data.response !== 'string') {
                throw new Error('Invalid response format');
            }
            
            addMessage(data.response, 'bot');
            
            // Show suggestions if available and valid
            if (Array.isArray(data.suggestions) && data.suggestions.length > 0) {
                showSuggestions(data.suggestions);
            }
        })
        .catch(error => {
            hideTyping();
            console.error('Chatbot error:', error);
            addMessage("Connection error. Please try again.", 'bot');
        });
    };
    
    // Send suggestion
    window.sendSuggestion = function(text) {
        chatInput.value = text;
        sendMessage();
    };
    
    // Add message to chat
    function addMessage(text, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${type}`;
        
        const avatar = type === 'bot' ? '🤖' : '👤';
        const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        
        // Create message bubble and safely set content
        const messageBubble = document.createElement('div');
        messageBubble.className = 'message-bubble';
        
        // For bot messages, parse markdown-like formatting safely
        if (type === 'bot') {
            // Escape HTML first
            const escaped = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            
            // Then apply markdown formatting
            const formatted = escaped
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>');
            
            messageBubble.innerHTML = formatted;
        } else {
            // For user messages, use textContent (no HTML)
            messageBubble.textContent = text;
        }
        
        // Create message structure
        const avatarDiv = document.createElement('div');
        avatarDiv.className = 'message-avatar';
        avatarDiv.textContent = avatar;
        
        const contentDiv = document.createElement('div');
        contentDiv.className = 'message-content';
        
        const timeDiv = document.createElement('div');
        timeDiv.className = 'message-time';
        timeDiv.textContent = time;
        
        contentDiv.appendChild(messageBubble);
        contentDiv.appendChild(timeDiv);
        
        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(contentDiv);
        
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Show typing indicator
    function showTyping() {
        isTyping = true;
        const typingDiv = document.createElement('div');
        typingDiv.id = 'typingIndicator';
        typingDiv.className = 'message bot';
        typingDiv.innerHTML = `
            <div class="message-avatar">🤖</div>
            <div class="message-content">
                <div class="typing-indicator">
                    <span></span><span></span><span></span>
                </div>
            </div>
        `;
        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Hide typing indicator
    function hideTyping() {
        isTyping = false;
        const typing = document.getElementById('typingIndicator');
        if (typing) typing.remove();
    }
    
    // Show suggestions
    function showSuggestions(suggestions) {
        // Validate suggestions array
        if (!Array.isArray(suggestions) || suggestions.length === 0) {
            return;
        }
        
        const suggestionsDiv = document.createElement('div');
        suggestionsDiv.className = 'quick-suggestions';
        
        suggestions.forEach(text => {
            // Validate and sanitize each suggestion
            if (typeof text === 'string' && text.trim()) {
                const btn = document.createElement('button');
                btn.textContent = text.substring(0, MAX_SUGGESTION_LENGTH); // Limit length
                btn.onclick = () => sendSuggestion(text);
                suggestionsDiv.appendChild(btn);
            }
        });
        
        // Only append if we have buttons
        if (suggestionsDiv.children.length > 0) {
            chatMessages.appendChild(suggestionsDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    // Enter key to send
    chatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
    
    // Auto open after 5 seconds (optional)
    // setTimeout(() => {
    //     if (!chatWindow.classList.contains('active')) {
    //         toggleChat();
    //     }
    // }, 5000);
})();
</script>
