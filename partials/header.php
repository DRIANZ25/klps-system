<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$user = current_user();

$pdo = db();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge WHERE created_by = ?');
$stmt->execute([$user['users_id']]);
$myKnowledge = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge_saves WHERE user_id = ?');
$stmt->execute([$user['users_id']]);
$savedCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$user['users_id']]);
$unreadNotifications = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('
    SELECT n.*, u.full_name as from_user_name 
    FROM notifications n
    LEFT JOIN users u ON u.users_id = n.from_user_id
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC 
    LIMIT 10
');
$stmt->execute([$user['users_id']]);
$recentNotifications = $stmt->fetchAll();

$xp = ($myKnowledge * 10) + ($savedCount * 2);
$level = intdiv($xp, 50) + 1;
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

$profilePic = !empty($user['profile_picture']) ? '../uploads/profiles/' . $user['profile_picture'] : '';

// Department name (looked up fresh so it's always correct even if not in session)
$deptName = null;
if (!empty($user['users_id'])) {
    try {
        $stmt = $pdo->prepare('SELECT d.name FROM users u LEFT JOIN departments d ON d.departments_id = u.department_id WHERE u.users_id = ?');
        $stmt->execute([$user['users_id']]);
        $deptName = $stmt->fetchColumn() ?: null;
    } catch (PDOException $e) {}
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? 'KLPS') ?></title>

<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g' x1='0%25' y1='0%25' x2='100%25' y2='100%25'><stop offset='0%25' style='stop-color:%23667eea'/><stop offset='100%25' style='stop-color:%23764ba2'/></linearGradient></defs><rect width='100' height='100' rx='20' fill='url(%23g)'/><text x='50' y='68' text-anchor='middle' font-size='42' font-family='Arial, sans-serif' font-weight='800' fill='white'>K</text><text x='72' y='68' text-anchor='middle' font-size='42' font-family='Arial, sans-serif' font-weight='800' fill='%2300f0ff'>L</text></svg>">
<meta name="theme-color" content="#0a0e1a">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= e(asset('css/layout.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/toast.css')) ?>">
<?php foreach ((array) ($page_css ?? []) as $page_css_file): ?>
<link rel="stylesheet" href="<?= e(asset('css/' . $page_css_file)) ?>">
<?php endforeach; ?>
</head>
<body>

<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
    <span style="font-size:0.7rem;font-weight:600;">Menu</span>
</button>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="container-fluid">
    <div class="row">
        <aside class="col-md-2 sidebar-enhanced p-3" id="mainSidebar">
            
            <!-- ============ BRAND / LOGO ============ -->
            <a href="<?= e($user['role'] === 'admin' ? app_url('admin/dashboard.php') : app_url('employee/dashboard.php')) ?>" class="sidebar-brand">
                <div class="logo-live">
                    <div class="glow"></div>
                    <div class="ring"></div>
                    <div class="core">
                        <i class="fas fa-brain"></i>
                    </div>
                    <div class="orbit">
                        <span class="dot"></span>
                        <span class="dot"></span>
                    </div>
                    <div class="orbit rev">
                        <span class="dot"></span>
                    </div>
                </div>
                <div class="brand-text-wrap">
                    <span class="brand-line-1">Knowledge Learning</span>
                    <span class="brand-line-2">Preservation <span class="accent">System</span></span>
                </div>
            </a>

            <!-- ============ USER CARD ============ -->
            <div class="sidebar-user" onclick="window.location.href='profile.php'">
                <div class="user-avatar-wrap">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?= e($profilePic) ?>" alt="Profile" class="user-avatar" style="width:44px;height:44px;border-radius:12px;object-fit:cover;">
                    <?php else: ?>
                        <div class="user-avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div>
                    <?php endif; ?>
                    <span class="online-dot"></span>
                </div>
                <div class="user-info">
                    <div class="user-name"><?= e($user['full_name']) ?></div>
                    <div class="user-role">
                        <span class="role-badge"><?= ucfirst($user['role']) ?></span>
                        <?php if ($deptName): ?>
                            <span class="dept-badge"><i class="fas fa-building" style="font-size:0.4rem;"></i><?= e($deptName) ?></span>
                        <?php endif; ?>
                        <span class="rank-text"><i class="fas <?= e($rankIcon) ?>"></i> <?= e($rankTitle) ?></span>
                    </div>
                    <div class="user-rank">
                        <span class="rank-level">Level <?= (int)$level ?></span>
                        <span>·</span>
                        <span><?= (int)$xp ?> XP</span>
                    </div>
                </div>
            </div>

            <!-- ============ NAV ============ -->
            <nav class="sidebar-nav">
                <div class="nav-label">Main</div>
                
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= e(app_url('admin/dashboard.php')) ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-gauge-high"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="<?= e(app_url('admin/users.php')) ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-users-gear"></i></span>
                        <span class="nav-text">User Management</span>
                    </a>
                    <a href="<?= e(app_url('admin/knowledge.php')) ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'knowledge.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-shield-halved"></i></span>
                        <span class="nav-text">Moderate Knowledge</span>
                        <span class="nav-badge"><?= $myKnowledge ?></span>
                    </a>
                <?php else: ?>
                    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-gauge-high"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    
                    <div class="nav-label" style="margin-top:0.75rem;">Tools</div>
                    
                    <a href="ai_chat.php" class="<?= basename($_SERVER['PHP_SELF']) === 'ai_chat.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-robot"></i></span>
                        <span class="nav-text">AI Assistant</span>
                        <i class="fas fa-chevron-right nav-arrow"></i>
                    </a>
                    <a href="knowledge.php" class="<?= basename($_SERVER['PHP_SELF']) === 'knowledge.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-book-open"></i></span>
                        <span class="nav-text">Knowledge Library</span>
                        <span class="nav-badge"><?= $myKnowledge ?></span>
                    </a>
                    
                    <div class="nav-label" style="margin-top:0.75rem;">Library</div>
                    
                    <a href="saved_articles.php" class="<?= basename($_SERVER['PHP_SELF']) === 'saved_articles.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-bookmark"></i></span>
                        <span class="nav-text">Saved Articles</span>
                        <?php if ($savedCount > 0): ?>
                            <span class="nav-badge"><?= $savedCount ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="my_contributions.php" class="<?= basename($_SERVER['PHP_SELF']) === 'my_contributions.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-star"></i></span>
                        <span class="nav-text">My Contributions</span>
                    </a>
                    <a href="chat_history.php" class="<?= basename($_SERVER['PHP_SELF']) === 'chat_history.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-clock-rotate-left"></i></span>
                        <span class="nav-text">Chat History</span>
                    </a>
                    <a href="notifications.php" class="<?= basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-bell"></i></span>
                        <span class="nav-text">Notifications</span>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="nav-badge"><?= $unreadNotifications ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-user-cog"></i></span>
                        <span class="nav-text">Profile Settings</span>
                    </a>
                <?php endif; ?>
            </nav>

            <!-- ============ LOGOUT ============ -->
            <div class="sidebar-logout">
                <button class="logout-btn" onclick="openLogoutModal()">
                    <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
                    <span class="nav-text">Sign Out</span>
                    <i class="fas fa-chevron-right nav-arrow"></i>
                </button>
                <div class="sidebar-footer">&copy; <?= date('Y') ?> KLPS &bull; Knowledge Learning Preservation System</div>
            </div>
        </aside>

        <main class="col-md-10 p-4">