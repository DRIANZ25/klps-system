<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
$vote = $_POST['vote'] ?? '';
$back = $_POST['back'] ?? '';

if ($id <= 0 || !in_array($vote, ['helpful', 'not_helpful'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$currentUserId = $_SESSION['user']['id'] ?? 0;

if ($currentUserId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Please login to vote']);
    exit;
}

try {
    // Check if knowledge_votes table exists
    try {
        db()->query('SELECT 1 FROM knowledge_votes LIMIT 1');
    } catch (PDOException $e) {
        db()->exec("
            CREATE TABLE IF NOT EXISTS knowledge_votes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                knowledge_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                vote ENUM('helpful', 'not_helpful') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_vote (knowledge_id, user_id),
                FOREIGN KEY (knowledge_id) REFERENCES knowledge(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }
    
    // Check if knowledge exists
    $stmt = db()->prepare('SELECT id, created_by, title FROM knowledge WHERE id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$id]);
    $knowledge = $stmt->fetch();
    
    if (!$knowledge) {
        echo json_encode(['success' => false, 'error' => 'Article not found']);
        exit;
    }

    // Check if user already voted
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
        $stmt = db()->prepare('INSERT INTO knowledge_votes (knowledge_id, user_id, vote) VALUES (?, ?, ?)');
        $stmt->execute([$id, $currentUserId, $vote]);
        $action = 'added';
        
        // Create notification for article author
        if ($knowledge['created_by'] != $currentUserId) {
            try {
                // Check if notifications table exists
                try {
                    db()->query('SELECT 1 FROM notifications LIMIT 1');
                } catch (PDOException $e) {
                    db()->exec("
                        CREATE TABLE IF NOT EXISTS notifications (
                            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            user_id INT UNSIGNED NOT NULL,
                            from_user_id INT UNSIGNED NULL,
                            type ENUM('vote', 'save', 'comment', 'mention', 'system') NOT NULL,
                            message TEXT NOT NULL,
                            link VARCHAR(255) NULL,
                            is_read TINYINT(1) DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                            FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
                            INDEX idx_notifications_user (user_id, is_read, created_at)
                        )
                    ");
                }
                
                $userName = $_SESSION['user']['full_name'] ?? 'Someone';
                $stmt = db()->prepare(
                    'INSERT INTO notifications (user_id, from_user_id, type, message, link) 
                     VALUES (?, ?, "vote", ?, ?)'
                );
                $stmt->execute([
                    $knowledge['created_by'],
                    $currentUserId,
                    $userName . ' liked your article "' . $knowledge['title'] . '"',
                    'knowledge_view.php?id=' . $id
                ]);
            } catch (PDOException $e) {
                // Ignore notification errors
            }
        }
    }

    // Update counts
    $stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_votes WHERE knowledge_id = ? AND vote = "helpful"');
    $stmt->execute([$id]);
    $helpful = (int)$stmt->fetchColumn();
    
    $stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_votes WHERE knowledge_id = ? AND vote = "not_helpful"');
    $stmt->execute([$id]);
    $notHelpful = (int)$stmt->fetchColumn();
    
    $stmt = db()->prepare('UPDATE knowledge SET helpful_count = ?, not_helpful_count = ? WHERE id = ?');
    $stmt->execute([$helpful, $notHelpful, $id]);

    echo json_encode([
        'success' => true,
        'action' => $action,
        'helpful' => $helpful,
        'not_helpful' => $notHelpful
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>