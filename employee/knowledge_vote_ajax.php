<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

$id = id_param($_POST['id'] ?? '');
$vote = $_POST['vote'] ?? '';
$currentUserId = $_SESSION['user']['users_id'] ?? 0;

if ($id === '' || !in_array($vote, ['helpful', 'not_helpful']) || $currentUserId === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    $stmt = db()->prepare('SELECT knowledge_id FROM knowledge WHERE knowledge_id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Article not found']);
        exit;
    }

    $stmt = db()->prepare('SELECT vote FROM knowledge_votes WHERE knowledge_id = ? AND user_id = ?');
    $stmt->execute([$id, $currentUserId]);
    $existingVote = $stmt->fetch();
    
    $action = '';
    if ($existingVote) {
        if ($existingVote['vote'] === $vote) {
            $stmt = db()->prepare('DELETE FROM knowledge_votes WHERE knowledge_id = ? AND user_id = ?');
            $stmt->execute([$id, $currentUserId]);
            $action = 'removed';
        } else {
            $stmt = db()->prepare('UPDATE knowledge_votes SET vote = ? WHERE knowledge_id = ? AND user_id = ?');
            $stmt->execute([$vote, $id, $currentUserId]);
            $action = 'updated';
        }
    } else {
        $stmt = db()->prepare('INSERT INTO knowledge_votes (knowledge_votes_id, knowledge_id, user_id, vote) VALUES (?, ?, ?, ?)');
        $stmt->execute([new_id('knowledge_votes'), $id, $currentUserId, $vote]);
        $action = 'added';
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_votes WHERE knowledge_id = ? AND vote = "helpful"');
    $stmt->execute([$id]);
    $helpful = (int)$stmt->fetchColumn();
    $stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_votes WHERE knowledge_id = ? AND vote = "not_helpful"');
    $stmt->execute([$id]);
    $notHelpful = (int)$stmt->fetchColumn();
    $stmt = db()->prepare('UPDATE knowledge SET helpful_count = ?, not_helpful_count = ? WHERE knowledge_id = ?');
    $stmt->execute([$helpful, $notHelpful, $id]);

    echo json_encode(['success' => true, 'action' => $action, 'helpful' => $helpful, 'not_helpful' => $notHelpful]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>