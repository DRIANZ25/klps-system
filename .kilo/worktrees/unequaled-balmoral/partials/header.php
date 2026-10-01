<?php
require_once __DIR__ . '/../config/config.php';
require_login();
$user = current_user();

// Get user stats
$pdo = db();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge WHERE created_by = ?');
$stmt->execute([$user['id']]);
$myKnowledge = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge_saves WHERE user_id = ?');
$stmt->execute([$user['id']]);
$savedCount = (int)$stmt->fetchColumn();

// Get unread notification count
$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$user['id']]);
$unreadNotifications = (int)$stmt->fetchColumn();

// Get recent notifications
$stmt = $pdo->prepare('
    SELECT n.*, u.full_name as from_user_name 
    FROM notifications n
    LEFT JOIN users u ON u.id = n.from_user_id
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC 
    LIMIT 10
');
$stmt->execute([$user['id']]);
$recentNotifications = $stmt->fetchAll();

// Calculate level
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

// Profile picture path
$profilePic = !empty($user['profile_picture']) ? '../uploads/profiles/' . $user['profile_picture'] : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? 'KLPS') ?></title>

<!-- Favicon -->
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g' x1='0%25' y1='0%25' x2='100%25' y2='100%25'><stop offset='0%25' style='stop-color:%23667eea'/><stop offset='100%25' style='stop-color:%23764ba2'/></linearGradient></defs><rect width='100' height='100' rx='20' fill='url(%23g)'/><text x='50' y='68' text-anchor='middle' font-size='42' font-family='Arial, sans-serif' font-weight='800' fill='white'>K</text><text x='72' y='68' text-anchor='middle' font-size='42' font-family='Arial, sans-serif' font-weight='800' fill='%2300f0ff'>L</text></svg>">
<link rel="apple-touch-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><defs><linearGradient id='g' x1='0%25' y1='0%25' x2='100%25' y2='100%25'><stop offset='0%25' style='stop-color:%23667eea'/><stop offset='100%25' style='stop-color:%23764ba2'/></linearGradient></defs><rect width='100' height='100' rx='20' fill='url(%23g)'/><text x='50' y='68' text-anchor='middle' font-size='42' font-family='Arial, sans-serif' font-weight='800' fill='white'>K</text><text x='72' y='68' text-anchor='middle' font-size='42' font-family='Arial, sans-serif' font-weight='800' fill='%2300f0ff'>L</text></svg>">
<meta name="theme-color" content="#0a0e1a">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --sidebar-bg: #0a0e1a;
    --sidebar-hover: rgba(139, 124, 246, 0.08);
    --sidebar-active: rgba(139, 124, 246, 0.15);
    --cyan: #00f0ff;
    --violet: #8b7cf6;
    --gold: #f6c453;
    --green: #00ff9c;
    --text: #e7ecf7;
    --text-dim: #8892b0;
    --border: rgba(255,255,255,0.06);
}

body {
    opacity: 1;
    transition: opacity 0.3s ease-in-out;
}

.mobile-menu-toggle {
    display: none;
}

.sidebar-enhanced {
    min-height: 100vh;
    background: linear-gradient(180deg, #0a0e1a 0%, #11172a 40%, #0d1224 100%);
    border-right: 1px solid rgba(0, 240, 255, 0.06);
    padding: 1.25rem 1rem;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 4px 0 30px rgba(0, 0, 0, 0.4);
}
.sidebar-enhanced::-webkit-scrollbar { width: 4px; }
.sidebar-enhanced::-webkit-scrollbar-track { background: transparent; }
.sidebar-enhanced::-webkit-scrollbar-thumb { background: rgba(139, 124, 246, 0.3); border-radius: 10px; }

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    margin-bottom: 1.5rem;
    text-decoration: none;
    transition: transform 0.3s ease;
}
.sidebar-brand:hover { transform: translateX(3px); }
.sidebar-brand .brand-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--cyan), var(--violet));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #0a0e1a;
    box-shadow: 0 0 20px rgba(0, 240, 255, 0.2);
    transition: all 0.3s ease;
    flex-shrink: 0;
}
.sidebar-brand:hover .brand-icon {
    transform: rotate(-10deg) scale(1.05);
    box-shadow: 0 0 30px rgba(0, 240, 255, 0.35);
}
.sidebar-brand .brand-text {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.3rem;
    font-weight: 800;
    letter-spacing: 2px;
    background: linear-gradient(135deg, var(--cyan), var(--violet));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.sidebar-brand .brand-badge {
    font-size: 0.5rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    background: rgba(0, 240, 255, 0.12);
    color: var(--cyan);
    padding: 0.1rem 0.5rem;
    border-radius: 10px;
    border: 1px solid rgba(0, 240, 255, 0.15);
    -webkit-text-fill-color: var(--cyan);
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 14px;
    padding: 0.6rem 0.7rem;
    margin-bottom: 1.25rem;
    transition: all 0.3s ease;
    cursor: pointer;
    min-height: 64px;
}
.sidebar-user:hover {
    border-color: rgba(139, 124, 246, 0.2);
    background: rgba(255, 255, 255, 0.05);
    transform: translateY(-2px);
}
.sidebar-user .user-avatar-wrap { position: relative; flex-shrink: 0; }
.sidebar-user .user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid rgba(0, 240, 255, 0.12);
    background: linear-gradient(135deg, var(--cyan), var(--violet));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 700;
    color: #0a0e1a;
}
.sidebar-user .online-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 11px;
    height: 11px;
    background: var(--green);
    border-radius: 50%;
    border: 2px solid #0a0e1a;
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(0.85); }
}
.sidebar-user .user-info { flex: 1; min-width: 0; overflow: hidden; }
.sidebar-user .user-name {
    font-weight: 600;
    color: #fff;
    font-size: 0.82rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sidebar-user .user-role {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-wrap: wrap;
}
.sidebar-user .user-role .role-badge {
    padding: 0.05rem 0.4rem;
    border-radius: 4px;
    font-size: 0.45rem;
    font-weight: 700;
    background: rgba(0, 240, 255, 0.1);
    color: var(--cyan);
    border: 1px solid rgba(0, 240, 255, 0.1);
    text-transform: uppercase;
}
.sidebar-user .user-role .rank-text {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 0.55rem;
    color: var(--gold);
}
.sidebar-user .user-rank {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.5rem;
    color: var(--text-dim);
}
.sidebar-user .user-rank .rank-level { color: var(--gold); font-weight: 600; }

.sidebar-nav { flex: 1; margin: 0.5rem 0 1rem; }
.sidebar-nav .nav-label {
    font-size: 0.55rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: rgba(255, 255, 255, 0.2);
    padding: 0.4rem 0.75rem;
    margin-top: 0.5rem;
    font-weight: 600;
}
.sidebar-nav a {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.8rem;
    border-radius: 10px;
    color: var(--text-dim);
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 500;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid transparent;
}
.sidebar-nav a .nav-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    transition: all 0.3s ease;
    flex-shrink: 0;
    background: rgba(255, 255, 255, 0.03);
}
.sidebar-nav a .nav-text { flex: 1; font-size: 0.82rem; }
.sidebar-nav a .nav-badge {
    font-size: 0.55rem;
    padding: 0.05rem 0.45rem;
    border-radius: 8px;
    background: rgba(0, 240, 255, 0.1);
    color: var(--cyan);
}
.sidebar-nav a .nav-arrow { font-size: 0.55rem; opacity: 0.2; transition: all 0.3s ease; }
.sidebar-nav a:hover {
    color: #fff;
    background: var(--sidebar-hover);
    transform: translateX(3px);
    border-color: rgba(255, 255, 255, 0.03);
}
.sidebar-nav a:hover .nav-icon {
    background: rgba(139, 124, 246, 0.12);
    color: var(--cyan);
    transform: scale(1.05);
}
.sidebar-nav a:hover .nav-arrow { opacity: 0.6; transform: translateX(3px); }
.sidebar-nav a.active {
    color: var(--cyan);
    background: var(--sidebar-active);
    border-color: rgba(0, 240, 255, 0.08);
}
.sidebar-nav a.active .nav-icon {
    background: rgba(0, 240, 255, 0.12);
    color: var(--cyan);
}

.sidebar-logout {
    margin-top: auto;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.04);
}
.sidebar-logout .logout-btn {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.5rem 0.8rem;
    border-radius: 10px;
    color: #ef4444;
    font-size: 0.82rem;
    font-weight: 500;
    transition: all 0.25s ease;
    cursor: pointer;
    border: 1px solid transparent;
    background: transparent;
    width: 100%;
}
.sidebar-logout .logout-btn .nav-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
    background: rgba(239, 68, 68, 0.08);
    color: #ef4444;
    transition: all 0.3s ease;
}
.sidebar-logout .logout-btn:hover {
    background: rgba(239, 68, 68, 0.08);
    color: #ff6b6b;
    transform: translateX(3px);
    border-color: rgba(239, 68, 68, 0.1);
}
.sidebar-logout .logout-btn:hover .nav-icon {
    background: rgba(239, 68, 68, 0.15);
    transform: scale(1.05);
}
.sidebar-footer {
    margin-top: 0.5rem;
    padding: 0.4rem 0.75rem;
    font-size: 0.5rem;
    color: rgba(255, 255, 255, 0.15);
    text-align: center;
}

.logout-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(12px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}
.logout-modal-overlay.active { display: flex; }
.logout-modal {
    background: linear-gradient(160deg, #1a2140, #0a0e1a);
    border: 1px solid rgba(239, 68, 68, 0.15);
    border-radius: 20px;
    padding: 2rem;
    max-width: 420px;
    width: 100%;
    text-align: center;
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.6);
}
.logout-modal .modal-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.1);
    border: 2px solid rgba(239, 68, 68, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: #ef4444;
    margin: 0 auto 1rem;
}
.logout-modal h3 {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.1rem;
    color: #fff;
    margin-bottom: 0.4rem;
}
.logout-modal p {
    color: var(--text-dim);
    font-size: 0.85rem;
    margin-bottom: 1.25rem;
    line-height: 1.5;
}
.logout-modal .modal-actions { display: flex; gap: 0.6rem; justify-content: center; }
.logout-modal .btn-cancel {
    padding: 0.55rem 1.5rem;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: var(--text-dim);
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
}
.logout-modal .btn-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transform: translateY(-2px);
}
.logout-modal .btn-logout {
    padding: 0.55rem 1.5rem;
    border-radius: 10px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    border: none;
    color: #fff;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}
.logout-modal .btn-logout:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(239, 68, 68, 0.4);
}

@media (max-width: 768px) {
    .mobile-menu-toggle {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        position: fixed;
        top: 10px;
        left: 10px;
        z-index: 9999;
        background: rgba(10,14,26,0.92);
        border: 1px solid rgba(0,240,255,0.15);
        border-radius: 10px;
        color: var(--cyan);
        padding: 8px 12px;
        font-size: 1.2rem;
        cursor: pointer;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .mobile-menu-toggle:hover {
        background: rgba(0,240,255,0.1);
    }
    
    .sidebar-enhanced {
        position: fixed;
        top: 0;
        left: -300px;
        width: 280px;
        height: 100vh;
        z-index: 9998;
        border-right: 1px solid rgba(0,240,255,0.1);
        box-shadow: 0 0 40px rgba(0,0,0,0.8);
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 1rem 0.75rem;
        border-radius: 0;
    }
    
    .sidebar-enhanced.open {
        left: 0;
    }
    
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 9997;
        backdrop-filter: blur(4px);
    }
    .sidebar-overlay.active {
        display: block;
    }
    
    .sidebar-brand .brand-text { font-size: 1rem; }
    .sidebar-brand .brand-icon { width: 36px; height: 36px; font-size: 1rem; }
    .sidebar-user .user-avatar { width: 36px; height: 36px; font-size: 0.8rem; }
    .sidebar-user .user-name { font-size: 0.75rem; }
    .sidebar-nav a { padding: 0.4rem 0.6rem; font-size: 0.8rem; }
    .sidebar-nav a .nav-icon { width: 28px; height: 28px; font-size: 0.75rem; }
}

@media (max-width: 576px) {
    .mobile-menu-toggle {
        top: 8px;
        left: 8px;
        padding: 6px 10px;
        font-size: 1rem;
    }
    
    .sidebar-enhanced {
        width: 260px;
        left: -260px;
        padding: 0.75rem 0.5rem;
    }
    
    .sidebar-brand .brand-text { font-size: 0.85rem; letter-spacing: 1px; }
    .sidebar-brand .brand-icon { width: 32px; height: 32px; font-size: 0.9rem; }
    .sidebar-user { padding: 0.4rem 0.5rem; gap: 0.4rem; min-height: 54px; }
    .sidebar-user .user-avatar { width: 30px; height: 30px; font-size: 0.65rem; }
    .sidebar-user .user-name { font-size: 0.65rem; }
    .sidebar-user .user-role .role-badge { font-size: 0.4rem; padding: 0.03rem 0.3rem; }
    .sidebar-user .user-role .rank-text { font-size: 0.45rem; }
    .sidebar-nav a { padding: 0.3rem 0.5rem; font-size: 0.7rem; gap: 0.4rem; }
    .sidebar-nav a .nav-icon { width: 24px; height: 24px; font-size: 0.65rem; }
    .sidebar-nav .nav-label { font-size: 0.45rem; padding: 0.25rem 0.5rem; }
    .sidebar-logout .logout-btn { padding: 0.3rem 0.5rem; font-size: 0.7rem; }
    .sidebar-logout .logout-btn .nav-icon { width: 24px; height: 24px; font-size: 0.65rem; }
}
</style>
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
            
            <a href="dashboard.php" class="sidebar-brand">
                <div class="brand-icon"><i class="fas fa-brain"></i></div>
                <div>
                    <span class="brand-text">KLPS</span>
                    <span class="brand-badge">v2.0</span>
                </div>
            </a>

            <!-- User Profile - Notification Bell REMOVED -->
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
                        <span class="rank-text"><i class="fas <?= e($rankIcon) ?>"></i> <?= e($rankTitle) ?></span>
                    </div>
                    <div class="user-rank">
                        <span class="rank-level">Level <?= (int)$level ?></span>
                        <span>·</span>
                        <span><?= (int)$xp ?> XP</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-label">Main</div>
                
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-gauge-high"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="users.php" class="<?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-users"></i></span>
                        <span class="nav-text">Users</span>
                    </a>
                    <a href="knowledge.php" class="<?= basename($_SERVER['PHP_SELF']) === 'knowledge.php' ? 'active' : '' ?>">
                        <span class="nav-icon"><i class="fas fa-book"></i></span>
                        <span class="nav-text">Knowledge</span>
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

            <div class="sidebar-logout">
                <button class="logout-btn" onclick="openLogoutModal()">
                    <span class="nav-icon"><i class="fas fa-right-from-bracket"></i></span>
                    <span class="nav-text">Sign Out</span>
                    <i class="fas fa-chevron-right nav-arrow"></i>
                </button>
                <div class="sidebar-footer">&copy; <?= date('Y') ?> KLPS &bull; v2.0</div>
            </div>
        </aside>

        <main class="col-md-10 p-4">