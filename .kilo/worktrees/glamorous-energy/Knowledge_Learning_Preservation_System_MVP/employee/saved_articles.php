<?php
$page_title = 'Saved Articles';
require_once __DIR__ . '/../partials/header.php';

$currentUserId = $_SESSION['user']['id'] ?? 0;

// Get saved articles
$stmt = db()->prepare(
    'SELECT k.*, kc.name AS category_name, u.full_name AS author,
            (SELECT COUNT(*) FROM knowledge_votes kv WHERE kv.knowledge_id = k.id AND kv.vote = "helpful") AS helpful_count,
            (SELECT COUNT(*) FROM knowledge_comments c WHERE c.knowledge_id = k.id) AS comment_count
     FROM knowledge_saves s
     JOIN knowledge k ON k.id = s.knowledge_id
     LEFT JOIN knowledge_categories kc ON kc.id = k.category_id
     JOIN users u ON u.id = k.created_by
     WHERE s.user_id = ? AND k.status = "active"
     ORDER BY s.created_at DESC'
);
$stmt->execute([$currentUserId]);
$savedArticles = $stmt->fetchAll();

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
?>
<style>
:root {
    --cyan: #00f0ff;
    --violet: #8b7cf6;
    --gold: #f6c453;
    --text-dim: #8892b0;
    --border: rgba(255,255,255,0.07);
}
.saved-page {
    background: radial-gradient(ellipse at 20% -10%, #1a2140 0%, #03050a 45%, #050710 100%);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(0,240,255,0.08);
}
.saved-header {
    margin-bottom: 2rem;
}
.saved-header h1 {
    font-family: 'Orbitron', sans-serif;
    font-size: 2rem;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.saved-header h1 i { color: var(--gold); }
.saved-header p { color: var(--text-dim); }
.saved-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
}
.saved-card {
    background: linear-gradient(160deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 14px;
    overflow: hidden;
    transition: all 0.3s ease;
}
.saved-card:hover {
    transform: translateY(-6px);
    border-color: rgba(0,240,255,0.12);
    box-shadow: 0 20px 40px -15px rgba(0,0,0,0.5);
}
.saved-card .card-spine {
    height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--cyan));
}
.saved-card .card-body { padding: 1.25rem 1.5rem 1.5rem; }
.saved-card .card-top {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}
.saved-card .card-category {
    font-size: 0.65rem;
    text-transform: uppercase;
    color: var(--text-dim);
    background: rgba(255,255,255,0.04);
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
}
.saved-card .card-saved-badge {
    font-size: 0.65rem;
    color: var(--gold);
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    background: rgba(246,196,83,0.08);
    border: 1px solid rgba(246,196,83,0.15);
}
.saved-card .card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    text-decoration: none;
    display: block;
    margin-bottom: 0.5rem;
}
.saved-card .card-title:hover { color: var(--cyan); }
.saved-card .card-excerpt {
    color: var(--text-dim);
    font-size: 0.85rem;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 1rem;
}
.saved-card .card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255,255,255,0.04);
}
.saved-card .card-author {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-dim);
}
.saved-card .card-author .avatar {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--cyan), var(--violet));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #0a0e1a;
    font-size: 0.6rem;
}
.saved-card .card-actions {
    display: flex;
    gap: 0.5rem;
}
.saved-card .card-actions .read-btn {
    padding: 0.3rem 0.8rem;
    border-radius: 6px;
    background: rgba(0,240,255,0.08);
    border: 1px solid rgba(0,240,255,0.08);
    color: var(--cyan);
    text-decoration: none;
    font-size: 0.7rem;
    font-weight: 600;
}
.saved-card .card-actions .read-btn:hover {
    background: rgba(0,240,255,0.15);
}
.saved-card .card-actions .unsave-btn {
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    background: rgba(239,68,68,0.05);
    border: 1px solid rgba(239,68,68,0.1);
    color: #ef4444;
    text-decoration: none;
    font-size: 0.7rem;
}
.saved-card .card-actions .unsave-btn:hover {
    background: rgba(239,68,68,0.12);
}
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-dim);
}
.empty-state i {
    font-size: 3.5rem;
    color: rgba(0,240,255,0.1);
    display: block;
    margin-bottom: 1.5rem;
}
.empty-state h3 { color: #fff; font-size: 1.25rem; margin-bottom: 0.5rem; }
.empty-state .btn-browse {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.6rem 1.5rem;
    background: linear-gradient(135deg, var(--cyan), var(--violet));
    border: none;
    border-radius: 10px;
    color: #0a0e1a;
    font-weight: 700;
    text-decoration: none;
}
@media (max-width: 768px) {
    .saved-page { padding: 1rem; }
    .saved-grid { grid-template-columns: 1fr; }
    .saved-header h1 { font-size: 1.4rem; }
}
</style>

<div class="saved-page">
    <div class="saved-header">
        <h1><i class="fas fa-bookmark"></i> Saved Articles</h1>
        <p>Articles you've bookmarked for later reading.</p>
    </div>

    <?php if (empty($savedArticles)): ?>
        <div class="empty-state">
            <i class="fas fa-bookmark"></i>
            <h3>No Saved Articles</h3>
            <p>Start saving articles you find useful by clicking the bookmark icon.</p>
            <a href="knowledge.php" class="btn-browse"><i class="fas fa-book-open"></i> Browse Knowledge Library</a>
        </div>
    <?php else: ?>
        <div class="saved-grid">
            <?php foreach ($savedArticles as $k): ?>
                <div class="saved-card">
                    <div class="card-spine"></div>
                    <div class="card-body">
                        <div class="card-top">
                            <span class="card-category">
                                <i class="fas <?= e(klps_category_icon($k['category_name'])) ?>"></i> <?= e($k['category_name'] ?? 'Uncategorized') ?>
                            </span>
                            <span class="card-saved-badge"><i class="fas fa-bookmark"></i> Saved</span>
                        </div>
                        <a href="knowledge_view.php?id=<?= (int)$k['id'] ?>" class="card-title">
                            <?= e($k['title']) ?>
                        </a>
                        <div class="card-excerpt">
                            <?= e(mb_substr($k['problem'], 0, 120)) ?>...
                        </div>
                        <div class="card-footer">
                            <div class="card-author">
                                <div class="avatar"><?= strtoupper(substr($k['author'], 0, 1)) ?></div>
                                <span><?= e($k['author']) ?></span>
                                <span style="color:var(--text-dim);font-size:0.65rem;">· <?= date('M d', strtotime($k['created_at'])) ?></span>
                            </div>
                            <div class="card-actions">
                                <a href="knowledge_save.php?id=<?= (int)$k['id'] ?>&back=<?= urlencode('page=saved_articles') ?>" class="unsave-btn" onclick="return confirm('Remove this from saved articles?')">
                                    <i class="fas fa-times"></i> Unsave
                                </a>
                                <a href="knowledge_view.php?id=<?= (int)$k['id'] ?>" class="read-btn">
                                    Read <i class="fas fa-arrow-right" style="font-size:0.6rem;"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>