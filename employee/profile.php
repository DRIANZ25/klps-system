<?php
$page_title = 'Profile Settings';
$page_css = ['employee-profile.css'];
$page_js  = ['employee-profile.js'];
require_once __DIR__ . '/../partials/header.php';

$currentUserId = $_SESSION['user']['users_id'] ?? 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed)) {
        $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
    } elseif ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
        $error = 'Image is too large — max 5MB.';
    } else {
        $filename = 'user_' . $currentUserId . '_' . time() . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filepath)) {
            $stmt = db()->prepare('SELECT profile_picture FROM users WHERE users_id = ?');
            $stmt->execute([$currentUserId]);
            $old = $stmt->fetchColumn();
            if ($old && file_exists($uploadDir . $old)) @unlink($uploadDir . $old);

            $stmt = db()->prepare('UPDATE users SET profile_picture = ? WHERE users_id = ?');
            $stmt->execute([$filename, $currentUserId]);
            $_SESSION['user']['profile_picture'] = $filename;
            $success = 'Profile picture updated successfully!';
        } else {
            $error = 'Failed to upload profile picture.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bio']) && !isset($_FILES['profile_picture']['tmp_name'])) {
    $bio = trim($_POST['bio'] ?? '');
    if (mb_strlen($bio) > 500) $bio = mb_substr($bio, 0, 500);
    try {
        $stmt = db()->prepare('UPDATE users SET bio = ? WHERE users_id = ?');
        $stmt->execute([$bio, $currentUserId]);
        $_SESSION['user']['bio'] = $bio;
        $success = 'Profile bio updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update profile.';
    }
}

$stmt = db()->prepare('SELECT * FROM users WHERE users_id = ?');
$stmt->execute([$currentUserId]);
$user = $stmt->fetch();

$pdo = db();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge WHERE created_by = ?'); $stmt->execute([$currentUserId]);
$statArticles = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM ai_conversations WHERE user_id = ?'); $stmt->execute([$currentUserId]);
$statChats = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge_saves WHERE user_id = ?'); $stmt->execute([$currentUserId]);
$statSaves = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge_votes WHERE knowledge_id IN (SELECT knowledge_id FROM knowledge WHERE created_by = ?)');
$stmt->execute([$currentUserId]);
$statVotes = (int)$stmt->fetchColumn();

$xp = ($statArticles * 10) + ($statChats * 3) + ($statSaves * 2);
$level = intdiv($xp, 50) + 1;

$ranks = [
    1 => ['title' => 'Seeker',        'icon' => 'fa-magnifying-glass'],
    2 => ['title' => 'Apprentice',    'icon' => 'fa-book'],
    3 => ['title' => 'Scholar',       'icon' => 'fa-graduation-cap'],
    4 => ['title' => 'Archivist',     'icon' => 'fa-book-atlas'],
    5 => ['title' => 'Sage',          'icon' => 'fa-brain'],
];
$rankKey   = min($level, 5);
$rankTitle = $ranks[$rankKey]['title'];
$rankIcon  = $ranks[$rankKey]['icon'];

$memberSince = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : '—';

$profilePic = !empty($user['profile_picture']) ? '../uploads/profiles/' . $user['profile_picture'] : '';
$initial    = strtoupper(substr($user['full_name'] ?? '?', 0, 1));
?>



<div class="profile-page">
    <!-- Backdrop -->
    <div class="pf-bg">
        <div class="grid"></div>
        <div class="aurora a1"></div>
        <div class="aurora a2"></div>
    </div>
    <div class="pf-scenery" id="pfScenery"></div>

    <!-- Header -->
    <div class="pf-header">
        <div>
            <h1><i class="fas fa-user-cog"></i> Profile Settings</h1>
            <p>Update your profile picture, bio, and personal details.</p>
        </div>
        <div class="pf-header-chip">
            <span class="dot"></span>
            <?= e($rankTitle) ?> · Level <?= (int)$level ?>
        </div>
    </div>

    <!-- Toasts -->
    <?php if ($success): ?>
        <div class="pf-toast success"><i class="fas fa-circle-check"></i> <?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="pf-toast error"><i class="fas fa-circle-exclamation"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <!-- Layout -->
    <div class="pf-layout">

        <!-- ============= AVATAR CARD ============= -->
        <div>
            <div class="pf-avatar-card">
                <form method="post" enctype="multipart/form-data" id="pfPhotoForm" style="display:contents;">
                    <input type="file" name="profile_picture" id="pf-file-input" accept="image/*">

                    <div class="pf-avatar-ring" id="pfAvatarRing" title="Click or drag & drop a photo">
                        <div class="inner">
                            <?php if (!empty($profilePic) && file_exists($profilePic)): ?>
                                <img id="pfAvatarImg" src="<?= e($profilePic) ?>" alt="<?= e($user['full_name']) ?>">
                            <?php else: ?>
                                <div class="initial" id="pfAvatarInitial"><?= e($initial) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="upload-hint">
                            <i class="fas fa-camera"></i>
                            <span>Change Photo</span>
                        </div>
                    </div>
                </form>

                <div class="pf-avatar-name"><?= e($user['full_name']) ?></div>
                <div class="pf-avatar-email"><?= e($user['email']) ?></div>

                <div class="pf-rank-chip">
                    <i class="fas <?= e($rankIcon) ?>"></i>
                    <?= e($rankTitle) ?> · LV <?= (int)$level ?>
                </div>

                <div class="pf-avatar-meta">
                    <span><i class="fas fa-calendar"></i> Joined <?= e($memberSince) ?></span>
                    <span><i class="fas fa-bolt"></i> <?= (int)$xp ?> XP</span>
                </div>
            </div>

            <!-- Stat pills -->
            <div class="pf-stats-card">
                <div class="pf-stats-row">
                    <div class="pf-stat">
                        <div class="pf-stat-icon articles"><i class="fas fa-book"></i></div>
                        <div class="pf-stat-info">
                            <div class="pf-stat-value"><?= (int)$statArticles ?></div>
                            <div class="pf-stat-label">Articles</div>
                        </div>
                    </div>
                    <div class="pf-stat">
                        <div class="pf-stat-icon chats"><i class="fas fa-comments"></i></div>
                        <div class="pf-stat-info">
                            <div class="pf-stat-value"><?= (int)$statChats ?></div>
                            <div class="pf-stat-label">AI Chats</div>
                        </div>
                    </div>
                    <div class="pf-stat">
                        <div class="pf-stat-icon saves"><i class="fas fa-bookmark"></i></div>
                        <div class="pf-stat-info">
                            <div class="pf-stat-value"><?= (int)$statSaves ?></div>
                            <div class="pf-stat-label">Saved</div>
                        </div>
                    </div>
                    <div class="pf-stat">
                        <div class="pf-stat-icon votes"><i class="fas fa-thumbs-up"></i></div>
                        <div class="pf-stat-info">
                            <div class="pf-stat-value"><?= (int)$statVotes ?></div>
                            <div class="pf-stat-label">Votes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============= FORM CARD ============= -->
        <div class="pf-form-card">
            <h3 class="pf-form-title"><i class="fas fa-pen-to-square"></i> Edit Profile</h3>
            <p class="pf-form-sub">Your name and email are managed by your administrator and can't be changed here.</p>

            <form method="post" id="pfBioForm">
                <div class="pf-group">
                    <label class="pf-label">
                        <span>Full Name</span>
                        <span style="color:rgba(136,146,176,0.6);font-weight:500;">Read-only</span>
                    </label>
                    <div class="pf-input-wrap">
                        <input type="text" class="pf-input" value="<?= e($user['full_name']) ?>" readonly>
                        <i class="fas fa-user pf-input-icon"></i>
                    </div>
                </div>

                <div class="pf-group">
                    <label class="pf-label">
                        <span>Email Address</span>
                        <span style="color:rgba(136,146,176,0.6);font-weight:500;">Read-only</span>
                    </label>
                    <div class="pf-input-wrap">
                        <input type="email" class="pf-input" value="<?= e($user['email']) ?>" readonly>
                        <i class="fas fa-envelope pf-input-icon"></i>
                    </div>
                </div>

                <div class="pf-group">
                    <label class="pf-label" for="pf-bio">
                        <span>Bio</span>
                        <span class="pf-counter" id="pf-bio-counter">0 / 500</span>
                    </label>
                    <textarea name="bio" id="pf-bio" class="pf-textarea" maxlength="500"
                              placeholder="Tell others a bit about yourself — your role, interests, or what kind of problems you like solving."><?= e($user['bio'] ?? '') ?></textarea>
                    <div class="pf-hint"><i class="fas fa-circle-info"></i> Maximum 500 characters. Press Shift + Enter for a new line.</div>
                </div>

                <div class="pf-btn-row">
                    <button type="submit" class="pf-btn-save" id="pfSaveBtn">
                        <i class="fas fa-floppy-disk"></i> Save Changes
                    </button>
                    <a href="dashboard.php" class="pf-btn-ghost">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>



<?php require_once __DIR__ . '/../partials/footer.php'; ?>