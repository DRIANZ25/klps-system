<?php
$page_title = 'Chat History';
require_once __DIR__ . '/../partials/header.php';

$stmt = db()->prepare('SELECT * FROM ai_conversations WHERE user_id = ? ORDER BY updated_at DESC');
$stmt->execute([$user['id']]);
$conversations = $stmt->fetchAll();

// Get message count for each conversation
$messageCounts = [];
foreach ($conversations as $c) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM ai_messages WHERE conversation_id = ?');
    $stmt->execute([$c['id']]);
    $messageCounts[$c['id']] = (int)$stmt->fetchColumn();
}

// Get last message for each conversation
$lastMessages = [];
foreach ($conversations as $c) {
    $stmt = db()->prepare('SELECT message, sender, created_at FROM ai_messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$c['id']]);
    $lastMessages[$c['id']] = $stmt->fetch();
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

.chat-history-page {
    background: radial-gradient(ellipse at 20% -10%, #1a2140 0%, var(--bg) 45%, #050710 100%);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(0,240,255,0.08);
    min-height: 500px;
    position: relative;
    overflow: hidden;
}

.chat-history-page::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: 
        linear-gradient(rgba(0,240,255,0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,240,255,0.02) 1px, transparent 1px);
    background-size: 42px 42px;
    pointer-events: none;
    z-index: 0;
    animation: gridDrift 20s linear infinite;
}
@keyframes gridDrift {
    0% { background-position: 0 0, 0 0; }
    100% { background-position: 42px 42px, 42px 42px; }
}

.chat-history-page > * { position: relative; z-index: 1; }

.chat-history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.chat-history-header h1 {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.8rem;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.chat-history-header h1 i {
    color: var(--gold);
    text-shadow: 0 0 20px rgba(246,196,83,0.3);
}
.chat-history-header .stats {
    display: flex;
    gap: 1.5rem;
    color: var(--text-dim);
    font-size: 0.85rem;
}
.chat-history-header .stats strong {
    color: #fff;
}
.chat-history-header .stats i {
    color: var(--cyan);
}
.chat-history-header .btn-new {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.2rem;
    background: linear-gradient(135deg, var(--cyan), var(--violet));
    border: none;
    border-radius: 10px;
    color: #0a0e1a;
    font-weight: 700;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.3s ease;
}
.chat-history-header .btn-new:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(0,240,255,0.3);
}

/* Conversation Cards Grid */
.conversations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.25rem;
}

.conversation-card {
    background: linear-gradient(160deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: block;
}
.conversation-card:hover {
    transform: translateY(-4px);
    border-color: rgba(0,240,255,0.12);
    box-shadow: 0 16px 30px -12px rgba(0,0,0,0.5);
}
.conversation-card .card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}
.conversation-card .card-title {
    font-weight: 600;
    color: #fff;
    font-size: 1rem;
    flex: 1;
    margin-right: 0.5rem;
}
.conversation-card .card-badge {
    font-size: 0.6rem;
    padding: 0.15rem 0.6rem;
    border-radius: 10px;
    background: rgba(0,240,255,0.08);
    color: var(--cyan);
    white-space: nowrap;
    flex-shrink: 0;
}
.conversation-card .card-preview {
    color: var(--text-dim);
    font-size: 0.85rem;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 0.75rem;
}
.conversation-card .card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255,255,255,0.04);
    font-size: 0.7rem;
    color: var(--text-dim);
}
.conversation-card .card-footer .msg-count {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.conversation-card .card-footer .msg-count i {
    color: var(--cyan);
}
.conversation-card .card-footer .time {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.conversation-card .card-footer .time i {
    color: var(--gold);
}
.conversation-card .card-actions {
    display: flex;
    gap: 0.3rem;
    margin-top: 0.5rem;
}
.conversation-card .card-actions .btn-open {
    padding: 0.3rem 0.8rem;
    background: rgba(0,240,255,0.08);
    border: 1px solid rgba(0,240,255,0.08);
    border-radius: 6px;
    color: var(--cyan);
    text-decoration: none;
    font-size: 0.7rem;
    font-weight: 600;
    transition: all 0.2s ease;
}
.conversation-card .card-actions .btn-open:hover {
    background: rgba(0,240,255,0.15);
}
.conversation-card .card-actions .btn-delete {
    padding: 0.3rem 0.6rem;
    background: rgba(239,68,68,0.05);
    border: 1px solid rgba(239,68,68,0.08);
    border-radius: 6px;
    color: #ef4444;
    text-decoration: none;
    font-size: 0.7rem;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none;
}
.conversation-card .card-actions .btn-delete:hover {
    background: rgba(239,68,68,0.12);
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-dim);
}
.empty-state i {
    font-size: 4rem;
    color: rgba(0,240,255,0.05);
    display: block;
    margin-bottom: 1.5rem;
}
.empty-state h3 {
    color: #fff;
    font-size: 1.25rem;
    font-family: 'Orbitron', sans-serif;
    margin-bottom: 0.5rem;
}
.empty-state p {
    font-size: 0.95rem;
}
.empty-state .btn-start {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.7rem 1.8rem;
    background: linear-gradient(135deg, var(--cyan), var(--violet));
    border: none;
    border-radius: 10px;
    color: #0a0e1a;
    font-weight: 700;
    text-decoration: none;
}
.empty-state .btn-start:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(0,240,255,0.3);
}

@media (max-width: 768px) {
    .chat-history-page { padding: 1rem; }
    .chat-history-header h1 { font-size: 1.3rem; }
    .conversations-grid { grid-template-columns: 1fr; }
    .chat-history-header .stats { gap: 1rem; font-size: 0.75rem; }
}
</style>

<div class="chat-history-page">
    <div class="chat-history-header">
        <h1><i class="fas fa-clock-rotate-left"></i> Chat History</h1>
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            <div class="stats">
                <span><i class="fas fa-comments"></i> <strong><?= count($conversations) ?></strong> Conversations</span>
                <?php 
                $totalMessages = 0;
                foreach ($messageCounts as $count) {
                    $totalMessages += $count;
                }
                ?>
                <span><i class="fas fa-message"></i> <strong><?= $totalMessages ?></strong> Messages</span>
            </div>
            <a href="ai_chat.php" class="btn-new">
                <i class="fas fa-plus"></i> New Chat
            </a>
        </div>
    </div>

    <?php if (empty($conversations)): ?>
        <div class="empty-state">
            <i class="fas fa-comment-slash"></i>
            <h3>No Conversations Yet</h3>
            <p>Start your first AI chat by describing a technical problem.</p>
            <a href="ai_chat.php" class="btn-start"><i class="fas fa-robot"></i> Start a Chat</a>
        </div>
    <?php else: ?>
        <div class="conversations-grid">
            <?php foreach ($conversations as $c): 
                $msgCount = $messageCounts[$c['id']] ?? 0;
                $lastMsg = $lastMessages[$c['id']] ?? null;
                $preview = $lastMsg ? mb_substr($lastMsg['message'], 0, 80) . (mb_strlen($lastMsg['message']) > 80 ? '…' : '') : 'No messages yet';
                $sender = $lastMsg ? ($lastMsg['sender'] === 'user' ? 'You: ' : 'AI: ') : '';
            ?>
                <div class="conversation-card" href="ai_chat.php?conversation_id=<?= $c['id'] ?>">
                    <div class="card-top">
                        <span class="card-title"><?= e($c['title']) ?></span>
                        <span class="card-badge"><i class="fas fa-<?= $msgCount > 0 ? 'message' : 'comment' ?>"></i> <?= $msgCount ?></span>
                    </div>
                    <div class="card-preview"><?= e($sender . $preview) ?></div>
                    <div class="card-footer">
                        <div class="msg-count">
                            <i class="fas fa-<?= $msgCount > 0 ? 'message' : 'comment' ?>"></i>
                            <?= $msgCount ?> messages
                        </div>
                        <div class="time">
                            <i class="fas fa-clock"></i>
                            <?= date('M d, h:i A', strtotime($c['updated_at'])) ?>
                        </div>
                    </div>
                    <div class="card-actions">
                        <a href="ai_chat.php?conversation_id=<?= $c['id'] ?>" class="btn-open">
                            <i class="fas fa-arrow-right"></i> Open
                        </a>
                        <button class="btn-delete" onclick="deleteConversation(<?= $c['id'] ?>, '<?= addslashes($c['title']) ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function deleteConversation(id, title) {
    if(confirm('Delete "' + title + '"? This will permanently remove all messages.')) {
        fetch('delete_conversation.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert('Failed to delete conversation.');
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
    }
}
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>