<?php
$page_title = 'Knowledge Article';
require_once __DIR__ . '/../partials/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('knowledge.php');
}

$currentUserId = $_SESSION['user']['id'] ?? 0;

function trackView($knowledgeId, $userId) {
    $sessionKey = 'viewed_articles';
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [];
    }
    
    if (!in_array($knowledgeId, $_SESSION[$sessionKey])) {
        try {
            $stmt = db()->prepare('UPDATE knowledge SET view_count = view_count + 1 WHERE id = ?');
            $stmt->execute([$knowledgeId]);
            $_SESSION[$sessionKey][] = $knowledgeId;
            $stmt = db()->prepare('INSERT INTO knowledge_views (knowledge_id, user_id) VALUES (?, ?)');
            $stmt->execute([$knowledgeId, $userId ?: null]);
        } catch (PDOException $e) {}
    }
}

trackView($id, $currentUserId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment'] ?? '');
    $parent_id = (int)($_POST['parent_id'] ?? 0);
    
    if ($comment) {
        try {
            db()->exec("
                CREATE TABLE IF NOT EXISTS knowledge_comments (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    knowledge_id INT UNSIGNED NOT NULL,
                    parent_id BIGINT UNSIGNED NULL,
                    comment TEXT NOT NULL,
                    is_edited TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (knowledge_id) REFERENCES knowledge(id) ON DELETE CASCADE,
                    FOREIGN KEY (parent_id) REFERENCES knowledge_comments(id) ON DELETE CASCADE,
                    INDEX idx_knowledge_comments (knowledge_id, created_at)
                )
            ");
            
            $stmt = db()->prepare('INSERT INTO knowledge_comments (user_id, knowledge_id, parent_id, comment) VALUES (?, ?, ?, ?)');
            $stmt->execute([$currentUserId, $id, $parent_id ?: null, $comment]);
            $commentId = db()->lastInsertId();
            
            $stmt = db()->prepare('SELECT created_by, title FROM knowledge WHERE id = ?');
            $stmt->execute([$id]);
            $article = $stmt->fetch();
            $userName = $_SESSION['user']['full_name'] ?? 'Someone';
            
            try {
                db()->query('SELECT 1 FROM notifications LIMIT 1');
            } catch (PDOException $e) {
                db()->exec("
                    CREATE TABLE IF NOT EXISTS notifications (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        user_id INT UNSIGNED NOT NULL,
                        from_user_id INT UNSIGNED NULL,
                        type ENUM('vote', 'save', 'comment', 'mention', 'system') NOT NULL,
                        message TEXT NOT NULL,
                        link VARCHAR(255) NULL,
                        is_read TINYINT(1) DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
                        INDEX idx_notifications_user (user_id, is_read, created_at)
                    )
                ");
            }
            
            if ($parent_id > 0) {
                $stmt = db()->prepare('SELECT user_id FROM knowledge_comments WHERE id = ?');
                $stmt->execute([$parent_id]);
                $parentComment = $stmt->fetch();
                
                if ($parentComment && $parentComment['user_id'] != $currentUserId) {
                    try {
                        $stmt = db()->prepare(
                            'INSERT INTO notifications (user_id, from_user_id, type, message, link) 
                             VALUES (?, ?, "comment", ?, ?)'
                        );
                        $stmt->execute([
                            $parentComment['user_id'],
                            $currentUserId,
                            $userName . ' replied to your comment on "' . $article['title'] . '"',
                            'knowledge_view.php?id=' . $id . '#comment-' . $parent_id
                        ]);
                    } catch (PDOException $e) {}
                }
            }
            
            if ($article && $article['created_by'] != $currentUserId) {
                $skipArticleNotification = false;
                if ($parent_id > 0) {
                    $stmt = db()->prepare('SELECT user_id FROM knowledge_comments WHERE id = ?');
                    $stmt->execute([$parent_id]);
                    $parentComment = $stmt->fetch();
                    if ($parentComment && $parentComment['user_id'] == $article['created_by']) {
                        $skipArticleNotification = true;
                    }
                }
                
                if (!$skipArticleNotification) {
                    try {
                        $stmt = db()->prepare(
                            'INSERT INTO notifications (user_id, from_user_id, type, message, link) 
                             VALUES (?, ?, "comment", ?, ?)'
                        );
                        $stmt->execute([
                            $article['created_by'],
                            $currentUserId,
                            $userName . ' commented on your article "' . $article['title'] . '"',
                            'knowledge_view.php?id=' . $id . '#comment-' . $commentId
                        ]);
                    } catch (PDOException $e) {}
                }
            }
            
            $_SESSION['success'] = '💬 Comment posted successfully!';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to add comment: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Please enter a comment.';
    }
    redirect('knowledge_view.php?id=' . $id);
}

if (isset($_GET['delete_comment']) && isset($_GET['cid'])) {
    $cid = (int)$_GET['cid'];
    try {
        $stmt = db()->prepare('SELECT user_id FROM knowledge_comments WHERE id = ?');
        $stmt->execute([$cid]);
        $comment = $stmt->fetch();
        if ($comment && ($comment['user_id'] == $currentUserId || $_SESSION['user']['role'] === 'admin')) {
            $stmt = db()->prepare('DELETE FROM knowledge_comments WHERE id = ?');
            $stmt->execute([$cid]);
            $_SESSION['success'] = '🗑️ Comment deleted.';
        }
    } catch (PDOException $e) {}
    redirect('knowledge_view.php?id=' . $id);
}

$votingEnabled = true;
$k = null;

try {
    $stmt = db()->prepare(
        'SELECT k.*, kc.name AS category_name, d.name AS department_name, u.full_name AS author,
                u.profile_picture AS author_profile_picture,
                (SELECT COUNT(*) FROM knowledge_votes kv WHERE kv.knowledge_id = k.id AND kv.vote = "helpful") AS helpful_count,
                (SELECT COUNT(*) FROM knowledge_votes kv2 WHERE kv2.knowledge_id = k.id AND kv2.vote = "not_helpful") AS not_helpful_count,
                (SELECT vote FROM knowledge_votes kv3 WHERE kv3.knowledge_id = k.id AND kv3.user_id = ?) AS my_vote,
                (SELECT 1 FROM knowledge_saves ks WHERE ks.knowledge_id = k.id AND ks.user_id = ?) AS is_saved,
                (SELECT COUNT(*) FROM knowledge_comments c WHERE c.knowledge_id = k.id AND c.parent_id IS NULL) AS comment_count
         FROM knowledge k
         LEFT JOIN knowledge_categories kc ON kc.id = k.category_id
         LEFT JOIN departments d ON d.id = k.department_id
         JOIN users u ON u.id = k.created_by
         WHERE k.id = ? AND k.status = "active" LIMIT 1'
    );
    $stmt->execute([$currentUserId, $currentUserId, $id]);
    $k = $stmt->fetch();
} catch (PDOException $e) {
    $votingEnabled = false;
    $stmt = db()->prepare(
        'SELECT k.*, kc.name AS category_name, d.name AS department_name, u.full_name AS author,
                u.profile_picture AS author_profile_picture
         FROM knowledge k
         LEFT JOIN knowledge_categories kc ON kc.id = k.category_id
         LEFT JOIN departments d ON d.id = k.department_id
         JOIN users u ON u.id = k.created_by
         WHERE k.id = ? AND k.status = "active" LIMIT 1'
    );
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
             JOIN users u ON u.id = c.user_id
             WHERE c.knowledge_id = ?
             ORDER BY 
                CASE WHEN c.parent_id IS NULL THEN c.id ELSE c.parent_id END DESC,
                c.created_at ASC'
        );
        $stmt->execute([$id]);
        $allComments = $stmt->fetchAll();
        
        $replies = [];
        foreach ($allComments as $c) {
            if ($c['parent_id'] === null) {
                $c['replies'] = [];
                $comments[$c['id']] = $c;
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
    } catch (PDOException $e) {
        $comments = [];
    }
}

$related = [];
if ($k && !empty($k['category_id'])) {
    $stmt = db()->prepare(
        'SELECT id, title FROM knowledge WHERE category_id = ? AND id != ? AND status = "active" ORDER BY updated_at DESC LIMIT 5'
    );
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
        'vpn' => 'fa-lock', 'backup' => 'fa-clock-rotate-left',
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
        $line = preg_replace('/^(\d+[\.\)]|[-*•])\s*/', '', $line);
        $steps[] = $line;
    }
    return $steps;
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
$authorInitial = strtoupper(substr($k['author'], 0, 1));
?>
<style>
:root {
    --cyan: #00f0ff;
    --violet: #8b7cf6;
    --gold: #f6c453;
    --bg: #03050a;
    --text: #e7ecf7;
    --text-dim: #8892b0;
    --border: rgba(255,255,255,0.07);
}

.kv-wrap {
    position: relative;
    background: radial-gradient(ellipse at 20% -10%, #1a2140 0%, var(--bg) 45%, #050710 100%);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    min-height: 500px;
    overflow: hidden;
    border: 1px solid rgba(0,240,255,0.08);
}
.kv-wrap::before {
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
.kv-wrap > * { position: relative; z-index: 1; }
.kv-scanline {
    position: absolute;
    left: 0;
    right: 0;
    height: 2px;
    z-index: 1;
    pointer-events: none;
    background: linear-gradient(90deg, transparent, rgba(0,240,255,0.3), transparent);
    animation: scanMove 5s linear infinite;
}
@keyframes scanMove {
    0% { top: -5%; }
    100% { top: 105%; }
}
.kv-notfound { text-align: center; padding: 4rem 2rem; }
.kv-notfound i { font-size: 4rem; color: rgba(139,124,246,0.3); display: block; margin-bottom: 1.5rem; }
.kv-notfound h3 { font-family: 'Orbitron', sans-serif; color: #fff; }
.kv-notfound p { color: #8892b0; }
.kv-breadcrumb { color: #8892b0; font-size: 0.8rem; margin-bottom: 1rem; padding: 0.5rem 1rem; background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid rgba(255,255,255,0.04); }
.kv-breadcrumb a { color: #00f0ff; text-decoration: none; }
.kv-header { margin-bottom: 2rem; padding: 1.5rem 2rem; background: linear-gradient(135deg,rgba(26,33,64,0.6),rgba(10,14,26,0.8)); border: 1px solid rgba(0,240,255,0.1); border-radius: 16px; }
.kv-badges { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }
.kv-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.8rem; background: rgba(0,240,255,0.08); border: 1px solid rgba(0,240,255,0.15); border-radius: 20px; font-size: 0.75rem; font-weight: 600; color: #00f0ff; text-transform: uppercase; letter-spacing: 0.5px; }
.kv-title { font-family: 'Orbitron', sans-serif; font-size: 2rem; font-weight: 700; color: #fff; margin-bottom: 0.75rem; }
.kv-subrow { display: flex; flex-wrap: wrap; gap: 1.5rem; font-size: 0.85rem; color: #8892b0; }
.kv-subrow i { color: #00f0ff; opacity: 0.6; }
.kv-avatar { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg,#00f0ff,#8b7cf6); font-size: 0.7rem; font-weight: 700; color: #0a0e1a; text-transform: uppercase; }
.kv-avatar-img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(0,240,255,0.15); }
.kv-layout { display: grid; grid-template-columns: 1fr 280px; gap: 2rem; }
.kv-main { min-width: 0; }
.kv-side { display: flex; flex-direction: column; gap: 1rem; }
.kv-section { background: linear-gradient(160deg,rgba(255,255,255,0.03),rgba(255,255,255,0.01)); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 1.5rem 1.75rem; margin-bottom: 1.25rem; }
.kv-section:hover { border-color: rgba(0,240,255,0.15); box-shadow: 0 8px 30px -12px rgba(0,0,0,0.5); }
.kv-section-title { display: flex; align-items: center; gap: 0.75rem; font-family: 'Orbitron', sans-serif; font-size: 0.9rem; font-weight: 700; color: #fff; margin-bottom: 0.75rem; letter-spacing: 0.5px; }
.kv-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; font-size: 0.9rem; flex-shrink: 0; }
.kv-icon.problem { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
.kv-icon.cause { background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.2); }
.kv-icon.solution { background: rgba(16,185,129,0.15); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
.kv-icon.steps { background: rgba(59,130,246,0.15); color: #3b82f6; border: 1px solid rgba(59,130,246,0.2); }
.kv-icon.practice { background: rgba(246,196,83,0.15); color: var(--gold); border: 1px solid rgba(246,196,83,0.2); }
.kv-section-text { color: #8892b0; font-size: 0.95rem; line-height: 1.7; padding-left: 3rem; }
.kv-steps { padding-left: 3rem; margin: 0; counter-reset: step; }
.kv-steps li { list-style: none; position: relative; padding: 0.6rem 0 0.6rem 2.2rem; color: #8892b0; font-size: 0.95rem; line-height: 1.6; border-bottom: 1px solid rgba(255,255,255,0.03); }
.kv-steps li:last-child { border-bottom: none; }
.kv-steps li::before { counter-increment: step; content: counter(step); position: absolute; left: 0; top: 0.6rem; width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(135deg,rgba(0,240,255,0.15),rgba(139,124,246,0.15)); border: 1px solid rgba(0,240,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; color: #00f0ff; }
.kv-steps li span { display: block; }
.kv-verify-block { background: linear-gradient(135deg,rgba(0,240,255,0.05),rgba(139,124,246,0.05)); border: 1px solid rgba(0,240,255,0.1); border-radius: 16px; padding: 1.5rem 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem; }
.kv-verify-text { font-weight: 700; color: #fff; font-size: 1rem; }
.kv-verify-sub { font-size: 0.8rem; color: #8892b0; }
.kv-verify-actions { display: flex; gap: 0.75rem; }
.kv-vote-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); color: #8892b0; }
.kv-vote-btn:hover { transform: translateY(-2px); color: #fff; }
.kv-vote-btn.up:hover,.kv-vote-btn.up.active { background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.3); color: #10b981; box-shadow: 0 0 20px rgba(16,185,129,0.1); }
.kv-vote-btn.down:hover,.kv-vote-btn.down.active { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.3); color: #ef4444; box-shadow: 0 0 20px rgba(239,68,68,0.1); }
.kv-card { background: linear-gradient(160deg,rgba(255,255,255,0.03),rgba(255,255,255,0.01)); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 1.25rem 1.5rem; }
.kv-card:hover { border-color: rgba(0,240,255,0.1); }
.kv-card h6 { font-family: 'Orbitron', sans-serif; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #00f0ff; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
.kv-toc { display: flex; flex-direction: column; gap: 0.3rem; }
.kv-toc a { display: flex; align-items: center; gap: 0.6rem; padding: 0.4rem 0.6rem; border-radius: 8px; color: #8892b0; text-decoration: none; font-size: 0.85rem; transition: all 0.2s ease; }
.kv-toc a:hover { background: rgba(0,240,255,0.05); color: #fff; padding-left: 0.9rem; }
.kv-toc a i { font-size: 0.7rem; color: #00f0ff; opacity: 0.5; width: 18px; }
.kv-related { display: flex; flex-direction: column; gap: 0.3rem; }
.kv-related a { display: flex; align-items: center; gap: 0.6rem; padding: 0.4rem 0.6rem; border-radius: 8px; color: #8892b0; text-decoration: none; font-size: 0.85rem; transition: all 0.2s ease; }
.kv-related a:hover { background: rgba(0,240,255,0.05); color: #fff; }
.kv-related a i { font-size: 0.7rem; color: var(--gold); opacity: 0.5; }
.kv-back-btn { display: inline-flex; align-items: center; gap: 0.6rem; padding: 0.7rem 1.2rem; border-radius: 12px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); color: #8892b0; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.3s ease; justify-content: center; }
.kv-back-btn:hover { background: rgba(0,240,255,0.08); border-color: rgba(0,240,255,0.15); color: #fff; transform: translateY(-2px); }
.kv-confirm-meter { margin-top: 0.5rem; }
.kv-confirm-meter .meter-bar { height: 6px; background: rgba(255,255,255,0.06); border-radius: 4px; overflow: hidden; margin-top: 0.3rem; }
.kv-confirm-meter .meter-fill { height: 100%; border-radius: 4px; background: linear-gradient(90deg,#8b7cf6,#f6c453); transition: width 1s ease; box-shadow: 0 0 12px rgba(246,196,83,0.2); }
.kv-confirm-meter .meter-label { display: flex; justify-content: space-between; font-size: 0.7rem; color: #8892b0; }

.comments-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.05); }
.comments-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.comments-header h3 { font-family: 'Orbitron', sans-serif; font-size: 1rem; color: #fff; display: flex; align-items: center; gap: 0.5rem; }
.comments-header h3 i { color: #00f0ff; }
.comment-count { font-size: 0.8rem; color: #8892b0; }
.add-comment { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; }
.add-comment .comment-input-wrap { margin-bottom: 0.75rem; }
.add-comment .comment-input-wrap textarea { width: 100%; padding: 0.75rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; color: #fff; font-size: 0.9rem; resize: vertical; transition: all 0.3s ease; }
.add-comment .comment-input-wrap textarea:focus { outline: none; border-color: rgba(0,240,255,0.2); background: rgba(255,255,255,0.06); }
.btn-comment { padding: 0.6rem 1.2rem; background: linear-gradient(135deg,#00f0ff,#8b7cf6); border: none; border-radius: 8px; color: #0a0e1a; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.5rem; }
.btn-comment:hover { transform: translateY(-2px); box-shadow: 0 8px 25px -5px rgba(0,240,255,0.3); }
.comments-list { display: flex; flex-direction: column; gap: 1rem; }
.comment-item { display: flex; gap: 0.75rem; padding: 0.75rem; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.03); border-radius: 10px; transition: all 0.3s ease; }
.comment-item:hover { background: rgba(255,255,255,0.04); }
.comment-item.reply { margin-left: 3rem; border-left: 2px solid rgba(0,240,255,0.15); padding-left: 1rem; }
.comment-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg,#00f0ff,#8b7cf6); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #0a0e1a; font-size: 0.8rem; flex-shrink: 0; text-transform: uppercase; overflow: hidden; }
.comment-avatar img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
.comment-body { flex: 1; min-width: 0; }
.comment-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-bottom: 0.3rem; }
.comment-author { font-weight: 600; color: #fff; font-size: 0.85rem; }
.comment-badge { font-size: 0.55rem; padding: 0.05rem 0.4rem; border-radius: 4px; text-transform: uppercase; font-weight: 700; }
.comment-badge.admin { background: rgba(0,240,255,0.1); color: #00f0ff; }
.comment-time { font-size: 0.7rem; color: #8892b0; }
.comment-text { color: var(--text); font-size: 0.9rem; line-height: 1.5; }
.comment-actions { display: flex; gap: 0.75rem; margin-top: 0.5rem; }
.comment-action { font-size: 0.7rem; color: #8892b0; background: none; border: none; cursor: pointer; padding: 0; transition: color 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem; }
.comment-action:hover { color: #fff; }
.comment-action.reply-btn:hover { color: #00f0ff; }
.comment-action.delete-btn:hover { color: #ef4444; }
.comment-action.reply-count { color: #8892b0; cursor: default; }
.reply-form { margin-top: 0.75rem; }
.reply-form .reply-input textarea { width: 100%; padding: 0.5rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 6px; color: #fff; font-size: 0.85rem; resize: vertical; transition: all 0.3s ease; }
.reply-form .reply-input textarea:focus { outline: none; border-color: rgba(0,240,255,0.15); }
.reply-actions { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
.btn-cancel-reply { padding: 0.4rem 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 6px; color: #8892b0; cursor: pointer; transition: all 0.3s ease; font-size: 0.75rem; }
.btn-cancel-reply:hover { background: rgba(255,255,255,0.06); color: #fff; }
.btn-reply { padding: 0.4rem 0.8rem; background: linear-gradient(135deg,#00f0ff,#8b7cf6); border: none; border-radius: 6px; color: #0a0e1a; font-weight: 600; font-size: 0.75rem; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 0.3rem; }
.btn-reply:hover { transform: translateY(-1px); box-shadow: 0 4px 15px -3px rgba(0,240,255,0.3); }
.no-comments { text-align: center; padding: 2rem; color: #8892b0; }
.no-comments i { font-size: 2rem; color: rgba(255,255,255,0.05); display: block; margin-bottom: 0.5rem; }

.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    max-width: 400px;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.toast {
    padding: 0.8rem 1.25rem;
    border-radius: 12px;
    animation: slideInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9rem;
    font-weight: 500;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
    min-width: 280px;
}
.toast .toast-icon { font-size: 1.2rem; flex-shrink: 0; }
.toast-success { background: rgba(0, 255, 156, 0.12); border-color: rgba(0, 255, 156, 0.2); color: #00ff9c; }
.toast-success .toast-icon { color: #00ff9c; }
.toast-error { background: rgba(255, 0, 60, 0.12); border-color: rgba(255, 0, 60, 0.2); color: #ff7c93; }
.toast-error .toast-icon { color: #ff7c93; }
.toast-info { background: rgba(0, 240, 255, 0.08); border-color: rgba(0, 240, 255, 0.15); color: #00f0ff; }
.toast-info .toast-icon { color: #00f0ff; }
.toast-warning { background: rgba(246, 196, 83, 0.08); border-color: rgba(246, 196, 83, 0.15); color: #f6c453; }
.toast-warning .toast-icon { color: #f6c453; }
.toast .toast-close { margin-left: auto; background: none; border: none; color: rgba(255, 255, 255, 0.3); cursor: pointer; font-size: 0.8rem; padding: 0.2rem 0.4rem; border-radius: 6px; transition: all 0.2s ease; }
.toast .toast-close:hover { color: #fff; background: rgba(255, 255, 255, 0.05); }
@keyframes slideInRight {
    from { transform: translateX(120%) scale(0.95); opacity: 0; }
    to { transform: translateX(0) scale(1); opacity: 1; }
}
@keyframes slideOutRight {
    from { transform: translateX(0) scale(1); opacity: 1; }
    to { transform: translateX(120%) scale(0.95); opacity: 0; }
}
.toast.hiding { animation: slideOutRight 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

@media (max-width: 992px) { .kv-layout { grid-template-columns: 1fr; } .kv-side { order: -1; } .kv-title { font-size: 1.5rem; } }
@media (max-width: 768px) { 
    .kv-wrap { padding: 1rem; } 
    .kv-header { padding: 1rem; } 
    .kv-title { font-size: 1.2rem; } 
    .kv-layout { grid-template-columns: 1fr; } 
    .kv-side { order: -1; } 
    .kv-section { padding: 0.75rem 1rem; } 
    .kv-section-text { padding-left: 0; } 
    .kv-steps { padding-left: 0; } 
    .kv-verify-block { flex-direction: column; text-align: center; } 
    .kv-verify-actions { flex-direction: column; width: 100%; } 
    .kv-verify-actions .kv-vote-btn { justify-content: center; } 
    .comment-item { flex-direction: column; } 
    .comment-item.reply { margin-left: 1rem; } 
    .toast-container { top: 10px; right: 10px; left: 10px; max-width: none; } 
    .toast { min-width: auto; font-size: 0.85rem; padding: 0.7rem 1rem; } 
}
@media (max-width: 576px) { 
    .kv-wrap { padding: 0.5rem; } 
    .kv-title { font-size: 1rem; } 
    .kv-badge { font-size: 0.6rem; padding: 0.15rem 0.5rem; } 
    .kv-subrow { font-size: 0.7rem; gap: 0.75rem; } 
    .kv-section { padding: 0.5rem 0.75rem; } 
    .comment-item.reply { margin-left: 0.5rem; } 
}
</style>

<div class="kv-wrap">
    <div class="kv-scanline"></div>

<?php if (!$k): ?>
    <div class="kv-notfound">
        <i class="fas fa-circle-question"></i>
        <h3>Article Not Found</h3>
        <p>This knowledge entry may have been removed or is no longer active.</p>
        <a href="knowledge.php" class="kv-back-btn" style="display:inline-flex;"><i class="fas fa-arrow-left"></i> Back to Knowledge Library</a>
    </div>
<?php else: ?>

    <div class="kv-breadcrumb">
        <a href="knowledge.php"><i class="fas fa-home me-1"></i>Knowledge Library</a>
        <?php if (!empty($k['category_name'])): ?>
            &nbsp;/&nbsp;<a href="knowledge.php?cat=<?= (int)$k['category_id'] ?>"><?= e($k['category_name']) ?></a>
        <?php endif; ?>
        &nbsp;/&nbsp;<span><?= e($k['title']) ?></span>
    </div>

    <div class="kv-header">
        <div class="kv-badges">
            <span class="kv-badge"><i class="fas <?= e(klps_category_icon($k['category_name'])) ?>"></i> <?= e($k['category_name'] ?? 'Uncategorized') ?></span>
            <?php if (!empty($k['department_name'])): ?>
                <span class="kv-badge"><i class="fas fa-building"></i> <?= e($k['department_name']) ?></span>
            <?php endif; ?>
            <?php if ($confirmPct >= 70 && $helpful > 0): ?>
                <span class="kv-badge" style="border-color:rgba(16,185,129,0.3);color:#10b981;"><i class="fas fa-circle-check"></i> Verified (<?= $confirmPct ?>%)</span>
            <?php endif; ?>
            <span class="kv-badge" style="border-color:rgba(255,255,255,0.05);color:var(--text-dim);"><i class="fas fa-eye"></i> <?= $viewCount ?> views</span>
        </div>
        <h1 class="kv-title"><?= e($k['title']) ?></h1>
        <div class="kv-subrow">
            <span class="d-flex align-items-center gap-2">
                <?php if (!empty($profilePic) && file_exists($profilePic)): ?>
                    <img src="<?= e($profilePic) ?>" alt="<?= e($k['author']) ?>" class="kv-avatar-img">
                <?php else: ?>
                    <span class="kv-avatar"><?= e($authorInitial) ?></span>
                <?php endif; ?>
                <?= e($k['author']) ?>
            </span>
            <span><i class="fas fa-calendar me-1"></i> Updated <?= date('M d, Y', strtotime($k['updated_at'] ?? $k['created_at'])) ?></span>
            <?php if ($totalVotes > 0): ?>
                <span><i class="fas fa-thumbs-up me-1" style="color:#10b981;"></i> <?= $helpful ?> confirmed <span style="color:var(--text-dim);margin:0 0.25rem;">·</span> <i class="fas fa-thumbs-down me-1" style="color:#ef4444;"></i> <?= $notHelpful ?> review</span>
            <?php endif; ?>
            <?php if ($isSaved): ?>
                <span style="color:var(--gold);"><i class="fas fa-bookmark me-1"></i> Saved</span>
            <?php endif; ?>
        </div>
        <?php if ($totalVotes > 0): ?>
        <div class="kv-confirm-meter">
            <div class="meter-label"><span>Community Confidence</span><span><?= $confirmPct ?>%</span></div>
            <div class="meter-bar"><div class="meter-fill" style="width:<?= $confirmPct ?>%;"></div></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="kv-layout">
        <div class="kv-main">
            <?php if (!empty($k['problem'])): ?>
            <div class="kv-section" id="problem">
                <div class="kv-section-title"><span class="kv-icon problem"><i class="fas fa-triangle-exclamation"></i></span> The Problem</div>
                <div class="kv-section-text"><?= e($k['problem']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($k['cause'])): ?>
            <div class="kv-section" id="cause">
                <div class="kv-section-title"><span class="kv-icon cause"><i class="fas fa-circle-question"></i></span> Root Cause</div>
                <div class="kv-section-text"><?= e($k['cause']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($k['solution'])): ?>
            <div class="kv-section" id="solution">
                <div class="kv-section-title"><span class="kv-icon solution"><i class="fas fa-wrench"></i></span> The Solution</div>
                <div class="kv-section-text"><?= e($k['solution']) ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($steps)): ?>
            <div class="kv-section" id="steps">
                <div class="kv-section-title"><span class="kv-icon steps"><i class="fas fa-list-ol"></i></span> Step-by-Step Procedure</div>
                <ol class="kv-steps"><?php foreach ($steps as $step): ?><li><span><?= e($step) ?></span></li><?php endforeach; ?></ol>
            </div>
            <?php endif; ?>
            <?php if (!empty($k['best_practice'])): ?>
            <div class="kv-section" id="practice">
                <div class="kv-section-title"><span class="kv-icon practice"><i class="fas fa-star"></i></span> Best Practice</div>
                <div class="kv-section-text"><?= e($k['best_practice']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($votingEnabled): ?>
            <div class="kv-verify-block">
                <div><div class="kv-verify-text">Did this solve your problem?</div><div class="kv-verify-sub">Help others trust this article by confirming its accuracy.</div></div>
                <div class="kv-verify-actions">
                    <a class="kv-vote-btn up <?= ($k['my_vote'] ?? null) === 'helpful' ? 'active' : '' ?>" href="#" onclick="handleVote(<?= (int)$k['id'] ?>, 'helpful', '<?= urlencode($backQuery) ?>'); return false;"><i class="fas fa-thumbs-up"></i> Yes, it worked<?= $helpful > 0 ? ' ('.$helpful.')' : '' ?></a>
                    <a class="kv-vote-btn down <?= ($k['my_vote'] ?? null) === 'not_helpful' ? 'active' : '' ?>" href="#" onclick="handleVote(<?= (int)$k['id'] ?>, 'not_helpful', '<?= urlencode($backQuery) ?>'); return false;"><i class="fas fa-thumbs-down"></i> Needs review<?= $notHelpful > 0 ? ' ('.$notHelpful.')' : '' ?></a>
                </div>
            </div>
            <?php endif; ?>

            <div id="comments" class="comments-section">
                <div class="comments-header">
                    <h3><i class="fas fa-comments"></i> Comments</h3>
                    <span class="comment-count"><?= count($comments) ?> comments</span>
                </div>

                <div class="add-comment">
                    <form method="post">
                        <div class="comment-input-wrap">
                            <textarea name="comment" class="form-control" rows="3" placeholder="Share your thoughts, ask questions, or provide additional insights..." required></textarea>
                            <input type="hidden" name="parent_id" value="0">
                        </div>
                        <button type="submit" class="btn-comment"><i class="fas fa-paper-plane"></i> Post Comment</button>
                    </form>
                </div>

                <div class="comments-list">
                    <?php if (!empty($comments)): ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item" id="comment-<?= $comment['id'] ?>">
                                <?php 
                                $commentPic = '';
                                try {
                                    $stmt = db()->prepare('SELECT profile_picture FROM users WHERE full_name = ?');
                                    $stmt->execute([$comment['full_name']]);
                                    $commentData = $stmt->fetch();
                                    if ($commentData && !empty($commentData['profile_picture'])) {
                                        $commentPic = '../uploads/profiles/' . $commentData['profile_picture'];
                                    }
                                } catch (PDOException $e) {}
                                ?>
                                <?php if (!empty($commentPic) && file_exists($commentPic)): ?>
                                    <img src="<?= e($commentPic) ?>" alt="<?= e($comment['full_name']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,240,255,0.1);">
                                <?php else: ?>
                                    <div class="comment-avatar"><?= strtoupper(substr($comment['full_name'], 0, 1)) ?></div>
                                <?php endif; ?>
                                <div class="comment-body">
                                    <div class="comment-meta">
                                        <span class="comment-author"><?= e($comment['full_name']) ?></span>
                                        <?php if ($comment['role'] === 'admin'): ?><span class="comment-badge admin">Admin</span><?php endif; ?>
                                        <span class="comment-time"><?= date('M d, Y • h:i A', strtotime($comment['created_at'])) ?></span>
                                    </div>
                                    <div class="comment-text"><?= nl2br(e($comment['comment'])) ?></div>
                                    <div class="comment-actions">
                                        <button class="comment-action reply-btn" onclick="showReplyForm(<?= $comment['id'] ?>)"><i class="fas fa-reply"></i> Reply</button>
                                        <?php if ($comment['user_id'] == $currentUserId || $_SESSION['user']['role'] === 'admin'): ?>
                                            <a href="?id=<?= $id ?>&delete_comment=1&cid=<?= $comment['id'] ?>" class="comment-action delete-btn" onclick="return confirm('Delete this comment?')"><i class="fas fa-trash"></i> Delete</a>
                                        <?php endif; ?>
                                        <?php if (!empty($comment['replies'])): ?>
                                            <span class="comment-action reply-count"><i class="fas fa-comment-dots"></i> <?= count($comment['replies']) ?> replies</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="reply-form" id="reply-form-<?= $comment['id'] ?>" style="display:none;">
                                        <form method="post">
                                            <div class="reply-input">
                                                <textarea name="comment" class="form-control" rows="2" placeholder="Write your reply..." required></textarea>
                                                <input type="hidden" name="parent_id" value="<?= $comment['id'] ?>">
                                            </div>
                                            <div class="reply-actions">
                                                <button type="button" class="btn-cancel-reply" onclick="hideReplyForm(<?= $comment['id'] ?>)">Cancel</button>
                                                <button type="submit" class="btn-reply"><i class="fas fa-reply"></i> Reply</button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <?php if (!empty($comment['replies'])): ?>
                                        <?php foreach ($comment['replies'] as $reply): ?>
                                            <div class="comment-item reply" id="reply-<?= $reply['id'] ?>">
                                                <?php 
                                                $replyPic = '';
                                                try {
                                                    $stmt = db()->prepare('SELECT profile_picture FROM users WHERE full_name = ?');
                                                    $stmt->execute([$reply['full_name']]);
                                                    $replyData = $stmt->fetch();
                                                    if ($replyData && !empty($replyData['profile_picture'])) {
                                                        $replyPic = '../uploads/profiles/' . $replyData['profile_picture'];
                                                    }
                                                } catch (PDOException $e) {}
                                                ?>
                                                <?php if (!empty($replyPic) && file_exists($replyPic)): ?>
                                                    <img src="<?= e($replyPic) ?>" alt="<?= e($reply['full_name']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,240,255,0.1);">
                                                <?php else: ?>
                                                    <div class="comment-avatar"><?= strtoupper(substr($reply['full_name'], 0, 1)) ?></div>
                                                <?php endif; ?>
                                                <div class="comment-body">
                                                    <div class="comment-meta">
                                                        <span class="comment-author"><?= e($reply['full_name']) ?></span>
                                                        <?php if ($reply['role'] === 'admin'): ?><span class="comment-badge admin">Admin</span><?php endif; ?>
                                                        <span class="comment-time"><?= date('M d, Y • h:i A', strtotime($reply['created_at'])) ?></span>
                                                        <span style="font-size:0.6rem;color:var(--text-dim);"><i class="fas fa-reply"></i> Reply</span>
                                                    </div>
                                                    <div class="comment-text"><?= nl2br(e($reply['comment'])) ?></div>
                                                    <?php if ($reply['user_id'] == $currentUserId || $_SESSION['user']['role'] === 'admin'): ?>
                                                        <div class="comment-actions">
                                                            <a href="?id=<?= $id ?>&delete_comment=1&cid=<?= $reply['id'] ?>" class="comment-action delete-btn" onclick="return confirm('Delete this reply?')"><i class="fas fa-trash"></i> Delete</a>
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
                        <div class="no-comments"><i class="fas fa-comment-slash"></i><p>No comments yet. Be the first to share your thoughts!</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

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
                <h6><i class="fas fa-book-open"></i> Related in <?= e($k['category_name']) ?></h6>
                <div class="kv-related">
                    <?php foreach ($related as $r): ?><a href="knowledge_view.php?id=<?= (int)$r['id'] ?>"><i class="fas fa-book"></i> <?= e($r['title']) ?></a><?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <a href="knowledge.php" class="kv-back-btn"><i class="fas fa-arrow-left"></i> Back to Knowledge Library</a>
        </div>
    </div>

<?php endif; ?>
</div>

<script>
function showToast(message, type = 'success', icon = '') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle', warning: 'fa-triangle-exclamation' };
    const toastIcon = icon || icons[type] || icons.info;
    toast.className = 'toast toast-' + type;
    toast.innerHTML = `<span class="toast-icon"><i class="fas ${toastIcon}"></i></span><span>${message}</span><button class="toast-close" onclick="this.parentElement.remove()">&times;</button>`;
    container.appendChild(toast);
    setTimeout(() => {
        if (toast.parentElement) {
            toast.classList.add('hiding');
            setTimeout(() => { if (toast.parentElement) toast.remove(); }, 300);
        }
    }, 3500);
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['success'])): ?>
        showToast('<?= addslashes($_SESSION['success']) ?>', 'success');
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        showToast('<?= addslashes($_SESSION['error']) ?>', 'error');
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});

function handleVote(id, vote, back) {
    showToast('⏳ Processing your vote...', 'info');
    fetch('knowledge_vote_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&vote=' + vote + '&back=' + encodeURIComponent(back)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.action === 'added') showToast('👍 Thanks for your feedback!', 'success');
            else if (data.action === 'removed') showToast('👎 Vote removed', 'warning');
            else if (data.action === 'updated') showToast('🔄 Vote updated!', 'info');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('❌ ' + data.error, 'error');
        }
    })
    .catch(error => showToast('❌ Something went wrong. Please try again.', 'error'));
}

function showReplyForm(commentId) {
    const form = document.getElementById('reply-form-' + commentId);
    if (form) { form.style.display = 'block'; const textarea = form.querySelector('textarea'); if (textarea) textarea.focus(); }
}
function hideReplyForm(commentId) {
    const form = document.getElementById('reply-form-' + commentId);
    if (form) form.style.display = 'none';
}
</script>
<div id="toastContainer" class="toast-container"></div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>