<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user'])) {
    redirect($_SESSION['user']['role'] === 'admin' ? 'admin/dashboard.php' : 'employee/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT u.*, d.name AS department_name FROM users u LEFT JOIN departments d ON d.departments_id = u.department_id WHERE u.email = ? AND u.is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']);
        $_SESSION['user'] = $user;

        // Stamp presence on sign-in so the admin console can show who is
        // actually active, not just who has an account.
        db()->prepare('UPDATE users SET last_seen = NOW() WHERE users_id = ?')->execute([$user['users_id']]);

        $log = db()->prepare('INSERT INTO activity_logs (activity_logs_id, user_id, action, details) VALUES (?, ?, ?, ?)');
        $log->execute([new_id('activity_logs'), $user['users_id'], 'login', 'User logged in']);

        redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'employee/dashboard.php');
    }
    $error = 'Invalid email or password.';
}

$registeredUsers = [];
$stmt = db()->query('SELECT u.full_name, u.email, u.role, d.name AS department_name FROM users u LEFT JOIN departments d ON d.departments_id = u.department_id WHERE u.is_active = 1 ORDER BY u.full_name');
while ($row = $stmt->fetch()) {
    $registeredUsers[] = $row;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>KLPS // Secure Access Terminal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/login.css')) ?>">
</head>
<body>

<!-- Boot Screen Transition -->
<div id="boot-screen">
    <div class="boot-logo"><i class="fas fa-brain me-2"></i>KLPS</div>
    <div class="boot-line" id="bootLine">INITIALIZING SECURE TERMINAL</div>
    <div class="boot-bar-track"><div class="boot-bar-fill"></div></div>
</div>

<div class="cyber-grid"></div>
<div class="scanline"></div>
<div id="particles"></div>

<div class="landscape-wrapper">
    <div class="login-container">
        <div class="login-header text-center mb-4">
            <div class="brand-logo">
                <i class="fas fa-brain"></i>
                <span>KLPS</span>
            </div>
            <h2>KNOWLEDGE LEARNING PRESERVATION SYSTEM</h2>
            <p>Secure access node for knowledge professionals</p>

            <div class="social-proof mt-3">
                <div class="badge-item"><i class="fas fa-shield-halved"></i><span>Enterprise Security</span></div>
                <div class="badge-item"><i class="fas fa-microchip"></i><span>10,000+ Users</span></div>
                <div class="badge-item"><i class="fas fa-signal"></i><span>99.9% Uptime</span></div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-triangle-exclamation me-2"></i><?= e($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-section">
                <h3 class="form-section-title"><i class="fas fa-terminal"></i>Sign In</h3>
                <p class="form-section-description">Access your knowledge repository and AI assistant</p>

                <div class="mb-2">
                    <label class="form-label"><i class="fas fa-envelope"></i>Email Address</label>
                    <input name="email" type="email" class="form-control" required placeholder="you@domain.com">
                </div>

                <div class="mb-2">
                    <label class="form-label"><i class="fas fa-key"></i>Password</label>
                    <div class="input-group">
                        <input name="password" type="password" class="form-control" required placeholder="••••••••">
                        <button class="btn toggle-password" type="button"><i class="fas fa-eye"></i></button>
                    </div>
                    <small class="text-muted">Forgot <a href="forgot_password.php">password?</a></small>
                </div>

                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-neon" type="submit">
                        <i class="fas fa-right-to-bracket"></i>Sign In
                    </button>
                </div>
            </div>

            <div class="text-center mt-2">
                <p class="mb-0 small text-muted">Don't have an account? <a href="register.php" class="fw-medium">Create one</a></p>
            </div>
        </form>
    </div>

    <div class="employees-container">
        <h3><i class="fas fa-users"></i>Registered Employees</h3>

        <?php if (!empty($registeredUsers)): ?>
            <div class="employees-list">
                <?php foreach ($registeredUsers as $user): ?>
                    <?php if ($user['role'] === 'employee'): ?>
                        <div class="employee-item">
                            <div class="employee-info">
                                <div class="employee-icon"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                                <div class="employee-details">
                                    <div class="employee-name"><?= e($user['full_name']) ?></div>
                                    <div class="employee-email"><?= e($user['email']) ?></div>
                                </div>
                            </div>
                            <span class="employee-role-badge">
                                <i class="fas fa-user"></i>Employee
                                <?php if (!empty($user['department_name'])): ?>
                                    · <?= e($user['department_name']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="employees-stats">
                <span><i class="fas fa-user-check"></i> <?= count(array_filter($registeredUsers, fn($u) => $u['role'] === 'employee')) ?> Employees</span>
                <span><i class="fas fa-users"></i> <?= count($registeredUsers) ?> Total</span>
            </div>
        <?php else: ?>
            <div class="help-text">
                <i class="fas fa-circle-info"></i>
                <p>No employees registered yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/login.js')) ?>"></script>
</body>
</html>