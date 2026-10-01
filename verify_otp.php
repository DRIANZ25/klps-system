<?php
require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');
$code  = trim($_POST['otp'] ?? '');

if (!$email || !$code) {
    echo json_encode(['success' => false, 'error' => 'Email and OTP are required.']);
    exit;
}

try {
    $stmt = db()->prepare('SELECT * FROM email_otp WHERE email = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email]);
    $record = $stmt->fetch();

    if (!$record) {
        echo json_encode(['success' => false, 'error' => 'No OTP found. Please request a new code.']);
        exit;
    }

    if (strtotime($record['expires_at']) < time()) {
        echo json_encode(['success' => false, 'error' => 'OTP expired. Please request a new code.']);
        exit;
    }

    if ($record['attempts'] >= 5) {
        echo json_encode(['success' => false, 'error' => 'Too many attempts. Please request a new code.']);
        exit;
    }

    if (!password_verify($code, $record['otp_code'])) {
        $stmt = db()->prepare('UPDATE email_otp SET attempts = attempts + 1 WHERE id = ?');
        $stmt->execute([$record['id']]);
        echo json_encode(['success' => false, 'error' => 'Incorrect OTP.']);
        exit;
    }

    $stmt = db()->prepare('UPDATE email_otp SET is_verified = 1 WHERE id = ?');
    $stmt->execute([$record['id']]);

    echo json_encode(['success' => true, 'message' => 'Email verified.']);
} catch (PDOException $e) {
    error_log('OTP verify failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}