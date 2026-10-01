<?php
$page_title = 'AI Assistant';
require_once __DIR__ . '/../partials/header.php';

$conversationId = (int)($_GET['conversation_id'] ?? 0);
if ($conversationId) {
    $stmt = db()->prepare('SELECT * FROM ai_conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$conversationId, $user['id']]);
    if (!$stmt->fetch()) $conversationId = 0;
}

function callAI($message) {
    $url = AI_API_URL . '?key=' . AI_API_KEY;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => "You are a helpful IT and technology support assistant. Please help with this problem: " . $message
                    ]
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => 'Connection error: ' . $error];
    }
    
    if ($httpCode !== 200) {
        return ['error' => 'API returned status ' . $httpCode . ': ' . $response];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return ['success' => $result['candidates'][0]['content']['parts'][0]['text']];
    }
    
    return ['error' => 'Unexpected response format: ' . $response];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    if ($message !== '') {
        if (!$conversationId) {
            $title = mb_substr($message, 0, 80);
            $stmt = db()->prepare('INSERT INTO ai_conversations (user_id, title) VALUES (?, ?)');
            $stmt->execute([$user['id'], $title]);
            $conversationId = (int)db()->lastInsertId();
        }
        $stmt = db()->prepare('INSERT INTO ai_messages (conversation_id, sender, message) VALUES (?, "user", ?)');
        $stmt->execute([$conversationId, $message]);

        $aiResponse = callAI($message);
        
        if (isset($aiResponse['success'])) {
            $aiText = $aiResponse['success'];
        } else {
            $aiText = "Error: " . ($aiResponse['error'] ?? 'Unknown error occurred');
        }
        
        $stmt = db()->prepare('INSERT INTO ai_messages (conversation_id, sender, message) VALUES (?, "ai", ?)');
        $stmt->execute([$conversationId, $aiText]);

        redirect('ai_chat.php?conversation_id=' . $conversationId);
    }
}

$messages = [];
if ($conversationId) {
    $stmt = db()->prepare('SELECT * FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC, id ASC');
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll();
}
?>
<style>
:root {
    --cyan: #00f0ff;
    --violet: #8b7cf6;
    --gold: #f6c453;
    --green: #00ff9c;
    --bg: #03050a;
    --panel: rgba(6,10,20,0.72);
    --text: #e7ecf7;
    --text-dim: #8892b0;
    --border: rgba(255,255,255,0.07);
}

.ai-chat-page {
    background: radial-gradient(ellipse at 20% -10%, #1a2140 0%, var(--bg) 45%, #050710 100%);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(0,240,255,0.08);
    min-height: 500px;
    position: relative;
    overflow: hidden;
}

.ai-chat-page::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: 
        linear-gradient(rgba(0,240,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,240,255,0.03) 1px, transparent 1px);
    background-size: 42px 42px;
    pointer-events: none;
    z-index: 0;
    animation: gridDrift 20s linear infinite;
}
@keyframes gridDrift {
    0% { background-position: 0 0, 0 0; }
    100% { background-position: 42px 42px, 42px 42px; }
}

.ai-chat-page > * { position: relative; z-index: 1; }

.ai-scanline {
    position: absolute;
    left: 0;
    right: 0;
    height: 2px;
    z-index: 1;
    pointer-events: none;
    background: linear-gradient(90deg, transparent, rgba(0,240,255,0.15), transparent);
    animation: scanMove 8s linear infinite;
}
@keyframes scanMove {
    0% { top: -5%; }
    100% { top: 105%; }
}

/* Header */
.ai-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.ai-header h1 {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.8rem;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.ai-header h1 i {
    color: var(--cyan);
    text-shadow: 0 0 20px rgba(0,240,255,0.3);
    animation: pulseGlow 2s ease-in-out infinite;
}
@keyframes pulseGlow {
    0%, 100% { text-shadow: 0 0 20px rgba(0,240,255,0.3); }
    50% { text-shadow: 0 0 40px rgba(0,240,255,0.6); }
}
.ai-header .ai-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 1rem;
    background: rgba(0,240,255,0.06);
    border: 1px solid rgba(0,240,255,0.1);
    border-radius: 20px;
    color: var(--cyan);
    font-size: 0.75rem;
    font-weight: 600;
}
.ai-header .ai-status .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--green);
    animation: pulseDot 1.5s ease-in-out infinite;
}
@keyframes pulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.8); }
}

/* Back button */
.ai-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    color: var(--text-dim);
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}
.ai-back-btn:hover {
    background: rgba(0,240,255,0.06);
    border-color: rgba(0,240,255,0.12);
    color: #fff;
    transform: translateX(-3px);
}

/* Chat Container */
.ai-chat-container {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.04);
    border-radius: 16px;
    padding: 1.5rem;
    min-height: 400px;
    max-height: 550px;
    overflow-y: auto;
    margin-bottom: 1.5rem;
}
.ai-chat-container::-webkit-scrollbar {
    width: 4px;
}
.ai-chat-container::-webkit-scrollbar-track {
    background: transparent;
}
.ai-chat-container::-webkit-scrollbar-thumb {
    background: rgba(0,240,255,0.3);
    border-radius: 10px;
}

.ai-chat-container .empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-dim);
}
.ai-chat-container .empty-state i {
    font-size: 3.5rem;
    color: rgba(0,240,255,0.08);
    display: block;
    margin-bottom: 1rem;
}
.ai-chat-container .empty-state h3 {
    color: #fff;
    font-size: 1.1rem;
    font-family: 'Orbitron', sans-serif;
    margin-bottom: 0.5rem;
}
.ai-chat-container .empty-state p {
    font-size: 0.9rem;
}

/* Messages */
.ai-message {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
    animation: messageSlide 0.3s ease;
}
@keyframes messageSlide {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.ai-message .msg-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
}
.ai-message.user .msg-avatar {
    background: linear-gradient(135deg, var(--violet), var(--cyan));
    color: #0a0e1a;
}
.ai-message.ai .msg-avatar {
    background: linear-gradient(135deg, var(--cyan), var(--violet));
    color: #0a0e1a;
    box-shadow: 0 0 20px rgba(0,240,255,0.2);
}
.ai-message .msg-content {
    flex: 1;
    min-width: 0;
}
.ai-message .msg-bubble {
    padding: 0.75rem 1rem;
    border-radius: 12px;
    font-size: 0.9rem;
    line-height: 1.6;
    color: var(--text);
    word-wrap: break-word;
    white-space: pre-wrap;
}
.ai-message.user .msg-bubble {
    background: rgba(139,124,246,0.12);
    border: 1px solid rgba(139,124,246,0.1);
}
.ai-message.ai .msg-bubble {
    background: rgba(0,240,255,0.06);
    border: 1px solid rgba(0,240,255,0.08);
}
.ai-message .msg-time {
    font-size: 0.6rem;
    color: var(--text-dim);
    margin-top: 0.2rem;
}

/* Typing indicator */
.typing-indicator {
    display: flex;
    gap: 0.5rem;
    padding: 0.5rem 0;
    align-items: center;
    color: var(--text-dim);
    font-size: 0.85rem;
}
.typing-indicator .dots span {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--cyan);
    animation: typingDot 1.4s ease-in-out infinite;
}
.typing-indicator .dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator .dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes typingDot {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.3; }
    30% { transform: translateY(-6px); opacity: 1; }
}

/* Input area */
.ai-input-area {
    display: flex;
    gap: 0.75rem;
    align-items: flex-end;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.04);
    border-radius: 14px;
    padding: 0.5rem;
}
.ai-input-area textarea {
    flex: 1;
    background: transparent;
    border: none;
    color: #fff;
    font-size: 0.95rem;
    resize: none;
    padding: 0.5rem 0.75rem;
    font-family: inherit;
    min-height: 50px;
    max-height: 150px;
}
.ai-input-area textarea:focus {
    outline: none;
}
.ai-input-area textarea::placeholder {
    color: var(--text-dim);
}
.ai-input-area .btn-send {
    padding: 0.6rem 1.2rem;
    background: linear-gradient(135deg, var(--cyan), var(--violet));
    border: none;
    border-radius: 10px;
    color: #0a0e1a;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
    height: 48px;
}
.ai-input-area .btn-send:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(0,240,255,0.3);
}
.ai-input-area .btn-send:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}
.ai-input-area .btn-send i {
    font-size: 0.9rem;
}

/* AI Response formatting */
.ai-message.ai .msg-bubble code {
    background: rgba(0,0,0,0.3);
    padding: 0.1rem 0.3rem;
    border-radius: 4px;
    font-size: 0.85rem;
    color: var(--cyan);
}
.ai-message.ai .msg-bubble pre {
    background: rgba(0,0,0,0.3);
    padding: 0.75rem;
    border-radius: 8px;
    overflow-x: auto;
    font-size: 0.85rem;
    color: var(--green);
    border: 1px solid rgba(255,255,255,0.05);
}
.ai-message.ai .msg-bubble ul, 
.ai-message.ai .msg-bubble ol {
    padding-left: 1.5rem;
}
.ai-message.ai .msg-bubble strong {
    color: var(--gold);
}
.ai-message.ai .msg-bubble h1, 
.ai-message.ai .msg-bubble h2, 
.ai-message.ai .msg-bubble h3 {
    color: var(--cyan);
    font-family: 'Orbitron', sans-serif;
}

@media (max-width: 768px) {
    .ai-chat-page { padding: 1rem; }
    .ai-header h1 { font-size: 1.3rem; }
    .ai-chat-container { max-height: 400px; padding: 1rem; }
    .ai-input-area { flex-direction: column; align-items: stretch; }
    .ai-input-area .btn-send { height: 44px; justify-content: center; }
    .ai-message .msg-avatar { width: 30px; height: 30px; font-size: 0.65rem; }
}
</style>

<div class="ai-chat-page">
    <div class="ai-scanline"></div>

    <div class="ai-header">
        <h1><i class="fas fa-robot"></i> AI Assistant</h1>
        <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
            <div class="ai-status">
                <span class="dot"></span>
                <span>Online</span>
            </div>
            <a href="chat_history.php" class="ai-back-btn">
                <i class="fas fa-clock-rotate-left"></i> History
            </a>
            <a href="knowledge.php" class="ai-back-btn">
                <i class="fas fa-book"></i> Library
            </a>
        </div>
    </div>

    <div class="ai-chat-container" id="chatContainer">
        <?php if (!$conversationId): ?>
            <div class="empty-state">
                <i class="fas fa-message"></i>
                <h3>Start a Conversation</h3>
                <p>Describe your technical problem below and the AI will assist you.</p>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;justify-content:center;margin-top:1rem;">
                    <span style="padding:0.3rem 0.8rem;background:rgba(0,240,255,0.06);border:1px solid rgba(0,240,255,0.08);border-radius:6px;font-size:0.7rem;color:var(--text-dim);">💻 Wi-Fi issues</span>
                    <span style="padding:0.3rem 0.8rem;background:rgba(0,240,255,0.06);border:1px solid rgba(0,240,255,0.08);border-radius:6px;font-size:0.7rem;color:var(--text-dim);">🖨️ Printer problems</span>
                    <span style="padding:0.3rem 0.8rem;background:rgba(0,240,255,0.06);border:1px solid rgba(0,240,255,0.08);border-radius:6px;font-size:0.7rem;color:var(--text-dim);">🖥️ Software errors</span>
                    <span style="padding:0.3rem 0.8rem;background:rgba(0,240,255,0.06);border:1px solid rgba(0,240,255,0.08);border-radius:6px;font-size:0.7rem;color:var(--text-dim);">🔒 Security concerns</span>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $m): ?>
                <div class="ai-message <?= e($m['sender']) ?>">
                    <div class="msg-avatar">
                        <?= $m['sender'] === 'user' ? 'U' : 'AI' ?>
                    </div>
                    <div class="msg-content">
                        <div class="msg-bubble"><?= nl2br(e($m['message'])) ?></div>
                        <div class="msg-time"><?= date('h:i A', strtotime($m['created_at'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form method="post" id="aiForm" style="position:relative;">
        <div class="ai-input-area">
            <textarea name="message" id="aiMessage" rows="1" placeholder="Type your technical issue here..." required></textarea>
            <button type="submit" class="btn-send" id="sendBtn">
                <i class="fas fa-paper-plane"></i> Send
            </button>
        </div>
    </form>
</div>

<script>
// Auto-resize textarea
document.getElementById('aiMessage')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 150) + 'px';
});

// Scroll to bottom
function scrollToBottom() {
    const container = document.getElementById('chatContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}
scrollToBottom();

// Handle form submission with loading state
document.getElementById('aiForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('sendBtn');
    const msg = document.getElementById('aiMessage');
    if (msg.value.trim() === '') {
        e.preventDefault();
        return;
    }
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    // Re-enable after form submit (will redirect)
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
    }, 5000);
});

// Enter key to submit (Shift+Enter for new line)
document.getElementById('aiMessage')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('aiForm').submit();
    }
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>