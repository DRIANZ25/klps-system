<?php
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
$back = $_POST['back'] ?? '';

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid article ID']);
    exit;
}

$currentUserId = $_SESSION['user']['id'] ?? 0;

if ($currentUserId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Please login to save']);
    exit;
}

try {
    // Check if knowledge_saves table exists
    try {
        db()->query('SELECT 1 FROM knowledge_saves LIMIT 1');
    } catch (PDOException $e) {
        db()->exec("
            CREATE TABLE IF NOT EXISTS knowledge_saves (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                knowledge_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_save (user_id, knowledge_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (knowledge_id) REFERENCES knowledge(id) ON DELETE CASCADE
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

    // Check if already saved
    $stmt = db()->prepare('SELECT id FROM knowledge_saves WHERE user_id = ? AND knowledge_id = ?');
    $stmt->execute([$currentUserId, $id]);
    $existing = $stmt->fetch();
    
    $action = '';

    if ($existing) {
        $stmt = db()->prepare('DELETE FROM knowledge_saves WHERE user_id = ? AND knowledge_id = ?');
        $stmt->execute([$currentUserId, $id]);
        $action = 'unsaved';
    } else {
        $stmt = db()->prepare('INSERT INTO knowledge_saves (user_id, knowledge_id) VALUES (?, ?)');
        $stmt->execute([$currentUserId, $id]);
        $action = 'saved';
        
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
                     VALUES (?, ?, "save", ?, ?)'
                );
                $stmt->execute([
                    $knowledge['created_by'],
                    $currentUserId,
                    $userName . ' saved your article "' . $knowledge['title'] . '"',
                    'knowledge_view.php?id=' . $id
                ]);
            } catch (PDOException $e) {
                // Ignore notification errors
            }
        }
    }

    // Update save count
    $stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_saves WHERE knowledge_id = ?');
    $stmt->execute([$id]);
    $saveCount = (int)$stmt->fetchColumn();
    
    $stmt = db()->prepare('UPDATE knowledge SET save_count = ? WHERE id = ?');
    $stmt->execute([$saveCount, $id]);

    echo json_encode([
        'success' => true,
        'action' => $action,
        'save_count' => $saveCount
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>