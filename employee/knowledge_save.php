<?php
require_once __DIR__ . '/../config/config.php';
require_login();

$id = id_param($_GET['id'] ?? $_POST['id'] ?? '');
$back = $_GET['back'] ?? $_POST['back'] ?? '';

if ($id === '') {
    $_SESSION['error'] = 'Invalid article ID.';
    redirect('knowledge.php');
}

$currentUserId = $_SESSION['user']['users_id'] ?? '';
if ($currentUserId === '' || $currentUserId === 0) {
    $_SESSION['error'] = 'Please login to save articles.';
    redirect('knowledge.php');
}

try {
    $stmt = db()->prepare('SELECT knowledge_id FROM knowledge WHERE knowledge_id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Article not found.';
        redirect('knowledge.php');
    }

    $stmt = db()->prepare('SELECT knowledge_saves_id FROM knowledge_saves WHERE user_id = ? AND knowledge_id = ?');
    $stmt->execute([$currentUserId, $id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = db()->prepare('DELETE FROM knowledge_saves WHERE user_id = ? AND knowledge_id = ?');
        $stmt->execute([$currentUserId, $id]);
        $_SESSION['success'] = 'Article removed from your saved list.';
    } else {
        $stmt = db()->prepare('INSERT INTO knowledge_saves (knowledge_saves_id, user_id, knowledge_id) VALUES (?, ?, ?)');
        $stmt->execute([new_id('knowledge_saves'), $currentUserId, $id]);
        $_SESSION['success'] = 'Article saved to your library!';
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM knowledge_saves WHERE knowledge_id = ?');
    $stmt->execute([$id]);
    $saveCount = (int)$stmt->fetchColumn();
    $stmt = db()->prepare('UPDATE knowledge SET save_count = ? WHERE knowledge_id = ?');
    $stmt->execute([$saveCount, $id]);

    // Send the user back to the page they came from. The app's convention is
    // back=page=<script name> (see knowledge.php), so honour that instead of
    // blindly re-appending it to knowledge.php — otherwise unsaving from
    // saved_articles.php dumped them on the library page.
    if (!empty($back)) {
        if (preg_match('#^https?://#i', $back)) redirect($back);
        $back = ltrim($back, '?');
        if (preg_match('/^page=([A-Za-z0-9_.-]+)$/', $back, $m)) {
            $target = basename($m[1]) . '.php';
            if (is_file(__DIR__ . '/' . $target)) redirect($target);
            redirect('knowledge.php');
        }
        // only allow plain query characters, never a raw path
        if (preg_match('/^[A-Za-z0-9_=&%.-]+$/', $back)) redirect('knowledge.php?' . $back);
    }
    redirect('knowledge.php');
} catch (PDOException $e) {
    $_SESSION['error'] = 'Failed to save article: ' . $e->getMessage();
    redirect('knowledge.php');
}
