// AI Chatbot Functionality
document.addEventListener('DOMContentLoaded', function() {
    const chatbotToggle = document.getElementById('chatbotToggle');
    const chatbot = document.getElementById('chatbot');
    const closeChatbot = document.querySelector('.close-chatbot');
    const sendMessageBtn = document.getElementById('sendMessage');
    const userMessageInput = document.getElementById('userMessage');
    const chatMessages = document.getElementById('chatMessages');
    
    // Toggle chatbot
    if (chatbotToggle) {
        chatbotToggle.addEventListener('click', function() {
            chatbot.classList.toggle('open');
        });
    }
    
    if (closeChatbot) {
        closeChatbot.addEventListener('click', function() {
            chatbot.classList.remove('open');
        });
    }
    
    // Send message
    function sendMessage() {
        const message = userMessageInput.value.trim();
        if (message === '') return;
        
        // Add user message
        addMessage('user', message);
        userMessageInput.value = '';
        
        // Send to server
        fetch('chatbot/ai_chatbot.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'message=' + encodeURIComponent(message)
        })
        .then(response => response.json())
        .then(data => {
            // Add bot response
            setTimeout(() => {
                addMessage('bot', data.response);
            }, 500);
        })
        .catch(error => {
            console.error('Error:', error);
            addMessage('bot', 'Sorry, I encountered an error. Please try again.');
        });
    }
    
    // Add message to chat
    function addMessage(sender, text) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message ' + sender;
        
        if (sender === 'bot') {
            messageDiv.innerHTML = `<strong>AI Assistant:</strong> ${text}`;
        } else {
            messageDiv.innerHTML = `<strong>You:</strong> ${text}`;
        }
        
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Event listeners
    if (sendMessageBtn) {
        sendMessageBtn.addEventListener('click', sendMessage);
    }
    
    if (userMessageInput) {
        userMessageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }
    
    // Predefined quick questions
    const quickQuestions = [
        'How to register?',
        'What is the task process?',
        'When will I get refund?',
        'How to contact admin?'
    ];
    
    // Add quick questions buttons
    const quickQuestionsDiv = document.createElement('div');
    quickQuestionsDiv.className = 'quick-questions';
    quickQuestionsDiv.innerHTML = '<p><strong>Quick Questions:</strong></p>';
    
    quickQuestions.forEach(question => {
        const btn = document.createElement('button');
        btn.className = 'btn-quick';
        btn.textContent = question;
        btn.addEventListener('click', function() {
            userMessageInput.value = question;
            sendMessage();
        });
        quickQuestionsDiv.appendChild(btn);
    });
    
    if (chatMessages) {
        chatMessages.parentNode.insertBefore(quickQuestionsDiv, chatMessages.nextSibling);
    }
});
