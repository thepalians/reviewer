<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chatbot - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .chatbot-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
            height: 600px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .chatbot-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .chatbot-header h2 {
            font-size: 22px;
            margin-bottom: 5px;
        }
        .chatbot-header p {
            font-size: 13px;
            opacity: 0.9;
        }
        .chatbot-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }
        .message {
            margin-bottom: 15px;
            display: flex;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .message.user {
            justify-content: flex-end;
        }
        .message.bot {
            justify-content: flex-start;
        }
        .message-content {
            max-width: 70%;
            padding: 12px 15px;
            border-radius: 12px;
            word-wrap: break-word;
            line-height: 1.4;
        }
        .message.user .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom-right-radius: 4px;
        }
        .message.bot .message-content {
            background: #e8e8e8;
            color: #333;
            border-bottom-left-radius: 4px;
        }
        .chatbot-input {
            padding: 15px;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
            background: white;
        }
        .chatbot-input input {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 25px;
            padding: 12px 20px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
        }
        .chatbot-input input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .chatbot-input button {
            width: 45px;
            height: 45px;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
        }
        .chatbot-input button:hover {
            transform: scale(1.05);
        }
        .chatbot-input button:active {
            transform: scale(0.95);
        }
        .loading {
            display: flex;
            gap: 5px;
        }
        .loading span {
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            animation: loading 1.4s infinite;
        }
        .loading span:nth-child(2) {
            animation-delay: 0.2s;
        }
        .loading span:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes loading {
            0%, 60%, 100% {
                opacity: 0.3;
                transform: translateY(0);
            }
            30% {
                opacity: 1;
                transform: translateY(-10px);
            }
        }
        .welcome-message {
            text-align: center;
            color: #999;
            padding: 20px;
        }
        .back-link {
            text-align: center;
            padding: 10px;
            margin-top: 10px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="chatbot-container">
        <div class="chatbot-header">
            <h2>🤖 AI Assistant</h2>
            <p>How can I help you today?</p>
        </div>
        
        <div class="chatbot-messages" id="messages">
            <div class="message bot">
                <div class="message-content">
                    👋 Hello <?php echo escape($user_name); ?>! I'm your AI assistant. Ask me anything about:
                    <br><br>
                    • How to complete tasks<br>
                    • Payment & Refund process<br>
                    • Account management<br>
                    • Technical support
                </div>
            </div>
        </div>
        
        <form id="chatForm" class="chatbot-input">
            <input type="text" id="messageInput" placeholder="Type your question..." autocomplete="off">
            <button type="submit">📤</button>
        </form>
    </div>
    
    <div class="back-link">
        <a href="<?php echo APP_URL; ?>/">← Back to Home</a>
    </div>
    
    <script>
        const form = document.getElementById('chatForm');
        const input = document.getElementById('messageInput');
        const messagesDiv = document.getElementById('messages');
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const message = input.value.trim();
            if (!message) return;
            
            // Add user message
            const userMsgDiv = document.createElement('div');
            userMsgDiv.className = 'message user';
            userMsgDiv.innerHTML = `<div class="message-content">${message}</div>`;
            messagesDiv.appendChild(userMsgDiv);
            
            input.value = '';
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
            
            // Show loading
            const botDiv = document.createElement('div');
            botDiv.className = 'message bot';
            botDiv.innerHTML = `<div class="message-content"><div class="loading"><span></span><span></span><span></span></div></div>`;
            messagesDiv.appendChild(botDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
            
            try {
                const formData = new FormData();
                formData.append('message', message);
                
                const response = await fetch('<?php echo APP_URL; ?>/chatbot/api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    botDiv.innerHTML = `<div class="message-content">${data.response}</div>`;
                } else {
                    botDiv.innerHTML = `<div class="message-content">Sorry, I encountered an error. Please try again.</div>`;
                }
                
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
                
            } catch (error) {
                botDiv.innerHTML = `<div class="message-content">Connection error. Please try again.</div>`;
            }
        });
    </script>
</body>
</html>
