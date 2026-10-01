<?php
require_once __DIR__ . '/config/config.php';

// Stamp the last activity moment before the session goes away, otherwise
// last_seen keeps pointing at whatever the previous page load recorded.
$uid = id_param($_SESSION['user']['users_id'] ?? '');
if ($uid !== '') {
    try {
        db()->prepare('UPDATE users SET last_seen = NOW() WHERE users_id = ?')->execute([$uid]);
    } catch (PDOException $e) {
        error_log('logout presence: ' . $e->getMessage());
    }
}

session_unset();
session_destroy();
redirect('login.php');