class MarketplaceChatbot {
    constructor() {
        this.isOpen = false;
        this.conversationHistory = [];
        this.isTyping = false;
        
        this.init();
    }

    init() {
        this.createChatbotHTML();
        this.loadConversationHistory();
        this.bindEvents();
    }

    createChatbotHTML() {
        // Chatbot Icon
        const icon = document.createElement('div');
        icon.id = 'chatbot-icon';
        icon.innerHTML = '💬';
        icon.setAttribute('aria-label', 'Open chat assistant');
        document.body.appendChild(icon);

        // Chatbot Window
        const window = document.createElement('div');
        window.id = 'chatbot-window';
        window.innerHTML = `
            <div class="chatbot-header">
                <div>
                    <h3>UniLearn Assistant</h3>
                    <div class="chatbot-status">
                        <span class="status-dot"></span>
                        <span>Online</span>
                    </div>
                </div>
                <button class="close-chatbot" aria-label="Close chat">×</button>
            </div>
            <div class="chatbot-messages">
                <div class="message bot">
                    <div class="message-content">
                        👋 Hi! I'm your UniLearn assistant. I can help you find the perfect services or job opportunities. What are you looking for today?
                    </div>
                    <div class="message-time">${this.getCurrentTime()}</div>
                </div>
            </div>
            <div class="chatbot-input-container">
                <div class="chatbot-input-wrapper">
                    <input type="text" class="chatbot-input" placeholder="Type your message..." maxlength="500">
                    <button class="chatbot-send" aria-label="Send message">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(window);
    }

    bindEvents() {
        const icon = document.getElementById('chatbot-icon');
        const window = document.getElementById('chatbot-window');
        const closeBtn = document.querySelector('.close-chatbot');
        const input = document.querySelector('.chatbot-input');
        const sendBtn = document.querySelector('.chatbot-send');

        // Toggle chat window
        icon.addEventListener('click', () => this.toggleChat());
        closeBtn.addEventListener('click', () => this.closeChat());

        // Send message
        sendBtn.addEventListener('click', () => this.sendMessage());
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Close on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.closeChat();
            }
        });
    }

    toggleChat() {
        const window = document.getElementById('chatbot-window');
        const icon = document.getElementById('chatbot-icon');
        
        if (this.isOpen) {
            this.closeChat();
        } else {
            this.openChat();
        }
    }

    openChat() {
        const window = document.getElementById('chatbot-window');
        const icon = document.getElementById('chatbot-icon');
        
        window.classList.add('active');
        icon.classList.add('active');
        this.isOpen = true;
        
        // Focus input
        setTimeout(() => {
            document.querySelector('.chatbot-input').focus();
        }, 300);
    }

    closeChat() {
        const window = document.getElementById('chatbot-window');
        const icon = document.getElementById('chatbot-icon');
        
        window.classList.remove('active');
        icon.classList.remove('active');
        this.isOpen = false;
    }

    async sendMessage() {
        const input = document.querySelector('.chatbot-input');
        const message = input.value.trim();
        
        if (!message || this.isTyping) return;
        
        console.log('🤖 Sending message:', message);
        console.log('📊 Conversation history:', this.conversationHistory);
        
        // Add user message
        this.addMessage(message, 'user');
        input.value = '';
        
        // Show typing indicator
        this.showTypingIndicator();
        
        try {
            // Call API
            console.log('🌐 Calling API at: /api/chatbot/chat');
            const response = await fetch('/api/chatbot/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    message: message,
                    history: this.conversationHistory.slice(-10) // Send last 10 messages
                })
            });

            console.log('📡 Response status:', response.status);
            
            if (!response.ok) {
                console.error('❌ API Error:', response.statusText);
                throw new Error('API request failed');
            }

            const data = await response.json();
            console.log('📝 AI Response:', data);
            
            // Add bot response
            this.addMessage(data.reply, 'bot', data.recommendations);
            
            // Update conversation history
            this.conversationHistory.push({
                user: message,
                assistant: data.reply,
                timestamp: new Date().toISOString()
            });
            
            // Save to sessionStorage
            this.saveConversationHistory();
            
        } catch (error) {
            console.error('❌ Chatbot Error:', error);
            this.addMessage('I apologize, but I\'m having trouble connecting right now. Please try again later or browse our services directly.', 'bot');
        } finally {
            this.hideTypingIndicator();
        }
    }

    addMessage(content, sender, recommendations = []) {
        const messagesContainer = document.querySelector('.chatbot-messages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;
        
        let messageHTML = `
            <div class="message-content">${this.formatMessage(content)}</div>
            <div class="message-time">${this.getCurrentTime()}</div>
        `;
        
        if (recommendations && recommendations.length > 0) {
            messageHTML += this.buildRecommendationsHTML(recommendations);
        }
        
        messageDiv.innerHTML = messageHTML;
        messagesContainer.appendChild(messageDiv);
        
        // Scroll to bottom
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        // Bind recommendation clicks
        this.bindRecommendationClicks(messageDiv);
    }

    formatMessage(content) {
        // Convert URLs to links
        content = content.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
        
        // Convert line breaks to HTML
        content = content.replace(/\n/g, '<br>');
        
        // Bold text between ** **
        content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        return content;
    }

    buildRecommendationsHTML(recommendations) {
        let html = '<div class="recommendations">';
        
        recommendations.forEach(rec => {
            const url = rec.type === 'service' ? `/marketplace/shop/${rec.slug}` : `/jobs/${rec.slug}`;
            const price = rec.type === 'service' ? `$${rec.price}` : `Budget: $${rec.budget}`;
            
            html += `
                <div class="recommendation-item" data-url="${url}">
                    <div class="recommendation-title">${rec.title}</div>
                    <div class="recommendation-price">${price}</div>
                    ${rec.category ? `<div class="recommendation-category">${rec.category}</div>` : ''}
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    }

    bindRecommendationClicks(messageDiv) {
        const items = messageDiv.querySelectorAll('.recommendation-item');
        items.forEach(item => {
            item.addEventListener('click', () => {
                const url = item.getAttribute('data-url');
                window.open(url, '_blank');
            });
        });
    }

    showTypingIndicator() {
        this.isTyping = true;
        const messagesContainer = document.querySelector('.chatbot-messages');
        
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot typing-message';
        typingDiv.innerHTML = `
            <div class="typing-indicator active">
                <div class="typing-dots">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        `;
        
        messagesContainer.appendChild(typingDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    hideTypingIndicator() {
        this.isTyping = false;
        const typingMessage = document.querySelector('.typing-message');
        if (typingMessage) {
            typingMessage.remove();
        }
    }

    getCurrentTime() {
        return new Date().toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true 
        });
    }

    saveConversationHistory() {
        try {
            sessionStorage.setItem('chatbot_history', JSON.stringify(this.conversationHistory.slice(-20))); // Keep last 20 messages
        } catch (e) {
            console.warn('Failed to save conversation history:', e);
        }
    }

    loadConversationHistory() {
        try {
            const saved = sessionStorage.getItem('chatbot_history');
            if (saved) {
                this.conversationHistory = JSON.parse(saved);
            }
        } catch (e) {
            console.warn('Failed to load conversation history:', e);
            this.conversationHistory = [];
        }
    }

    clearHistory() {
        this.conversationHistory = [];
        sessionStorage.removeItem('chatbot_history');
    }

    // Public method to programmatically open chat with a message
    openWithMessage(message) {
        this.openChat();
        setTimeout(() => {
            const input = document.querySelector('.chatbot-input');
            input.value = message;
            this.sendMessage();
        }, 500);
    }
}

// Initialize chatbot when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('🤖 Chatbot script loaded');
    console.log('📊 Ready to initialize MarketplaceChatbot');
    
    try {
        window.marketplaceChatbot = new MarketplaceChatbot();
        console.log('✅ MarketplaceChatbot instance created successfully');
        
        // Make it globally available for external calls
        window.openChatbot = (message) => {
            console.log('🎯 openChatbot called with message:', message);
            if (window.marketplaceChatbot) {
                window.marketplaceChatbot.openWithMessage(message);
                return true;
            } else {
                console.error('❌ marketplaceChatbot instance not found');
                return false;
            }
        };
        
        // Test global function
        if (typeof window.openChatbot === 'function') {
            console.log('✅ Global openChatbot function is available');
        } else {
            console.error('❌ Global openChatbot function is NOT available');
        }
        
        // Add global function for testing
        window.testChatbot = () => {
            console.log('🧪 Testing chatbot...');
            return window.openChatbot('Test message from global function');
        };
        
        console.log('✅ Chatbot initialization complete');
        
    } catch (error) {
        console.error('❌ Failed to initialize chatbot:', error);
        
        // Fallback: create a simple function that shows an alert
        window.openChatbot = (message) => {
            console.log('🔄 Fallback openChatbot called:', message);
            alert('Chatbot is loading... Please try again in a moment.');
            return false;
        };
    }
});

// Handle page visibility changes to save conversation
document.addEventListener('visibilitychange', () => {
    if (window.marketplaceChatbot) {
        window.marketplaceChatbot.saveConversationHistory();
    }
});
