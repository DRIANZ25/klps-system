<?php
$page_title = 'Knowledge Article';
$page_css = ['employee-knowledge_view.css'];
$page_js  = ['employee-knowledge_view.js'];
require_once __DIR__ . '/../partials/header.php';

$id = id_param($_GET['id'] ?? '');
if ($id === '') redirect('knowledge.php');

$currentUserId = $_SESSION['user']['users_id'] ?? '';

function trackView($knowledgeId, $userId) {
    // One view row per user per article: the DB is the source of truth so a new
    // login/session cannot insert the same view again.
    if ($userId !== '') {
        try {
            $stmt = db()->prepare('SELECT 1 FROM knowledge_views WHERE knowledge_id = ? AND user_id = ? LIMIT 1');
            $stmt->execute([$knowledgeId, $userId]);
            if ($stmt->fetchColumn()) return;
        } catch (PDOException $e) {}
    } else {
        $sessionKey = 'viewed_articles';
        if (!isset($_SESSION[$sessionKey])) $_SESSION[$sessionKey] = [];
        if (in_array($knowledgeId, $_SESSION[$sessionKey])) return;
    }
    try {
        $stmt = db()->prepare('UPDATE knowledge SET view_count = view_count + 1 WHERE knowledge_id = ?');
        $stmt->execute([$knowledgeId]);
        if ($userId === '') $_SESSION['viewed_articles'][] = $knowledgeId;
        $stmt = db()->prepare('INSERT INTO knowledge_views (knowledge_views_id, knowledge_id, user_id) VALUES (?, ?, ?)');
        $stmt->execute([new_id('knowledge_views'), $knowledgeId, $userId ?: null]);
    } catch (PDOException $e) {}
}
trackView($id, $currentUserId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment'] ?? '');
    $parent_id = id_param($_POST['parent_id'] ?? '');
    $anchor = 'comments';

    if ($comment) {
        try {
            $commentId = new_id('knowledge_comments');
            $stmt = db()->prepare('INSERT INTO knowledge_comments (knowledge_comments_id, user_id, knowledge_id, parent_id, comment) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$commentId, $currentUserId, $id, $parent_id ?: null, $comment]);

            $stmt = db()->prepare('SELECT created_by, title FROM knowledge WHERE knowledge_id = ?');
            $stmt->execute([$id]);
            $article = $stmt->fetch();
            $userName = $_SESSION['user']['full_name'] ?? 'Someone';

            $anchor = $parent_id !== '' ? 'reply-' . $commentId : 'comment-' . $commentId;

            if ($parent_id !== '') {
                $stmt = db()->prepare('SELECT user_id, parent_id FROM knowledge_comments WHERE knowledge_comments_id = ?');
                $stmt->execute([$parent_id]);
                $parentComment = $stmt->fetch();
                $parentAnchor = (!empty($parentComment['parent_id']) ? 'reply-' : 'comment-') . $parent_id;
                if ($parentComment && $parentComment['user_id'] != $currentUserId) {
                    try {
                        $stmt = db()->prepare('INSERT INTO notifications (notifications_id, user_id, from_user_id, type, message, link) VALUES (?, ?, ?, "comment", ?, ?)');
                        $stmt->execute([new_id('notifications'), $parentComment['user_id'], $currentUserId, $userName . ' replied to your comment on "' . $article['title'] . '"', 'knowledge_view.php?id=' . $id . '#' . $parentAnchor]);
                    } catch (PDOException $e) {}
                }
            }

            if ($article && $article['created_by'] != $currentUserId) {
                $skipArticleNotification = false;
                if ($parent_id !== '') {
                    $stmt = db()->prepare('SELECT user_id FROM knowledge_comments WHERE knowledge_comments_id = ?');
                    $stmt->execute([$parent_id]);
                    $parentComment = $stmt->fetch();
                    if ($parentComment && $parentComment['user_id'] == $article['created_by']) $skipArticleNotification = true;
                }
                if (!$skipArticleNotification) {
                    try {
                        $stmt = db()->prepare('INSERT INTO notifications (notifications_id, user_id, from_user_id, type, message, link) VALUES (?, ?, ?, "comment", ?, ?)');
                        $stmt->execute([new_id('notifications'), $article['created_by'], $currentUserId, $userName . ' commented on your article "' . $article['title'] . '"', 'knowledge_view.php?id=' . $id . '#comment-' . $commentId]);
                    } catch (PDOException $e) {}
                }
            }
            $_SESSION['success'] = 'Comment posted successfully!';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to add comment: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Please enter a comment.';
    }
    redirect('knowledge_view.php?id=' . $id . '#' . $anchor);
}

if (isset($_GET['delete_comment']) && isset($_GET['cid'])) {
    $cid = id_param($_GET['cid'] ?? '');
    if ($cid !== '') {
        try {
        $stmt = db()->prepare('SELECT user_id FROM knowledge_comments WHERE knowledge_comments_id = ?');
        $stmt->execute([$cid]);
        $comment = $stmt->fetch();
        if ($comment && ($comment['user_id'] == $currentUserId || $_SESSION['user']['role'] === 'admin')) {
            $stmt = db()->prepare('DELETE FROM knowledge_comments WHERE knowledge_comments_id = ?');
            $stmt->execute([$cid]);
            $_SESSION['success'] = 'Comment deleted.';
        }
        } catch (PDOException $e) {}
    }
    redirect('knowledge_view.php?id=' . $id . '#comments');
}

$votingEnabled = true;
$k = null;
try {
    $stmt = db()->prepare(
        'SELECT k.*, kc.name AS category_name, d.name AS department_name, u.full_name AS author,
                u.profile_picture AS author_profile_picture,
                (SELECT COUNT(*) FROM knowledge_votes kv WHERE kv.knowledge_id = k.knowledge_id AND kv.vote = "helpful") AS helpful_count,
                (SELECT COUNT(*) FROM knowledge_votes kv2 WHERE kv2.knowledge_id = k.knowledge_id AND kv2.vote = "not_helpful") AS not_helpful_count,
                (SELECT vote FROM knowledge_votes kv3 WHERE kv3.knowledge_id = k.knowledge_id AND kv3.user_id = ?) AS my_vote,
                (SELECT 1 FROM knowledge_saves ks WHERE ks.knowledge_id = k.knowledge_id AND ks.user_id = ?) AS is_saved,
                (SELECT COUNT(*) FROM knowledge_comments c WHERE c.knowledge_id = k.knowledge_id AND c.parent_id IS NULL) AS comment_count
         FROM knowledge k
         LEFT JOIN knowledge_categories kc ON kc.knowledge_categories_id = k.category_id
         LEFT JOIN departments d ON d.departments_id = k.department_id
         JOIN users u ON u.users_id = k.created_by
         WHERE k.knowledge_id = ? AND k.status = "active" LIMIT 1'
    );
    $stmt->execute([$currentUserId, $currentUserId, $id]);
    $k = $stmt->fetch();
} catch (PDOException $e) {
    $votingEnabled = false;
    $stmt = db()->prepare('SELECT k.*, kc.name AS category_name, d.name AS department_name, u.full_name AS author, u.profile_picture AS author_profile_picture FROM knowledge k LEFT JOIN knowledge_categories kc ON kc.knowledge_categories_id = k.category_id LEFT JOIN departments d ON d.departments_id = k.department_id JOIN users u ON u.users_id = k.created_by WHERE k.knowledge_id = ? AND k.status = "active" LIMIT 1');
    $stmt->execute([$id]);
    $k = $stmt->fetch();
}

$comments = [];
if ($k) {
    try {
        $stmt = db()->prepare(
            'SELECT c.*, u.full_name, u.profile_picture, u.role,
                    CASE WHEN c.parent_id IS NOT NULL THEN 1 ELSE 0 END AS is_reply
             FROM knowledge_comments c
             JOIN users u ON u.users_id = c.user_id
             WHERE c.knowledge_id = ?
             ORDER BY CAST(SUBSTRING(CASE WHEN c.parent_id IS NULL THEN c.knowledge_comments_id ELSE c.parent_id END, 3) AS UNSIGNED) DESC,
                      c.created_at ASC'
        );
        $stmt->execute([$id]);
        $allComments = $stmt->fetchAll();

        $replies = [];
        foreach ($allComments as $c) {
            if ($c['parent_id'] === null || $c['parent_id'] === '' || $c['parent_id'] === '0') {
                $c['replies'] = [];
                $comments[$c['knowledge_comments_id']] = $c;
            } else {
                $replies[] = $c;
            }
        }
        foreach ($replies as $reply) {
            if (isset($comments[$reply['parent_id']])) {
                $comments[$reply['parent_id']]['replies'][] = $reply;
            }
        }
        $comments = array_values($comments);
    } catch (PDOException $e) { $comments = []; }
}

$related = [];
if ($k && !empty($k['category_id'])) {
    $stmt = db()->prepare('SELECT knowledge_id, title FROM knowledge WHERE category_id = ? AND knowledge_id != ? AND status = "active" ORDER BY updated_at DESC LIMIT 5');
    $stmt->execute([$k['category_id'], $id]);
    $related = $stmt->fetchAll();
}

function klps_category_icon(?string $name): string {
    $name = strtolower($name ?? '');
    $map = [
        'network' => 'fa-network-wired', 'security' => 'fa-shield-halved', 'database' => 'fa-database',
        'hardware' => 'fa-microchip', 'software' => 'fa-code', 'cloud' => 'fa-cloud',
        'email' => 'fa-envelope', 'printer' => 'fa-print', 'server' => 'fa-server',
        'mobile' => 'fa-mobile-screen', 'browser' => 'fa-globe', 'account' => 'fa-user-lock',
        'vpn' => 'fa-lock', 'backup' => 'fa-clock-rotate-left', 'wi-fi' => 'fa-wifi', 'wifi' => 'fa-wifi',
    ];
    foreach ($map as $key => $icon) {
        if (str_contains($name, $key)) return $icon;
    }
    return 'fa-lightbulb';
}

function klps_steps_list(?string $text): array {
    if (!$text) return [];
    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    $steps = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $line = preg_replace('/^(\d+[\.\)]|[-*\xE2\x80\xA2])\s*/', '', $line);
        $steps[] = $line;
    }
    return $steps;
}

function kvTimeAgo($ts) {
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', strtotime($ts));
}

$backQuery = 'page=knowledge_view.php&back=' . urlencode('id=' . $id);
$steps = $k ? klps_steps_list($k['procedure_steps'] ?? '') : [];

$helpful = (int)($k['helpful_count'] ?? 0);
$notHelpful = (int)($k['not_helpful_count'] ?? 0);
$totalVotes = $helpful + $notHelpful;
$confirmPct = $totalVotes > 0 ? round(($helpful / $totalVotes) * 100) : 0;
$isSaved = isset($k['is_saved']) && $k['is_saved'] == 1;
$viewCount = (int)($k['view_count'] ?? 0);
$profilePic = !empty($k['author_profile_picture']) ? '../uploads/profiles/' . $k['author_profile_picture'] : '';
$profilePicExists = $profilePic && file_exists(__DIR__ . '/../uploads/profiles/' . $k['author_profile_picture']);
$authorInitial = strtoupper(substr($k['author'] ?? '?', 0, 1));
?>

<div class="kv-progress" id="kvProgress"></div>

<div class="kv-page">
    <div class="kv-bg">
        <div class="grid"></div>
        <div class="aurora a1"></div>
        <div class="aurora a2"></div>
    </div>
    <div class="kv-scenery" id="kvScenery"></div>

<?php if (isset($_SESSION['success'])): ?>
<div class="kv-toast success" id="kvToast"><i class="fas fa-circle-check"></i><span><?= e($_SESSION['success']); unset($_SESSION['success']); ?></span></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="kv-toast error" id="kvToast"><i class="fas fa-circle-exclamation"></i><span><?= e($_SESSION['error']); unset($_SESSION['error']); ?></span></div>
<?php endif; ?>

<?php if (!$k): ?>
    <div class="kv-notfound">
        <i class="fas fa-circle-question"></i>
        <h3>Article Not Found</h3>
        <p>This knowledge entry may have been removed or is no longer active.</p>
        <a href="knowledge.php" class="kv-back-btn" style="display:inline-flex;"><i class="fas fa-arrow-left"></i> Back to Knowledge Library</a>
    </div>
<?php else: ?>

    <!-- Breadcrumb -->
    <div class="kv-breadcrumb">
        <a href="knowledge.php"><i class="fas fa-home"></i> Knowledge Library</a>
        <?php if (!empty($k['category_name'])): ?>
            <span class="sep">/</span>
                <a href="knowledge.php?cat=<?= e($k['category_id']) ?>"><?= e($k['category_name']) ?></a>
        <?php endif; ?>
        <span class="sep">/</span>
        <span class="current"><?= e($k['title']) ?></span>
    </div>

    <!-- Hero -->
    <div class="kv-hero">
        <div class="kv-badges">
            <span class="kv-badge"><i class="fas <?= e(klps_category_icon($k['category_name'])) ?>"></i> <?= e($k['category_name'] ?? 'Uncategorized') ?></span>
            <?php if (!empty($k['department_name'])): ?>
                <span class="kv-badge"><i class="fas fa-building"></i> <?= e($k['department_name']) ?></span>
            <?php endif; ?>
            <?php if ($confirmPct >= 70 && $helpful > 0): ?>
                <span class="kv-badge verified"><i class="fas fa-circle-check"></i> Verified &middot; <?= $confirmPct ?>%</span>
            <?php endif; ?>
            <span class="kv-badge dim"><i class="fas fa-eye"></i> <?= (int)$viewCount ?> views</span>
        </div>
        <h1 class="kv-title"><?= e($k['title']) ?></h1>
        <div class="kv-subrow">
            <span class="sub-item">
                <?php if ($profilePicExists): ?>
                    <img src="<?= e($profilePic) ?>" alt="<?= e($k['author']) ?>" class="kv-avatar-img">
                <?php else: ?>
                    <span class="kv-avatar"><?= e($authorInitial) ?></span>
                <?php endif; ?>
                <?= e($k['author']) ?>
            </span>
            <span class="sub-item"><i class="far fa-calendar"></i> Updated <?= date('M d, Y', strtotime($k['updated_at'] ?? $k['created_at'])) ?></span>
            <?php if ($totalVotes > 0): ?>
                <span class="sub-item"><i class="fas fa-thumbs-up"></i> <?= $helpful ?> confirmed</span>
                <span class="sub-item"><i class="fas fa-thumbs-down"></i> <?= $notHelpful ?> review</span>
            <?php endif; ?>
            <?php if ($isSaved): ?>
                <span class="sub-item" style="color:var(--gold);"><i class="fas fa-bookmark"></i> Saved</span>
            <?php endif; ?>
        </div>
        <?php if ($totalVotes > 0): ?>
            <div class="kv-confidence">
                <div class="labels">
                    <span>Community Confidence</span>
                    <strong><?= $confirmPct ?>%</strong>
                </div>
                <div class="bar"><div class="fill" style="width:<?= $confirmPct ?>%;"></div></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main layout -->
    <div class="kv-layout">
        <div class="kv-main">

            <?php if (!empty($k['problem'])): ?>
            <div class="kv-section problem" id="problem">
                <div class="kv-section-title">
                    <span class="kv-icon problem"><i class="fas fa-triangle-exclamation"></i></span>
                    The Problem
                </div>
                <div class="kv-rich markdown-body" data-raw="<?= e($k['problem']) ?>"><?= nl2br(e($k['problem'])) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($k['cause'])): ?>
            <div class="kv-section cause" id="cause">
                <div class="kv-section-title">
                    <span class="kv-icon cause"><i class="fas fa-circle-question"></i></span>
                    Root Cause
                </div>
                <div class="kv-rich markdown-body" data-raw="<?= e($k['cause']) ?>"><?= nl2br(e($k['cause'])) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($k['solution'])): ?>
            <div class="kv-section solution" id="solution">
                <div class="kv-section-title">
                    <span class="kv-icon solution"><i class="fas fa-wrench"></i></span>
                    The Solution
                </div>
                <div class="kv-rich markdown-body" data-raw="<?= e($k['solution']) ?>"><?= nl2br(e($k['solution'])) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($steps)): ?>
            <div class="kv-section steps" id="steps">
                <div class="kv-section-title">
                    <span class="kv-icon steps"><i class="fas fa-list-ol"></i></span>
                    Step-by-Step Procedure
                </div>
                <ol class="kv-steps">
                    <?php foreach ($steps as $step): ?>
                        <li><span class="step-text"><?= e($step) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php endif; ?>

            <?php if (!empty($k['best_practice'])): ?>
            <div class="kv-section practice" id="practice">
                <div class="kv-section-title">
                    <span class="kv-icon practice"><i class="fas fa-star"></i></span>
                    Best Practice
                </div>
                <div class="kv-rich markdown-body" data-raw="<?= e($k['best_practice']) ?>"><?= nl2br(e($k['best_practice'])) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($votingEnabled): ?>
            <div class="kv-verify-block">
                <div>
                    <div class="kv-verify-text">Did this solve your problem?</div>
                    <div class="kv-verify-sub">Help others trust this article by confirming its accuracy.</div>
                </div>
                <div class="kv-verify-actions">
                    <a class="kv-vote-btn up <?= ($k['my_vote'] ?? null) === 'helpful' ? 'active' : '' ?>"
                  href="#" onclick="handleVote('<?= e($k['knowledge_id']) ?>', 'helpful', '<?= urlencode($backQuery) ?>'); return false;">
                        <i class="fas fa-thumbs-up"></i> Yes, it worked<?= $helpful > 0 ? ' ('.$helpful.')' : '' ?>
                    </a>
                    <a class="kv-vote-btn down <?= ($k['my_vote'] ?? null) === 'not_helpful' ? 'active' : '' ?>"
                  href="#" onclick="handleVote('<?= e($k['knowledge_id']) ?>', 'not_helpful', '<?= urlencode($backQuery) ?>'); return false;">
                        <i class="fas fa-thumbs-down"></i> Needs review<?= $notHelpful > 0 ? ' ('.$notHelpful.')' : '' ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Comments -->
            <div id="comments" class="comments-section">
                <div class="comments-header">
                    <h3><i class="fas fa-comments"></i> Comments</h3>
                    <span class="comment-count"><?= count($comments) ?> conversation<?= count($comments) !== 1 ? 's' : '' ?></span>
                </div>

                <div class="add-comment">
                    <form method="post">
                        <textarea name="comment" rows="3" placeholder="Share your thoughts, ask questions, or provide additional insights..." required></textarea>
                        <input type="hidden" name="parent_id" value="">
                        <button type="submit" class="btn-comment"><i class="fas fa-paper-plane"></i> Post Comment</button>
                    </form>
                </div>

                <div class="comments-list">
                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment):
                            $commentCid = $comment['knowledge_comments_id'];
                            $commentPic = '';
                            try {
                                $stmt = db()->prepare('SELECT profile_picture FROM users WHERE users_id = ?');
                                $stmt->execute([$comment['user_id']]);
                                $cd = $stmt->fetch();
                                if ($cd && !empty($cd['profile_picture'])) $commentPic = '../uploads/profiles/' . $cd['profile_picture'];
                            } catch (PDOException $e) {}
                        ?>
                            <div class="comment-item" id="comment-<?= $commentCid ?>">
                                <?php if (!empty($commentPic) && file_exists(__DIR__ . '/../uploads/profiles/' . basename($commentPic))): ?>
                                    <img src="<?= e($commentPic) ?>" alt="<?= e($comment['full_name']) ?>" class="comment-avatar">
                                <?php else: ?>
                                    <div class="comment-avatar"><?= strtoupper(substr($comment['full_name'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div class="comment-body">
                                    <div class="comment-meta">
                                        <span class="comment-author"><?= e($comment['full_name']) ?></span>
                                        <?php if ($comment['role'] === 'admin'): ?>
                                            <span class="comment-badge">Admin</span>
                                        <?php endif; ?>
                                        <span class="comment-time"><?= date('M d, Y', strtotime($comment['created_at'])) ?> &bull; <?= date('h:i A', strtotime($comment['created_at'])) ?></span>
                                    </div>
                                    <div class="comment-text"><?= nl2br(e($comment['comment'])) ?></div>
                                    <div class="comment-actions">
                                        <button class="comment-action reply-btn" onclick="showReplyForm('<?= e($commentCid) ?>')"><i class="fas fa-reply"></i> Reply</button>
                                        <?php if ($comment['user_id'] == $currentUserId || $_SESSION['user']['role'] === 'admin'): ?>
                                            <a href="?id=<?= $id ?>&delete_comment=1&cid=<?= $commentCid ?>" class="comment-action delete-btn" onclick="return confirm('Delete this comment?')"><i class="fas fa-trash"></i> Delete</a>
                                        <?php endif; ?>
                                        <?php if (!empty($comment['replies'])): ?>
                                            <span class="comment-action reply-count"><i class="fas fa-comment-dots"></i> <?= count($comment['replies']) ?> replies</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="reply-form" id="reply-form-<?= $commentCid ?>">
                                        <form method="post">
                                            <textarea name="comment" rows="2" placeholder="Write your reply..." required></textarea>
                                            <input type="hidden" name="parent_id" value="<?= $commentCid ?>">
                                            <div class="reply-actions">
                                                <button type="button" class="btn-cancel-reply" onclick="hideReplyForm('<?= e($commentCid) ?>')">Cancel</button>
                                                <button type="submit" class="btn-reply"><i class="fas fa-reply"></i> Reply</button>
                                            </div>
                                        </form>
                                    </div>

                                    <?php if (!empty($comment['replies'])): ?>
                                        <?php foreach ($comment['replies'] as $reply):
                                            $replyCid = $reply['knowledge_comments_id'];
                                            $replyPic = '';
                                            try {
                                                $stmt = db()->prepare('SELECT profile_picture FROM users WHERE users_id = ?');
                                                $stmt->execute([$reply['user_id']]);
                                                $rd = $stmt->fetch();
                                                if ($rd && !empty($rd['profile_picture'])) $replyPic = '../uploads/profiles/' . $rd['profile_picture'];
                                            } catch (PDOException $e) {}
                                        ?>
                                            <div class="comment-item reply" id="reply-<?= $replyCid ?>">
                                                <?php if (!empty($replyPic) && file_exists(__DIR__ . '/../uploads/profiles/' . basename($replyPic))): ?>
                                                    <img src="<?= e($replyPic) ?>" alt="<?= e($reply['full_name']) ?>" class="comment-avatar">
                                                <?php else: ?>
                                                    <div class="comment-avatar"><?= strtoupper(substr($reply['full_name'], 0, 1)) ?></div>
                                                <?php endif; ?>
                                                <div class="comment-body">
                                                    <div class="comment-meta">
                                                        <span class="comment-author"><?= e($reply['full_name']) ?></span>
                                                        <?php if ($reply['role'] === 'admin'): ?>
                                                            <span class="comment-badge">Admin</span>
                                                        <?php endif; ?>
                                                        <span class="comment-time"><?= date('M d, Y', strtotime($reply['created_at'])) ?> &bull; <?= date('h:i A', strtotime($reply['created_at'])) ?></span>
                                                        <span class="comment-time"><i class="fas fa-reply"></i> Reply</span>
                                                    </div>
                                                    <div class="comment-text"><?= nl2br(e($reply['comment'])) ?></div>
                                                    <?php if ($reply['user_id'] == $currentUserId || $_SESSION['user']['role'] === 'admin'): ?>
                                                        <div class="comment-actions">
                                                            <a href="?id=<?= $id ?>&delete_comment=1&cid=<?= $replyCid ?>" class="comment-action delete-btn" onclick="return confirm('Delete this reply?')"><i class="fas fa-trash"></i> Delete</a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-comments">
                            <i class="fas fa-comment-slash"></i>
                            <p>No comments yet. Be the first to share your thoughts!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="kv-side">
            <div class="kv-card">
                <h6><i class="fas fa-list"></i> On This Page</h6>
                <div class="kv-toc">
                    <?php if (!empty($k['problem'])): ?><a href="#problem"><i class="fas fa-circle"></i>The Problem</a><?php endif; ?>
                    <?php if (!empty($k['cause'])): ?><a href="#cause"><i class="fas fa-circle"></i>Root Cause</a><?php endif; ?>
                    <?php if (!empty($k['solution'])): ?><a href="#solution"><i class="fas fa-circle"></i>The Solution</a><?php endif; ?>
                    <?php if (!empty($steps)): ?><a href="#steps"><i class="fas fa-circle"></i>Procedure Steps</a><?php endif; ?>
                    <?php if (!empty($k['best_practice'])): ?><a href="#practice"><i class="fas fa-circle"></i>Best Practice</a><?php endif; ?>
                    <a href="#comments"><i class="fas fa-circle"></i>Comments</a>
                </div>
            </div>

            <?php if (!empty($related)): ?>
            <div class="kv-card">
                <h6><i class="fas fa-book-open"></i> Related in <?= e($k['category_name'] ?? 'this category') ?></h6>
                <div class="kv-related">
                    <?php foreach ($related as $r): ?>
                <a href="knowledge_view.php?id=<?= e($r['knowledge_id']) ?>"><i class="fas fa-book"></i> <?= e($r['title']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <a href="knowledge.php" class="kv-back-btn"><i class="fas fa-arrow-left"></i> Back to Library</a>
        </div>
    </div>

<?php endif; ?>
</div>

<!-- Markdown + Sanitizer -->
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.11/dist/purify.min.js"></script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
