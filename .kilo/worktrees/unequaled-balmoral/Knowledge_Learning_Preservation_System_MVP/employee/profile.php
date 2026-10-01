<?php
$page_title = 'Profile Settings';
require_once __DIR__ . '/../partials/header.php';

$currentUserId = $_SESSION['user']['id'] ?? 0;
$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = trim($_POST['bio'] ?? '');
    
    // Update bio
    try {
        $stmt = db()->prepare('UPDATE users SET bio = ? WHERE id = ?');
        $stmt->execute([$bio, $currentUserId]);
        $_SESSION['user']['bio'] = $bio;
        $success = 'Profile updated successfully!';
    } catch (PDOException $e) {
        $error = 'Failed to update profile.';
    }
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array(strtolower($ext), $allowed)) {
            $error = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
        } else {
            $filename = 'user_' . $currentUserId . '_' . time() . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filepath)) {
                // Delete old profile picture
                $stmt = db()->prepare('SELECT profile_picture FROM users WHERE id = ?');
                $stmt->execute([$currentUserId]);
                $old = $stmt->fetchColumn();
                if ($old && file_exists($uploadDir . $old)) {
                    unlink($uploadDir . $old);
                }
                
                $stmt = db()->prepare('UPDATE users SET profile_picture = ? WHERE id = ?');
                $stmt->execute([$filename, $currentUserId]);
                $_SESSION['user']['profile_picture'] = $filename;
                $success = 'Profile picture updated successfully!';
            } else {
                $error = 'Failed to upload profile picture.';
            }
        }
    }
}

// Get user data
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$currentUserId]);
$user = $stmt->fetch();

$profilePic = !empty($user['profile_picture']) ? '../uploads/profiles/' . $user['profile_picture'] : '';
?>
<style>
.profile-page {
    background: radial-gradient(ellipse at 20% -10%, #1a2140 0%, #03050a 45%, #050710 100%);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(0,240,255,0.08);
}
.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 16px;
    object-fit: cover;
    border: 3px solid rgba(0,240,255,0.15);
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: #fff;
}
.profile-avatar-wrap {
    position: relative;
    display: inline-block;
}
.upload-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0,0,0,0.7);
    border-radius: 0 0 16px 16px;
    padding: 0.5rem;
    text-align: center;
    cursor: pointer;
    color: #fff;
    font-size: 0.7rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.profile-avatar-wrap:hover .upload-overlay {
    opacity: 1;
}
</style>

<div class="profile-page">
    <h1 style="font-family:'Orbitron',sans-serif;font-size:1.5rem;color:#fff;margin-bottom:0.5rem;">
        <i class="fas fa-user-cog" style="color:#f6c453;"></i> Profile Settings
    </h1>
    <p style="color:#8892b0;margin-bottom:2rem;">Update your profile picture and personal information.</p>

    <?php if ($success): ?>
        <div style="padding:1rem 1.5rem;background:rgba(0,255,156,0.08);border:1px solid rgba(0,255,156,0.2);border-radius:10px;color:#00ff9c;margin-bottom:1.5rem;">
            <i class="fas fa-check-circle me-2"></i> <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="padding:1rem 1.5rem;background:rgba(255,0,60,0.08);border:1px solid rgba(255,0,60,0.2);border-radius:10px;color:#ff7c93;margin-bottom:1.5rem;">
            <i class="fas fa-exclamation-circle me-2"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="profile-avatar-wrap">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= e($profilePic) ?>" alt="Profile" class="profile-avatar">
                <?php else: ?>
                    <div class="profile-avatar">
                        <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="upload-overlay" onclick="document.getElementById('profile_pic_input').click();">
                    <i class="fas fa-camera"></i> Change Photo
                </div>
            </div>
            <p style="color:#8892b0;font-size:0.75rem;margin-top:0.5rem;">Click the photo to upload a new one. Supported: JPG, PNG, GIF, WEBP</p>
        </div>
        <div class="col-md-8">
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="profile_picture" id="profile_pic_input" style="display:none;" accept="image/*" onchange="this.form.submit()">
                
                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="color:#8892b0;font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.3rem;">Full Name</label>
                    <input type="text" value="<?= e($user['full_name']) ?>" class="form-control" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);border-radius:10px;color:#fff;padding:0.6rem 1rem;width:100%;" readonly>
                </div>

                <div class="form-group" style="margin-bottom:1rem;">
                    <label style="color:#8892b0;font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.3rem;">Email</label>
                    <input type="email" value="<?= e($user['email']) ?>" class="form-control" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);border-radius:10px;color:#fff;padding:0.6rem 1rem;width:100%;" readonly>
                </div>

                <div class="form-group" style="margin-bottom:1.5rem;">
                    <label style="color:#8892b0;font-size:0.8rem;font-weight:600;display:block;margin-bottom:0.3rem;">Bio</label>
                    <textarea name="bio" class="form-control" rows="4" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);border-radius:10px;color:#fff;padding:0.6rem 1rem;width:100%;resize:vertical;"><?= e($user['bio'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg,#00f0ff,#8b7cf6);border:none;border-radius:10px;padding:0.6rem 1.5rem;color:#0a0e1a;font-weight:700;">
                    <i class="fas fa-save"></i> Save Profile
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>