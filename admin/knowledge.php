<?php
$page_title = 'Knowledge Management';
$page_css = ['admin-shared.css'];
$page_js  = ['admin-shared.js'];
require_once __DIR__ . '/../config/config.php';
require_role('admin');                         // role check BEFORE any HTML is sent
/* ---- shared helpers for admin/knowledge.php and admin/users.php ---- */
/**
 * Shared helpers for admin/knowledge.php and admin/users.php.
 * Include right after config.php + require_role(), BEFORE handling POST actions.
 * Page CSS/JS are declared via $page_css / $page_js and loaded by the partials.
 */
if (!function_exists('am_csrf')) {

    function am_csrf(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
    function am_check_csrf(): void {
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
            http_response_code(419);
            exit('Your session expired. Go back, reload the page and try again.');
        }
    }
    function am_back(string $page, array $allowed): never {
        parse_str((string)($_POST['qs'] ?? ''), $q);
        $q = array_filter(array_intersect_key($q, array_flip($allowed)), fn($v) => is_string($v) && $v !== '');
        redirect($page . ($q ? '?' . http_build_query($q) : ''));
    }
    function am_log(PDO $pdo, string $uid, string $action, string $details): void {
        try { $pdo->prepare('INSERT INTO activity_logs (activity_logs_id, user_id, action, details) VALUES (?, ?, ?, ?)')->execute([new_id('activity_logs'), $uid, $action, $details]); }
        catch (PDOException $e) { error_log('activity log: ' . $e->getMessage()); }
    }
    function am_time(string $ts): string {
        $d = time() - strtotime($ts);
        if ($d < 60) return 'just now';
        if ($d < 3600) return floor($d / 60) . 'm ago';
        if ($d < 86400) return floor($d / 3600) . 'h ago';
        if ($d < 604800) return floor($d / 86400) . 'd ago';
        return date('M j, Y', strtotime($ts));
    }
    function am_avatar(?string $name, ?string $pic, int $s = 36): string {
        $st = "width:{$s}px;height:{$s}px;font-size:" . round($s * .36) . 'px';
        $f = $pic ? basename($pic) : '';
        if ($f && is_file(__DIR__ . '/../uploads/profiles/' . $f))
            return '<img class="am-av" style="' . $st . '" src="' . e('../uploads/profiles/' . $f) . '" alt="">';
        return '<span class="am-av" style="' . $st . '" aria-hidden="true">' . e(mb_strtoupper(mb_substr($name ?: '?', 0, 1))) . '</span>';
    }
    /** A state-changing action as a POST form + CSRF token (replaces GET links). */
    function am_form(string $action, array $fields, string $icon, string $label, string $cls = '', string $confirm = '', bool $disabled = false): string {
        $h = '<form method="post" class="am-inline"' . ($confirm ? ' onsubmit="return confirm(' . e(json_encode($confirm)) . ')"' : '') . '>'
           . '<input type="hidden" name="csrf" value="' . e(am_csrf()) . '">'
           . '<input type="hidden" name="action" value="' . e($action) . '">'
           . '<input type="hidden" name="qs" value="' . e($_SERVER['QUERY_STRING'] ?? '') . '">';
        foreach ($fields as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string)$v) . '">';
        return $h . '<button type="submit" class="' . e($cls) . '" title="' . e($label) . '" aria-label="' . e($label) . '"' . ($disabled ? ' disabled' : '') . '>'
             . '<i class="fas ' . e($icon) . '"></i><span class="am-lbl">' . e($label) . '</span></button></form>';
    }
    function am_flash(): void {
        foreach (['success' => 'fa-circle-check', 'error' => 'fa-circle-exclamation'] as $t => $ic) {
            if (isset($_SESSION[$t])) {
                echo '<div class="am-toast ' . $t . '" role="status"><i class="fas ' . $ic . '"></i><span>' . e($_SESSION[$t]) . '</span></div>';
                unset($_SESSION[$t]);
            }
        }
    }
}

$pdo = db();
$me  = id_param($_SESSION['user']['users_id'] ?? '');
const KN_STATUSES = ['active', 'draft', 'archived'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    am_check_csrf();
    $action = $_POST['action'] ?? '';
    $id     = id_param($_POST['id'] ?? '');
    try {
        if ($action === 'set_status' && $id && in_array($_POST['status'] ?? '', KN_STATUSES, true)) {
            $pdo->prepare('UPDATE knowledge SET status = ? WHERE knowledge_id = ?')->execute([$_POST['status'], $id]);
            am_log($pdo, $me, 'update_knowledge', "Set article #{$id} to {$_POST['status']}");
            $_SESSION['success'] = "Article set to {$_POST['status']}.";
        } elseif ($action === 'delete' && $id) {
            $pdo->prepare('DELETE FROM knowledge WHERE knowledge_id = ?')->execute([$id]);
            am_log($pdo, $me, 'delete_knowledge', "Deleted article #{$id}");
            $_SESSION['success'] = 'Article deleted.';
        }
    } catch (PDOException $e) {
        error_log('knowledge admin: ' . $e->getMessage());
        $_SESSION['error'] = 'That change could not be saved. Try again.';
    }
    am_back('knowledge.php', ['q', 'status', 'cat']);
}

$search = trim($_GET['q'] ?? '');
$status = in_array($_GET['status'] ?? '', KN_STATUSES, true) ? $_GET['status'] : '';
$catId  = ($_GET['cat'] ?? '') !== '' ? trim((string)($_GET['cat'] ?? '')) : null;

$sql = 'SELECT k.knowledge_id, k.title, k.status, k.view_count, k.created_at, k.updated_at,
               u.full_name AS author, u.profile_picture AS author_pic, kc.name AS category_name,
               (SELECT COUNT(*) FROM knowledge_votes v WHERE v.knowledge_id = k.knowledge_id AND v.vote = "helpful")     AS helpful_count,
               (SELECT COUNT(*) FROM knowledge_votes v WHERE v.knowledge_id = k.knowledge_id AND v.vote = "not_helpful") AS not_helpful_count,
               (SELECT COUNT(*) FROM knowledge_comments c WHERE c.knowledge_id = k.knowledge_id) AS comment_count,
               (SELECT COUNT(*) FROM knowledge_saves s WHERE s.knowledge_id = k.knowledge_id)    AS save_count
        FROM knowledge k
        JOIN users u ON u.users_id = k.created_by
        LEFT JOIN knowledge_categories kc ON kc.knowledge_categories_id = k.category_id
        WHERE 1=1';
$params = [];
if ($search !== '') { $sql .= ' AND (k.title LIKE ? OR k.problem LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status !== '') { $sql .= ' AND k.status = ?';      $params[] = $status; }
if ($catId)         { $sql .= ' AND k.category_id = ?'; $params[] = $catId; }
$sql .= ' ORDER BY k.updated_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$categories = [];
try { $categories = $pdo->query('SELECT knowledge_categories_id AS id, name FROM knowledge_categories ORDER BY name')->fetchAll(); }
catch (PDOException $e) {}

$stats = $pdo->query("SELECT COUNT(*) AS total,
                             COALESCE(SUM(status = 'active'), 0)   AS active,
                             COALESCE(SUM(status = 'draft'), 0)    AS draft,
                             COALESCE(SUM(status = 'archived'), 0) AS archived
                      FROM knowledge")->fetch();

require_once __DIR__ . '/../partials/header.php';
$filtered = $search !== '' || $status !== '' || $catId;
?>
<div class="am-page" style="--a1:rgba(246,196,83,.22);--a2:rgba(139,124,246,.32);--b1:var(--gold);--b2:var(--violet)">
    <div class="am-bg" aria-hidden="true"><i></i><i></i></div>
    <?php am_flash(); ?>

    <div class="am-head">
        <div>
            <h1><span class="am-badge"><i class="fas fa-book"></i></span> Knowledge management</h1>
            <p>Review, archive or remove articles in the library.</p>
        </div>
        <div class="am-count"><i class="fas fa-book-open"></i> <span><b><?= count($rows) ?></b> shown</span></div>
    </div>

    <div class="am-chips">
        <?php foreach ([['Total', 'total', 'fa-layer-group', 'var(--violet)'], ['Active', 'active', 'fa-circle-check', 'var(--green)'],
                        ['Draft', 'draft', 'fa-pen', 'var(--gold)'], ['Archived', 'archived', 'fa-box-archive', '#8892b0']] as [$l, $k, $ic, $c]): ?>
            <div class="am-chip" style="--c:<?= $c ?>">
                <span class="am-chip-ico"><i class="fas <?= $ic ?>"></i></span>
                <div><b><?= (int)$stats[$k] ?></b><small><?= $l ?></small></div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="get" class="am-toolbar" role="search">
        <div class="am-search">
            <i class="fas fa-search"></i>
            <input class="am-input" type="text" name="q" value="<?= e($search) ?>" placeholder="Search by title or problem" aria-label="Search articles">
        </div>
        <select name="status" class="am-select" aria-label="Filter by status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (KN_STATUSES as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
        </select>
        <select name="cat" class="am-select" aria-label="Filter by category" onchange="this.form.submit()">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?><option value="<?= e($c['id']) ?>" <?= $catId === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="am-btn primary"><i class="fas fa-filter"></i> Filter</button>
        <?php if ($filtered): ?><a href="knowledge.php" class="am-btn"><i class="fas fa-xmark"></i> Clear</a><?php endif; ?>
    </form>

    <div class="am-tablewrap">
        <?php if ($rows): ?>
        <div class="am-scroll">
            <table class="am-table">
                <thead><tr>
                    <th>Article</th><th class="am-hide-md">Author</th><th class="am-hide-md">Category</th>
                    <th>Status</th><th class="am-hide-md">Engagement</th><th style="text-align:right">Actions</th>
                </tr></thead>
                <tbody>
    <?php foreach ($rows as $k): $kid = $k['knowledge_id']; $view = '../employee/knowledge_view.php?id=' . $kid; ?>
                    <tr>
<td>
                            <a class="am-title" href="<?= e($view) ?>" target="_blank" rel="noopener" title="<?= e($k['title']) ?>"><?= e($k['title']) ?></a>
                            <div class="am-sub">
                                Published <span class="am-ago" data-ts="<?= e(klps_iso($k['created_at'])) ?>" title="<?= e(date('M j, Y \a\t g:i A', strtotime($k['created_at']))) ?>"><?= e(am_time($k['created_at'])) ?></span>
                                <?php if ($k['updated_at'] !== $k['created_at']): ?>
                                    &middot; edited <span class="am-ago" data-ts="<?= e(klps_iso($k['updated_at'])) ?>" title="<?= e(date('M j, Y \a\t g:i A', strtotime($k['updated_at']))) ?>"><?= e(am_time($k['updated_at'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="am-hide-md"><div class="am-who"><?= am_avatar($k['author'], $k['author_pic'], 28) ?><span class="am-sub" style="margin:0;max-width:110px"><?= e($k['author']) ?></span></div></td>
                        <td class="am-hide-md"><span class="am-pill cat"><?= e($k['category_name'] ?? 'Uncategorized') ?></span></td>
                        <td><span class="am-pill <?= e($k['status']) ?>"><?= e(ucfirst($k['status'])) ?></span></td>
                        <td class="am-hide-md">
                            <div class="am-metrics">
                                <span class="am-metric" style="--mc:var(--green)" title="Helpful"><i class="fas fa-thumbs-up"></i><?= (int)$k['helpful_count'] ?></span>
                                <span class="am-metric" style="--mc:var(--red)" title="Not helpful"><i class="fas fa-thumbs-down"></i><?= (int)$k['not_helpful_count'] ?></span>
                                <span class="am-metric" style="--mc:var(--cyan)" title="Comments"><i class="fas fa-comment"></i><?= (int)$k['comment_count'] ?></span>
                                <span class="am-metric" style="--mc:var(--gold)" title="Saves"><i class="fas fa-bookmark"></i><?= (int)$k['save_count'] ?></span>
                                <span class="am-metric" style="--mc:var(--violet)" title="Views"><i class="fas fa-eye"></i><?= (int)$k['view_count'] ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="am-actions">
                                <button type="button" class="am-menu-btn" aria-haspopup="menu" aria-expanded="false" aria-label="Actions for <?= e($k['title']) ?>"><i class="fas fa-ellipsis-vertical"></i></button>
                                <div class="am-menu-list" role="menu">
                                    <a href="<?= e($view) ?>" target="_blank" rel="noopener"><i class="fas fa-eye"></i> View article</a>
                                    <?php foreach (['active' => ['fa-circle-check', 'Set active'], 'draft' => ['fa-pen', 'Set draft'], 'archived' => ['fa-box-archive', 'Archive']] as $s => [$ic, $lbl]):
                                        if ($k['status'] !== $s) echo am_form('set_status', ['id' => $kid, 'status' => $s], $ic, $lbl); endforeach; ?>
                                    <hr>
                                    <?= am_form('delete', ['id' => $kid], 'fa-trash', 'Delete', 'danger', 'Delete this article permanently? Its votes, comments and saves go with it.') ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="am-empty">
                <i class="fas fa-folder-open"></i>
                <h3><?= $filtered ? 'No articles match' : 'No articles yet' ?></h3>
                <p><?= $filtered ? 'Change or clear the filters to see more.' : 'Articles appear here once employees publish them.' ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>