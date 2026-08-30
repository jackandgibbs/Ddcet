<?php
require_once __DIR__ . '/config.php';
$user = requireAuth();
$pageTitle = 'AI Doubt Assistant ' . icon('sparkles', 16);
include __DIR__ . '/includes/header.php';
?>

<!-- Include marked.js for markdown parsing -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<style>
/* Reset basic scrollbars for the chat area */
.chat-messages::-webkit-scrollbar { width: 6px; }
.chat-messages::-webkit-scrollbar-track { background: transparent; }
.chat-messages::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
.theme-dark .chat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); }

/* The main wrapper */
.chat-container {
    display: flex;
    flex-direction: column;
    height: calc(100dvh - 90px);
    max-width: 850px;
    width: 100%;
    margin: 0 auto;
    box-sizing: border-box;
    
    /* Modern glassy look */
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    position: relative;
}

.theme-dark .chat-container {
    box-shadow: 0 12px 40px rgba(0,0,0,0.3);
    background: rgba(30, 30, 35, 0.7);
    backdrop-filter: blur(20px);
}

/* Header */
.chat-header {
    padding: 16px 24px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 16px;
    z-index: 10;
}
.theme-dark .chat-header {
    background: rgba(30, 30, 35, 0.6);
}

.chat-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1, #a855f7);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}

/* Chat History */
.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    scroll-behavior: smooth;
    /* Soft mesh gradient hint behind messages */
    background: radial-gradient(circle at top left, rgba(99,102,241,0.03), transparent 40%),
                radial-gradient(circle at bottom right, rgba(168,85,247,0.03), transparent 40%);
}

.message {
    max-width: 85%;
    padding: 14px 18px;
    border-radius: 18px;
    font-size: 15px;
    line-height: 1.6;
    animation: fadeIn 0.3s ease-out forwards;
    opacity: 0;
    transform: translateY(10px);
}

@keyframes fadeIn {
    to { opacity: 1; transform: translateY(0); }
}

/* User Message */
.message.user {
    align-self: flex-end;
    background: linear-gradient(135deg, #6366f1, #a855f7);
    color: #fff;
    border-bottom-right-radius: 4px;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
}

/* AI Message */
.message.ai {
    align-self: flex-start;
    background: var(--bg-primary);
    border: 1px solid var(--border);
    color: var(--text-primary);
    border-bottom-left-radius: 4px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.theme-dark .message.ai {
    background: var(--bg-surface);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

/* Markdown specific styling inside AI messages to prevent scrollbar bugs */
.message.ai p { margin: 0 0 10px 0; word-break: break-word; }
.message.ai p:last-child { margin: 0; }
.message.ai .katex-display {
    overflow-x: auto;
    overflow-y: hidden;
    max-width: 100%;
    padding: 10px 0;
}
.message.ai code { 
    background: rgba(120, 120, 120, 0.1); 
    padding: 2px 6px; 
    border-radius: 6px; 
    font-family: var(--font-mono); 
    font-size: 13px;
    color: var(--accent);
    word-break: break-word;
}
.theme-dark .message.ai code { color: #a855f7; }
.message.ai pre {
    background: rgba(0,0,0,0.05);
    padding: 12px;
    border-radius: 8px;
    overflow-x: auto;
    font-size: 13px;
    margin: 10px 0;
}
.theme-dark .message.ai pre { background: rgba(0,0,0,0.3); }

/* Input Area */
.chat-input-area {
    padding: 20px 24px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(12px);
    border-top: 1px solid var(--border);
}
.theme-dark .chat-input-area {
    background: rgba(30, 30, 35, 0.6);
}

.quick-prompts {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 16px;
    scrollbar-width: none;
}
.quick-prompts::-webkit-scrollbar { display: none; }

.prompt-chip {
    white-space: nowrap;
    padding: 8px 16px;
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 13px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}
.prompt-chip:hover {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
}

.input-wrapper {
    display: flex;
    gap: 12px;
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 30px;
    padding: 8px 12px 8px 20px;
    align-items: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    transition: all 0.2s;
}
.input-wrapper:focus-within {
    border-color: #a855f7;
    box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.15);
}

#chatInput {
    flex: 1;
    background: transparent;
    border: none;
    color: var(--text-primary);
    font-size: 15px;
    outline: none;
    padding: 8px 0;
}
#chatInput::placeholder { color: var(--text-muted); }

.send-btn {
    background: linear-gradient(135deg, #6366f1, #a855f7);
    color: #fff;
    border: none;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
}
.send-btn:hover {
    transform: scale(1.05) translateY(-1px);
    box-shadow: 0 6px 14px rgba(99, 102, 241, 0.4);
}
.send-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}
</style>

<div class="chat-container">
    <div class="chat-header">
        <div class="chat-header-icon"><?= icon('cpu', 24) ?></div>
        <div>
            <h3 style="margin: 0; font-size: 18px; font-weight: 700;">AI Tutor: Elara</h3>
            <div style="font-size: 12px; color: var(--green); display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                <div style="width:8px;height:8px;background:var(--green);border-radius:50%; box-shadow: 0 0 6px var(--green);"></div> Online
            </div>
        </div>
    </div>
    
    <div class="chat-messages" id="chatMessages">
        <div class="message ai">
            Hi <strong><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></strong>! 👋 I'm Elara, your AI study assistant. You can ask me to explain concepts, solve DDCET syllabus doubts, or generate a study plan. What would you like to learn today?
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
            <input type="text" id="chatInput" placeholder="Ask Elara a question..." onkeypress="handleEnter(event)" autocomplete="off">
            <button class="send-btn" id="sendBtn" onclick="sendMessage()"><?= icon('send', 18) ?></button>
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
    div.className = `message ${role}`;
    
    if (role === 'user') {
        // Just text for user
        div.textContent = text;
    } else {
        // Use marked.js to render Markdown into HTML safely
        const parsedHTML = marked.parse(text);
        div.innerHTML = parsedHTML;
        
        // Render KaTeX math equations found within the parsed HTML
        if (window.renderMathInElement) {
            renderMathInElement(div, {
                delimiters: [
                    {left: '$$', right: '$$', display: true},
                    {left: '\\[', right: '\\]', display: true},
                    {left: '$', right: '$', display: false},
                    {left: '\\(', right: '\\)', display: false}
                ],
                throwOnError: false
            });
        }
    }
    
    container.appendChild(div);
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
