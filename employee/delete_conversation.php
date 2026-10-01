<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

$id = id_param($_GET['id'] ?? $_POST['id'] ?? '');
// User ids are prefixed strings ('U001'). An int cast here collapses the id to 0,
// so every delete failed the "Not logged in" guard below.
$currentUserId = id_param($_SESSION['user']['users_id'] ?? '');

if ($id === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation ID']);
    exit;
}

if ($currentUserId === '') {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

try {
    $stmt = db()->prepare('SELECT ai_conversations_id FROM ai_conversations WHERE ai_conversations_id = ? AND user_id = ?');
    $stmt->execute([$id, $currentUserId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Conversation not found or permission denied']);
        exit;
    }

    $stmt = db()->prepare('DELETE FROM ai_conversations WHERE ai_conversations_id = ? AND user_id = ?');
    $stmt->execute([$id, $currentUserId]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Delete conversation failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}