<?php
$page_title = 'My Contributions';
$page_css = ['employee-my_contributions.css'];
$page_js  = ['employee-my_contributions.js'];
require_once __DIR__ . '/../partials/header.php';

$user = current_user();
$currentUserId = $user['users_id'];

$stmt = db()->prepare(
    'SELECT k.*, kc.name AS category_name, d.name AS department_name,
            (SELECT COUNT(*) FROM knowledge_votes kv WHERE kv.knowledge_id = k.knowledge_id AND kv.vote = "helpful") AS helpful_count,
            (SELECT COUNT(*) FROM knowledge_votes kv2 WHERE kv2.knowledge_id = k.knowledge_id AND kv2.vote = "not_helpful") AS not_helpful_count,
            (SELECT COUNT(*) FROM knowledge_comments c WHERE c.knowledge_id = k.knowledge_id) AS comment_count,
            (SELECT COUNT(*) FROM knowledge_saves s WHERE s.knowledge_id = k.knowledge_id) AS save_count
     FROM knowledge k
     LEFT JOIN knowledge_categories kc ON kc.knowledge_categories_id = k.category_id
     LEFT JOIN departments d ON d.departments_id = k.department_id
     WHERE k.created_by = ?
     ORDER BY k.updated_at DESC'
);
$stmt->execute([$currentUserId]);
$rows = $stmt->fetchAll();

$totalContributions = count($rows);

$totalHelpful = 0;
$totalComments = 0;
$totalSaves = 0;
$totalViews = 0;
$activeCount = 0;
foreach ($rows as $r) {
    $totalHelpful += (int)$r['helpful_count'];
    $totalComments += (int)$r['comment_count'];
    $totalSaves += (int)$r['save_count'];
    $totalViews += (int)($r['view_count'] ?? 0);
    if (($r['status'] ?? 'active') === 'active') $activeCount++;
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

function mcTimeAgo($ts) {
    $diff = time() - strtotime($ts);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', strtotime($ts));
}
?>

<?php if (isset($_SESSION['success'])): ?>
<div class="mc-toast success" id="mcToast"><i class="fas fa-circle-check"></i><span><?= e($_SESSION['success']); unset($_SESSION['success']); ?></span></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="mc-toast error" id="mcToast"><i class="fas fa-circle-exclamation"></i><span><?= e($_SESSION['error']); unset($_SESSION['error']); ?></span></div>
<?php endif; ?>

<div class="mc-page">
    <div class="mc-bg">
        <div class="grid"></div>
        <div class="aurora a1"></div>
        <div class="aurora a2"></div>
    </div>
    <div class="mc-scenery" id="mcScenery"></div>

    <!-- Hero -->
    <div class="mc-hero">
        <div class="mc-hero-row">
            <div>
                <h1><span class="logo-badge"><i class="fas fa-star"></i></span> My Contributions</h1>
                <p>All the knowledge articles you've shared with the community.</p>
            </div>
            <a href="share_knowledge.php" class="mc-hero-cta">
                <span class="cta-logo"><i class="fas fa-plus"></i></span>
                New Article
            </a>
        </div>

        <div class="mc-hero-stats">
            <div class="mc-stat-chip">
                <i class="total"><i class="fas fa-book"></i></i>
                <div class="mc-stat-chip-info">
                    <span class="val"><?= (int)$totalContributions ?></span>
                    <span class="lbl">Articles</span>
                </div>
            </div>
            <div class="mc-stat-chip">
                <i class="help"><i class="fas fa-thumbs-up"></i></i>
                <div class="mc-stat-chip-info">
                    <span class="val"><?= (int)$totalHelpful ?></span>
                    <span class="lbl">Confirmed</span>
                </div>
            </div>
            <div class="mc-stat-chip">
                <i class="comm"><i class="fas fa-comment"></i></i>
                <div class="mc-stat-chip-info">
                    <span class="val"><?= (int)$totalComments ?></span>
                    <span class="lbl">Comments</span>
                </div>
            </div>
            <div class="mc-stat-chip">
                <i class="saves"><i class="fas fa-bookmark"></i></i>
                <div class="mc-stat-chip-info">
                    <span class="val"><?= (int)$totalSaves ?></span>
                    <span class="lbl">Saves</span>
                </div>
            </div>
            <div class="mc-stat-chip">
                <i class="views"><i class="fas fa-eye"></i></i>
                <div class="mc-stat-chip-info">
                    <span class="val"><?= number_format($totalViews) ?></span>
                    <span class="lbl">Views</span>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="mc-empty">
            <div class="empty-icon"><i class="fas fa-pen-fancy"></i></div>
            <h3>No Contributions Yet</h3>
            <p>Share your knowledge with the community by creating your first article. Every contribution helps someone solve a problem faster.</p>
            <a href="share_knowledge.php" class="btn-create">
                <span class="logo"><i class="fas fa-pen-fancy"></i></span>
                Create Your First Article
            </a>
        </div>
    <?php else: ?>
        <div class="mc-grid">
            <?php foreach ($rows as $i => $k):
                $helpful    = (int)($k['helpful_count'] ?? 0);
                $comments   = (int)($k['comment_count'] ?? 0);
                $saves      = (int)($k['save_count'] ?? 0);
                $views      = (int)($k['view_count'] ?? 0);
                $status     = $k['status'] ?? 'active';
    $kid        = $k['knowledge_id'];
            ?>
                <div class="mc-card" style="animation-delay: <?= min($i * 0.04, 0.6) ?>s;">
                    <div class="mc-card-spine"></div>
                    <div class="mc-card-body">
                        <div class="mc-card-top">
                            <span class="mc-cat-chip">
                                <i class="fas <?= e(klps_category_icon($k['category_name'])) ?>"></i>
                                <?= e($k['category_name'] ?? 'Uncategorized') ?>
                            </span>
                            <span class="mc-status <?= e($status) ?>">
                                <i class="fas fa-<?= $status === 'active' ? 'circle-check' : ($status === 'draft' ? 'pen' : 'box-archive') ?>"></i>
                                <?= ucfirst($status) ?>
                            </span>
                        </div>

                        <a href="knowledge_view.php?id=<?= $kid ?>" class="mc-card-title"><?= e($k['title']) ?></a>
                        <div class="mc-card-excerpt"><?= e(mb_substr($k['problem'], 0, 120)) ?>…</div>

                        <div class="mc-metrics">
                            <div class="mc-metric help">
                                <i class="fas fa-thumbs-up m-icon"></i>
                                <span class="m-val"><?= $helpful ?></span>
                                <span class="m-lbl">Helpful</span>
                            </div>
                            <div class="mc-metric comm">
                                <i class="fas fa-comment m-icon"></i>
                                <span class="m-val"><?= $comments ?></span>
                                <span class="m-lbl">Comments</span>
                            </div>
                            <div class="mc-metric save">
                                <i class="fas fa-bookmark m-icon"></i>
                                <span class="m-val"><?= $saves ?></span>
                                <span class="m-lbl">Saves</span>
                            </div>
                            <div class="mc-metric view">
                                <i class="fas fa-eye m-icon"></i>
                                <span class="m-val"><?= $views ?></span>
                                <span class="m-lbl">Views</span>
                            </div>
                        </div>

                        <div class="mc-card-footer">
                            <span class="mc-card-date">
                                <i class="fas fa-clock"></i> <?= e(mcTimeAgo($k['updated_at'] ?? $k['created_at'])) ?>
                            </span>
                            <div class="mc-card-actions">
                                <a class="mc-action view" href="knowledge_view.php?id=<?= $kid ?>" title="View article">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a class="mc-action open" href="knowledge_view.php?id=<?= $kid ?>">
                                    Open <i class="fas fa-arrow-right" style="font-size:0.55rem;"></i>
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