<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user'])) {
    redirect($_SESSION['user']['role'] === 'admin' ? 'admin/dashboard.php' : 'employee/dashboard.php');
}

$error = '';
$success = '';

$emailValue    = trim($_POST['email'] ?? '');
$fullNameValue = trim($_POST['full_name'] ?? '');
$departmentValue = id_param($_POST['department_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_otp_ajax') {
        header('Content-Type: application/json');

        if (empty($emailValue)) {
            echo json_encode(['success' => false, 'error' => 'Please enter your email first.']);
            exit;
        }
        if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
            exit;
        }

        $stmt = db()->prepare('SELECT users_id FROM users WHERE email = ?');
        $stmt->execute([$emailValue]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'An account with this email already exists.']);
            exit;
        }

        $otp = (string) random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);

        $stmt = db()->prepare('DELETE FROM email_otp WHERE email = ?');
        $stmt->execute([$emailValue]);

        $stmt = db()->prepare('INSERT INTO email_otp (id, email, otp_code, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))');
        $stmt->execute([new_id('email_otp'), $emailValue, $otpHash]);

        try {
            require_once __DIR__ . '/src/Exception.php';
            require_once __DIR__ . '/src/PHPMailer.php';
            require_once __DIR__ . '/src/SMTP.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = str_replace(' ', '', SMTP_PASS);
            $mail->SMTPSecure = SMTP_SECURE === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($emailValue);
            $mail->isHTML(true);
            $mail->Subject = 'Your KLPS Verification Code';
            $mail->Body    = "
                <div style='font-family:Arial,sans-serif;padding:24px;background:#0a0e1a;color:#e7ecf7;border-radius:12px;max-width:500px;'>
                    <h2 style='color:#4fd7e8;margin:0 0 12px;'>KLPS Email Verification</h2>
                    <p style='margin:0 0 8px;'>Hi {$fullNameValue},</p>
                    <p style='margin:0 0 8px;'>Your verification code is:</p>
                    <div style='font-size:36px;letter-spacing:10px;color:#f6c453;font-weight:800;margin:16px 0;'>{$otp}</div>
                    <p style='color:#8892b0;font-size:13px;margin:0;'>This code expires in 5 minutes.</p>
                </div>
            ";
            $mail->send();

            error_log("OTP sent to {$emailValue} via " . SMTP_USER);
            echo json_encode(['success' => true, 'message' => 'Code sent.']);
        } catch (\Exception $e) {
            error_log('OTP mail failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to send email. Please try again.']);
        }
        exit;
    }

    if ($action === 'verify_otp_ajax') {
        header('Content-Type: application/json');

        $email = trim($_POST['email'] ?? '');
        $otp   = trim($_POST['otp'] ?? '');

        if (!$email || !$otp) {
            echo json_encode(['success' => false, 'error' => 'Missing email or code.']);
            exit;
        }

        try {
            $stmt = db()->prepare(
                'SELECT *, (expires_at < NOW()) AS is_expired
                 FROM email_otp
                 WHERE email = ?
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $stmt->execute([$email]);
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
            if (!password_verify($otp, $record['otp_code'])) {
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

    if ($action === 'create_account') {
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $department_id    = id_param($_POST['department_id'] ?? '');

        if (empty($fullNameValue) || empty($emailValue)) {
            $error = 'Please enter your full name and email.';
        } elseif ($department_id <= 0) {
            $error = 'Please select your department.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $stmt = db()->prepare('SELECT is_verified FROM email_otp WHERE email = ? ORDER BY id DESC LIMIT 1');
                $stmt->execute([$emailValue]);
                $otpRow = $stmt->fetch();

                if (!$otpRow || (int)$otpRow['is_verified'] !== 1) {
                    $error = 'Please verify your email code first.';
                } else {
                    $stmt = db()->prepare('SELECT users_id FROM users WHERE email = ?');
                    $stmt->execute([$emailValue]);
                    if ($stmt->fetch()) {
                        $error = 'An account with this email already exists.';
                    } else {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = db()->prepare('INSERT INTO users (users_id, email, password_hash, full_name, role, department_id, is_active) VALUES (?, ?, ?, ?, "employee", ?, 1)');
                        $stmt->execute([new_id('users'), $emailValue, $password_hash, $fullNameValue, $department_id ?: null]);

                        $stmt = db()->prepare('DELETE FROM email_otp WHERE email = ?');
                        $stmt->execute([$emailValue]);

                        $success = 'Account created successfully! You can now sign in.';
                        $emailValue = '';
                        $fullNameValue = '';
                        $departmentValue = 0;
                    }
                }
            } catch (PDOException $e) {
                error_log('Register failed: ' . $e->getMessage());
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
<title>KLPS // New Access Registration</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/register.css')) ?>">
</head>
<body>

<div id="boot-screen">
    <div class="boot-logo"><i class="fas fa-brain me-2"></i>KLPS</div>
    <div class="boot-line" id="bootLine">PREPARING REGISTRATION PROTOCOL</div>
    <div class="boot-bar-track"><div class="boot-bar-fill"></div></div>
</div>

<div class="cyber-grid"></div>
<div class="scanline"></div>
<div id="particles"></div>

<div class="landscape-wrapper">
    <div class="register-container">
        <div class="register-header text-center">
            <div class="brand-logo">
                <i class="fas fa-brain"></i>
                <span>KLPS</span>
            </div>
            <h2>JOIN THE KNOWLEDGE NETWORK</h2>
            <p>Create your account to start preserving and sharing knowledge</p>

            <div class="social-proof">
                <div class="badge-item"><i class="fas fa-shield-halved"></i><span>Enterprise Security</span></div>
                <div class="badge-item"><i class="fas fa-microchip"></i><span>10,000+ Users</span></div>
                <div class="badge-item"><i class="fas fa-signal"></i><span>99.9% Uptime</span></div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-triangle-exclamation me-2"></i><?= e($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-circle-check me-2"></i><?= e($success) ?>
            </div>
            <div class="d-grid gap-2 mt-2">
                <a href="login.php" class="btn btn-neon" style="text-decoration:none;">
                    <i class="fas fa-right-to-bracket"></i> Sign In
                </a>
            </div>
        <?php else: ?>
            <form method="post" autocomplete="off" id="registerForm">
                <div class="form-section">
                    <h3 class="form-section-title"><i class="fas fa-user-plus"></i>Create Account</h3>
                    <p class="form-section-description">Join thousands of knowledge professionals using KLPS</p>

                    <!-- Full Name -->
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-id-badge"></i>Full Name</label>
                        <input name="full_name" id="fullNameInput" type="text" class="form-control"
                               required placeholder="Enter your full name"
                               value="<?= e($fullNameValue) ?>">
                    </div>

                    <!-- Department -->
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-building"></i>Department</label>
                        <select name="department_id" id="departmentInput" class="form-control" required>
                            <option value="">Select your department</option>
                            <?php foreach (get_departments() as $dept): ?>
                                <option value="<?= e($dept['id']) ?>"
                                        <?= $departmentValue === $dept['id'] ? 'selected' : '' ?>>
                                    <?= e($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Email + Send Code -->
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-envelope"></i>Email Address</label>
                        <div class="email-send-row">
                            <input name="email" id="emailInput" type="email" class="form-control"
                                   required placeholder="you@domain.com"
                                   value="<?= e($emailValue) ?>">
                            <button type="button" class="btn-send-otp" id="sendOtpBtn">
                                <i class="fas fa-paper-plane btn-icon"></i> <span class="btn-label">Send Code</span>
                            </button>
                        </div>
                    </div>

                    <!-- OTP code -->
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-key"></i>Verification Code</label>
                        <input
                            type="text"
                            id="otpInput"
                            class="form-control otp-input"
                            maxlength="6"
                            pattern="\d{6}"
                            inputmode="numeric"
                            placeholder="000000"
                            autocomplete="one-time-code"
                        >
                        <div id="otpHint" style="color:#8fa3c9;font-size:0.68rem;display:block;margin-top:.3rem;">
                            <i class="fas fa-clock"></i> Enter the 6-digit code from your email.
                        </div>

                        <button type="button" class="btn-verify" id="verifyBtn">
                            <i class="fas fa-circle-check"></i> Verify Code
                        </button>
                    </div>

                    <!-- Passwords (hidden until verified) -->
                    <div class="reveal-section" id="revealSection">
                        <div class="mb-2">
                            <label class="form-label"><i class="fas fa-lock"></i>Password</label>
                            <input name="password" type="password" class="form-control"
                                   placeholder="Create a password" minlength="8" id="passwordInput">
                            <small style="color:#8fa3c9;font-size:0.65rem;">Min. 8 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-lock"></i>Confirm Password</label>
                            <input name="confirm_password" type="password" class="form-control"
                                   placeholder="Confirm password" minlength="8" id="confirmInput">
                        </div>

                        <div class="d-grid gap-2">
                            <input type="hidden" name="action" value="create_account">
                            <button class="btn btn-neon" type="submit" id="createBtn">
                                <i class="fas fa-user-plus"></i> Create Account
                            </button>
                        </div>
                    </div>
                </div>

                <div class="text-center" style="margin-top:.5rem;">
                    <p class="mb-0 small text-muted" style="font-size:.72rem;">Already have an account? <a href="login.php" class="fw-medium">Sign in</a></p>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="info-container">
        <h3><i class="fas fa-circle-info"></i>Why Join KLPS?</h3>
        <div class="info-list">
            <div class="info-item"><i class="fas fa-book"></i><div><h4>Preserve Knowledge</h4><p>Document institutional knowledge for the future</p></div></div>
            <div class="info-item"><i class="fas fa-robot"></i><div><h4>AI Assistant</h4><p>Get instant help with technical issues</p></div></div>
            <div class="info-item"><i class="fas fa-diagram-project"></i><div><h4>Collaborate</h4><p>Work with colleagues on a shared knowledge base</p></div></div>
            <div class="info-item"><i class="fas fa-chart-line"></i><div><h4>Track Progress</h4><p>Monitor your learning journey</p></div></div>
            <div class="info-item"><i class="fas fa-shield-halved"></i><div><h4>Email Verified</h4><p>Every account is protected with verification</p></div></div>
        </div>
        <div class="info-stats">
            <div class="stat-item"><span class="stat-value">10K+</span><span class="stat-label">Users</span></div>
            <div class="stat-item"><span class="stat-value">50K+</span><span class="stat-label">Articles</span></div>
            <div class="stat-item"><span class="stat-value">99.9%</span><span class="stat-label">Uptime</span></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/register.js')) ?>"></script>
</body>
</html>