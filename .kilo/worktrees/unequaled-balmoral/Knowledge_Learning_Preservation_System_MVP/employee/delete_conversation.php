<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
$currentUserId = $_SESSION['user']['id'] ?? 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

try {
    // Check if conversation belongs to user
    $stmt = db()->prepare('SELECT id FROM ai_conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $currentUserId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Not found or permission denied']);
        exit;
    }
    
    // Delete conversation (cascade will delete messages)
    $stmt = db()->prepare('DELETE FROM ai_conversations WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $currentUserId]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>