<?php
$page_title = 'My Contributions';
require_once __DIR__ . '/../partials/header.php';

$user = current_user();
$currentUserId = $user['id'];

// Get user's articles with stats
$stmt = db()->prepare(
    'SELECT k.*, kc.name AS category_name,
            (SELECT COUNT(*) FROM knowledge_votes kv WHERE kv.knowledge_id = k.id AND kv.vote = "helpful") AS helpful_count,
            (SELECT COUNT(*) FROM knowledge_votes kv2 WHERE kv2.knowledge_id = k.id AND kv2.vote = "not_helpful") AS not_helpful_count,
            (SELECT COUNT(*) FROM knowledge_comments c WHERE c.knowledge_id = k.id) AS comment_count,
            (SELECT COUNT(*) FROM knowledge_saves s WHERE s.knowledge_id = k.id) AS save_count
     FROM knowledge k
     LEFT JOIN knowledge_categories kc ON kc.id = k.category_id
     WHERE k.created_by = ?
     ORDER BY k.updated_at DESC'
);
$stmt->execute([$currentUserId]);
$rows = $stmt->fetchAll();

// Get user's total contributions stats
$stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_contributions WHERE user_id = ?');
$stmt->execute([$currentUserId]);
$totalContributions = (int)$stmt->fetchColumn();

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
.contributions-page {
    background: radial-gradient(ellipse at 20% -10%, #1a2140 0%, #03050a 45%, #050710 100%);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(0,240,255,0.08);
}
.contributions-header {
    margin-bottom: 2rem;
}
.contributions-header h1 {
    font-family: 'Orbitron', sans-serif;
    font-size: 2rem;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.contributions-header h1 i { color: var(--gold); }
.contributions-header p { color: var(--text-dim); }
.contributions-stats {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
    margin: 1rem 0;
}
.contributions-stats .stat {
    color: var(--text-dim);
    font-size: 0.85rem;
}
.contributions-stats .stat strong { color: #fff; }
.contributions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
}
.contribution-card {
    background: linear-gradient(160deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 14px;
    overflow: hidden;
    transition: all 0.3s ease;
}
.contribution-card:hover {
    transform: translateY(-6px);
    border-color: rgba(0,240,255,0.12);
    box-shadow: 0 20px 40px -15px rgba(0,0,0,0.5);
}
.contribution-card .card-spine {
    height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--violet));
}
.contribution-card .card-body { padding: 1.25rem 1.5rem 1.5rem; }
.contribution-card .card-top {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}
.contribution-card .card-category {
    font-size: 0.65rem;
    text-transform: uppercase;
    color: var(--text-dim);
    background: rgba(255,255,255,0.04);
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
}
.contribution-card .card-status {
    font-size: 0.65rem;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
}
.contribution-card .card-status.active {
    color: #00ff9c;
    background: rgba(0,255,156,0.06);
    border: 1px solid rgba(0,255,156,0.1);
}
.contribution-card .card-status.draft {
    color: var(--gold);
    background: rgba(246,196,83,0.06);
    border: 1px solid rgba(246,196,83,0.1);
}
.contribution-card .card-status.archived {
    color: var(--text-dim);
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.05);
}
.contribution-card .card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    text-decoration: none;
    display: block;
    margin-bottom: 0.5rem;
}
.contribution-card .card-title:hover { color: var(--cyan); }
.contribution-card .card-excerpt {
    color: var(--text-dim);
    font-size: 0.85rem;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 1rem;
}
.contribution-card .card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255,255,255,0.04);
    flex-wrap: wrap;
    gap: 0.5rem;
}
.contribution-card .card-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.7rem;
    color: var(--text-dim);
}
.contribution-card .card-meta i { color: var(--cyan); }
.contribution-card .card-actions {
    display: flex;
    gap: 0.3rem;
}
.contribution-card .card-actions .action-btn {
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.04);
    background: rgba(255,255,255,0.02);
    color: var(--text-dim);
    cursor: pointer;
    font-size: 0.7rem;
    text-decoration: none;
}
.contribution-card .card-actions .action-btn:hover {
    background: rgba(255,255,255,0.06);
    color: #fff;
}
.contribution-card .card-actions .edit-btn:hover { color: var(--cyan); }
.contribution-card .card-actions .view-btn:hover { color: var(--gold); }
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
.empty-state .btn-create {
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
    .contributions-page { padding: 1rem; }
    .contributions-grid { grid-template-columns: 1fr; }
    .contributions-header h1 { font-size: 1.4rem; }
}
</style>

<div class="contributions-page">
    <div class="contributions-header">
        <h1><i class="fas fa-star"></i> My Contributions</h1>
        <p>All the knowledge articles you've shared with the community.</p>
        <div class="contributions-stats">
            <div class="stat"><strong><?= count($rows) ?></strong> Articles</div>
            <div class="stat"><strong><?= $totalContributions ?></strong> Total Contributions</div>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty-state">
            <i class="fas fa-pen-fancy"></i>
            <h3>No Contributions Yet</h3>
            <p>Share your knowledge with the community by creating an article.</p>
            <a href="knowledge.php" class="btn-create"><i class="fas fa-plus"></i> Create Article</a>
        </div>
    <?php else: ?>
        <div class="contributions-grid">
            <?php foreach ($rows as $k): 
                $helpful = (int)($k['helpful_count'] ?? 0);
                $status = $k['status'] ?? 'active';
            ?>
                <div class="contribution-card">
                    <div class="card-spine"></div>
                    <div class="card-body">
                        <div class="card-top">
                            <span class="card-category">
                                <i class="fas <?= e(klps_category_icon($k['category_name'])) ?>"></i> <?= e($k['category_name'] ?? 'Uncategorized') ?>
                            </span>
                            <span class="card-status <?= e($status) ?>"><?= ucfirst($status) ?></span>
                        </div>
                        <a href="knowledge_view.php?id=<?= (int)$k['id'] ?>" class="card-title">
                            <?= e($k['title']) ?>
                        </a>
                        <div class="card-excerpt">
                            <?= e(mb_substr($k['problem'], 0, 100)) ?>...
                        </div>
                        <div class="card-footer">
                            <div class="card-meta">
                                <span><i class="fas fa-thumbs-up"></i> <?= $helpful ?></span>
                                <span><i class="fas fa-comment"></i> <?= (int)($k['comment_count'] ?? 0) ?></span>
                                <span><i class="fas fa-bookmark"></i> <?= (int)($k['save_count'] ?? 0) ?></span>
                                <span><i class="fas fa-calendar"></i> <?= date('M d', strtotime($k['updated_at'] ?? $k['created_at'])) ?></span>
                            </div>
                            <div class="card-actions">
                                <a href="knowledge_view.php?id=<?= (int)$k['id'] ?>" class="action-btn view-btn"><i class="fas fa-eye"></i></a>
                                <a href="knowledge.php?action=edit&id=<?= (int)$k['id'] ?>" class="action-btn edit-btn"><i class="fas fa-edit"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>