<?php
$page_title = 'Knowledge Library';
require_once __DIR__ . '/../partials/header.php';

$q = trim($_GET['q'] ?? '');
$catId = isset($_GET['cat']) && $_GET['cat'] !== '' ? (int)$_GET['cat'] : null;
$deptId = isset($_GET['dept']) && $_GET['dept'] !== '' ? (int)$_GET['dept'] : null;
$sort = $_GET['sort'] ?? 'recent';
$currentUserId = $_SESSION['user']['id'] ?? 0;

$action = '';
if (isset($_POST['action'])) {
    $action = $_POST['action'];
} elseif (isset($_GET['action'])) {
    $action = $_GET['action'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    $problem = trim($_POST['problem'] ?? '');
    $cause = trim($_POST['cause'] ?? '');
    $solution = trim($_POST['solution'] ?? '');
    $procedure_steps = trim($_POST['procedure_steps'] ?? '');
    $best_practice = trim($_POST['best_practice'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    
    if ($title && $problem && $solution) {
        try {
            $stmt = db()->prepare(
                'INSERT INTO knowledge (category_id, department_id, created_by, title, problem, cause, solution, procedure_steps, best_practice, tags, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")'
            );
            $stmt->execute([$category_id ?: null, $department_id ?: null, $currentUserId, $title, $problem, $cause, $solution, $procedure_steps, $best_practice, $tags]);
            $_SESSION['success'] = '✅ Article created successfully!';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to create article.';
        }
    } else {
        $_SESSION['error'] = 'Title, Problem, and Solution are required.';
    }
    redirect('knowledge.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    $problem = trim($_POST['problem'] ?? '');
    $cause = trim($_POST['cause'] ?? '');
    $solution = trim($_POST['solution'] ?? '');
    $procedure_steps = trim($_POST['procedure_steps'] ?? '');
    $best_practice = trim($_POST['best_practice'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    
    if ($id && $title && $problem && $solution) {
        try {
            $stmt = db()->prepare('SELECT created_by FROM knowledge WHERE id = ?');
            $stmt->execute([$id]);
            $article = $stmt->fetch();
            if ($article && ($article['created_by'] == $currentUserId || $_SESSION['user']['role'] === 'admin')) {
                $stmt = db()->prepare(
                    'UPDATE knowledge SET category_id=?, department_id=?, title=?, problem=?, cause=?, solution=?, procedure_steps=?, best_practice=?, tags=? WHERE id=?'
                );
                $stmt->execute([$category_id ?: null, $department_id ?: null, $title, $problem, $cause, $solution, $procedure_steps, $best_practice, $tags, $id]);
                $_SESSION['success'] = '✅ Article updated successfully!';
            } else {
                $_SESSION['error'] = 'Permission denied.';
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Failed to update article.';
        }
    }
    redirect('knowledge.php');
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = db()->prepare('SELECT created_by FROM knowledge WHERE id = ?');
        $stmt->execute([$id]);
        $article = $stmt->fetch();
        if ($article && ($article['created_by'] == $currentUserId || $_SESSION['user']['role'] === 'admin')) {
            $stmt = db()->prepare('DELETE FROM knowledge WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['success'] = '🗑️ Article deleted successfully.';
        } else {
            $_SESSION['error'] = 'Permission denied.';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete article.';
    }
    redirect('knowledge.php');
}

$categories = [];
$departments = [];
try {
    $categories = db()->query('SELECT id, name FROM knowledge_categories ORDER BY name')->fetchAll();
    $departments = db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
} catch (PDOException $e) {}

$orderBy = match($sort) {
    'helpful' => 'helpful_count DESC, k.updated_at DESC',
    'az' => 'k.title ASC',
    'oldest' => 'k.created_at ASC',
    'saved' => 'save_count DESC, k.updated_at DESC',
    default => 'k.updated_at DESC'
};

$rows = [];
try {
    $sql = "SELECT k.*, kc.name AS category_name, d.name AS department_name, u.full_name AS author,
            u.profile_picture AS author_profile_picture,
            (SELECT COUNT(*) FROM knowledge_votes kv WHERE kv.knowledge_id = k.id AND kv.vote = 'helpful') AS helpful_count,
            (SELECT COUNT(*) FROM knowledge_votes kv2 WHERE kv2.knowledge_id = k.id AND kv2.vote = 'not_helpful') AS not_helpful_count,
            (SELECT vote FROM knowledge_votes kv3 WHERE kv3.knowledge_id = k.id AND kv3.user_id = ?) AS my_vote,
            (SELECT COUNT(*) FROM knowledge_comments c WHERE c.knowledge_id = k.id) AS comment_count,
            (SELECT COUNT(*) FROM knowledge_saves s WHERE s.knowledge_id = k.id) AS save_count,
            (SELECT 1 FROM knowledge_saves s2 WHERE s2.knowledge_id = k.id AND s2.user_id = ?) AS is_saved
            FROM knowledge k
            LEFT JOIN knowledge_categories kc ON kc.id = k.category_id
            LEFT JOIN departments d ON d.id = k.department_id
            JOIN users u ON u.id = k.created_by
            WHERE k.status = 'active'";
    
    $params = [$currentUserId, $currentUserId];
    
    if ($q !== '') {
        $sql .= " AND (k.title LIKE ? OR k.problem LIKE ? OR k.solution LIKE ?)";
        $like = "%$q%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($catId) {
        $sql .= " AND k.category_id = ?";
        $params[] = $catId;
    }
    if ($deptId) {
        $sql .= " AND k.department_id = ?";
        $params[] = $deptId;
    }
    $sql .= " ORDER BY $orderBy LIMIT 100";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {}

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

$backQuery = http_build_query(array_filter(['q' => $q, 'cat' => $catId, 'dept' => $deptId, 'sort' => $sort]));
?>

<?php if (isset($_SESSION['success'])): ?>
<div style="position:fixed;top:20px;right:20px;z-index:99999;padding:15px 25px;background:rgba(0,255,156,0.15);border:1px solid rgba(0,255,156,0.3);border-radius:10px;color:#00ff9c;animation:slideIn 0.3s ease;max-width:400px;">
    <i class="fas fa-check-circle me-2"></i> <?= e($_SESSION['success']); unset($_SESSION['success']); ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div style="position:fixed;top:20px;right:20px;z-index:99999;padding:15px 25px;background:rgba(255,0,60,0.15);border:1px solid rgba(255,0,60,0.3);border-radius:10px;color:#ff7c93;animation:slideIn 0.3s ease;max-width:400px;">
    <i class="fas fa-exclamation-circle me-2"></i> <?= e($_SESSION['error']); unset($_SESSION['error']); ?>
</div>
<?php endif; ?>

<style>
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
:root {
    --cyan: #00f0ff;
    --violet: #8b7cf6;
    --gold: #f6c453;
    --text-dim: #8892b0;
    --border: rgba(255,255,255,0.07);
}
.knowledge-page {
    background: radial-gradient(ellipse at 20% -10%, #1a2140 0%, #03050a 45%, #050710 100%);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(0,240,255,0.08);
}
.library-hero {
    background: linear-gradient(135deg, rgba(26,33,64,0.6), rgba(10,14,26,0.8));
    border: 1px solid rgba(0,240,255,0.08);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
}
.library-hero h1 {
    font-family: 'Orbitron', sans-serif;
    font-size: 2rem;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.library-hero h1 i { color: var(--gold); }
.library-hero p { color: var(--text-dim); margin-bottom: 1rem; }
.library-hero .btn-create {
    padding: 0.6rem 1.2rem;
    background: linear-gradient(135deg, var(--cyan), var(--violet));
    border: none;
    border-radius: 10px;
    color: #0a0e1a;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}
.library-hero .btn-create:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(0,240,255,0.3);
}
.hero-stats {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
    margin: 1rem 0;
}
.hero-stats .stat {
    color: var(--text-dim);
    font-size: 0.85rem;
}
.hero-stats .stat strong { color: #fff; }
.category-shelf {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}
.category-shelf .shelf-item {
    padding: 0.45rem 1rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    color: var(--text-dim);
    text-decoration: none;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}
.category-shelf .shelf-item:hover { background: rgba(0,240,255,0.06); color: #fff; }
.category-shelf .shelf-item.active {
    background: linear-gradient(135deg, rgba(0,240,255,0.1), rgba(139,124,246,0.1));
    border-color: var(--cyan);
    color: var(--cyan);
}
.library-search-bar {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    padding: 1rem 1.25rem;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.04);
    border-radius: 14px;
    margin-bottom: 1.5rem;
}
.library-search-bar .search-input-wrap {
    flex: 1;
    min-width: 180px;
    position: relative;
}
.library-search-bar .search-input-wrap input {
    width: 100%;
    padding: 0.6rem 1rem 0.6rem 2.6rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    color: #fff;
}
.library-search-bar .search-input-wrap input:focus {
    outline: none;
    border-color: rgba(0,240,255,0.2);
}
.library-search-bar .search-input-wrap .search-icon {
    position: absolute;
    left: 0.9rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-dim);
}
.library-search-bar .filter-select {
    padding: 0.6rem 2.2rem 0.6rem 1rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    color: #e7ecf7;
    font-size: 0.8rem;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238892b0' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.9rem center;
}
.library-search-bar .filter-select:focus {
    outline: none;
    border-color: rgba(0,240,255,0.2);
}
.library-search-bar .filter-select option {
    background: #0a0e1a;
    color: #e7ecf7;
}
.library-search-bar .search-btn {
    padding: 0.6rem 1.2rem;
    background: linear-gradient(135deg, rgba(0,240,255,0.15), rgba(139,124,246,0.15));
    border: 1px solid rgba(0,240,255,0.15);
    border-radius: 10px;
    color: var(--cyan);
    font-weight: 600;
    cursor: pointer;
}
.library-search-bar .search-btn:hover {
    background: linear-gradient(135deg, rgba(0,240,255,0.25), rgba(139,124,246,0.25));
}
.results-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.results-header .count { color: var(--text-dim); }
.results-header .count strong { color: #fff; }
.library-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 1.5rem;
}
.library-card {
    background: linear-gradient(160deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 14px;
    overflow: hidden;
    transition: all 0.3s ease;
}
.library-card:hover {
    transform: translateY(-6px);
    border-color: rgba(0,240,255,0.12);
    box-shadow: 0 20px 40px -15px rgba(0,0,0,0.5);
}
.library-card .card-spine {
    height: 3px;
    background: linear-gradient(90deg, var(--cyan), var(--violet));
}
.library-card .card-body { padding: 1.25rem 1.5rem 1.5rem; }
.library-card .card-top {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}
.library-card .card-category {
    font-size: 0.65rem;
    text-transform: uppercase;
    color: var(--text-dim);
    background: rgba(255,255,255,0.04);
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
}
.library-card .card-verified {
    font-size: 0.65rem;
    color: #00ff9c;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    background: rgba(0,255,156,0.06);
    border: 1px solid rgba(0,255,156,0.1);
}
.library-card .card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    text-decoration: none;
    display: block;
    margin-bottom: 0.5rem;
}
.library-card .card-title:hover { color: var(--cyan); }
.library-card .card-excerpt {
    color: var(--text-dim);
    font-size: 0.85rem;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 1rem;
}
.library-card .card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255,255,255,0.04);
    flex-wrap: wrap;
    gap: 0.5rem;
}
.library-card .card-author {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-dim);
}
.library-card .card-author .avatar {
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
.library-card .card-author .avatar-img {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(0, 240, 255, 0.15);
    flex-shrink: 0;
}
.library-card .card-actions {
    display: flex;
    gap: 0.3rem;
    align-items: center;
    flex-wrap: wrap;
}
.library-card .card-actions .action-btn {
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.04);
    background: rgba(255,255,255,0.02);
    color: var(--text-dim);
    cursor: pointer;
    font-size: 0.7rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    transition: all 0.2s ease;
}
.library-card .card-actions .action-btn:hover {
    background: rgba(255,255,255,0.06);
    color: #fff;
    transform: translateY(-1px);
}
.library-card .card-actions .action-btn.active {
    background: rgba(0,240,255,0.08);
    border-color: rgba(0,240,255,0.15);
    color: var(--cyan);
}
.library-card .card-actions .save-btn.active {
    background: rgba(246,196,83,0.08);
    border-color: rgba(246,196,83,0.15);
    color: var(--gold);
}
.library-card .card-actions .action-btn .count {
    font-size: 0.6rem;
    opacity: 0.7;
}
.library-card .card-actions .read-btn {
    padding: 0.3rem 0.8rem;
    border-radius: 6px;
    background: rgba(0,240,255,0.08);
    border: 1px solid rgba(0,240,255,0.08);
    color: var(--cyan);
    text-decoration: none;
    font-size: 0.7rem;
    font-weight: 600;
    transition: all 0.2s ease;
}
.library-card .card-actions .read-btn:hover {
    background: rgba(0,240,255,0.15);
    transform: translateY(-1px);
}
.library-card .card-actions .delete-btn {
    background: rgba(239,68,68,0.05);
    border-color: rgba(239,68,68,0.1);
    color: #ef4444;
}
.library-card .card-actions .delete-btn:hover {
    background: rgba(239,68,68,0.12);
}
.library-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-dim);
}
.library-empty i {
    font-size: 3.5rem;
    color: rgba(0,240,255,0.1);
    display: block;
    margin-bottom: 1.5rem;
}
.library-empty h3 { color: #fff; font-size: 1.25rem; margin-bottom: 0.5rem; }

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    z-index: 99999;
    overflow-y: auto;
    padding: 20px;
}
.modal-overlay.active {
    display: block;
}
.modal {
    background: linear-gradient(160deg, #1a2140, #0a0e1a);
    border: 1px solid rgba(0, 240, 255, 0.15);
    border-radius: 20px;
    padding: 2rem;
    max-width: 700px;
    width: 100%;
    margin: 40px auto;
    position: relative;
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.8);
}
.modal-close {
    position: absolute;
    top: 15px;
    right: 20px;
    background: none;
    border: none;
    color: #8892b0;
    font-size: 1.5rem;
    cursor: pointer;
}
.modal-close:hover { color: #fff; }
.modal h2 {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.3rem;
    color: #fff;
    margin-bottom: 1.5rem;
}
.modal .form-group { margin-bottom: 1rem; }
.modal .form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    color: #8892b0;
    margin-bottom: 0.3rem;
}
.modal .form-group label .required { color: #ef4444; }
.modal .form-control {
    width: 100%;
    padding: 0.6rem 1rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    color: #fff;
    font-size: 0.9rem;
}
.modal .form-control:focus {
    outline: none;
    border-color: rgba(0,240,255,0.2);
}
.modal .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.modal .btn-submit {
    width: 100%;
    padding: 0.75rem;
    background: linear-gradient(135deg, #00f0ff, #8b7cf6);
    border: none;
    border-radius: 10px;
    color: #0a0e1a;
    font-weight: 700;
    cursor: pointer;
}
.modal .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(0,240,255,0.3);
}
.modal .btn-cancel {
    padding: 0.75rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    color: #8892b0;
    cursor: pointer;
    width: 100%;
}
.modal .btn-cancel:hover {
    background: rgba(255,255,255,0.06);
    color: #fff;
}
.modal .modal-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-top: 1rem;
}
@media (max-width: 768px) {
    .knowledge-page { padding: 1rem; }
    .library-hero { padding: 1.25rem; }
    .library-hero h1 { font-size: 1.3rem; }
    .library-grid { grid-template-columns: 1fr; }
    .library-search-bar { flex-direction: column; align-items: stretch; }
    .library-search-bar .filter-group { flex-wrap: wrap; }
    .library-search-bar .filter-select { flex: 1; min-width: 120px; }
    .category-shelf { flex-wrap: nowrap; overflow-x: auto; padding-bottom: 0.5rem; }
    .category-shelf .shelf-item { white-space: nowrap; flex-shrink: 0; }
    .results-header { flex-direction: column; gap: 0.5rem; }
    .modal { padding: 1rem; margin: 0.5rem; }
    .modal .form-row { grid-template-columns: 1fr; }
    .modal .modal-actions { grid-template-columns: 1fr; }
}
@media (max-width: 576px) {
    .knowledge-page { padding: 0.75rem; }
    .library-hero { padding: 1rem; }
    .library-hero h1 { font-size: 1.1rem; }
    .library-hero .btn-create { width: 100%; justify-content: center; }
    .library-card .card-body { padding: 1rem; }
    .library-card .card-actions { flex-wrap: wrap; }
    .library-card .card-actions .action-btn { font-size: 0.65rem; padding: 0.2rem 0.4rem; }
}
</style>

<div class="knowledge-page">
    <div class="library-hero">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
            <div>
                <h1><i class="fas fa-book-open"></i> Knowledge Library</h1>
                <p>Share your expertise, learn from others, and build our collective knowledge base.</p>
                <div class="hero-stats">
                    <div class="stat"><strong><?= count($rows) ?></strong> Articles</div>
                    <div class="stat"><strong><?= count($categories) ?></strong> Categories</div>
                </div>
            </div>
            <a href="share_knowledge.php" class="btn-create">
                <i class="fas fa-plus"></i> Share Knowledge
            </a>
        </div>
        <div class="category-shelf">
            <a class="shelf-item <?= !$catId ? 'active' : '' ?>" href="?<?= e(http_build_query(array_filter(['q'=>$q,'sort'=>$sort]))) ?>">
                <i class="fas fa-th-large"></i> All
            </a>
            <?php
            $quickFilters = [
                'Hardware' => ['icon' => 'fa-microchip', 'id' => null],
                'Software' => ['icon' => 'fa-code', 'id' => null],
                'Network' => ['icon' => 'fa-network-wired', 'id' => null],
                'Security' => ['icon' => 'fa-shield-halved', 'id' => null],
                'Cloud' => ['icon' => 'fa-cloud', 'id' => null],
            ];
            foreach ($categories as $c) {
                $name = strtolower($c['name']);
                foreach ($quickFilters as $key => &$filter) {
                    if (str_contains($name, strtolower($key)) && !$filter['id']) {
                        $filter['id'] = $c['id'];
                    }
                }
            }
            foreach ($quickFilters as $label => $chip):
                if ($chip['id']):
            ?>
                <a class="shelf-item <?= $catId === $chip['id'] ? 'active' : '' ?>" href="?<?= e(http_build_query(array_filter(['q'=>$q,'cat'=>$chip['id'],'sort'=>$sort]))) ?>">
                    <i class="fas <?= e($chip['icon']) ?>"></i> <?= e($label) ?>
                </a>
            <?php endif; endforeach; ?>
        </div>
    </div>

    <div class="library-search-bar">
        <div class="search-input-wrap">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" value="<?= e($q) ?>" placeholder="Search the library..." onkeydown="if(event.key==='Enter') doSearch()">
        </div>
        <select class="filter-select" id="catFilter" onchange="doSearch()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-select" id="deptFilter" onchange="doSearch()">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>" <?= $deptId === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-select" id="sortFilter" onchange="doSearch()">
            <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Most Recent</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="helpful" <?= $sort === 'helpful' ? 'selected' : '' ?>>Most Confirmed</option>
            <option value="saved" <?= $sort === 'saved' ? 'selected' : '' ?>>Most Saved</option>
            <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>>Title A–Z</option>
        </select>
        <button class="search-btn" onclick="doSearch()"><i class="fas fa-search"></i> Browse</button>
        <?php if ($q !== '' || $catId || $deptId): ?>
            <a href="knowledge.php" style="padding:0.6rem 1rem;color:var(--text-dim);text-decoration:none;border:1px solid rgba(255,255,255,0.04);border-radius:10px;font-size:0.8rem;">
                <i class="fas fa-times"></i> Clear
            </a>
        <?php endif; ?>
    </div>

    <script>
    function doSearch() {
        const q = document.getElementById('searchInput').value;
        const cat = document.getElementById('catFilter').value;
        const dept = document.getElementById('deptFilter').value;
        const sort = document.getElementById('sortFilter').value;
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (cat) params.set('cat', cat);
        if (dept) params.set('dept', dept);
        if (sort && sort !== 'recent') params.set('sort', sort);
        window.location.href = '?' + params.toString();
    }
    
    function openModal(id) {
        var modal = document.getElementById(id);
        if (modal) {
            modal.style.display = 'block';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeModal(id) {
        var modal = document.getElementById(id);
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    function confirmDelete(id, title) {
        if(confirm('Delete "'+title+'"? This cannot be undone.')) {
            window.location.href='?action=delete&id='+id;
        }
    }
    
    function openEdit(id, title, cat, dept, problem, cause, solution, steps, practice, tags) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_title').value = title || '';
        document.getElementById('edit_category_id').value = cat || '';
        document.getElementById('edit_department_id').value = dept || '';
        document.getElementById('edit_problem').value = problem || '';
        document.getElementById('edit_cause').value = cause || '';
        document.getElementById('edit_solution').value = solution || '';
        document.getElementById('edit_procedure_steps').value = steps || '';
        document.getElementById('edit_best_practice').value = practice || '';
        document.getElementById('edit_tags').value = tags || '';
        openModal('editModal');
    }
    
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList && e.target.classList.contains('modal-overlay')) {
            var modals = document.querySelectorAll('.modal-overlay');
            modals.forEach(function(m) {
                m.style.display = 'none';
                m.classList.remove('active');
            });
            document.body.style.overflow = '';
        }
    });
    
    setTimeout(function() {
        document.querySelectorAll('[style*="position:fixed"][style*="top:20px"]').forEach(function(el) {
            if (el) {
                el.style.transition = 'opacity 0.5s ease';
                el.style.opacity = '0';
                setTimeout(function() { el.remove(); }, 500);
            }
        });
    }, 4000);
    </script>

    <div class="results-header">
        <div class="count"><strong><?= count($rows) ?></strong> article<?= count($rows) !== 1 ? 's' : '' ?></div>
        <div style="display:flex;gap:0.3rem;">
            <button onclick="document.querySelector('.library-grid').style.gridTemplateColumns='repeat(auto-fill, minmax(340px, 1fr))'" style="padding:0.3rem 0.7rem;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.04);border-radius:8px;color:var(--text-dim);cursor:pointer;"><i class="fas fa-th-large"></i></button>
            <button onclick="document.querySelector('.library-grid').style.gridTemplateColumns='1fr'" style="padding:0.3rem 0.7rem;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.04);border-radius:8px;color:var(--text-dim);cursor:pointer;"><i class="fas fa-list"></i></button>
        </div>
    </div>

    <div class="library-grid">
    <?php foreach ($rows as $k):
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
    ?>
        <div class="library-card">
            <div class="card-spine"></div>
            <div class="card-body">
                <div class="card-top">
                    <span class="card-category"><i class="fas <?= e(klps_category_icon($k['category_name'])) ?>"></i> <?= e($k['category_name'] ?? 'Uncategorized') ?></span>
                    <?php if ($verified): ?><span class="card-verified"><i class="fas fa-circle-check"></i> Verified</span><?php endif; ?>
                </div>
                <a href="knowledge_view.php?id=<?= (int)$k['id'] ?>" class="card-title"><?= e($k['title']) ?></a>
                <div class="card-excerpt"><?= e($preview) ?></div>
                <div class="card-footer">
                    <div class="card-author">
                        <?php if (!empty($profilePic) && file_exists($profilePic)): ?>
                            <img src="<?= e($profilePic) ?>" alt="<?= e($k['author']) ?>" class="avatar-img">
                        <?php else: ?>
                            <div class="avatar"><?= e($initials) ?></div>
                        <?php endif; ?>
                        <span><?= e($k['author']) ?></span>
                        <span style="color:var(--text-dim);font-size:0.65rem;">· <?= date('M d', strtotime($k['updated_at'] ?? $k['created_at'])) ?></span>
                    </div>
                    <div class="card-actions">
                        <a class="action-btn <?= $myVote === 'helpful' ? 'active' : '' ?>" 
                           href="knowledge_vote.php?id=<?= (int)$k['id'] ?>&vote=helpful&back=<?= urlencode($backQuery) ?>">
                            <i class="fas fa-thumbs-up"></i>
                            <?php if ($helpful > 0): ?>
                                <span class="count"><?= $helpful ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="action-btn <?= $myVote === 'not_helpful' ? 'active' : '' ?>" 
                           href="knowledge_vote.php?id=<?= (int)$k['id'] ?>&vote=not_helpful&back=<?= urlencode($backQuery) ?>">
                            <i class="fas fa-thumbs-down"></i>
                            <?php if ($notHelpful > 0): ?>
                                <span class="count"><?= $notHelpful ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="action-btn save-btn <?= $isSaved ? 'active' : '' ?>" 
                           href="knowledge_save.php?id=<?= (int)$k['id'] ?>&back=<?= urlencode($backQuery) ?>">
                            <i class="fas fa-bookmark"></i>
                            <?php if (($k['save_count'] ?? 0) > 0): ?>
                                <span class="count"><?= (int)($k['save_count'] ?? 0) ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="action-btn" href="knowledge_view.php?id=<?= (int)$k['id'] ?>#comments">
                            <i class="fas fa-comment"></i>
                            <?php if (($k['comment_count'] ?? 0) > 0): ?>
                                <span class="count"><?= (int)($k['comment_count'] ?? 0) ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="read-btn" href="knowledge_view.php?id=<?= (int)$k['id'] ?>">
                            Read <i class="fas fa-arrow-right" style="font-size:0.6rem;"></i>
                        </a>
                        <?php if ($isOwner || $_SESSION['user']['role'] === 'admin'): ?>
                            <button class="action-btn" onclick="openEdit(<?= (int)$k['id'] ?>, '<?= addslashes($k['title']) ?>', <?= (int)($k['category_id'] ?? 0) ?>, <?= (int)($k['department_id'] ?? 0) ?>, '<?= addslashes($k['problem']) ?>', '<?= addslashes($k['cause']) ?>', '<?= addslashes($k['solution']) ?>', '<?= addslashes($k['procedure_steps']) ?>', '<?= addslashes($k['best_practice']) ?>', '<?= addslashes($k['tags']) ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn delete-btn" onclick="confirmDelete(<?= (int)$k['id'] ?>, '<?= addslashes($k['title']) ?>')">
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
    <div class="library-empty">
        <i class="fas fa-book-open"></i>
        <h3>No Articles Found</h3>
        <p>Be the first to share your knowledge! Click "Share Knowledge" to contribute.</p>
    </div>
    <?php endif; ?>
</div>

<div id="createModal" class="modal-overlay">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        <h2><i class="fas fa-plus-circle"></i> Share Knowledge</h2>
        <form method="post" action="knowledge.php">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Title <span class="required">*</span></label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-control">
                        <option value="">Select</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">Select</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Problem <span class="required">*</span></label>
                <textarea name="problem" class="form-control" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>Root Cause</label>
                <textarea name="cause" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Solution <span class="required">*</span></label>
                <textarea name="solution" class="form-control" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>Procedure Steps</label>
                <textarea name="procedure_steps" class="form-control" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label>Best Practice</label>
                <textarea name="best_practice" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Tags</label>
                <input type="text" name="tags" class="form-control" placeholder="troubleshooting, network, windows">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Publish</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal">
        <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        <h2><i class="fas fa-edit"></i> Edit Article</h2>
        <form method="post" action="knowledge.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Title <span class="required">*</span></label>
                <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" id="edit_category_id" class="form-control">
                        <option value="">Select</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" id="edit_department_id" class="form-control">
                        <option value="">Select</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Problem <span class="required">*</span></label>
                <textarea name="problem" id="edit_problem" class="form-control" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>Root Cause</label>
                <textarea name="cause" id="edit_cause" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Solution <span class="required">*</span></label>
                <textarea name="solution" id="edit_solution" class="form-control" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>Procedure Steps</label>
                <textarea name="procedure_steps" id="edit_procedure_steps" class="form-control" rows="4"></textarea>
            </div>
            <div class="form-group">
                <label>Best Practice</label>
                <textarea name="best_practice" id="edit_best_practice" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Tags</label>
                <input type="text" name="tags" id="edit_tags" class="form-control">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>