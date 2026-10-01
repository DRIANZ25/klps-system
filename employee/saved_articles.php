<?php
$page_title = 'Saved Articles';
$page_css = ['employee-saved_articles.css'];
$page_js  = ['employee-saved_articles.js'];
require_once __DIR__ . '/../partials/header.php';

$currentUserId = $_SESSION['user']['users_id'] ?? 0;

$stmt = db()->prepare(
    'SELECT k.*, kc.name AS category_name, u.full_name AS author,
            u.profile_picture AS author_profile_picture,
            s.created_at AS saved_at,
            (SELECT COUNT(*) FROM knowledge_votes kv WHERE kv.knowledge_id = k.knowledge_id AND kv.vote = "helpful") AS helpful_count,
            (SELECT COUNT(*) FROM knowledge_comments c WHERE c.knowledge_id = k.knowledge_id) AS comment_count,
            (SELECT COUNT(*) FROM knowledge_saves s2 WHERE s2.knowledge_id = k.knowledge_id) AS save_count,
            (SELECT vote FROM knowledge_votes kv2 WHERE kv2.knowledge_id = k.knowledge_id AND kv2.user_id = ?) AS my_vote
     FROM knowledge_saves s
     JOIN knowledge k ON k.knowledge_id = s.knowledge_id
     LEFT JOIN knowledge_categories kc ON kc.knowledge_categories_id = k.category_id
     JOIN users u ON u.users_id = k.created_by
     WHERE s.user_id = ? AND k.status = "active"
     ORDER BY s.created_at DESC'
);
$stmt->execute([$currentUserId, $currentUserId]);
$savedArticles = $stmt->fetchAll();

$totalSaved = count($savedArticles);
$totalHelpful = 0;
$totalViews = 0;
$uniqueCategories = [];
foreach ($savedArticles as $a) {
    $totalHelpful += (int)$a['helpful_count'];
    $totalViews += (int)($a['view_count'] ?? 0);
    if (!empty($a['category_name'])) $uniqueCategories[$a['category_name']] = true;
}
$totalCategories = count($uniqueCategories);

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

function svTimeAgo($ts) {
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
<div class="sv-toast success" id="svToast"><i class="fas fa-circle-check"></i><span><?= e($_SESSION['success']); unset($_SESSION['success']); ?></span></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="sv-toast error" id="svToast"><i class="fas fa-circle-exclamation"></i><span><?= e($_SESSION['error']); unset($_SESSION['error']); ?></span></div>
<?php endif; ?>



<div class="sv-page">
    <div class="sv-bg">
        <div class="grid"></div>
        <div class="aurora a1"></div>
        <div class="aurora a2"></div>
    </div>
    <div class="sv-scenery" id="svScenery"></div>

    <!-- HERO -->
    <div class="sv-hero">
        <div class="sv-hero-row">
            <div>
                <h1><span class="logo-badge"><i class="fas fa-bookmark"></i></span> Saved Articles</h1>
                <p>Your personal reading list — articles you've bookmarked for later.</p>
            </div>
            <a href="knowledge.php" class="sv-hero-cta">
                <span class="cta-logo"><i class="fas fa-book-open"></i></span>
                Browse Library
            </a>
        </div>

        <div class="sv-hero-stats">
            <div class="sv-stat-chip">
                <i class="saved"><i class="fas fa-bookmark"></i></i>
                <div class="sv-stat-chip-info">
                    <span class="val"><?= (int)$totalSaved ?></span>
                    <span class="lbl">Saved</span>
                </div>
            </div>
            <div class="sv-stat-chip">
                <i class="cats"><i class="fas fa-tags"></i></i>
                <div class="sv-stat-chip-info">
                    <span class="val"><?= (int)$totalCategories ?></span>
                    <span class="lbl">Categories</span>
                </div>
            </div>
            <div class="sv-stat-chip">
                <i class="help"><i class="fas fa-thumbs-up"></i></i>
                <div class="sv-stat-chip-info">
                    <span class="val"><?= (int)$totalHelpful ?></span>
                    <span class="lbl">Confirmed</span>
                </div>
            </div>
            <div class="sv-stat-chip">
                <i class="views"><i class="fas fa-eye"></i></i>
                <div class="sv-stat-chip-info">
                    <span class="val"><?= number_format($totalViews) ?></span>
                    <span class="lbl">Total Views</span>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($savedArticles)): ?>
        <div class="sv-empty">
            <div class="empty-icon"><i class="fas fa-bookmark"></i></div>
            <h3>Nothing Saved Yet</h3>
            <p>Start saving useful articles by clicking the bookmark icon on any knowledge article. They'll appear here for easy access.</p>
            <a href="knowledge.php" class="btn-browse">
                <span class="logo"><i class="fas fa-book-open"></i></span>
                Browse Knowledge Library
            </a>
        </div>
    <?php else: ?>
        <div class="sv-grid">
            <?php foreach ($savedArticles as $i => $k):
                $initials = strtoupper(substr($k['author'], 0, 1));
                $preview = mb_substr($k['problem'], 0, 120) . (mb_strlen($k['problem']) > 120 ? '…' : '');
                $profilePic = !empty($k['author_profile_picture']) ? '../uploads/profiles/' . $k['author_profile_picture'] : '';
                $profilePicExists = $profilePic && file_exists(__DIR__ . '/../uploads/profiles/' . $k['author_profile_picture']);
  $kid = $k['knowledge_id'];
            ?>
                <div class="sv-card" style="animation-delay: <?= min($i * 0.04, 0.6) ?>s;">
                    <div class="sv-card-spine"></div>
                    <div class="sv-card-body">
                        <div class="sv-card-top">
                            <span class="sv-cat-chip">
                                <i class="fas <?= e(klps_category_icon($k['category_name'])) ?>"></i>
                                <?= e($k['category_name'] ?? 'Uncategorized') ?>
                            </span>
                            <span class="sv-saved-badge"><i class="fas fa-bookmark"></i> Saved</span>
                        </div>

                        <a href="knowledge_view.php?id=<?= $kid ?>" class="sv-card-title"><?= e($k['title']) ?></a>
                        <div class="sv-card-excerpt"><?= e($preview) ?></div>

                        <div class="sv-card-footer">
                            <div class="sv-card-author">
                                <?php if ($profilePicExists): ?>
                                    <img src="<?= e($profilePic) ?>" alt="<?= e($k['author']) ?>" class="avatar-img">
                                <?php else: ?>
                                    <div class="avatar"><?= e($initials) ?></div>
                                <?php endif; ?>
                                <span class="name"><?= e($k['author']) ?></span>
                                <span class="date">Saved <?= e(svTimeAgo($k['saved_at'])) ?></span>
                            </div>
                            <div class="sv-card-actions">
                                <a class="sv-action unsave"
                                   href="knowledge_save.php?id=<?= $kid ?>&back=<?= urlencode('page=saved_articles') ?>"
                                   onclick="return confirm('Remove this from your saved articles?')"
                                    title="Remove from saved">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <a class="sv-action read" href="knowledge_view.php?id=<?= $kid ?>">
                                    Read <i class="fas fa-arrow-right" style="font-size:0.55rem;"></i>
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