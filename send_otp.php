<?php
require_once __DIR__ . '/config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require __DIR__ . '/src/Exception.php';
require __DIR__ . '/src/PHPMailer.php';
require __DIR__ . '/src/SMTP.php';

header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

try {
    $stmt = db()->prepare('SELECT users_id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'This email is already registered.']);
        exit;
    }

    $stmt = db()->prepare('SELECT created_at FROM email_otp WHERE email = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email]);
    $last = $stmt->fetch();
    if ($last && (time() - strtotime($last['created_at'])) < 60) {
        echo json_encode(['success' => false, 'error' => 'Please wait 60 seconds before requesting a new code.']);
        exit;
    }

    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);

    $stmt = db()->prepare('DELETE FROM email_otp WHERE email = ?');
    $stmt->execute([$email]);

    $stmt = db()->prepare('INSERT INTO email_otp (id, email, otp_code, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))');
    $stmt->execute([new_id('email_otp'), $email, $otpHash]);

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'lancetejero05@gmail.com';
    $mail->Password   = 'mcep bvih nqwd syrw';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('lancetejero05@gmail.com', 'KLPS');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->Subject = 'Your KLPS Verification Code';
    $mail->Body = "
        <div style='font-family:Arial,sans-serif;padding:24px;background:#0a0e1a;color:#e7ecf7;border-radius:12px;'>
            <h2 style='color:#4fd7e8;'>KLPS Email Verification</h2>
            <p>Your verification code is:</p>
            <h1 style='font-size:36px;letter-spacing:8px;color:#f6c453;'>{$otp}</h1>
            <p style='color:#8892b0;font-size:13px;'>This code expires in 5 minutes.</p>
        </div>
    ";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'OTP sent to your email.']);
} catch (Exception $e) {
    error_log('OTP mail failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to send email. Check Gmail App Password.']);
} catch (PDOException $e) {
    error_log('OTP DB failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}