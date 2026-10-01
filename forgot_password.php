<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user'])) {
    redirect($_SESSION['user']['role'] === 'admin' ? 'admin/dashboard.php' : 'employee/dashboard.php');
}

$error = '';
$success = '';

$emailValue = trim($_POST['email'] ?? '');
$otpValue   = trim($_POST['otp'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- STEP 1: Send OTP ----------
    if ($action === 'send_reset_otp') {
        header('Content-Type: application/json');

        if (empty($emailValue)) {
            echo json_encode(['success' => false, 'error' => 'Please enter your email.']);
            exit;
        }
        if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
            exit;
        }

        $stmt = db()->prepare('SELECT users_id, full_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$emailValue]);
        $user = $stmt->fetch();

        if (!$user) {
            // Don't reveal whether the email exists
            echo json_encode(['success' => true, 'message' => 'If that email exists, a reset code was sent.']);
            exit;
        }

        $otp = (string) random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);

        db()->prepare('DELETE FROM email_otp WHERE email = ?')->execute([$emailValue]);
        db()->prepare('INSERT INTO email_otp (id, email, otp_code, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))')
            ->execute([new_id('email_otp'), $emailValue, $otpHash]);

        try {
            $mailtrapToken = getenv('MAILTRAP_API_TOKEN') ?: '';
            $inboxId       = getenv('MAILTRAP_INBOX_ID') ?: '';
            if ($mailtrapToken === '' || $inboxId === '') {
                throw new \Exception('Mailtrap API credentials not configured');
            }

            $emailBody = "
                <div style='font-family:Arial,sans-serif;padding:24px;background:#0a0e1a;color:#e7ecf7;border-radius:12px;max-width:500px;'>
                    <h2 style='color:#4fd7e8;margin:0 0 12px;'>KLPS Password Reset</h2>
                    <p style='margin:0 0 8px;'>Hi {$user['full_name']},</p>
                    <p style='margin:0 0 8px;'>Your password reset code is:</p>
                    <div style='font-size:36px;letter-spacing:10px;color:#f6c453;font-weight:800;margin:16px 0;'>{$otp}</div>
                    <p style='color:#8892b0;font-size:13px;margin:0;'>This code expires in 5 minutes.</p>
                    <p style='color:#8892b0;font-size:12px;margin:12px 0 0;'>If you didn't request this, ignore this email.</p>
                </div>
            ";

            $payload = json_encode([
                'from'    => ['email' => SMTP_FROM, 'name' => SMTP_FROM_NAME],
                'to'      => [['email' => $emailValue]],
                'subject' => 'Reset Your KLPS Password',
                'html'    => $emailBody,
            ], JSON_UNESCAPED_UNICODE);

            $ch = curl_init('https://sandbox.api.mailtrap.io/api/send/' . $inboxId);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Api-Token: ' . $mailtrapToken,
                ],
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr !== '') throw new \Exception('cURL error: ' . $curlErr);
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new \Exception('Mailtrap API HTTP ' . $httpCode . ': ' . $response);
            }

            error_log("Password reset OTP sent to {$emailValue}");
            echo json_encode(['success' => true, 'message' => 'Code sent.']);
        } catch (\Exception $e) {
            error_log('Password reset OTP failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to send email. Please try again.']);
        }
        exit;
    }

    // ---------- STEP 2: Verify OTP ----------
    if ($action === 'verify_reset_otp') {
        header('Content-Type: application/json');

        if (empty($emailValue) || empty($otpValue)) {
            echo json_encode(['success' => false, 'error' => 'Missing email or code.']);
            exit;
        }

        try {
            $stmt = db()->prepare(
                'SELECT *, (expires_at < NOW()) AS is_expired
                 FROM email_otp WHERE email = ? ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$emailValue]);
            $record = $stmt->fetch();

            if (!$record) {
                echo json_encode(['success' => false, 'error' => 'No code found. Please request a new one.']);
                exit;
            }
            if ((int)$record['is_expired'] === 1) {
                echo json_encode(['success' => false, 'error' => 'Code expired. Please request a new one.']);
                exit;
            }
            if ($record['attempts'] >= 5) {
                echo json_encode(['success' => false, 'error' => 'Too many attempts. Please request a new code.']);
                exit;
            }
            if (!password_verify($otpValue, $record['otp_code'])) {
                db()->prepare('UPDATE email_otp SET attempts = attempts + 1 WHERE id = ?')->execute([$record['id']]);
                echo json_encode(['success' => false, 'error' => 'Incorrect code. Please try again.']);
                exit;
            }

            db()->prepare('UPDATE email_otp SET is_verified = 1 WHERE id = ?')->execute([$record['id']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Database error.']);
        }
        exit;
    }

    // ---------- STEP 3: Reset Password ----------
    if ($action === 'reset_password') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $stmt = db()->prepare('SELECT is_verified FROM email_otp WHERE email = ? ORDER BY id DESC LIMIT 1');
                $stmt->execute([$emailValue]);
                $otpRow = $stmt->fetch();

                if (!$otpRow || (int)$otpRow['is_verified'] !== 1) {
                    $error = 'Please verify your code first.';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    db()->prepare('UPDATE users SET password_hash = ? WHERE email = ?')->execute([$hash, $emailValue]);
                    db()->prepare('DELETE FROM email_otp WHERE email = ?')->execute([$emailValue]);

                    $success = 'Password reset successfully! You can now sign in with your new password.';
                }
            } catch (PDOException $e) {
                error_log('Password reset failed: ' . $e->getMessage());
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>KLPS // Password Recovery</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/login.css')) ?>">
</head>
<body>

<div id="boot-screen">
    <div class="boot-logo"><i class="fas fa-key me-2"></i>KLPS</div>
    <div class="boot-line" id="bootLine">INITIALIZING RECOVERY PROTOCOL</div>
    <div class="boot-bar-track"><div class="boot-bar-fill"></div></div>
</div>

<div class="cyber-grid"></div>
<div class="scanline"></div>
<div id="particles"></div>

<div class="landscape-wrapper" style="max-width:520px; justify-content:center;">
    <div class="login-container" style="flex:1;">
        <div class="login-header text-center mb-4">
            <div class="brand-logo">
                <i class="fas fa-key"></i>
                <span>KLPS</span>
            </div>
            <h2>PASSWORD RECOVERY</h2>
            <p>Reset your password using email verification</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-2"></i><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" style="background:rgba(0,255,156,0.1);border:1px solid rgba(0,255,156,0.4);color:#00ff9c;border-radius:10px;font-size:.85rem;padding:.65rem .9rem;">
                <i class="fas fa-circle-check me-2"></i><?= e($success) ?>
            </div>
            <div class="d-grid gap-2 mt-3">
                <a href="login.php" class="btn btn-neon" style="text-decoration:none;">
                    <i class="fas fa-right-to-bracket"></i> Back to Sign In
                </a>
            </div>
        <?php else: ?>
            <form method="post" id="resetForm">
                <div class="form-section">
                    <h3 class="form-section-title"><i class="fas fa-envelope"></i>Step 1: Verify Email</h3>
                    <p class="form-section-description">Enter your account email to receive a reset code.</p>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-envelope"></i>Email Address</label>
                        <div style="display:flex; gap:.4rem;">
                            <input name="email" id="emailInput" type="email" class="form-control"
                                   required placeholder="you@domain.com" value="<?= e($emailValue) ?>" style="flex:1;">
                            <button type="button" id="sendOtpBtn"
                                    style="padding:.65rem 1rem; background:linear-gradient(135deg, var(--cyan), var(--magenta)); border:none; border-radius:8px; color:#020409; font-weight:700; font-size:.75rem; letter-spacing:1px; text-transform:uppercase; cursor:pointer; white-space:nowrap;">
                                <i class="fas fa-paper-plane"></i> Send Code
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-key"></i>Step 2: Enter Code</label>
                        <input type="text" id="otpInput" class="form-control otp-input"
                               maxlength="6" pattern="\d{6}" inputmode="numeric" placeholder="000000">
                        <div id="otpHint" style="color:#8fa3c9;font-size:.7rem;margin-top:.35rem;">
                            <i class="fas fa-clock"></i> Enter the 6-digit code from your email.
                        </div>
                        <button type="button" id="verifyBtn"
                                style="display:none; width:100%; margin-top:.5rem; padding:.65rem; background:linear-gradient(90deg,var(--magenta),var(--cyan)); border:none; border-radius:8px; color:#020409; font-weight:700; font-size:.8rem; letter-spacing:1.5px; text-transform:uppercase; cursor:pointer;">
                            <i class="fas fa-circle-check"></i> Verify Code
                        </button>
                    </div>

                    <div id="revealSection" style="display:none; margin-top:1rem; padding-top:1rem; border-top:1px solid rgba(0,240,255,0.15);">
                        <h3 class="form-section-title" style="font-size:.95rem;"><i class="fas fa-lock"></i>Step 3: New Password</h3>

                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="email" id="hiddenEmail" value="<?= e($emailValue) ?>">

                        <div class="mb-2">
                            <label class="form-label"><i class="fas fa-lock"></i>New Password</label>
                            <input name="new_password" type="password" class="form-control" minlength="8" required placeholder="At least 8 characters">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-lock"></i>Confirm Password</label>
                            <input name="confirm_password" type="password" class="form-control" minlength="8" required placeholder="Repeat password">
                        </div>

                        <button class="btn btn-neon" type="submit" style="width:100%;">
                            <i class="fas fa-key"></i> Reset Password
                        </button>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <p class="mb-0 small text-muted">
                        Remembered it? <a href="login.php" class="fw-medium">Back to Sign In</a>
                    </p>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const lines = ['INITIALIZING RECOVERY PROTOCOL', 'VERIFYING SECURITY TOKENS', 'READY'];
    const el = document.getElementById('bootLine');
    let i = 0;
    const iv = setInterval(() => { i++; if (i < lines.length) el.textContent = lines[i]; }, 380);
    setTimeout(() => {
        clearInterval(iv);
        document.getElementById('boot-screen').classList.add('hide');
    }, 1650);

    const icons = ['fa-key', 'fa-lock', 'fa-shield-halved'];
    const container = document.getElementById('particles');
    for (let n = 0; n < 12; n++) {
        const span = document.createElement('i');
        span.className = 'fas ' + icons[Math.floor(Math.random() * icons.length)] + ' knowledge-particle';
        span.style.left = Math.random() * 100 + '%';
        span.style.top = Math.random() * 100 + '%';
        span.style.fontSize = (Math.random() * 16 + 14) + 'px';
        span.style.animationDelay = (Math.random() * 6) + 's';
        span.style.animationDuration = (Math.random() * 5 + 7) + 's';
        container.appendChild(span);
    }

    const sendBtn = document.getElementById('sendOtpBtn');
    const emailInput = document.getElementById('emailInput');
    const otpInput = document.getElementById('otpInput');
    const verifyBtn = document.getElementById('verifyBtn');
    const otpHint = document.getElementById('otpHint');
    const revealSection = document.getElementById('revealSection');
    const hiddenEmail = document.getElementById('hiddenEmail');
    let isVerified = false;

    function setHint(html) { otpHint.innerHTML = html; }

    sendBtn.addEventListener('click', async function() {
        const email = emailInput.value.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> Enter a valid email.');
            return;
        }

        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

        const fd = new FormData();
        fd.append('action', 'send_reset_otp');
        fd.append('email', email);

        try {
            const res = await fetch(window.location.href, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                setHint('<i class="fas fa-circle-check" style="color:#00ff9c;"></i> Code sent to <strong>' + email + '</strong>');
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Resend';
                sendBtn.disabled = false;
                otpInput.focus();
            } else {
                setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> ' + (data.error || 'Failed.'));
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Retry';
                sendBtn.disabled = false;
            }
        } catch (err) {
            setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> Network error.');
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Retry';
            sendBtn.disabled = false;
        }
    });

    otpInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
        verifyBtn.style.display = (this.value.length === 6 && !isVerified) ? 'block' : 'none';
    });

    verifyBtn.addEventListener('click', async function() {
        const otp = otpInput.value.trim();
        if (otp.length !== 6) return;

        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

        const fd = new FormData();
        fd.append('action', 'verify_reset_otp');
        fd.append('email', emailInput.value.trim());
        fd.append('otp', otp);

        try {
            const res = await fetch(window.location.href, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                isVerified = true;
                otpInput.setAttribute('readonly', true);
                verifyBtn.innerHTML = '<i class="fas fa-circle-check"></i> Verified';
                verifyBtn.style.background = 'rgba(0,255,156,0.15)';
                verifyBtn.style.color = '#00ff9c';
                verifyBtn.style.border = '1px solid rgba(0,255,156,0.4)';
                verifyBtn.disabled = true;
                setHint('<i class="fas fa-circle-check" style="color:#00ff9c;"></i> Email verified! Set your new password below.');
                hiddenEmail.value = emailInput.value.trim();
                revealSection.style.display = 'block';
                revealSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-circle-check"></i> Verify Code';
                setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> ' + (data.error || 'Verification failed.'));
            }
        } catch (err) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<i class="fas fa-circle-check"></i> Verify Code';
            setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> Network error.');
        }
    });
});
</script>
</body>
</html>