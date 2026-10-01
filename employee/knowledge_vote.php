<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = id_param($_GET['id'] ?? '');
$vote = $_GET['vote'] ?? '';
$back = $_GET['back'] ?? 'knowledge.php';

if ($id === '' || !in_array($vote, ['helpful', 'not_helpful'])) {
    $_SESSION['error'] = 'Invalid vote request.';
    redirect('knowledge.php');
}

$currentUserId = $_SESSION['user']['users_id'] ?? 0;

try {
    $stmt = db()->prepare('SELECT knowledge_id FROM knowledge WHERE knowledge_id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Article not found.';
        redirect('knowledge.php');
    }

    $stmt = db()->prepare('SELECT vote FROM knowledge_votes WHERE knowledge_id = ? AND user_id = ?');
    $stmt->execute([$id, $currentUserId]);
    $existingVote = $stmt->fetch();

    if ($existingVote) {
        if ($existingVote['vote'] === $vote) {
            $stmt = db()->prepare('DELETE FROM knowledge_votes WHERE knowledge_id = ? AND user_id = ?');
            $stmt->execute([$id, $currentUserId]);
            $_SESSION['success'] = 'Vote removed.';
        } else {
            $stmt = db()->prepare('UPDATE knowledge_votes SET vote = ?, updated_at = NOW() WHERE knowledge_id = ? AND user_id = ?');
            $stmt->execute([$vote, $id, $currentUserId]);
            $_SESSION['success'] = 'Vote updated!';
        }
    } else {
        $stmt = db()->prepare('INSERT INTO knowledge_votes (knowledge_votes_id, knowledge_id, user_id, vote) VALUES (?, ?, ?, ?)');
        $stmt->execute([new_id('knowledge_votes'), $id, $currentUserId, $vote]);
        $_SESSION['success'] = 'Vote recorded!';
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_votes WHERE knowledge_id = ? AND vote = "helpful"');
    $stmt->execute([$id]);
    $helpful = (int)$stmt->fetchColumn();
    $stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_votes WHERE knowledge_id = ? AND vote = "not_helpful"');
    $stmt->execute([$id]);
    $notHelpful = (int)$stmt->fetchColumn();
    $stmt = db()->prepare('UPDATE knowledge SET helpful_count = ?, not_helpful_count = ? WHERE knowledge_id = ?');
    $stmt->execute([$helpful, $notHelpful, $id]);

    if (!empty($back)) {
        if (strpos($back, 'http') === 0) redirect($back);
        redirect('knowledge.php?' . ltrim($back, '?'));
    }
    redirect('knowledge.php');
} catch (PDOException $e) {
    $_SESSION['error'] = 'Failed to process vote: ' . $e->getMessage();
    redirect('knowledge.php');
}
?>