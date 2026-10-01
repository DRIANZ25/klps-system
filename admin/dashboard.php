<?php
$page_title = 'Admin Dashboard';
$page_css = ['admin-dashboard.css'];
$page_js  = ['admin-shared.js', 'admin-dashboard.js'];
require_once __DIR__ . '/../config/config.php';
require_role('admin');
require_once __DIR__ . '/../partials/header.php';

$pdo = db();

function adm_rows(string $sql, array $p = []): array {
    try { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
    catch (PDOException $e) { error_log('dashboard: ' . $e->getMessage()); return []; }
}
/** 14-day daily counts (one query per table instead of 7). */
function adm_daily(string $table): array {
    $m = [];
    foreach (adm_rows("SELECT DATE(created_at) d, COUNT(*) c FROM `$table`
                   WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY d") as $r) $m[$r['d']] = (int)$r['c'];
    $out = [];
    for ($i = 13; $i >= 0; $i--) $out[] = $m[date('Y-m-d', strtotime("-$i days"))] ?? 0;
    return $out;
}
function adm_trend(array $s): array {
    $now = array_sum(array_slice($s, 7)); $prev = array_sum(array_slice($s, 0, 7));
    return [$now, $prev > 0 ? round((($now - $prev) / $prev) * 100) : null];
}
function adm_ago(string $ts): string {
    $d = time() - strtotime($ts);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    if ($d < 172800) return 'yesterday';
    if ($d < 604800) return floor($d / 86400) . 'd ago';
    return date('M j, Y', strtotime($ts));
}
function adm_action_icon(string $a): array {
    $a = strtolower($a);
    foreach (['login' => ['fa-right-to-bracket', 'ok'], 'logout' => ['fa-right-from-bracket', 'mute'],
              'create' => ['fa-plus', 'info'], 'update' => ['fa-pen', 'warn'], 'delete' => ['fa-trash', 'bad']] as $k => $v)
        if (str_contains($a, $k)) return $v;
    return ['fa-circle-info', 'mute'];
}
function adm_initial(?string $n): string { return strtoupper(mb_substr($n ?: '?', 0, 1)); }
function adm_avatar(?string $name, ?string $pic, int $size = 34): string {
    $file = $pic ? __DIR__ . '/../uploads/profiles/' . basename($pic) : '';
    $st = "width:{$size}px;height:{$size}px";
    if ($file && is_file($file))
        return '<img class="av" style="' . $st . '" src="' . e('../uploads/profiles/' . basename($pic)) . '" alt="">';
    return '<span class="av" style="' . $st . '">' . e(adm_initial($name)) . '</span>';
}
function adm_bars(array $rows, string $unit): void {
    if (!$rows) { echo '<div class="empty"><i class="fas fa-chart-simple"></i>Nothing to show yet</div>'; return; }
    $max = max(array_map(fn($r) => (int)$r['count'], $rows)) ?: 1;
    echo '<div class="bars">';
    foreach ($rows as $r) {
        $c = (int)$r['count'];
        echo '<div><div class="bar-top"><span>' . e($r['name']) . '</span><b>' . $c . ' ' . $unit . ($c === 1 ? '' : 's') . '</b></div>'
           . '<div class="bar"><i style="width:' . round($c / $max * 100) . '%"></i></div></div>';
    }
    echo '</div>';
}

// Additional admin stats
$systemHealth = adm_rows("SELECT 
    (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
    (SELECT COUNT(*) FROM knowledge WHERE status = 'active') as active_articles,
    (SELECT COUNT(*) FROM ai_conversations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as chats_24h,
    (SELECT ROUND(SUM(LENGTH(problem) + LENGTH(solution)) / 1024 / 1024, 2) FROM knowledge) as db_size_mb");

// Departments/categories are listed by $depts and $cats below; a separate
// id+name query here was never rendered.

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM users)                                              AS users,
    (SELECT COUNT(*) FROM users WHERE is_active = 1)                          AS active_users,
    (SELECT COUNT(*) FROM users WHERE role = 'admin')                         AS admin_count,
    (SELECT COUNT(*) FROM knowledge)                                          AS knowledge,
    (SELECT COUNT(*) FROM knowledge WHERE status = 'active')                  AS active_articles,
    (SELECT COUNT(*) FROM knowledge WHERE status = 'draft')                   AS drafts,
    (SELECT COUNT(*) FROM knowledge WHERE status = 'active' AND view_count = 0) AS unviewed,
    (SELECT COUNT(*) FROM ai_conversations)                                   AS conversations,
    (SELECT COUNT(*) FROM departments)                                        AS departments,
    (SELECT COUNT(*) FROM knowledge_categories)                               AS categories,
    (SELECT COUNT(*) FROM knowledge_votes)                                    AS votes,
    (SELECT COUNT(*) FROM knowledge_comments)                                 AS comments,
    (SELECT COUNT(*) FROM knowledge_saves)                                    AS saves,
    -- Exclusive bands (upper bound included, lower bound excluded) so the
    -- counts sum to the number of accounts instead of double counting.
    (SELECT COUNT(*) FROM users WHERE is_active = 1 AND last_seen >= DATE_SUB(NOW(), INTERVAL 1 MINUTE))                          AS on_now,
    (SELECT COUNT(*) FROM users WHERE is_active = 1 AND last_seen <  DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                                                AND last_seen >= DATE_SUB(NOW(), INTERVAL 1 HOUR))                             AS on_hour,
    (SELECT COUNT(*) FROM users WHERE is_active = 1 AND last_seen <  DATE_SUB(NOW(), INTERVAL 1 HOUR)
                                                AND last_seen >= CURDATE())                                                  AS on_today,
    (SELECT COUNT(*) FROM users WHERE is_active = 1 AND last_seen <  CURDATE())                                                 AS off_today,
    (SELECT COUNT(*) FROM users WHERE is_active = 0)                                                                             AS off_accounts")->fetch();

$series = ['users' => adm_daily('users'), 'articles' => adm_daily('knowledge'), 'chats' => adm_daily('ai_conversations')];
$inactive = (int)$stats['users'] - (int)$stats['active_users'];

$recentUsers = adm_rows('SELECT u.users_id, u.full_name, u.email, u.role, u.is_active, u.profile_picture, u.created_at, u.last_seen, d.name AS department_name
                     FROM users u LEFT JOIN departments d ON d.departments_id = u.department_id
                     ORDER BY u.created_at DESC LIMIT 6');
$recentKnowledge = adm_rows('SELECT k.knowledge_id, k.title, k.status, k.view_count, k.created_at, u.full_name AS author, u.profile_picture AS author_pic, kc.name AS category_name
                        FROM knowledge k JOIN users u ON u.users_id = k.created_by
                        LEFT JOIN knowledge_categories kc ON kc.knowledge_categories_id = k.category_id
                        ORDER BY k.created_at DESC LIMIT 6');
$activity = adm_rows('SELECT al.action, al.details, al.created_at, u.full_name
                   FROM activity_logs al LEFT JOIN users u ON u.users_id = al.user_id
                   ORDER BY al.created_at DESC LIMIT 10');
$contributors = adm_rows("SELECT u.full_name, u.profile_picture, COUNT(*) AS articles, COALESCE(SUM(h.c), 0) AS helpful
                      FROM users u
                      JOIN knowledge k ON k.created_by = u.users_id AND k.status = 'active'
                      LEFT JOIN (SELECT knowledge_id, COUNT(*) c FROM knowledge_votes WHERE vote = 'helpful' GROUP BY knowledge_id) h
                             ON h.knowledge_id = k.knowledge_id
                      GROUP BY u.users_id, u.full_name, u.profile_picture
                      ORDER BY articles DESC, helpful DESC LIMIT 5");
$topViewed = adm_rows("SELECT knowledge_id, title, view_count FROM knowledge WHERE status = 'active' ORDER BY view_count DESC LIMIT 5");
$flagged = adm_rows("SELECT k.knowledge_id, k.title,
                         SUM(v.vote = 'not_helpful') AS nh, SUM(v.vote = 'helpful') AS h
                  FROM knowledge k JOIN knowledge_votes v ON v.knowledge_id = k.knowledge_id
                  WHERE k.status = 'active'
GROUP BY k.knowledge_id, k.title
                   -- HAVING/ORDER BY must repeat the aggregate expressions, not the
                   -- nh/h aliases: MariaDB rejects an alias that wraps a group
                   -- function (error 1247), which made the whole query fail
                   -- silently inside adm_rows().
                   HAVING SUM(v.vote = 'not_helpful') > SUM(v.vote = 'helpful')
                   ORDER BY (SUM(v.vote = 'not_helpful') - SUM(v.vote = 'helpful')) DESC LIMIT 5");
$depts = adm_rows('SELECT d.name, COUNT(u.users_id) AS count FROM departments d
                LEFT JOIN users u ON u.department_id = d.departments_id
                GROUP BY d.departments_id, d.name ORDER BY count DESC LIMIT 6');
$cats = adm_rows("SELECT COALESCE(kc.name, 'Uncategorized') AS name, COUNT(*) AS count FROM knowledge k
               LEFT JOIN knowledge_categories kc ON kc.knowledge_categories_id = k.category_id
               WHERE k.status = 'active' GROUP BY name ORDER BY count DESC LIMIT 6");

$cards = [
    ['Total users',        $stats['users'],         'fa-users',          'violet', 'users',    "<b>{$stats['active_users']}</b> active accounts"],
    ['Knowledge articles', $stats['knowledge'],     'fa-book',           'cyan',   'articles', "<b>{$stats['active_articles']}</b> active · {$stats['categories']} categories"],
    ['AI conversations',   $stats['conversations'], 'fa-comments',       'gold',   'chats',    "<b>{$stats['comments']}</b> comments · <b>{$stats['votes']}</b> votes"],
];
// Team presence, split into the same buckets klps_presence() uses.
$presenceBands = [
    ['on_now',   'Active now',    '#00ff9c'],
    ['on_hour',  'Last hour',     '#7dffbe'],
    ['on_today', 'Earlier today', 'var(--gold)'],
    ['off_today','Earlier',       '#ffa46b'],
    ['off_accounts', 'Account off', '#5b647f'],
];
$presenceTotal = 0;
foreach ($presenceBands as $b) $presenceTotal += (int) $stats[$b[0]];
$attention = [
    ['Inactive accounts',   $inactive,          'fa-user-slash', app_url('admin/users.php'),     $inactive > 0],
    ['Draft articles',      $stats['drafts'],   'fa-pen-ruler',  app_url('admin/knowledge.php'), (int)$stats['drafts'] > 0],
    ['Never viewed',        $stats['unviewed'], 'fa-eye-slash',  app_url('admin/knowledge.php'), (int)$stats['unviewed'] > 0],
    ['Rated not helpful',   count($flagged),    'fa-flag',       '#flagged',             count($flagged) > 0],
];
?>

<div class="adm">
    <div class="adm-bg" aria-hidden="true"><i></i><i></i></div>

    <header class="hero">
        <div>
            <h1>Admin Control Center</h1>
            <p><?= date('l, F j') ?> · <?= (int)$stats['active_users'] ?> active users · <?= (int)$stats['active_articles'] ?> live articles · <?= (int)$stats['admin_count'] ?> admins</p>
        </div>
        <div class="hero-actions">
            <span class="live">Live</span>
            <a class="btn-a" href="<?= e(app_url('admin/users.php')) ?>"><i class="fas fa-users-gear"></i> Manage Users</a>
            <a class="btn-a main" href="<?= e(app_url('admin/knowledge.php')) ?>"><i class="fas fa-shield-halved"></i> Moderate Knowledge</a>
        </div>
    </header>

    <!-- Quick Admin Actions -->
    <section class="quick-actions" aria-label="Quick admin actions">
        <div class="adm-card action-grid">
            <a href="<?= e(app_url('admin/users.php')) ?>" class="action-btn primary" title="Manage all user accounts">
                <i class="fas fa-user-plus"></i>
                <span>Add New User</span>
            </a>
            <a href="<?= e(app_url('admin/knowledge.php')) ?>" class="action-btn secondary" title="Moderate knowledge articles">
                <i class="fas fa-shield-halved"></i>
                <span>Moderate Articles</span>
            </a>
            <a href="<?= e(app_url('admin/users.php?role=admin')) ?>" class="action-btn secondary" title="View all administrators">
                <i class="fas fa-user-shield"></i>
                <span>Admin Accounts</span>
            </a>
            <a href="<?= e(app_url('admin/knowledge.php?status=draft')) ?>" class="action-btn warning" title="Review draft articles">
                <i class="fas fa-pen-ruler"></i>
                <span>Review Drafts</span>
                <?php if ((int)$stats['drafts'] > 0): ?><span class="action-badge"><?= (int)$stats['drafts'] ?></span><?php endif; ?>
            </a>
            <a href="<?= e(app_url('admin/users.php?status=inactive')) ?>" class="action-btn warning" title="Manage inactive accounts">
                <i class="fas fa-user-slash"></i>
                <span>Inactive Users</span>
                <?php if ($inactive > 0): ?><span class="action-badge"><?= $inactive ?></span><?php endif; ?>
            </a>
            <button class="action-btn info" onclick="openSystemModal()" title="System health & settings">
                <i class="fas fa-server"></i>
                <span>System Health</span>
            </button>
        </div>
    </section>

    <!-- System Health Overview -->
    <section class="system-health" aria-label="System health overview">
        <div class="adm-card health-grid">
            <div class="health-item">
                <span class="health-icon" style="--c:var(--green)"><i class="fas fa-database"></i></span>
                <div class="health-info">
                    <div class="health-value"><?= e($systemHealth[0]['db_size_mb'] ?? '0') ?> MB</div>
                    <div class="health-label">Database Size</div>
                </div>
            </div>
            <div class="health-item">
                <span class="health-icon" style="--c:var(--cyan)"><i class="fas fa-comments"></i></span>
                <div class="health-info">
                    <div class="health-value"><?= (int)$systemHealth[0]['chats_24h'] ?></div>
                    <div class="health-label">Chats (24h)</div>
                </div>
            </div>
            <div class="health-item">
                <span class="health-icon" style="--c:var(--violet)"><i class="fas fa-building"></i></span>
                <div class="health-info">
                    <div class="health-value"><?= (int)$stats['departments'] ?></div>
                    <div class="health-label">Departments</div>
                </div>
            </div>
            <div class="health-item">
                <span class="health-icon" style="--c:var(--gold)"><i class="fas fa-tags"></i></span>
                <div class="health-info">
                    <div class="health-value"><?= (int)$stats['categories'] ?></div>
                    <div class="health-label">Categories</div>
                </div>
            </div>
        </div>
    </section>

    <!-- KPIs with week-over-week change -->
    <section class="stats" aria-label="Key metrics">
        <?php foreach ($cards as [$label, $value, $icon, $color, $key, $foot]):
            [$week, $pct] = adm_trend($series[$key]);
            $cls = $pct === null ? '' : ($pct >= 0 ? 'up' : 'down'); ?>
            <div class="adm-card stat <?= $color ?>">
                <div class="top">
                    <span class="ico"><i class="fas <?= $icon ?>"></i></span>
                    <span class="delta <?= $cls ?>" title="New this week vs the week before">
                        +<?= (int)$week ?> this week<?= $pct === null ? '' : ' (' . ($pct >= 0 ? '+' : '') . $pct . '%)' ?>
                    </span>
                </div>
                <div class="lbl"><?= e($label) ?></div>
                <div class="val"><?= number_format((int)$value) ?></div>
                <div class="foot"><?= $foot ?></div>
                <div class="spark"><canvas data-values="<?= e(implode(',', array_slice($series[$key], 7))) ?>" aria-hidden="true"></canvas></div>
            </div>
        <?php endforeach; ?>
        <div class="adm-card stat green">
            <div class="top"><span class="ico"><i class="fas fa-building"></i></span></div>
            <div class="lbl">Departments</div>
            <div class="val"><?= (int)$stats['departments'] ?></div>
            <div class="foot"><b><?= (int)$stats['saves'] ?></b> saves recorded</div>
        </div>
        <div class="adm-card stat red">
            <div class="top"><span class="ico"><i class="fas fa-flag"></i></span></div>
            <div class="lbl">Flagged Content</div>
            <div class="val"><?= count($flagged) ?></div>
            <div class="foot">Articles needing review</div>
        </div>
    </section>

    <!-- Needs attention -->
    <section class="attn" aria-label="Needs attention">
        <?php foreach ($attention as [$label, $n, $icon, $href, $hot]): ?>
            <a class="adm-card att <?= $hot ? 'hot' : '' ?>" href="<?= e($href) ?>">
                <span class="ai"><i class="fas <?= $icon ?>"></i></span>
                <span><span class="n"><?= (int)$n ?></span><small><?= e($label) ?></small></span>
            </a>
        <?php endforeach; ?>
    </section>

<!-- Team presence: who is actually using the system right now -->
    <section class="adm-card panel" style="--acc:var(--green)">
        <div class="ph">
            <h3><i class="fas fa-signal"></i> Team presence</h3>
            <a href="<?= e(app_url('admin/users.php')) ?>">All users</a>
        </div>
        <div class="pb">
            <div class="presence-bar" role="img" aria-label="Distribution of team presence">
                <?php foreach ($presenceBands as [$k, $lbl, $col]):
                    $n = (int) $stats[$k];
                    if ($n === 0) continue; ?>
                    <i style="width:<?= $presenceTotal ? round($n / $presenceTotal * 100, 2) : 0 ?>%;background:<?= $col ?>"
                       title="<?= e($lbl . ': ' . $n) ?>"></i>
                <?php endforeach; ?>
            </div>
            <div class="presence-legend">
                <?php foreach ($presenceBands as [$k, $lbl, $col]): ?>
                    <span class="presence-key"><i style="background:<?= $col ?>"></i><b><?= (int) $stats[$k] ?></b><?= e($lbl) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="grid2">
        <section class="adm-card panel" style="--acc:var(--violet)">
            <div class="ph"><h3><i class="fas fa-user-plus"></i> Newest users</h3><a href="users.php">Manage</a></div>
            <div class="pb scroll">
            <?php if ($recentUsers): ?>
                <table>
                    <thead><tr><th>User</th><th>Role</th><th class="hide-sm">Department</th><th>Presence</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentUsers as $u): ?>
                        <tr>
                            <td><div class="who"><?= adm_avatar($u['full_name'], $u['profile_picture']) ?>
                                <div style="min-width:0"><div class="nm"><?= e($u['full_name']) ?></div><div class="mt"><?= e($u['email']) ?></div></div></div></td>
                            <td><span class="tag <?= $u['role'] === 'admin' ? 'admin' : 'employee' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
<td class="hide-sm"><?= $u['department_name'] ? '<span class="tag dept">' . e($u['department_name']) . '</span>' : '<span class="mt">—</span>' ?></td>
                            <td><?php
                                $p = klps_presence($u['last_seen'] ?? null, $u['users_id'] === $_SESSION['user']['users_id']);
                                echo $u['is_active']
                                    ? '<span class="am-presence is-' . e($p['state']) . '" data-pres data-ts="' . e(klps_iso($u['last_seen'] ?? null)) . '"><i class="am-dot"></i>' . e($p['label']) . '</span>'
                                    : '<span class="am-presence is-off"><i class="am-dot"></i>Account off</span>';
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?><div class="empty"><i class="fas fa-users"></i>No users yet</div><?php endif; ?>
            </div>
        </section>

        <section class="adm-card panel" style="--acc:var(--cyan)">
            <div class="ph"><h3><i class="fas fa-book-open"></i> Newest articles</h3><a href="knowledge.php">Moderate</a></div>
            <div class="pb scroll">
            <?php if ($recentKnowledge): ?>
                <table>
                    <thead><tr><th>Article</th><th class="hide-sm">Author</th><th>Status</th><th>Views</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentKnowledge as $k): ?>
                        <tr>
                            <td><div class="nm" title="<?= e($k['title']) ?>"><a href="../employee/knowledge_view.php?id=<?= e($k['knowledge_id']) ?>" style="color:inherit;text-decoration:none"><?= e($k['title']) ?></a></div>
                                <div class="mt"><?= e($k['category_name'] ?? 'Uncategorized') ?> · published <span class="am-ago" data-ts="<?= e(klps_iso($k['created_at'])) ?>" title="<?= e(date('M j, Y \a\t g:i A', strtotime($k['created_at']))) ?>"><?= e(adm_ago($k['created_at'])) ?></span></div></td>
                            <td class="hide-sm"><div class="who"><?= adm_avatar($k['author'], $k['author_pic'], 26) ?><span class="mt"><?= e($k['author']) ?></span></div></td>
                            <td><span class="tag <?= e($k['status']) ?>"><?= e(ucfirst($k['status'])) ?></span></td>
                            <td class="score"><?= number_format((int)$k['view_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?><div class="empty"><i class="fas fa-folder-open"></i>No articles yet</div><?php endif; ?>
            </div>
        </section>
    </div>

    <div class="grid2">
        <section class="adm-card panel" style="--acc:var(--gold)">
            <div class="ph"><h3><i class="fas fa-wave-square"></i> System activity</h3><span>Last 10 events</span></div>
            <div class="pb">
            <?php if ($activity): foreach ($activity as $log): [$ic, $tone] = adm_action_icon($log['action']); ?>
                <div class="adm-row">
                    <span class="ev <?= $tone ?>"><i class="fas <?= $ic ?>"></i></span>
                    <div class="row-body">
                        <div class="nm"><?= e($log['full_name'] ?? 'System') ?></div>
                        <div class="mt"><?= e(ucfirst(str_replace('_', ' ', $log['action']))) ?><?= !empty($log['details']) ? ' — ' . e(mb_substr($log['details'], 0, 48)) : '' ?></div>
                    </div>
                    <span class="time"><?= e(adm_ago($log['created_at'])) ?></span>
                </div>
            <?php endforeach; else: ?><div class="empty"><i class="fas fa-circle-info"></i>No activity recorded yet</div><?php endif; ?>
            </div>
        </section>

        <section class="adm-card panel" style="--acc:var(--green)">
            <div class="ph"><h3><i class="fas fa-trophy"></i> Top contributors</h3><span>By active articles</span></div>
            <div class="pb">
            <?php if ($contributors): foreach ($contributors as $i => $c): ?>
                <div class="adm-row">
                    <span class="rank <?= $i < 3 ? 'r' . ($i + 1) : '' ?>"><?= $i + 1 ?></span>
                    <?= adm_avatar($c['full_name'], $c['profile_picture'], 32) ?>
                    <div class="row-body">
                        <div class="nm"><?= e($c['full_name']) ?></div>
                        <div class="row-meta"><b><?= (int)$c['articles'] ?></b> articles · <b><?= (int)$c['helpful'] ?></b> helpful votes</div>
                    </div>
                </div>
            <?php endforeach; else: ?><div class="empty"><i class="fas fa-trophy"></i>Contributors appear once articles are published</div><?php endif; ?>
            </div>
        </section>
    </div>

    <div class="grid2">
        <section class="adm-card panel" style="--acc:var(--cyan)">
            <div class="ph"><h3><i class="fas fa-fire"></i> Most viewed</h3><span>Active articles</span></div>
            <div class="pb">
            <?php if ($topViewed): foreach ($topViewed as $t): ?>
                <div class="adm-row">
                        <div class="row-body"><div class="nm" style="max-width:100%"><a href="../employee/knowledge_view.php?id=<?= e($t['knowledge_id']) ?>"><?= e($t['title']) ?></a></div></div>
                    <span class="score"><i class="fas fa-eye" style="font-size:.65rem"></i> <?= number_format((int)$t['view_count']) ?></span>
                </div>
            <?php endforeach; else: ?><div class="empty"><i class="fas fa-eye"></i>No views recorded yet</div><?php endif; ?>
            </div>
        </section>

        <section class="adm-card panel" id="flagged" style="--acc:var(--red)">
            <div class="ph"><h3><i class="fas fa-flag"></i> Rated not helpful</h3><span>Review or archive</span></div>
            <div class="pb">
            <?php if ($flagged): foreach ($flagged as $f): ?>
                <div class="adm-row">
                    <div class="row-body">
                        <div class="nm" style="max-width:100%"><a href="../employee/knowledge_view.php?id=<?= e($f['knowledge_id']) ?>"><?= e($f['title']) ?></a></div>
                        <div class="row-meta"><?= (int)$f['h'] ?> helpful · <?= (int)$f['nh'] ?> not helpful</div>
                    </div>
                    <span class="score neg">−<?= (int)$f['nh'] - (int)$f['h'] ?></span>
                </div>
            <?php endforeach; else: ?><div class="empty"><i class="fas fa-circle-check"></i>No article has more “not helpful” than “helpful” votes</div><?php endif; ?>
            </div>
        </section>
    </div>

<div class="grid2">
        <section class="adm-card panel" style="--acc:var(--violet)">
            <div class="ph"><h3><i class="fas fa-building"></i> Users by department</h3></div>
            <div class="pb"><?php adm_bars($depts, 'user'); ?></div>
        </section>
        <section class="adm-card panel" style="--acc:var(--gold)">
            <div class="ph"><h3><i class="fas fa-tags"></i> Articles by category</h3></div>
            <div class="pb"><?php adm_bars($cats, 'article'); ?></div>
        </section>
    </div>
</div>

<!-- System health modal: opened by the "System Health" quick action -->
<div class="adm-overlay" id="sysModal" role="dialog" aria-modal="true" aria-labelledby="sysModalTitle">
    <div class="adm-modal">
        <button class="adm-x" type="button" onclick="closeSystemModal()" aria-label="Close"><i class="fas fa-xmark"></i></button>
        <h2 id="sysModalTitle"><i class="fas fa-server"></i> System health</h2>
        <div class="adm-kv"><span>Database size (knowledge text)</span><b><?= e($systemHealth[0]['db_size_mb'] ?? '0') ?> MB</b></div>
        <div class="adm-kv"><span>Active user accounts</span><b><?= (int)$systemHealth[0]['active_users'] ?></b></div>
        <div class="adm-kv"><span>Live knowledge articles</span><b><?= (int)$systemHealth[0]['active_articles'] ?></b></div>
        <div class="adm-kv"><span>AI conversations (last 24h)</span><b><?= (int)$systemHealth[0]['chats_24h'] ?></b></div>
        <div class="adm-kv"><span>AI conversations (all time)</span><b><?= (int)$stats['conversations'] ?></b></div>
        <div class="adm-kv"><span>Departments / categories</span><b><?= (int)$stats['departments'] ?> / <?= (int)$stats['categories'] ?></b></div>
        <div class="adm-kv"><span>Votes / comments / saves</span><b><?= (int)$stats['votes'] ?> / <?= (int)$stats['comments'] ?> / <?= (int)$stats['saves'] ?></b></div>
        <div class="adm-modal-actions">
            <button class="btn-a" type="button" onclick="closeSystemModal()">Close</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>