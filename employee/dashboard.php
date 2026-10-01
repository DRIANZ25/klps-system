<?php
$page_title = 'Employee Dashboard';
$page_css = ['employee-dashboard.css'];
$page_js  = ['employee-dashboard.js'];
require_once __DIR__ . '/../partials/header.php';

$pdo = db();
$me = $user['users_id'];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge WHERE created_by = ?');
$stmt->execute([$me]);
$myKnowledge = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM ai_conversations WHERE user_id = ?');
$stmt->execute([$me]);
$myChats = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge_saves WHERE user_id = ?');
$stmt->execute([$me]);
$bookmarks = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT k.*, u.full_name, u.profile_picture, kc.name AS category_name
     FROM knowledge k
     JOIN users u ON k.created_by = u.users_id
     LEFT JOIN knowledge_categories kc ON kc.knowledge_categories_id = k.category_id
     ORDER BY k.created_at DESC LIMIT 5'
);
$stmt->execute();
$recentKnowledge = $stmt->fetchAll();

// ===== 7-day activity sparkline data =====
// A tiny rollup: how many knowledge articles were added per day over the last 7 days.
$sparkline = [0,0,0,0,0,0,0];
$sparklineChats = [0,0,0,0,0,0,0];
try {
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge WHERE DATE(created_at) = ?');
        $stmt->execute([$day]);
        $sparkline[6 - $i] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM ai_conversations WHERE DATE(created_at) = ?');
        $stmt->execute([$day]);
        $sparklineChats[6 - $i] = (int)$stmt->fetchColumn();
    }
} catch (PDOException $e) {}

$avgPrev = array_sum(array_slice($sparkline, 0, 6)) / 6;
$last = (float)$sparkline[6];
$trendPct = $avgPrev > 0 ? round((($last - $avgPrev) / $avgPrev) * 100) : ($last > 0 ? 100 : 0);

$xp = ($myKnowledge * 10) + ($myChats * 3) + ($bookmarks * 2);
$xpPerLevel = 50;
$level = intdiv($xp, $xpPerLevel) + 1;
$xpIntoLevel = $xp % $xpPerLevel;
$xpProgressPct = (int) round(($xpIntoLevel / $xpPerLevel) * 100);
$xpToNext = $xpPerLevel - $xpIntoLevel;

$ranks = [
    1 => ['title' => 'Seeker', 'icon' => 'fa-magnifying-glass'],
    2 => ['title' => 'Apprentice', 'icon' => 'fa-book'],
    3 => ['title' => 'Scholar', 'icon' => 'fa-graduation-cap'],
    4 => ['title' => 'Archivist', 'icon' => 'fa-book-atlas'],
    5 => ['title' => 'Sage', 'icon' => 'fa-brain'],
];
$rankKey = min($level, 5);
$rankTitle = $ranks[$rankKey]['title'];
$rankIcon = $ranks[$rankKey]['icon'];

$hour = (int)date('G');
if ($hour < 12)      $greeting = 'Good morning';
elseif ($hour < 18)  $greeting = 'Good afternoon';
else                 $greeting = 'Good evening';

// ===== Longest-term streak: how many consecutive days (incl today) have at least one activity =====
$streak = 0;
try {
    for ($i = 0; $i < 30; $i++) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare('SELECT (
            (SELECT COUNT(*) FROM knowledge WHERE DATE(created_at) = ? AND created_by = ?) +
            (SELECT COUNT(*) FROM ai_conversations WHERE DATE(created_at) = ? AND user_id = ?)
        )');
        $stmt->execute([$day, $me, $day, $me]);
        if ((int)$stmt->fetchColumn() > 0) $streak++;
        else break;
    }
} catch (PDOException $e) {}

function klps_initials_avatar(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(substr($parts[0] ?? '?', 0, 1) . substr($parts[1] ?? '', 0, 1));
    return $initials ?: '?';
}
?>

<!-- Global animated backdrop -->
<div class="dash-bg" aria-hidden="true">
    <div class="grid"></div>
    <div class="aurora a1"></div>
    <div class="aurora a2"></div>
    <div class="aurora a3"></div>
    <div class="scan"></div>
</div>

<div class="container-fluid">
    <div class="row">
        <main class="col-12 p-4">

            <!-- ============ WELCOME HERO ============ -->
            <div class="welcome-section">
                <div class="glow-orb orb1"></div>
                <div class="glow-orb orb2"></div>
                <canvas id="neonBrainCanvas"></canvas>
                <div class="content-wrapper">
                    <div class="welcome-top">
                        <div>
                            <div class="greeting-chip">
                                <span class="pulse-dot"></span>
                                <?= e($greeting) ?> · <?= date('l, M j') ?>
                            </div>
                            <h2><span class="wave">👋</span> Welcome back, <?= e($user['full_name']) ?>!</h2>
                            <p>Every question asked and every answer shared expands the archive. Where will your search for knowledge take you today?</p>
                        </div>
                        <div class="rank-badge">
                            <div class="rank-icon"><i class="fas <?= e($rankIcon) ?>"></i></div>
                            <div>
                                <div class="rank-title"><?= e($rankTitle) ?></div>
                                <div class="rank-sub">Level <?= (int)$level ?> · <?= (int)$xp ?> XP</div>
                            </div>
                        </div>
                    </div>

                    <div class="xp-track">
                        <div class="xp-ring-wrap">
                            <svg width="90" height="90" viewBox="0 0 90 90">
                                <defs>
                                    <linearGradient id="xpGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#8b7cf6"/>
                                        <stop offset="100%" stop-color="#f6c453"/>
                                    </linearGradient>
                                </defs>
                                <circle class="ring-bg" cx="45" cy="45" r="36" fill="none" stroke-width="6"/>
                                <circle class="ring-fg" cx="45" cy="45" r="36" fill="none" stroke-width="6"
                                        stroke-dasharray="226.2"
                                        stroke-dashoffset="<?= 226.2 - (226.2 * $xpProgressPct / 100) ?>"/>
                            </svg>
                            <div class="xp-ring-label">
                                <div class="pct"><?= $xpProgressPct ?>%</div>
                                <div class="sub">LV <?= (int)$level ?></div>
                            </div>
                        </div>
                        <div class="xp-text-labels">
                            <div class="row1">Level <?= (int)$level ?> · <?= e($rankTitle) ?></div>
                            <div class="row2"><strong><?= (int)$xpToNext ?> XP</strong> to Level <?= (int)$level + 1 ?> · <strong><?= (int)$xp ?></strong> total</div>
                            <div class="row2" style="margin-top:0.35rem;">
                                <?php if ($streak > 0): ?>
                                    🔥 <strong><?= (int)$streak ?></strong>-day streak
                                <?php else: ?>
                                    Start a streak today — ask the AI or contribute an article
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="welcome-actions">
                        <a href="ai_chat.php" class="btn-quest btn-quest-primary"><i class="fas fa-robot"></i>Start AI Chat</a>
                        <a href="knowledge.php" class="btn-quest btn-quest-outline"><i class="fas fa-book-open"></i>Explore Knowledge</a>
                    </div>
                </div>
            </div>

            <!-- ============ STAT CARDS ============ -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stats-card" style="--stat-glow:rgba(139,124,246,0.2); --accent: var(--violet);">
                        <div class="stats-card-head">
                            <div class="cube-wrap">
                                <div class="cube">
                                    <div class="face stats-icon-knowledge" style="transform: translateZ(24px);"><i class="fas fa-book"></i></div>
                                    <div class="face stats-icon-knowledge" style="transform: rotateY(180deg) translateZ(24px);"><i class="fas fa-book"></i></div>
                                    <div class="face stats-icon-knowledge" style="transform: rotateY(90deg) translateZ(24px);"><i class="fas fa-book"></i></div>
                                    <div class="face stats-icon-knowledge" style="transform: rotateY(-90deg) translateZ(24px);"><i class="fas fa-book"></i></div>
                                </div>
                            </div>
                            <?php if ($trendPct > 0): ?>
                                <span class="trend-chip up"><i class="fas fa-arrow-trend-up"></i> +<?= (int)$trendPct ?>%</span>
                            <?php elseif ($trendPct < 0): ?>
                                <span class="trend-chip down"><i class="fas fa-arrow-trend-down"></i> <?= (int)$trendPct ?>%</span>
                            <?php else: ?>
                                <span class="trend-chip flat"><i class="fas fa-minus"></i> Flat</span>
                            <?php endif; ?>
                        </div>
                        <div class="stats-label">Knowledge Contributed</div>
                        <div class="stats-value"><?= $myKnowledge ?></div>
                        <div class="stats-foot"><i class="fas fa-seedling"></i>Articles you've added to the archive</div>
                        <div class="spark-wrap">
                            <canvas class="spark" data-values="<?= e(implode(',', $sparkline)) ?>"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card" style="--stat-glow:rgba(79,215,232,0.2); --accent: var(--cyan);">
                        <div class="stats-card-head">
                            <div class="cube-wrap">
                                <div class="cube" style="animation-duration: 7s;">
                                    <div class="face stats-icon-chats" style="transform: translateZ(24px);"><i class="fas fa-comments"></i></div>
                                    <div class="face stats-icon-chats" style="transform: rotateY(180deg) translateZ(24px);"><i class="fas fa-comments"></i></div>
                                    <div class="face stats-icon-chats" style="transform: rotateY(90deg) translateZ(24px);"><i class="fas fa-comments"></i></div>
                                    <div class="face stats-icon-chats" style="transform: rotateY(-90deg) translateZ(24px);"><i class="fas fa-comments"></i></div>
                                </div>
                            </div>
                            <?php
                            $chatSum = array_sum($sparklineChats);
                            $chatTrend = $chatSum > 0 ? '+' . $chatSum : 'Flat';
                            ?>
                            <span class="trend-chip <?= $chatSum > 0 ? 'up' : 'flat' ?>">
                                <i class="fas <?= $chatSum > 0 ? 'fa-arrow-trend-up' : 'fa-minus' ?>"></i>
                                <?= $chatSum > 0 ? 'Active' : 'Idle' ?>
                            </span>
                        </div>
                        <div class="stats-label">AI Conversations</div>
                        <div class="stats-value"><?= $myChats ?></div>
                        <div class="stats-foot"><i class="fas fa-wand-magic-sparkles"></i>Questions explored with your AI assistant</div>
                        <div class="spark-wrap">
                            <canvas class="spark" data-values="<?= e(implode(',', $sparklineChats)) ?>"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card" style="--stat-glow:rgba(246,196,83,0.22); --accent: var(--gold);">
                        <div class="stats-card-head">
                            <div class="cube-wrap">
                                <div class="cube" style="animation-duration: 8s;">
                                    <div class="face stats-icon-bookmarks" style="transform: translateZ(24px);"><i class="fas fa-bookmark"></i></div>
                                    <div class="face stats-icon-bookmarks" style="transform: rotateY(180deg) translateZ(24px);"><i class="fas fa-bookmark"></i></div>
                                    <div class="face stats-icon-bookmarks" style="transform: rotateY(90deg) translateZ(24px);"><i class="fas fa-bookmark"></i></div>
                                    <div class="face stats-icon-bookmarks" style="transform: rotateY(-90deg) translateZ(24px);"><i class="fas fa-bookmark"></i></div>
                                </div>
                            </div>
                            <span class="trend-chip up"><i class="fas fa-bookmark"></i> <?= $bookmarks ?></span>
                        </div>
                        <div class="stats-label">Saved Knowledge</div>
                        <div class="stats-value"><?= $bookmarks ?></div>
                        <div class="stats-foot"><i class="fas fa-map-pin"></i>Articles bookmarked for later reading</div>
                        <div class="spark-wrap">
                            <canvas class="spark" data-values="<?= e(implode(',', array_reverse($sparkline))) ?>" data-color="gold"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ RECENT ACTIVITY + LIVE BRAIN ============ -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card activity-card-live">
                        <div class="card-header">
                            <h5 class="card-title"><i class="fas fa-timeline"></i>Recent Activity</h5>
                            <span style="font-size:0.7rem;color:var(--text-dim);letter-spacing:1px;text-transform:uppercase;">Last 5 contributions</span>
                        </div>
                        <div class="card-body">
                            <div class="activity-split">
                                <div class="activity-list">
                                    <?php if (!empty($recentKnowledge)): ?>
                                            <div class="timeline">
                                            <?php foreach ($recentKnowledge as $ri => $item): ?>
                                                <a class="timeline-item"
                                                   href="knowledge_view.php?id=<?= e($item['knowledge_id']) ?>"
                                                   style="--d: <?= min($ri * 0.07, 0.42) ?>s">
                                                    <span class="tx-spine" aria-hidden="true"></span>
                                                    <span class="tx-body">
                                                        <span class="tx-top">
                                                            <?php if (!empty($item['category_name'])): ?>
                                                                <span class="tx-chip"><?= e($item['category_name']) ?></span>
                                                            <?php else: ?>
                                                                <span class="tx-chip muted">Uncategorized</span>
                                                            <?php endif; ?>
                                                            <span class="tx-ago"><i class="far fa-clock"></i><?= e(klps_time_ago($item['created_at'])) ?></span>
                                                        </span>
                                                        <span class="timeline-title"><?= e($item['title']) ?></span>
                                                        <span class="timeline-meta">
                                                            <span><i class="fas fa-user"></i><?= e($item['full_name']) ?></span>
                                                            <span class="tx-stat"><i class="far fa-eye"></i><?= (int)$item['view_count'] ?></span>
                                                            <span class="tx-stat"><i class="far fa-thumbs-up"></i><?= (int)$item['helpful_count'] ?></span>
                                                            <span class="tx-stat"><i class="far fa-bookmark"></i><?= (int)$item['save_count'] ?></span>
                                                            <span class="tx-date"><i class="fas fa-calendar"></i><?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                                        </span>
                                                    </span>
                                                </a>
                                            <?php endforeach; ?>
                                            </div>
                                    <?php else: ?>
                                        <div class="empty-state">
                                            <i class="fas fa-compass-drafting"></i>
                                            <h3>No recent activity</h3>
                                            <p>Start contributing knowledge to see your activity here.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="activity-brain">
                                    <canvas id="brainCanvas"></canvas>
                                    <div class="brain-label">
                                        <span class="brain-dot"></span>
                                        <span>Neural Pulse</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ FEATURED KNOWLEDGE ============ -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title"><i class="fas fa-layer-group"></i>Featured Knowledge</h5>
                            <a href="knowledge.php" style="font-size:0.75rem;color:var(--cyan);text-decoration:none;">
                                View all <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="knowledge-grid">
                                <?php if (!empty($recentKnowledge)): ?>
                                    <?php foreach ($recentKnowledge as $ri => $item): ?>
                                        <a class="knowledge-card" href="knowledge_view.php?id=<?= e($item['knowledge_id']) ?>"
                                           style="--d: <?= min($ri * 0.07, 0.42) ?>s">
                                            <div class="knowledge-card-spine"></div>
                                            <div class="knowledge-card-header">
                                                <?php if (!empty($item['category_name'])): ?>
                                                    <span class="kx-chip"><?= e($item['category_name']) ?></span>
                                                <?php endif; ?>
                                                <h6 class="knowledge-card-title"><i class="fas fa-book-open"></i><?= e($item['title']) ?></h6>
                                                <p class="knowledge-card-excerpt"><?= e(mb_substr($item['problem'] ?? '', 0, 150)) ?>…</p>
                                            </div>
                                            <div class="knowledge-card-footer">
                                                <div class="knowledge-card-author">
                                                    <?php $pic = !empty($item['profile_picture']) ? '../uploads/profiles/' . $item['profile_picture'] : ''; ?>
                                                    <div class="avatar-sm">
                                                        <?php if ($pic && file_exists(__DIR__ . '/../uploads/profiles/' . $item['profile_picture'])): ?>
                                                            <img src="<?= e($pic) ?>" alt="<?= e($item['full_name']) ?>">
                                                        <?php else: ?>
                                                            <?= e(klps_initials_avatar($item['full_name'])) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span><?= e($item['full_name']) ?></span>
                                                </div>
                                                <div class="knowledge-card-stats">
                                                    <span title="Views"><i class="far fa-eye"></i><?= (int)$item['view_count'] ?></span>
                                                    <span title="Helpful"><i class="far fa-thumbs-up"></i><?= (int)$item['helpful_count'] ?></span>
                                                    <span class="knowledge-card-date"><?= e(klps_time_ago($item['created_at'])) ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-12">
                                        <div class="empty-state">
                                            <i class="fas fa-folder-open"></i>
                                            <h3>No knowledge available yet</h3>
                                            <p>Be the first to contribute knowledge to the system!</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>