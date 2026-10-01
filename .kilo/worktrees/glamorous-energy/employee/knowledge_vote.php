<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$vote = $_GET['vote'] ?? '';
$back = $_GET['back'] ?? 'knowledge.php';

if ($id <= 0 || !in_array($vote, ['helpful', 'not_helpful'])) {
    $_SESSION['error'] = 'Invalid vote request.';
    redirect('knowledge.php');
}

$currentUserId = $_SESSION['user']['id'] ?? 0;

if ($currentUserId <= 0) {
    $_SESSION['error'] = 'You must be logged in to vote.';
    redirect('knowledge.php');
}

try {
    // Check if knowledge exists
    $stmt = db()->prepare('SELECT id, created_by, title FROM knowledge WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$id]);
    $article = $stmt->fetch();
    
    if (!$article) {
        $_SESSION['error'] = 'Article not found.';
        redirect('knowledge.php');
    }

    // Check if user already voted
    $stmt = db()->prepare('SELECT vote FROM knowledge_votes WHERE knowledge_id = ? AND user_id = ?');
    $stmt->execute([$id, $currentUserId]);
    $existingVote = $stmt->fetch();

    // Handle the vote
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
        $stmt = db()->prepare('INSERT INTO knowledge_votes (knowledge_id, user_id, vote) VALUES (?, ?, ?)');
        $stmt->execute([$id, $currentUserId, $vote]);
        $_SESSION['success'] = 'Vote recorded!';
        
        // The trigger will handle notification
    }

    // Update counts manually
    $stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_votes WHERE knowledge_id = ? AND vote = "helpful"');
    $stmt->execute([$id]);
    $helpful = (int)$stmt->fetchColumn();
    
    $stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_votes WHERE knowledge_id = ? AND vote = "not_helpful"');
    $stmt->execute([$id]);
    $notHelpful = (int)$stmt->fetchColumn();
    
    $stmt = db()->prepare('UPDATE knowledge SET helpful_count = ?, not_helpful_count = ? WHERE id = ?');
    $stmt->execute([$helpful, $notHelpful, $id]);

    // Redirect back
    if (!empty($back)) {
        if (strpos($back, 'http') === 0) {
            redirect($back);
        }
        redirect('knowledge.php?' . ltrim($back, '?'));
    } else {
        redirect('knowledge.php');
    }
    
} catch (PDOException $e) {
    error_log("Knowledge vote error: " . $e->getMessage());
    $_SESSION['error'] = 'Failed to process vote: ' . $e->getMessage();
    redirect('knowledge.php');
}
?>