<?php
$page_title = 'Chat History';
$page_css = ['employee-chat_history.css'];
$page_js  = ['employee-chat_history.js'];
require_once __DIR__ . '/../partials/header.php';

$currentUserId = $_SESSION['user']['users_id'];

$stmt = db()->prepare('SELECT * FROM ai_conversations WHERE user_id = ? ORDER BY updated_at DESC');
$stmt->execute([$currentUserId]);
$conversations = $stmt->fetchAll();

$messageCounts = [];
$lastMessages  = [];
$contributedMap = [];

foreach ($conversations as $c) {
    $cid = $c['ai_conversations_id'];

    $stmt = db()->prepare('SELECT COUNT(*) FROM ai_messages WHERE conversation_id = ?');
    $stmt->execute([$cid]);
    $messageCounts[$cid] = (int)$stmt->fetchColumn();

    $stmt = db()->prepare('SELECT message, sender, created_at FROM ai_messages WHERE conversation_id = ? ORDER BY created_at DESC, CAST(SUBSTRING(ai_messages_id, 2) AS UNSIGNED) DESC LIMIT 1');
    $stmt->execute([$cid]);
    $lastMessages[$cid] = $stmt->fetch() ?: null;

    $contributedMap[$cid] = null;
    try {
        $stmt = db()->prepare('SELECT contributed_knowledge_id FROM ai_conversations WHERE ai_conversations_id = ?');
        $stmt->execute([$cid]);
        $contributedMap[$cid] = $stmt->fetchColumn() ?: null;
    } catch (PDOException $e) {}
}

$totalMessages = array_sum($messageCounts);
$totalConversations = count($conversations);

/**
 * Auto-detect a topic for a conversation by scanning its messages for keywords.
 * Returns [label, icon, color-key] or null if nothing matched.
 */
function detectTopic(array $messages): ?array {
    $bag = '';
    foreach ($messages as $m) $bag .= ' ' . strtolower($m['message']);

    $topics = [
        'wi-fi'      => ['label' => 'Wi-Fi',      'icon' => 'fa-wifi',            'color' => 'cyan'],
        'wifi'       => ['label' => 'Wi-Fi',      'icon' => 'fa-wifi',            'color' => 'cyan'],
        'printer'    => ['label' => 'Printer',    'icon' => 'fa-print',           'color' => 'violet'],
        'network'    => ['label' => 'Network',    'icon' => 'fa-network-wired',   'color' => 'cyan'],
        'vpn'        => ['label' => 'VPN',        'icon' => 'fa-lock',            'color' => 'violet'],
        'excel'      => ['label' => 'Excel',      'icon' => 'fa-file-excel',      'color' => 'green'],
        'word'       => ['label' => 'Word',       'icon' => 'fa-file-word',       'color' => 'cyan'],
        'outlook'    => ['label' => 'Outlook',    'icon' => 'fa-envelope',        'color' => 'violet'],
        'email'      => ['label' => 'Email',      'icon' => 'fa-envelope',        'color' => 'violet'],
        'phishing'   => ['label' => 'Security',   'icon' => 'fa-shield-halved',   'color' => 'red'],
        'virus'      => ['label' => 'Security',   'icon' => 'fa-shield-halved',   'color' => 'red'],
        'malware'    => ['label' => 'Security',   'icon' => 'fa-shield-halved',   'color' => 'red'],
        'password'   => ['label' => 'Account',    'icon' => 'fa-key',             'color' => 'gold'],
        'login'      => ['label' => 'Account',    'icon' => 'fa-key',             'color' => 'gold'],
        'account'    => ['label' => 'Account',    'icon' => 'fa-key',             'color' => 'gold'],
        'battery'    => ['label' => 'Battery',    'icon' => 'fa-battery-half',    'color' => 'green'],
        'charger'    => ['label' => 'Battery',    'icon' => 'fa-battery-half',    'color' => 'green'],
        'laptop'     => ['label' => 'Laptop',     'icon' => 'fa-laptop',          'color' => 'cyan'],
        'slow'       => ['label' => 'Performance','icon' => 'fa-gauge-high',      'color' => 'gold'],
        'performance'=> ['label' => 'Performance','icon' => 'fa-gauge-high',      'color' => 'gold'],
        'blue screen'=> ['label' => 'Crash',      'icon' => 'fa-triangle-exclamation', 'color' => 'red'],
        'bsod'       => ['label' => 'Crash',      'icon' => 'fa-triangle-exclamation', 'color' => 'red'],
        'crash'      => ['label' => 'Crash',      'icon' => 'fa-triangle-exclamation', 'color' => 'red'],
        'server'     => ['label' => 'Server',     'icon' => 'fa-server',          'color' => 'violet'],
        'backup'     => ['label' => 'Backup',     'icon' => 'fa-cloud-arrow-up',  'color' => 'cyan'],
        'cloud'      => ['label' => 'Cloud',      'icon' => 'fa-cloud',           'color' => 'cyan'],
        'software'   => ['label' => 'Software',   'icon' => 'fa-code',            'color' => 'violet'],
        'update'     => ['label' => 'Updates',    'icon' => 'fa-arrows-rotate',   'color' => 'gold'],
        'driver'     => ['label' => 'Drivers',    'icon' => 'fa-microchip',       'color' => 'violet'],
        'database'   => ['label' => 'Database',   'icon' => 'fa-database',        'color' => 'cyan'],
        'monitor'    => ['label' => 'Display',    'icon' => 'fa-desktop',         'color' => 'cyan'],
        'keyboard'   => ['label' => 'Peripheral', 'icon' => 'fa-keyboard',        'color' => 'violet'],
        'mouse'      => ['label' => 'Peripheral', 'icon' => 'fa-computer-mouse',  'color' => 'violet'],
    ];

foreach ($topics as $kw => $meta) {
        // Word-boundary match: a plain strpos makes 'word' match inside
        // 'password' and 'printer' match inside 'printer firmware', which
        // labels an unrelated conversation with the wrong topic.
        if (preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $bag)) return $meta;
    }
    return null;
}

function chTimeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', strtotime($timestamp));
}

/**
 * Grab all messages for a conversation so we can detect a topic.
 * (Done inline per-conversation so we don't waste memory on huge histories.)
 */
function getConversationTopic($conversationId): ?array {
    try {
        $stmt = db()->prepare('SELECT sender, message FROM ai_messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 30');
        $stmt->execute([$conversationId]);
        return detectTopic($stmt->fetchAll());
    } catch (PDOException $e) {
        return null;
    }
}
?>

<div class="ch-page">
    <div class="ch-bg">
        <div class="grid"></div>
        <div class="aurora a1"></div>
        <div class="aurora a2"></div>
    </div>
    <div class="ch-scenery" id="chScenery"></div>

    <!-- Header -->
    <div class="ch-header">
        <div class="ch-header-left">
            <div>
                <h1>
                    <span class="logo-badge"><i class="fas fa-clock-rotate-left"></i></span>
                    Chat History
                </h1>
                <p>Every AI conversation you've had — searchable, tagged, and yours to manage.</p>
            </div>
        </div>

        <div style="display:flex;gap:0.85rem;align-items:center;flex-wrap:wrap;">
            <div class="ch-mini-stats">
                <div class="ch-mini-stat">
                    <i class="conv"><i class="fas fa-comments"></i></i>
                    <div class="ch-mini-stat-info">
                        <span class="val"><?= (int)$totalConversations ?></span>
                        <span class="lbl">Chats</span>
                    </div>
                </div>
                <div class="ch-mini-stat">
                    <i class="msg"><i class="fas fa-message"></i></i>
                    <div class="ch-mini-stat-info">
                        <span class="val"><?= (int)$totalMessages ?></span>
                        <span class="lbl">Messages</span>
                    </div>
                </div>
            </div>
            <a href="ai_chat.php" class="ch-new-btn">
                <span class="btn-logo"><i class="fas fa-robot"></i></span>
                New Chat
            </a>
        </div>
    </div>

    <?php if (empty($conversations)): ?>
        <!-- Empty state -->
        <div class="ch-empty">
            <div class="empty-icon"><i class="fas fa-comment-slash"></i></div>
            <h3>No Conversations Yet</h3>
            <p>Start your first AI chat by describing a technical problem. Every conversation you have will be saved here so you can revisit or contribute it later.</p>
            <a href="ai_chat.php" class="btn-start">
                <span class="btn-logo"><i class="fas fa-robot"></i></span>
                Start Your First Chat
            </a>
        </div>
    <?php else: ?>

        <!-- Toolbar -->
        <div class="ch-toolbar">
            <div class="ch-search">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="chSearchInput" placeholder="Search conversations by title, message, or topic...">
            </div>
            <select class="ch-select" id="chSortSelect">
                <option value="recent">Most Recent</option>
                <option value="oldest">Oldest First</option>
                <option value="messages">Most Messages</option>
                <option value="az">Title A–Z</option>
            </select>
            <div class="ch-count-badge" id="chCountBadge">
                <i class="fas fa-filter"></i>
                <span><strong id="chVisibleCount"><?= $totalConversations ?></strong> shown</span>
            </div>
        </div>

        <!-- Grid -->
        <div class="ch-grid" id="chGrid">
            <?php foreach ($conversations as $i => $c):
  $cid       = $c['ai_conversations_id'];
                $msgCount  = $messageCounts[$cid] ?? 0;
                $lastMsg   = $lastMessages[$cid] ?? null;
                $contributedId = $contributedMap[$cid] ?? null;

                $preview = 'No messages yet';
                $fromLabel = '';
                if ($lastMsg) {
                    $preview = mb_substr($lastMsg['message'], 0, 100) . (mb_strlen($lastMsg['message']) > 100 ? '…' : '');
                    $fromLabel = $lastMsg['sender'] === 'user' ? 'You:' : 'AI:';
                }

                $topic = getConversationTopic($cid);
                $timeAgo = chTimeAgo($c['updated_at']);
            ?>
                <div class="ch-card"
                     data-conversation-id="<?= $cid ?>"
                     data-title="<?= e(strtolower($c['title'])) ?>"
                     data-preview="<?= e(strtolower($preview)) ?>"
                     data-topic="<?= $topic ? e(strtolower($topic['label'])) : '' ?>"
                     data-messages="<?= (int)$msgCount ?>"
                     data-updated="<?= strtotime($c['updated_at']) ?>"
                     data-href="ai_chat.php?conversation_id=<?= $cid ?>"
                     style="animation-delay: <?= min($i * 0.03, 0.6) ?>s;">

                    <div class="ch-card-spine"></div>

                    <div class="ch-card-body">
                        <div class="ch-card-top">
                            <div class="ch-card-title"><?= e($c['title']) ?></div>
                            <div style="display:flex;gap:0.35rem;flex-direction:column;align-items:flex-end;">
                                <span class="ch-card-badge msg">
                                    <i class="fas fa-message"></i> <?= (int)$msgCount ?>
                                </span>
                                <?php if ($contributedId): ?>
                                    <span class="ch-card-badge library" title="This conversation is in the Knowledge Library">
                                        <i class="fas fa-circle-check"></i> Library
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($topic): ?>
                            <div class="ch-topic <?= e($topic['color']) ?>">
                                <i class="fas <?= e($topic['icon']) ?>"></i>
                                <?= e($topic['label']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="ch-preview">
                            <?php if ($fromLabel): ?>
                                <span class="from-label"><?= e($fromLabel) ?></span>
                            <?php endif; ?>
                            <?= e($preview) ?>
                        </div>

                        <div class="ch-card-footer">
                            <div class="ch-card-meta">
                                <span class="meta-item"><i class="far fa-clock"></i> <?= e($timeAgo) ?></span>
                                <span class="meta-item"><i class="fas fa-calendar"></i> <?= date('M j', strtotime($c['created_at'])) ?></span>
                            </div>
                            <div class="ch-card-actions" onclick="event.stopPropagation();">
                                <a href="ai_chat.php?conversation_id=<?= $cid ?>" class="ch-action open">
                                    <i class="fas fa-arrow-right"></i> <span>Open</span>
                                </a>
                                <button type="button"
                                        class="ch-action delete"
                                        data-delete-id="<?= $cid ?>"
                                        data-delete-title="<?= e($c['title']) ?>"
                                        title="Delete conversation">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- No-results placeholder (hidden by default) -->
            <div class="ch-no-results" id="chNoResults" style="display:none;">
                <i class="fas fa-search-minus"></i>
                <strong style="color:#fff;display:block;margin-bottom:0.35rem;">No conversations match your search</strong>
                Try a different keyword or clear the search box.
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Delete confirmation modal -->
<div class="ch-modal-overlay" id="chDeleteModal">
    <div class="ch-modal">
        <div class="ch-modal-icon"><i class="fas fa-triangle-exclamation"></i></div>
        <h3>Delete Conversation</h3>
        <p>You're about to permanently delete <strong id="chDeleteTitle">this conversation</strong>. All messages in it will be removed. This cannot be undone.</p>
        <div class="ch-modal-actions">
            <button type="button" class="ch-modal-btn cancel" id="chDeleteCancel">Cancel</button>
            <button type="button" class="ch-modal-btn confirm" id="chDeleteConfirm">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>