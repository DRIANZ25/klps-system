<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

$currentUserId = $_SESSION['user']['users_id'] ?? 0;

if ($currentUserId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

try {
    if (isset($_GET['all']) && $_GET['all'] == 1) {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }
    if (isset($_GET['id'])) {
        $id = id_param($_GET['id'] ?? '');
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE notifications_id = ? AND user_id = ?');
        $stmt->execute([$id, $currentUserId]);
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>