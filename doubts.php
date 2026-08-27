<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'AI Doubt Assistant ✨';
include __DIR__ . '/includes/header.php';
?>

<style>
.chat-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 80px); /* Account for header */
    max-width: 800px;
    margin: 0 auto;
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}

.chat-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    background: rgba(30, 30, 30, 0.8);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    gap: 12px;
}

.chat-header-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981, #3b82f6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    scroll-behavior: smooth;
}

.message {
    max-width: 85%;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.6;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.message.user {
    align-self: flex-end;
    background: var(--accent);
    color: #fff;
    border-bottom-right-radius: 4px;
}

.message.ai {
    align-self: flex-start;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
}

.chat-input-area {
    padding: 16px;
    background: var(--bg-primary);
    border-top: 1px solid var(--border);
}

.quick-prompts {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 12px;
    scrollbar-width: none;
}

.quick-prompts::-webkit-scrollbar { display: none; }

.prompt-chip {
    white-space: nowrap;
    padding: 6px 12px;
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    font-size: 12px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s;
}

.prompt-chip:hover {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
}

.input-wrapper {
    display: flex;
    gap: 10px;
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 6px 12px;
    align-items: center;
}

.input-wrapper:focus-within {
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

#chatInput {
    flex: 1;
    background: transparent;
    border: none;
    color: var(--text-primary);
    font-size: 14px;
    outline: none;
    padding: 8px 4px;
}

.send-btn {
    background: linear-gradient(135deg, #10b981, #3b82f6);
    color: #fff;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: transform 0.2s;
}

.send-btn:hover {
    transform: scale(1.05);
}
.send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* markdown styles */
.message.ai strong { color: #fff; }
.message.ai code { background: rgba(255,255,255,0.1); padding: 2px 4px; border-radius: 4px; font-family: monospace; }
</style>

<div class="chat-container">
    <div class="chat-header">
        <div class="chat-header-icon"><?= icon('cpu', 20) ?></div>
        <div>
            <h3 style="margin: 0; font-size: 16px;">DDCET AI Tutor</h3>
            <div style="font-size: 11px; color: var(--green); display: flex; align-items: center; gap: 4px;">
                <div style="width:6px;height:6px;background:var(--green);border-radius:50%;"></div> Online & Ready
            </div>
        </div>
    </div>
    
    <div class="chat-messages" id="chatMessages">
        <div class="message ai mathy">
            Hi <strong><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></strong>! 👋 I'm your AI study assistant. You can ask me to explain concepts, solve DDCET syllabus doubts, or generate a study plan. What would you like to learn today?
        </div>
    </div>
    
    <div class="chat-input-area">
        <div class="quick-prompts">
            <button class="prompt-chip" onclick="setPrompt('What is the syllabus for the DDCET exam?')">Syllabus details</button>
            <button class="prompt-chip" onclick="setPrompt('Can you explain how to find the determinant of a 3x3 matrix?')">Determinants trick</button>
            <button class="prompt-chip" onclick="setPrompt('How do I manage my time during the 150-minute exam?')">Time management</button>
            <button class="prompt-chip" onclick="setPrompt('Give me a quick mock question for Chemistry.')">Practice Question</button>
        </div>
        <div class="input-wrapper">
            <input type="text" id="chatInput" placeholder="Ask your doubt..." onkeypress="handleEnter(event)">
            <button class="send-btn" id="sendBtn" onclick="sendMessage()"><?= icon('send', 16) ?></button>
        </div>
    </div>
</div>

<script>
let chatHistory = [];

function setPrompt(text) {
    const input = document.getElementById('chatInput');
    input.value = text;
    input.focus();
}

function handleEnter(e) {
    if (e.key === 'Enter') sendMessage();
}

function appendMessage(role, text) {
    const container = document.getElementById('chatMessages');
    const div = document.createElement('div');
    div.className = `message ${role} mathy`;
    
    // Formatting: \n to <br>, bold to <strong>, code blocks
    let html = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    html = html.replace(/\n/g, '<br>');
    
    div.innerHTML = html;
    container.appendChild(div);
    
    if (window.renderMathInElement) {
        renderMathInElement(div, {
            delimiters: [
                {left: '$$', right: '$$', display: true},
                {left: '$', right: '$', display: false}
            ],
            throwOnError: false
        });
    }
    
    container.scrollTop = container.scrollHeight;
    return div;
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const btn = document.getElementById('sendBtn');
    const text = input.value.trim();
    
    if (!text) return;
    
    input.value = '';
    btn.disabled = true;
    
    appendMessage('user', text);
    chatHistory.push({ role: 'user', text: text });
    
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'message ai';
    loadingDiv.innerHTML = '<span class="spinner" style="display:inline-block;width:16px;height:16px;border:2px solid var(--accent);border-top-color:transparent;border-radius:50%;animation:spin 1s linear infinite;vertical-align:-3px;"></span> Thinking...';
    document.getElementById('chatMessages').appendChild(loadingDiv);
    document.getElementById('chatMessages').scrollTop = document.getElementById('chatMessages').scrollHeight;
    
    fetch('<?= BASE_PATH ?>api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= htmlspecialchars(csrfToken()) ?>' },
        body: JSON.stringify({ messages: chatHistory })
    })
    .then(r => r.json())
    .then(data => {
        loadingDiv.remove();
        btn.disabled = false;
        
        if (data.error) {
            appendMessage('ai', 'Oops! I encountered an error: ' + data.error);
        } else {
            appendMessage('ai', data.text);
            chatHistory.push({ role: 'model', text: data.text });
        }
    })
    .catch(() => {
        loadingDiv.remove();
        btn.disabled = false;
        appendMessage('ai', 'Network error. Please try again.');
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
