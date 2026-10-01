<?php
$page_title = 'Knowledge Library';
$page_css = ['employee-knowledge.css'];
$page_js  = ['employee-knowledge.js'];
require_once __DIR__ . '/../partials/header.php';

$q = trim($_GET['q'] ?? '');
$catId  = isset($_GET['cat'])  && $_GET['cat']  !== '' ? trim((string)($_GET['cat'] ?? ''))  : null;
$deptId = isset($_GET['dept']) && $_GET['dept'] !== '' ? trim((string)($_GET['dept'] ?? '')) : null;
$sort = $_GET['sort'] ?? 'recent';
$currentUserId = id_param($_SESSION['user']['users_id'] ?? '');

$action = '';
if (isset($_POST['action'])) $action = $_POST['action'];
elseif (isset($_GET['action'])) $action = $_GET['action'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $category_id = id_param($_POST['category_id'] ?? '');
    $department_id = id_param($_POST['department_id'] ?? '');
    $problem = trim($_POST['problem'] ?? '');
    $cause = trim($_POST['cause'] ?? '');
    $solution = trim($_POST['solution'] ?? '');
    $procedure_steps = trim($_POST['procedure_steps'] ?? '');
    $best_practice = trim($_POST['best_practice'] ?? '');
    $tags = trim($_POST['tags'] ?? '');

    if ($title && $problem && $solution) {
        try {
            $newKnowledgeId = new_id('knowledge');
            $stmt = db()->prepare(
                'INSERT INTO knowledge (knowledge_id, category_id, department_id, created_by, title, problem, cause, solution, procedure_steps, best_practice, tags, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")'
            );
            $stmt->execute([
                $newKnowledgeId,
                $category_id ?: null,
                $department_id ?: ($currentUserDept ?: null),
                $currentUserId,
                $title, $problem, $cause, $solution, $procedure_steps, $best_practice, $tags
            ]);
            $_SESSION['success'] = '✅ Article created successfully!';
        } catch (PDOException $e) {
            error_log('Knowledge create failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to create article: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Title, Problem, and Solution are required.';
    }
    redirect('knowledge.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $id = id_param($_POST['id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $category_id = id_param($_POST['category_id'] ?? '');
    $department_id = id_param($_POST['department_id'] ?? '');
    $problem = trim($_POST['problem'] ?? '');
    $cause = trim($_POST['cause'] ?? '');
    $solution = trim($_POST['solution'] ?? '');
    $procedure_steps = trim($_POST['procedure_steps'] ?? '');
    $best_practice = trim($_POST['best_practice'] ?? '');
    $tags = trim($_POST['tags'] ?? '');

    if ($id && $title && $problem && $solution) {
        try {
            $stmt = db()->prepare('SELECT created_by FROM knowledge WHERE knowledge_id = ?');
            $stmt->execute([$id]);
            $article = $stmt->fetch();
            if ($article && ($article['created_by'] == $currentUserId || $_SESSION['user']['role'] === 'admin')) {
                // Blank department on edit falls back to the ARTICLE AUTHOR's department,
                // so an admin editing someone else's article never steals its attribution.
                $authorDept = $currentUserDept;
                if ($article['created_by'] !== $currentUserId) {
                    try {
                        $ad = db()->prepare('SELECT department_id FROM users WHERE users_id = ?');
                        $ad->execute([$article['created_by']]);
                        $authorDept = id_param($ad->fetchColumn()) ?: $currentUserDept;
                    } catch (PDOException $e) {}
                }
                $stmt = db()->prepare(
                    'UPDATE knowledge SET category_id=?, department_id=?, title=?, problem=?, cause=?, solution=?, procedure_steps=?, best_practice=?, tags=? WHERE knowledge_id=?'
                );
                $stmt->execute([
                    $category_id ?: null,
                    $department_id ?: ($authorDept ?: null),
                    $title, $problem, $cause, $solution, $procedure_steps, $best_practice, $tags,
                    $id
                ]);
                $_SESSION['success'] = '✅ Article updated successfully!';
            } else {
                $_SESSION['error'] = 'Permission denied.';
            }
        } catch (PDOException $e) {
            error_log('Knowledge edit failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to update article: ' . $e->getMessage();
        }
    }
    redirect('knowledge.php');
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = trim((string)($_GET['id'] ?? ''));
    try {
        $stmt = db()->prepare('SELECT created_by FROM knowledge WHERE knowledge_id = ?');
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        if ($article && ($article['created_by'] == $currentUserId || $_SESSION['user']['role'] === 'admin')) {
            $stmt = db()->prepare('DELETE FROM knowledge WHERE knowledge_id = ?');
            $stmt->execute([$id]);
            $_SESSION['success'] = '🗑️ Article deleted successfully.';
        } else {
            $_SESSION['error'] = 'Permission denied.';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete article: ' . $e->getMessage();
    }
    redirect('knowledge.php');
}

$categories = [];
$departments = [];
$currentUserDept = '';
try {
    $categories = db()->query('SELECT knowledge_categories_id AS id, name FROM knowledge_categories ORDER BY name')->fetchAll();
    $departments = db()->query('SELECT departments_id AS id, name FROM departments ORDER BY name')->fetchAll();
} catch (PDOException $e) {}

try {
    $s = db()->prepare('SELECT department_id FROM users WHERE users_id = ?');
    $s->execute([$currentUserId]);
    $currentUserDept = id_param($s->fetchColumn());
} catch (PDOException $e) {}


$orderBy = match($sort) {
    'helpful' => 'helpful_count DESC, k.updated_at DESC',
    'az'      => 'k.title ASC',
    'oldest'  => 'k.created_at ASC',
    'saved'   => 'save_count DESC, k.updated_at DESC',
    default   => 'k.updated_at DESC'
};

$rows = [];
$queryError = '';
try {
    $sql = "SELECT k.*, kc.name AS category_name, d.name AS department_name, u.full_name AS author,
            u.profile_picture AS author_profile_picture,
            (SELECT COUNT(*) FROM knowledge_votes kv WHERE kv.knowledge_id = k.knowledge_id AND kv.vote = 'helpful') AS helpful_count,
            (SELECT COUNT(*) FROM knowledge_votes kv2 WHERE kv2.knowledge_id = k.knowledge_id AND kv2.vote = 'not_helpful') AS not_helpful_count,
            (SELECT vote FROM knowledge_votes kv3 WHERE kv3.knowledge_id = k.knowledge_id AND kv3.user_id = ?) AS my_vote,
            (SELECT COUNT(*) FROM knowledge_comments c WHERE c.knowledge_id = k.knowledge_id) AS comment_count,
            (SELECT COUNT(*) FROM knowledge_saves s WHERE s.knowledge_id = k.knowledge_id) AS save_count,
            (SELECT 1 FROM knowledge_saves s2 WHERE s2.knowledge_id = k.knowledge_id AND s2.user_id = ?) AS is_saved
            FROM knowledge k
            LEFT JOIN knowledge_categories kc ON kc.knowledge_categories_id = k.category_id
            LEFT JOIN departments d ON d.departments_id = k.department_id
            JOIN users u ON u.users_id = k.created_by
            WHERE k.status = 'active'";

    $params = [$currentUserId, $currentUserId];

    if ($q !== '') {
        $sql .= " AND (k.title LIKE ? OR k.problem LIKE ? OR k.solution LIKE ?)";
        $like = "%$q%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($catId) { $sql .= " AND k.category_id = ?"; $params[] = $catId; }
    if ($deptId) { $sql .= " AND k.department_id = ?"; $params[] = $deptId; }
    $sql .= " ORDER BY $orderBy LIMIT 100";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    $queryError = $e->getMessage();
    error_log('Knowledge query failed: ' . $queryError);
}

$totalArticles = count($rows);
$totalViews = 0;
$totalContributors = [];
foreach ($rows as $r) {
    $totalViews += (int)($r['view_count'] ?? 0);
    $totalContributors[$r['author']] = true;
}
$totalContributors = count($totalContributors);

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

$backQuery = http_build_query(array_filter(['q' => $q, 'cat' => $catId, 'dept' => $deptId, 'sort' => $sort]));
?>

<?php if (isset($_SESSION['success'])): ?>
<div class="kl-toast success" id="klToastSuccess"><i class="fas fa-circle-check"></i><span><?= e($_SESSION['success']); unset($_SESSION['success']); ?></span></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
<div class="kl-toast error" id="klToastError"><i class="fas fa-circle-exclamation"></i><span><?= e($_SESSION['error']); unset($_SESSION['error']); ?></span></div>
<?php endif; ?>

<div class="kl-page">
    <div class="kl-bg">
        <div class="grid"></div>
        <div class="aurora a1"></div>
        <div class="aurora a2"></div>
    </div>
    <div class="kl-scenery" id="klScenery"></div>

    <!-- ============ HERO ============ -->
    <div class="kl-hero">
        <div class="kl-hero-row">
            <div>
                <h1>
                    <span class="hero-badge"><i class="fas fa-book-open"></i></span>
                    Knowledge Library
                </h1>
                <p>Share your expertise, learn from others, and build our collective knowledge base.</p>
            </div>
            <a href="share_knowledge.php" class="kl-hero-cta">
                <span class="cta-logo"><i class="fas fa-plus"></i></span>
                Share Knowledge
            </a>
        </div>

        <div class="kl-hero-stats">
            <div class="kl-hero-stat">
                <i class="articles"><i class="fas fa-book"></i></i>
                <div class="kl-hero-stat-info">
                    <span class="val"><?= (int)$totalArticles ?></span>
                    <span class="lbl">Articles</span>
                </div>
            </div>
            <div class="kl-hero-stat">
                <i class="cats"><i class="fas fa-tags"></i></i>
                <div class="kl-hero-stat-info">
                    <span class="val"><?= count($categories) ?></span>
                    <span class="lbl">Categories</span>
                </div>
            </div>
            <div class="kl-hero-stat">
                <i class="people"><i class="fas fa-users"></i></i>
                <div class="kl-hero-stat-info">
                    <span class="val"><?= (int)$totalContributors ?></span>
                    <span class="lbl">Contributors</span>
                </div>
            </div>
            <div class="kl-hero-stat">
                <i class="views"><i class="fas fa-eye"></i></i>
                <div class="kl-hero-stat-info">
                    <span class="val"><?= number_format($totalViews) ?></span>
                    <span class="lbl">Total Views</span>
                </div>
            </div>
        </div>

        <div class="kl-shelf">
            <a class="kl-shelf-item <?= !$catId ? 'active' : '' ?>"
               href="?<?= e(http_build_query(array_filter(['q'=>$q,'dept'=>$deptId,'sort'=>$sort]))) ?>">
                <i class="fas fa-th-large"></i> All Topics
            </a>
            <?php
            $quickFilters = [
                'Hardware' => ['icon' => 'fa-microchip',       'id' => null],
                'Software' => ['icon' => 'fa-code',            'id' => null],
                'Network'  => ['icon' => 'fa-network-wired',   'id' => null],
                'Security' => ['icon' => 'fa-shield-halved',   'id' => null],
                'Cloud'    => ['icon' => 'fa-cloud',           'id' => null],
                'Wi-Fi'    => ['icon' => 'fa-wifi',            'id' => null],
            ];
            foreach ($categories as $c) {
                $name = strtolower($c['name']);
                foreach ($quickFilters as $key => &$filter) {
                    if (str_contains($name, strtolower($key)) && !$filter['id']) {
                        $filter['id'] = $c['id'];
                    }
                }
            }
            unset($filter);
            foreach ($quickFilters as $label => $chip):
                if ($chip['id']):
            ?>
                <a class="kl-shelf-item <?= $catId === $chip['id'] ? 'active' : '' ?>"
                   href="?<?= e(http_build_query(array_filter(['q'=>$q,'cat'=>$chip['id'],'dept'=>$deptId,'sort'=>$sort]))) ?>">
                    <i class="fas <?= e($chip['icon']) ?>"></i> <?= e($label) ?>
                </a>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <!-- ============ SEARCH BAR ============ -->
    <div class="kl-search">
        <div class="kl-search-input-wrap">
            <i class="fas fa-search kl-search-icon"></i>
            <input type="text" id="searchInput" value="<?= e($q) ?>" placeholder="Search the library..."
                   onkeydown="if(event.key==='Enter') doSearch()">
        </div>
        <select class="kl-select" id="catFilter" onchange="doSearch()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= e($c['id']) ?>" <?= $catId === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="kl-select" id="deptFilter" onchange="doSearch()">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= e($d['id']) ?>" <?= $deptId === $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="kl-select" id="sortFilter" onchange="doSearch()">
            <option value="recent"  <?= $sort === 'recent'  ? 'selected' : '' ?>>Most Recent</option>
            <option value="oldest"  <?= $sort === 'oldest'  ? 'selected' : '' ?>>Oldest First</option>
            <option value="helpful" <?= $sort === 'helpful' ? 'selected' : '' ?>>Most Confirmed</option>
            <option value="saved"   <?= $sort === 'saved'   ? 'selected' : '' ?>>Most Saved</option>
            <option value="az"      <?= $sort === 'az'      ? 'selected' : '' ?>>Title A–Z</option>
        </select>
        <button class="kl-search-btn" onclick="doSearch()"><i class="fas fa-search"></i> Browse</button>
        <?php if ($q !== '' || $catId || $deptId): ?>
            <a href="knowledge.php" class="kl-clear-btn"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </div>

    

    <!-- ============ RESULTS HEADER ============ -->
    <div class="kl-results-head">
        <div class="kl-results-count">
            <strong><?= count($rows) ?></strong> article<?= count($rows) !== 1 ? 's' : '' ?>
            <?php if ($q !== ''): ?>
                for "<span class="accent"><?= e($q) ?></span>"
            <?php endif; ?>
        </div>
        <div class="kl-view-toggle">
            <button class="kl-view-btn active" id="viewGrid" title="Grid view"><i class="fas fa-th-large"></i></button>
            <button class="kl-view-btn" id="viewList" title="List view"><i class="fas fa-list"></i></button>
        </div>
    </div>

    <!-- ============ GRID ============ -->
    <div class="kl-grid">
    <?php foreach ($rows as $i => $k):
        $helpful = (int)($k['helpful_count'] ?? 0);
        $notHelpful = (int)($k['not_helpful_count'] ?? 0);
        $myVote = $k['my_vote'] ?? null;
        $isSaved = isset($k['is_saved']) && $k['is_saved'] == 1;
        $initials = strtoupper(substr($k['author'], 0, 1));
        $preview = mb_substr($k['problem'], 0, 130) . (mb_strlen($k['problem']) > 130 ? '…' : '');
        $totalVotes = $helpful + $notHelpful;
        $verified = $totalVotes > 0 && ($helpful / $totalVotes * 100) >= 70 && $helpful > 2;
        $isOwner = $k['created_by'] == $currentUserId;
        $profilePic = !empty($k['author_profile_picture']) ? '../uploads/profiles/' . $k['author_profile_picture'] : '';
        $profilePicExists = $profilePic && file_exists(__DIR__ . '/../uploads/profiles/' . $k['author_profile_picture']);
  $kid = $k['knowledge_id'];
    ?>
        <div class="kl-card" style="animation-delay: <?= min($i * 0.03, 0.6) ?>s;">
            <div class="kl-card-spine"></div>
            <div class="kl-card-body">
                <div class="kl-card-top">
                    <span class="kl-cat-chip">
                        <i class="fas <?= e(klps_category_icon($k['category_name'])) ?>"></i>
                        <?= e($k['category_name'] ?? 'Uncategorized') ?>
                    </span>
                    <?php if ($verified): ?>
                        <span class="kl-verified"><i class="fas fa-circle-check"></i> Verified</span>
                    <?php endif; ?>
                </div>

                <a href="knowledge_view.php?id=<?= $kid ?>" class="kl-card-title"><?= e($k['title']) ?></a>
                <div class="kl-card-excerpt"><?= e($preview) ?></div>

                <div class="kl-card-footer">
                    <div class="kl-card-author">
                        <?php if ($profilePicExists): ?>
                            <img src="<?= e($profilePic) ?>" alt="<?= e($k['author']) ?>" class="avatar-img">
                        <?php else: ?>
                            <div class="avatar"><?= e($initials) ?></div>
                        <?php endif; ?>
                        <span class="name"><?= e($k['author']) ?></span>
                        <span class="date"><?= date('M d', strtotime($k['updated_at'] ?? $k['created_at'])) ?></span>
                    </div>

                    <div class="kl-card-actions">
                        <a class="kl-action vote <?= $myVote === 'helpful' ? 'active' : '' ?>"
                           href="knowledge_vote.php?id=<?= $kid ?>&vote=helpful&back=<?= urlencode($backQuery) ?>"
                           title="Helpful">
                            <i class="fas fa-thumbs-up"></i>
                            <?php if ($helpful > 0): ?><span class="count"><?= $helpful ?></span><?php endif; ?>
                        </a>
                        <a class="kl-action vote down <?= $myVote === 'not_helpful' ? 'active' : '' ?>"
                           href="knowledge_vote.php?id=<?= $kid ?>&vote=not_helpful&back=<?= urlencode($backQuery) ?>"
                           title="Not helpful">
                            <i class="fas fa-thumbs-down"></i>
                            <?php if ($notHelpful > 0): ?><span class="count"><?= $notHelpful ?></span><?php endif; ?>
                        </a>
                        <a class="kl-action save <?= $isSaved ? 'active' : '' ?>"
                           href="knowledge_save.php?id=<?= $kid ?>&back=<?= urlencode($backQuery) ?>"
                           title="Save">
                            <i class="fas fa-bookmark"></i>
                            <?php if (($k['save_count'] ?? 0) > 0): ?><span class="count"><?= (int)$k['save_count'] ?></span><?php endif; ?>
                        </a>
                        <a class="kl-action comment" href="knowledge_view.php?id=<?= $kid ?>#comments" title="Comments">
                            <i class="fas fa-comment"></i>
                            <?php if (($k['comment_count'] ?? 0) > 0): ?><span class="count"><?= (int)$k['comment_count'] ?></span><?php endif; ?>
                        </a>
                        <a class="kl-action read" href="knowledge_view.php?id=<?= $kid ?>">
                            Read <i class="fas fa-arrow-right" style="font-size:0.55rem;"></i>
                        </a>
                        <?php if ($isOwner || $_SESSION['user']['role'] === 'admin'): ?>
                            <button class="kl-action delete" title="Delete"
                                    onclick="confirmDelete('<?= e($kid) ?>', '<?= addslashes($k['title']) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <?php if (empty($rows)): ?>
        <div class="kl-empty">
            <div class="empty-icon"><i class="fas fa-book-open"></i></div>
            <h3>No Articles Found</h3>
            <p>Be the first to share your knowledge! Contribute an article and help others facing the same problem.</p>
            <a href="share_knowledge.php" class="btn-share">
                <span class="logo"><i class="fas fa-plus"></i></span>
                Share Knowledge
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- ============ EDIT MODAL ============ -->
<div id="editModal" class="kl-modal-overlay">
    <div class="kl-modal">
        <button class="kl-modal-close" onclick="closeModal('editModal')">&times;</button>
        <h2><i class="fas fa-edit"></i> Edit Article</h2>
        <form method="post" action="knowledge.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <div class="kl-form-group">
                <label class="kl-form-label">Title <span class="required">*</span></label>
                <input type="text" name="title" id="edit_title" class="kl-form-control" required>
            </div>

            <div class="kl-form-row">
                <div class="kl-form-group">
                    <label class="kl-form-label">Category</label>
                    <select name="category_id" id="edit_category_id" class="kl-form-control">
                        <option value="">Select</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="kl-form-group">
                    <label class="kl-form-label">Department</label>
                    <select name="department_id" id="edit_department_id" class="kl-form-control">
                        <option value="">Select</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= e($d['id']) ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="kl-form-group">
                <label class="kl-form-label">Problem <span class="required">*</span></label>
                <textarea name="problem" id="edit_problem" class="kl-form-control" rows="3" required></textarea>
            </div>

            <div class="kl-form-group">
                <label class="kl-form-label">Root Cause</label>
                <textarea name="cause" id="edit_cause" class="kl-form-control" rows="2"></textarea>
            </div>

            <div class="kl-form-group">
                <label class="kl-form-label">Solution <span class="required">*</span></label>
                <textarea name="solution" id="edit_solution" class="kl-form-control" rows="3" required></textarea>
            </div>

            <div class="kl-form-group">
                <label class="kl-form-label">Procedure Steps</label>
                <textarea name="procedure_steps" id="edit_procedure_steps" class="kl-form-control" rows="4"></textarea>
            </div>

            <div class="kl-form-group">
                <label class="kl-form-label">Best Practice</label>
                <textarea name="best_practice" id="edit_best_practice" class="kl-form-control" rows="2"></textarea>
            </div>

            <div class="kl-form-group">
                <label class="kl-form-label">Tags</label>
                <input type="text" name="tags" id="edit_tags" class="kl-form-control">
            </div>

            <div class="kl-modal-actions">
                <button type="button" class="kl-btn ghost" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="kl-btn primary"><i class="fas fa-save"></i> Update Article</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>