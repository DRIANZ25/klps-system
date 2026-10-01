<?php
$page_title = 'All Notifications';
require_once __DIR__ . '/../partials/header.php';

$currentUserId = $_SESSION['user']['id'] ?? 0;

// Mark all as read if requested
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

// Mark single notification as read
if (isset($_GET['read']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $currentUserId]);
        // Redirect to the link
        $stmt = db()->prepare('SELECT link FROM notifications WHERE id = ?');
        $stmt->execute([$id]);
        $notif = $stmt->fetch();
        if ($notif && $notif['link']) {
            redirect($notif['link']);
        }
    } catch (PDOException $e) {}
    redirect('notifications.php');
}

// Delete notification
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = db()->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $currentUserId]);
        $_SESSION['success'] = 'Notification deleted.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Failed to delete notification.';
    }
    redirect('notifications.php');
}

// Get all notifications with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$stmt->execute([$currentUserId]);
$totalNotifications = (int)$stmt->fetchColumn();
$totalPages = ceil($totalNotifications / $perPage);

// Get notifications
$stmt = db()->prepare('
    SELECT n.*, u.full_name as from_user_name 
    FROM notifications n
    LEFT JOIN users u ON u.id = n.from_user_id
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC 
    LIMIT ? OFFSET ?
');
$stmt->execute([$currentUserId, $perPage, $offset]);
$notifications = $stmt->fetchAll();

// Get counts
$stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$currentUserId]);
$unreadCount = (int)$stmt->fetchColumn();

function getNotificationIcon($type) {
    $icons = [
        'vote' => ['icon' => 'fa-thumbs-up', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.15)'],
        'save' => ['icon' => 'fa-bookmark', 'color' => '#f6c453', 'bg' => 'rgba(246,196,83,0.15)'],
        'comment' => ['icon' => 'fa-comment', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.15)'],
        'mention' => ['icon' => 'fa-at', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.15)'],
        'system' => ['icon' => 'fa-info-circle', 'color' => '#8892b0', 'bg' => 'rgba(136,146,176,0.15)'],
    ];
    return $icons[$type] ?? $icons['system'];
}
?>
<style>
.notifications-page {
    background: radial-gradient(ellipse at 20% -10%, #1a2140 0%, #03050a 45%, #050710 100%);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(0,240,255,0.08);
    min-height: 500px;
}

.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
}

.notifications-header h1 {
    font-family: 'Orbitron', sans-serif;
    font-size: 1.8rem;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.notifications-header h1 i {
    color: #f6c453;
}

.notifications-header .header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.notifications-header .header-actions a {
    padding: 0.5rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.notifications-header .header-actions .btn-mark-read {
    background: rgba(0,240,255,0.1);
    border: 1px solid rgba(0,240,255,0.15);
    color: #00f0ff;
}

.notifications-header .header-actions .btn-mark-read:hover {
    background: rgba(0,240,255,0.2);
    transform: translateY(-2px);
}

.notifications-header .header-actions .btn-back {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.06);
    color: #8892b0;
}

.notifications-header .header-actions .btn-back:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
    transform: translateY(-2px);
}

.notifications-stats {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    padding: 1rem 1.5rem;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.04);
    border-radius: 12px;
}

.notifications-stats .stat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #8892b0;
}

.notifications-stats .stat strong {
    color: #fff;
    font-size: 1rem;
}

.notifications-stats .stat .unread-badge {
    background: #ef4444;
    color: #fff;
    padding: 0.05rem 0.5rem;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 700;
}

/* Notification List */
.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.notification-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.25rem;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.04);
    border-radius: 12px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.notification-item:hover {
    background: rgba(255,255,255,0.04);
    border-color: rgba(255,255,255,0.08);
    transform: translateX(4px);
}

.notification-item.unread {
    background: rgba(0,240,255,0.04);
    border-left: 3px solid #00f0ff;
}

.notification-item .notif-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.notification-item .notif-content {
    flex: 1;
    min-width: 0;
}

.notification-item .notif-content .notif-message {
    font-size: 0.95rem;
    color: #e7ecf7;
    line-height: 1.5;
    word-wrap: break-word;
}

.notification-item .notif-content .notif-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 0.3rem;
    flex-wrap: wrap;
}

.notification-item .notif-content .notif-meta .notif-time {
    font-size: 0.75rem;
    color: #8892b0;
}

.notification-item .notif-content .notif-meta .notif-from {
    font-size: 0.75rem;
    color: #8892b0;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.notification-item .notif-content .notif-meta .notif-from i {
    font-size: 0.6rem;
}

.notification-item .notif-actions {
    display: flex;
    gap: 0.3rem;
    flex-shrink: 0;
}

.notification-item .notif-actions .action-btn {
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
    border: 1px solid rgba(255,255,255,0.04);
    background: rgba(255,255,255,0.02);
    color: #8892b0;
    text-decoration: none;
    font-size: 0.7rem;
    transition: all 0.2s ease;
    cursor: pointer;
}

.notification-item .notif-actions .action-btn:hover {
    background: rgba(255,255,255,0.06);
    color: #fff;
}

.notification-item .notif-actions .action-btn.delete-btn:hover {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
}

.notification-item .notif-status {
    font-size: 0.6rem;
    padding: 0.1rem 0.5rem;
    border-radius: 10px;
    background: rgba(0,240,255,0.08);
    color: #00f0ff;
    flex-shrink: 0;
    margin-top: 0.2rem;
}

/* Empty State */
.notifications-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: #8892b0;
}

.notifications-empty i {
    font-size: 4rem;
    color: rgba(255,255,255,0.05);
    display: block;
    margin-bottom: 1.5rem;
}

.notifications-empty h3 {
    color: #fff;
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    font-family: 'Orbitron', sans-serif;
}

.notifications-empty p {
    font-size: 0.95rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.pagination a, .pagination span {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.3s ease;
}

.pagination a {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    color: #8892b0;
}

.pagination a:hover {
    background: rgba(0,240,255,0.08);
    border-color: rgba(0,240,255,0.15);
    color: #00f0ff;
}

.pagination .active {
    background: linear-gradient(135deg, rgba(0,240,255,0.15), rgba(139,124,246,0.15));
    border: 1px solid rgba(0,240,255,0.2);
    color: #00f0ff;
}

.pagination .disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .notifications-page { padding: 1rem; }
    .notifications-header h1 { font-size: 1.3rem; }
    .notifications-header .header-actions a { font-size: 0.75rem; padding: 0.4rem 0.8rem; }
    .notification-item { flex-wrap: wrap; padding: 0.75rem; }
    .notification-item .notif-icon-wrap { width: 36px; height: 36px; font-size: 0.9rem; }
    .notification-item .notif-content .notif-message { font-size: 0.85rem; }
    .notifications-stats { gap: 1rem; padding: 0.75rem 1rem; }
    .notifications-stats .stat { font-size: 0.75rem; }
}

@media (max-width: 576px) {
    .notifications-page { padding: 0.75rem; }
    .notification-item { flex-direction: column; align-items: stretch; }
    .notification-item .notif-actions { justify-content: flex-end; }
}
</style>

<div class="notifications-page">
    <div class="notifications-header">
        <h1><i class="fas fa-bell"></i> Notifications</h1>
        <div class="header-actions">
            <a href="notifications.php?mark_all_read=1" class="btn-mark-read" onclick="return confirm('Mark all notifications as read?')">
                <i class="fas fa-check-double"></i> Mark all read
            </a>
            <a href="knowledge.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="notifications-stats">
        <div class="stat">
            <i class="fas fa-bell"></i>
            Total: <strong><?= $totalNotifications ?></strong>
        </div>
        <div class="stat">
            <i class="fas fa-envelope-open"></i>
            Unread: <strong><?= $unreadCount ?></strong>
            <?php if ($unreadCount > 0): ?>
                <span class="unread-badge"><?= $unreadCount ?></span>
            <?php endif; ?>
        </div>
        <div class="stat">
            <i class="fas fa-book"></i>
            Page: <strong><?= $page ?> / <?= $totalPages ?: 1 ?></strong>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="notifications-empty">
            <i class="fas fa-bell-slash"></i>
            <h3>No Notifications</h3>
            <p>You're all caught up! When someone likes, saves, or comments on your articles, you'll see them here.</p>
            <a href="knowledge.php" style="display:inline-block;margin-top:1rem;padding:0.6rem 1.5rem;background:linear-gradient(135deg,#00f0ff,#8b7cf6);border:none;border-radius:10px;color:#0a0e1a;font-weight:700;text-decoration:none;">
                <i class="fas fa-book-open"></i> Browse Knowledge
            </a>
        </div>
    <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notif):
                $icon = getNotificationIcon($notif['type']);
                $isUnread = !$notif['is_read'];
            ?>
                <div class="notification-item <?= $isUnread ? 'unread' : '' ?>">
                    <div class="notif-icon-wrap" style="background:<?= $icon['bg'] ?>;color:<?= $icon['color'] ?>;">
                        <i class="fas <?= $icon['icon'] ?>"></i>
                    </div>
                    <div class="notif-content">
                        <div class="notif-message">
                            <?= e($notif['message']) ?>
                        </div>
                        <div class="notif-meta">
                            <span class="notif-time">
                                <i class="fas fa-clock"></i> <?= date('M d, Y • h:i A', strtotime($notif['created_at'])) ?>
                            </span>
                            <?php if ($notif['from_user_name']): ?>
                                <span class="notif-from">
                                    <i class="fas fa-user"></i> <?= e($notif['from_user_name']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($isUnread): ?>
                                <span class="notif-status"><i class="fas fa-circle" style="font-size:0.5rem;color:#00f0ff;"></i> New</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="notif-actions">
                        <?php if ($notif['link']): ?>
                            <a href="notifications.php?read=1&id=<?= $notif['id'] ?>" class="action-btn" title="Read and view">
                                <i class="fas fa-eye"></i>
                            </a>
                        <?php endif; ?>
                        <a href="notifications.php?delete=1&id=<?= $notif['id'] ?>" class="action-btn delete-btn" onclick="return confirm('Delete this notification?')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>">Next <i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>