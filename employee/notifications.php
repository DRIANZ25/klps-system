<?php
$page_title = 'Notifications';
$page_css = ['employee-notifications.css'];
$page_js  = ['employee-notifications.js'];
require_once __DIR__ . '/../partials/header.php';

$currentUserId = $_SESSION['user']['users_id'] ?? 0;

if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$currentUserId]);
        $_SESSION['success'] = 'All notifications marked as read.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to mark all as read.';
    }
    redirect('notifications.php');
}

if (isset($_GET['clear_read'])) {
    try {
        $stmt = db()->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1');
        $stmt->execute([$currentUserId]);
        $_SESSION['success'] = 'Cleared all read notifications.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to clear read notifications.';
    }
    redirect('notifications.php');
}

if (isset($_GET['read']) && isset($_GET['id'])) {
    $id = id_param($_GET['id'] ?? '');
    try {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE notifications_id = ? AND user_id = ?');
        $stmt->execute([$id, $currentUserId]);

        $stmt = db()->prepare('SELECT link FROM notifications WHERE notifications_id = ?');
        $stmt->execute([$id]);
        $notif = $stmt->fetch();
        if ($notif && $notif['link']) redirect($notif['link']);
    } catch (PDOException $e) {}
    redirect('notifications.php');
}

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = id_param($_GET['id'] ?? '');
    try {
        $stmt = db()->prepare('DELETE FROM notifications WHERE notifications_id = ? AND user_id = ?');
        $stmt->execute([$id, $currentUserId]);
        $_SESSION['success'] = 'Notification deleted.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete notification.';
    }
    redirect('notifications.php');
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$stmt->execute([$currentUserId]);
$totalNotifications = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalNotifications / $perPage));

$stmt = db()->prepare('
    SELECT n.*, u.full_name AS from_user_name, u.profile_picture AS from_user_pic
    FROM notifications n
    LEFT JOIN users u ON u.users_id = n.from_user_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$currentUserId, $perPage, $offset]);
$notifications = $stmt->fetchAll();

$stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$currentUserId]);
$unreadCount = (int)$stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND DATE(created_at) = CURDATE()');
$stmt->execute([$currentUserId]);
$todayCount = (int)$stmt->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
$stmt->execute([$currentUserId]);
$thisWeekCount = (int)$stmt->fetchColumn();

$stmt = db()->prepare('
    SELECT type, COUNT(*) AS c
    FROM notifications
    WHERE user_id = ?
    GROUP BY type
');
$stmt->execute([$currentUserId]);
$typeCounts = ['vote' => 0, 'save' => 0, 'comment' => 0, 'mention' => 0, 'system' => 0];
foreach ($stmt->fetchAll() as $row) {
    if (isset($typeCounts[$row['type']])) $typeCounts[$row['type']] = (int)$row['c'];
}

function getNotificationMeta($type) {
    $map = [
        'vote'    => ['icon' => 'fa-thumbs-up',   'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.14)',  'label' => 'Vote'],
        'save'    => ['icon' => 'fa-bookmark',    'color' => '#f6c453', 'bg' => 'rgba(246,196,83,0.14)',  'label' => 'Save'],
        'comment' => ['icon' => 'fa-comment',     'color' => '#4fd7e8', 'bg' => 'rgba(79,215,232,0.14)',  'label' => 'Comment'],
        'mention' => ['icon' => 'fa-at',          'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.14)',  'label' => 'Mention'],
        'system'  => ['icon' => 'fa-info-circle', 'color' => '#8892b0', 'bg' => 'rgba(136,146,176,0.14)', 'label' => 'System'],
    ];
    return $map[$type] ?? $map['system'];
}

function timeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60)        return 'just now';
    if ($diff < 3600)      return floor($diff / 60) . ' min ago';
    if ($diff < 86400)     return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800)    return 'yesterday';
    if ($diff < 604800)    return floor($diff / 86400) . ' days ago';
    return date('M j, Y', strtotime($timestamp));
}
?>



<div class="notifications-page">
    <div class="nt-bg">
        <div class="grid"></div>
        <div class="aurora a1"></div>
        <div class="aurora a2"></div>
    </div>
    <div class="nt-scenery" id="ntScenery"></div>

    <!-- Toasts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="nt-toast success" id="ntToast">
            <i class="fas fa-circle-check"></i>
            <span><?= e($_SESSION['success']); unset($_SESSION['success']); ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="nt-toast error" id="ntToast">
            <i class="fas fa-circle-exclamation"></i>
            <span><?= e($_SESSION['error']); unset($_SESSION['error']); ?></span>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="nt-header">
        <div class="nt-header-left">
            <div class="nt-ring">
                <div class="inner">
                    <div class="num"><?= (int)$unreadCount ?></div>
                    <div class="lbl">Unread</div>
                </div>
            </div>
            <div>
                <h1><i class="fas fa-bell"></i> Notifications</h1>
                <p>Everything that happened around your contributions and activity.</p>
            </div>
        </div>
        <div class="nt-header-actions">
            <?php if ($unreadCount > 0): ?>
                <a href="notifications.php?mark_all_read=1" class="nt-btn primary"
                   onclick="return confirm('Mark all notifications as read?')">
                    <i class="fas fa-check-double"></i> Mark all read
                </a>
            <?php endif; ?>
            <?php if ($totalNotifications > 0): ?>
                <a href="notifications.php?clear_read=1" class="nt-btn danger"
                   onclick="return confirm('Delete all read notifications? This cannot be undone.')">
                    <i class="fas fa-broom"></i> Clear read
                </a>
            <?php endif; ?>
            <a href="dashboard.php" class="nt-btn ghost">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Stat chips -->
    <div class="nt-stats">
        <div class="nt-chip">
            <div class="nt-chip-icon total"><i class="fas fa-bell"></i></div>
            <div class="nt-chip-info">
                <div class="nt-chip-value"><?= (int)$totalNotifications ?></div>
                <div class="nt-chip-label">Total</div>
            </div>
        </div>
        <div class="nt-chip">
            <div class="nt-chip-icon unread"><i class="fas fa-envelope"></i></div>
            <div class="nt-chip-info">
                <div class="nt-chip-value"><?= (int)$unreadCount ?></div>
                <div class="nt-chip-label">Unread</div>
            </div>
        </div>
        <div class="nt-chip">
            <div class="nt-chip-icon today"><i class="fas fa-sun"></i></div>
            <div class="nt-chip-info">
                <div class="nt-chip-value"><?= (int)$todayCount ?></div>
                <div class="nt-chip-label">Today</div>
            </div>
        </div>
        <div class="nt-chip">
            <div class="nt-chip-icon week"><i class="fas fa-chart-line"></i></div>
            <div class="nt-chip-info">
                <div class="nt-chip-value"><?= (int)$thisWeekCount ?></div>
                <div class="nt-chip-label">This Week</div>
            </div>
        </div>
    </div>

    <!-- Filter tabs -->
    <div class="nt-tabs" id="ntTabs">
        <button type="button" class="nt-tab active" data-filter="all">
            <i class="fas fa-layer-group"></i> All
            <span class="tab-count"><?= (int)$totalNotifications ?></span>
        </button>
        <button type="button" class="nt-tab" data-filter="unread">
            <i class="fas fa-circle"></i> Unread
            <span class="tab-count"><?= (int)$unreadCount ?></span>
        </button>
        <button type="button" class="nt-tab" data-filter="vote">
            <i class="fas fa-thumbs-up"></i> Votes
            <span class="tab-count"><?= (int)$typeCounts['vote'] ?></span>
        </button>
        <button type="button" class="nt-tab" data-filter="save">
            <i class="fas fa-bookmark"></i> Saves
            <span class="tab-count"><?= (int)$typeCounts['save'] ?></span>
        </button>
        <button type="button" class="nt-tab" data-filter="comment">
            <i class="fas fa-comment"></i> Comments
            <span class="tab-count"><?= (int)$typeCounts['comment'] ?></span>
        </button>
    </div>

    <!-- List -->
    <?php if (empty($notifications)): ?>
        <div class="nt-empty">
            <div class="empty-icon"><i class="fas fa-bell-slash"></i></div>
            <h3>You're all caught up</h3>
            <p>No notifications to show. When someone likes, saves, or comments on your articles, you'll see them here.</p>
            <a href="knowledge.php" class="cta"><i class="fas fa-book-open"></i> Browse Knowledge Library</a>
        </div>
    <?php else: ?>
        <div class="nt-list" id="ntList">
            <?php foreach ($notifications as $i => $notif):
                $meta = getNotificationMeta($notif['type']);
                $isUnread = !$notif['is_read'];
                $fromPic = !empty($notif['from_user_pic']) ? '../uploads/profiles/' . $notif['from_user_pic'] : '';
                $profilePicExists = $fromPic && file_exists(__DIR__ . '/../uploads/profiles/' . $notif['from_user_pic']);
            ?>
                <div class="nt-item <?= $isUnread ? 'unread' : '' ?>"
                     data-type="<?= e($notif['type']) ?>"
                     data-unread="<?= $isUnread ? '1' : '0' ?>"
                     style="animation-delay: <?= min($i * 0.03, 0.5) ?>s;">

                    <div class="nt-icon-wrap" style="background: <?= $meta['bg'] ?>; color: <?= $meta['color'] ?>;">
                        <i class="fas <?= $meta['icon'] ?>"></i>
                        <?php if ($isUnread): ?><span class="dot"></span><?php endif; ?>
                    </div>

                    <div class="nt-content">
                        <div class="nt-message">
                            <?= e($notif['message']) ?>
                        </div>
                        <div class="nt-meta">
                            <span class="meta-chip"><i class="far fa-clock"></i> <?= e(timeAgo($notif['created_at'])) ?></span>
                            <?php if (!empty($notif['from_user_name'])): ?>
                                <span class="meta-chip"><i class="fas fa-user"></i> <?= e($notif['from_user_name']) ?></span>
                            <?php endif; ?>
                            <span class="type-badge" style="background: <?= $meta['bg'] ?>; color: <?= $meta['color'] ?>;">
                                <?= e($meta['label']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="nt-actions">
                        <?php if (!empty($notif['link'])): ?>
                <a href="notifications.php?read=1&id=<?= e($notif['notifications_id']) ?>"
                               class="nt-action read" title="Mark read & open">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php elseif ($isUnread): ?>
                <a href="notifications.php?read=1&id=<?= e($notif['notifications_id']) ?>"
                               class="nt-action read" title="Mark as read">
                                <i class="fas fa-check"></i>
                            </a>
                        <?php endif; ?>
                <a href="notifications.php?delete=1&id=<?= e($notif['notifications_id']) ?>"
                           class="nt-action delete"
                           onclick="return confirm('Delete this notification?');"
                           title="Delete">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="nt-pagination">
            <?php if ($page > 1): ?>
                <a class="nt-page" href="?page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <span class="nt-page disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            if ($start > 1) {
                echo '<a class="nt-page" href="?page=1">1</a>';
                if ($start > 2) echo '<span class="nt-page disabled">…</span>';
            }
            for ($p = $start; $p <= $end; $p++):
            ?>
                <?php if ($p == $page): ?>
                    <span class="nt-page active"><?= $p ?></span>
                <?php else: ?>
                    <a class="nt-page" href="?page=<?= $p ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1) echo '<span class="nt-page disabled">…</span>'; ?>
                <a class="nt-page" href="?page=<?= $totalPages ?>"><?= $totalPages ?></a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a class="nt-page" href="?page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <span class="nt-page disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>



<?php require_once __DIR__ . '/../partials/footer.php'; ?>