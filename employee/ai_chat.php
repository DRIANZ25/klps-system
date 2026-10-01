<?php
$page_title = 'AI Assistant';
$page_css = ['employee-ai_chat.css'];
$page_js  = ['employee-ai_chat.js'];
require_once __DIR__ . '/../partials/header.php';

$conversationId = id_param($_GET['conversation_id'] ?? '');
if ($conversationId) {
    $stmt = db()->prepare('SELECT * FROM ai_conversations WHERE ai_conversations_id = ? AND user_id = ?');
    $stmt->execute([$conversationId, $user['users_id']]);
    if (!$stmt->fetch()) $conversationId = 0;
}

$messages = [];
if ($conversationId) {
    $stmt = db()->prepare('SELECT * FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC, CAST(SUBSTRING(ai_messages_id, 2) AS UNSIGNED) ASC');
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll();
}

$contributedKnowledgeId = null;
if ($conversationId) {
    try {
        $stmt = db()->prepare('SELECT contributed_knowledge_id FROM ai_conversations WHERE ai_conversations_id = ?');
        $stmt->execute([$conversationId]);
        $contributedKnowledgeId = $stmt->fetchColumn() ?: null;
    } catch (PDOException $e) {}
}

$hasAiReply = false;
foreach ($messages as $m) {
    if ($m['sender'] === 'ai') { $hasAiReply = true; break; }
}

$suggestions = [
    ['icon' => 'fa-wifi',                'label' => 'Wi-Fi keeps disconnecting',  'prompt' => 'My Wi-Fi keeps disconnecting. How do I fix it?'],
    ['icon' => 'fa-print',               'label' => 'Printer shows offline',       'prompt' => "My printer shows offline but it's turned on. Help me fix it."],
    ['icon' => 'fa-gauge-high',          'label' => 'Laptop running slow',         'prompt' => 'My laptop is running very slow. What should I check first?'],
    ['icon' => 'fa-triangle-exclamation','label' => 'Windows blue screen',         'prompt' => 'Blue screen of death on Windows 11 after an update. What do I do?'],
    ['icon' => 'fa-shield-halved',       'label' => 'Suspicious phishing email',   'prompt' => 'I think I received a phishing email. What steps should I take?'],
    ['icon' => 'fa-file-excel',          'label' => 'Excel freezes on big files',  'prompt' => 'Excel freezes when opening a large file. How can I fix it?'],
    ['icon' => 'fa-network-wired',       'label' => 'VPN keeps dropping',          'prompt' => 'My VPN connection drops every few minutes. What can I do?'],
    ['icon' => 'fa-server',              'label' => 'Server running out of space', 'prompt' => 'My server is running low on disk space. How should I clean it up?'],
    ['icon' => 'fa-lock',                'label' => 'Forgot my password',          'prompt' => "I forgot my account password. What's the safest way to reset it?"],
    ['icon' => 'fa-database',            'label' => 'Database is running slow',    'prompt' => 'My database queries are suddenly very slow. What should I check?'],
];
?>

<div class="ai-chat-page">
    <div class="ai-bg">
        <div class="grid"></div>
        <div class="aurora a1"></div>
        <div class="aurora a2"></div>
    </div>
    <div class="ai-scenery" id="aiScenery"></div>

    <!-- ============ HEADER ============ -->
    <div class="ai-header">
        <h1>
            <span class="logo-badge"><i class="fas fa-robot"></i></span>
            AI Assistant
        </h1>
        <div style="display:flex;gap:0.65rem;align-items:center;flex-wrap:wrap;">
            <div class="ai-live-status">
                <span class="pulse-ring"></span>
                <span class="pulse-dot"></span>
                <span class="status-text" id="aiStatusText">Listening</span>
            </div>
            <a href="chat_history.php" class="ai-back-btn">
                <i class="fas fa-clock-rotate-left"></i> History
            </a>
            <a href="knowledge.php" class="ai-back-btn">
                <i class="fas fa-book"></i> Library
            </a>
            <?php if ($conversationId && $hasAiReply): ?>
                <?php if ($contributedKnowledgeId): ?>
                    <a href="knowledge_view.php?id=<?= e($contributedKnowledgeId) ?>" class="ai-contribute-btn contributed">
                        <i class="fas fa-circle-check"></i> In Library
                    </a>
                <?php else: ?>
                    <a href="contribute_knowledge.php?conversation_id=<?= $conversationId ?>" class="ai-contribute-btn">
                        <i class="fas fa-book-open"></i> Contribute to Library
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============ THE FRAME ============ -->
    <div class="ai-frame">
        <div class="ai-frame-inner">

            <!-- LEFT: vertical news ticker -->
            <aside class="ai-ticker-column">
                <div class="ai-ticker-header">
                    <i class="fas fa-bolt"></i>
                    <span class="label">Quick Prompts</span>
                    <span class="live-badge">
                        <span class="live-dot"></span>
                        Live
                    </span>
                </div>
                <div class="ai-ticker-viewport">
                    <div class="ai-ticker-track" id="aiTickerTrack">
                        <?php
                        for ($pass = 0; $pass < 2; $pass++):
                            foreach ($suggestions as $idx => $s):
                        ?>
                            <button type="button" class="ticker-item"
                                    data-prompt="<?= e($s['prompt']) ?>"
                                    aria-label="<?= e($s['label']) ?>">
                                <span class="ticker-icon"><i class="fas <?= e($s['icon']) ?>"></i></span>
                                <span class="ticker-text"><?= e($s['label']) ?></span>
                            </button>
                        <?php
                            endforeach;
                        endfor;
                        ?>
                    </div>
                </div>
            </aside>

            <!-- RIGHT: chat area + pinned input -->
            <div class="ai-chat-area">

                <!-- Scrolling message viewport -->
                <div class="ai-chat-scroll" id="chatContainer"
                     data-user-pic="<?= e($profilePic) ?>"
                     data-user-initial="<?= e(strtoupper(mb_substr($user['full_name'] ?? '?', 0, 1))) ?>">
                    <?php if (!$conversationId): ?>
                        <div class="ai-empty-state" id="aiEmptyState">
                            <div class="ai-head-3d">
                                <div class="holo-base"></div>
                                <div class="holo-ring r1"><span class="holo-spark"></span></div>
                                <div class="holo-ring r2"><span class="holo-spark"></span></div>
                                <div class="holo-ring r3"><span class="holo-spark"></span></div>
                                <canvas id="aiHeadCanvas"></canvas>
                            </div>
                            <h3>Hi! I'm your <span class="accent">AI Assistant</span></h3>
                            <p>Ask me anything about IT and technology. I'll help you troubleshoot problems, explain concepts, and find the fix you need.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                            <div class="ai-message <?= e($m['sender']) ?>">
                                <div class="msg-avatar">
                                    <?php if ($m['sender'] === 'user'): ?>
                                        <?php if ($profilePic && file_exists(__DIR__ . '/../uploads/profiles/' . $user['profile_picture'])): ?>
                                            <img src="<?= e($profilePic) ?>" alt="<?= e($user['full_name']) ?>">
                                        <?php else: ?>
                                            <?= e(strtoupper(mb_substr($user['full_name'] ?? '?', 0, 1))) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <i class="fas fa-robot" aria-hidden="true"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="msg-content">
                                    <?php if ($m['sender'] === 'ai'): ?>
                                        <div class="msg-bubble markdown-body" data-raw="<?= e($m['message']) ?>"></div>
                                    <?php else: ?>
                                        <div class="msg-bubble"><?= nl2br(e($m['message'])) ?></div>
                                    <?php endif; ?>
                                    <div class="msg-time"><i class="far fa-clock"></i><?= date('h:i A', strtotime($m['created_at'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Input dock, pinned to bottom of the frame -->
                <div class="ai-input-dock">
                    <form id="aiForm" autocomplete="off">
                        <input type="hidden" name="conversation_id" id="conversationIdInput" value="<?= e($conversationId) ?>">
                        <div class="ai-input-area">
                            <textarea name="message" id="aiMessage" rows="1" placeholder="Type your technical issue here..." required></textarea>
                            <button type="submit" class="btn-send" id="sendBtn">
                                <i class="fas fa-paper-plane"></i> <span class="btn-send-label">Send</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Markdown + Sanitizer -->
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.11/dist/purify.min.js"></script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>