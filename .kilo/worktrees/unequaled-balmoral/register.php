<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user'])) {
    redirect($_SESSION['user']['role'] === 'admin' ? 'admin/dashboard.php' : 'employee/dashboard.php');
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'employee';

    // Basic validation
    if (empty($email) || empty($password) || empty($confirm_password) || empty($full_name)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        // Force role to employee (admin registration removed - only 1 admin accessible)
        $role = 'employee';

        // Check if user already exists
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            // Create new user
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, 1)');
            if ($stmt->execute([$email, $password_hash, $full_name, $role])) {
                $success = 'Account created successfully! Please login.';
            } else {
                $error = 'Failed to create account. Please try again.';
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
<style>
:root{
    --cyan:#00f0ff;
    --magenta:#ff2fd0;
    --green:#00ff9c;
    --bg:#03050a;
    --panel:rgba(6,10,20,0.72);
}
*{box-sizing:border-box;}
html,body{height:100%;}
body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    font-family:'Share Tech Mono', monospace;
    padding:1rem;
    position:relative;
    overflow:hidden;
    background:radial-gradient(ellipse at 50% -10%, #0a1330 0%, var(--bg) 55%, #000 100%);
    color:#dfe9ff;
}
h1,h2,h3,h4,.brand-logo span,.form-section-title{font-family:'Orbitron', sans-serif;}

/* ---------- BOOT / PRELOADER ---------- */
#boot-screen{
    position:fixed; inset:0; z-index:999;
    background:#000;
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:1rem;
    transition:opacity .6s ease, visibility .6s ease;
}
#boot-screen.hide{opacity:0; visibility:hidden; pointer-events:none;}
.boot-logo{
    font-family:'Orbitron', sans-serif;
    font-size:2.2rem; font-weight:900; letter-spacing:6px;
    color:var(--magenta);
    text-shadow:0 0 8px var(--magenta), 0 0 24px rgba(255,47,208,0.6);
}
.boot-line{
    font-family:'Share Tech Mono', monospace;
    color:var(--green);
    font-size:.85rem;
    min-height:1.2em;
    letter-spacing:1px;
}
.boot-line::after{
    content:'_';
    animation:blink 1s steps(1) infinite;
}
@keyframes blink{50%{opacity:0;}}
.boot-bar-track{
    width:260px; height:4px; background:rgba(255,255,255,0.08);
    border-radius:2px; overflow:hidden;
    box-shadow:inset 0 0 4px rgba(0,0,0,0.6);
}
.boot-bar-fill{
    height:100%; width:0%;
    background:linear-gradient(90deg, var(--magenta), var(--cyan));
    box-shadow:0 0 10px var(--magenta);
    animation:bootfill 1.6s cubic-bezier(.4,0,.2,1) forwards;
}
@keyframes bootfill{to{width:100%;}}

/* ---------- PAGE ENTRANCE ---------- */
.landscape-wrapper{
    display:flex; width:100%; max-width:1200px; max-height:100vh;
    gap:1.5rem; position:relative; z-index:2;
    opacity:0; transform:translateY(24px) scale(.97);
}
body.revealed .landscape-wrapper{
    animation:riseIn .85s cubic-bezier(.16,1,.3,1) forwards;
}
@keyframes riseIn{
    to{opacity:1; transform:translateY(0) scale(1);}
}

/* ---------- BACKGROUND GRID + CIRCUIT NODES ---------- */
.cyber-grid{
    position:fixed; inset:-2px; z-index:0; pointer-events:none;
    background-image:
        linear-gradient(rgba(255,47,208,0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,240,255,0.05) 1px, transparent 1px);
    background-size:42px 42px;
    mask-image:radial-gradient(ellipse at center, rgba(0,0,0,1) 0%, rgba(0,0,0,0.2) 75%);
    animation:gridDrift 20s linear infinite;
}
@keyframes gridDrift{
    0%{background-position:0 0,0 0;}
    100%{background-position:42px 42px,42px 42px;}
}
.scanline{
    position:fixed; left:0; right:0; height:2px; z-index:1; pointer-events:none;
    background:linear-gradient(90deg, transparent, rgba(255,47,208,0.5), transparent);
    animation:scan 5s linear infinite;
    box-shadow:0 0 12px rgba(255,47,208,0.6);
}
@keyframes scan{
    0%{top:-5%;} 100%{top:105%;}
}
.knowledge-particle{
    position:fixed; z-index:1; pointer-events:none; color:rgba(255,47,208,0.16);
    animation:floaty 9s ease-in-out infinite;
}
@keyframes floaty{
    0%,100%{transform:translateY(0) rotate(0deg); opacity:.5;}
    50%{transform:translateY(-26px) rotate(8deg); opacity:1;}
}

/* ---------- PANELS ---------- */
.register-container, .info-container{
    flex:1; min-width:0;
    background:var(--panel);
    backdrop-filter:blur(18px);
    border-radius:16px;
    padding:1.25rem;
    position:relative;
    overflow:hidden;
    display:flex; flex-direction:column;
    border:1px solid rgba(255,47,208,0.15);
}
.info-container{flex:0 0 320px;}
.register-container::before, .info-container::before{
    content:''; position:absolute; inset:-1px; z-index:-1; border-radius:16px;
    padding:1px;
    background:conic-gradient(from var(--angle,0deg), var(--magenta), var(--cyan), var(--green), var(--magenta));
    -webkit-mask:linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite:xor; mask-composite:exclude;
    animation:rotateBorder 6s linear infinite;
    opacity:.8;
}
@property --angle{syntax:'<angle>'; inherits:false; initial-value:0deg;}
@keyframes rotateBorder{to{--angle:360deg;}}

@media (max-width:900px){
    .landscape-wrapper{flex-direction:column; max-width:500px; max-height:none;}
    .info-container{flex:1;}
}

/* ---------- HEADER ---------- */
.register-header{text-align:center; margin-bottom:1rem; flex-shrink:0;}
.brand-logo{display:inline-flex; align-items:center; margin-bottom:.5rem; gap:.5rem;}
.brand-logo i{
    font-size:1.9rem; color:var(--magenta);
    text-shadow:0 0 10px var(--magenta), 0 0 22px rgba(255,47,208,.6);
    animation:pulseIcon 2.4s ease-in-out infinite;
}
@keyframes pulseIcon{0%,100%{filter:brightness(1);}50%{filter:brightness(1.4);}}
.brand-logo span{
    font-size:1.5rem; font-weight:900; letter-spacing:3px; color:#fff;
    text-shadow:0 0 6px rgba(255,47,208,.5);
}
.register-header h2{
    font-size:1.1rem; font-weight:700; color:var(--magenta);
    letter-spacing:.5px; margin-bottom:.25rem;
    text-shadow:0 0 8px rgba(255,47,208,.4);
}
.register-header p{color:#8fa3c9; font-size:.82rem; margin-bottom:.75rem;}

.social-proof{display:flex; justify-content:center; flex-wrap:wrap; gap:.5rem;}
.badge-item{
    display:flex; align-items:center; gap:.4rem;
    background:rgba(255,47,208,0.05);
    padding:.35rem .75rem; border-radius:20px;
    border:1px solid rgba(255,47,208,0.2);
}
.badge-item i{font-size:.85rem; color:var(--green);}
.badge-item span{font-size:.72rem; color:#c8d6f0; letter-spacing:.5px;}

/* ---------- FORM ---------- */
.form-section{
    background:rgba(15,0,20,0.35);
    border-radius:12px; padding:1.25rem; margin-bottom:1rem; flex:1;
    border:1px solid rgba(255,47,208,0.08);
}
.form-section-title{
    display:flex; align-items:center; font-size:1.05rem; font-weight:700;
    color:#fff; margin-bottom:.35rem; letter-spacing:1px;
}
.form-section-title i{margin-right:.5rem; color:var(--cyan); text-shadow:0 0 8px var(--cyan);}
.form-section-description{color:#8fa3c9; font-size:.8rem; margin-bottom:.9rem;}

.form-label{
    font-weight:600; color:var(--magenta); margin-bottom:.35rem;
    display:flex; align-items:center; font-size:.78rem;
    text-transform:uppercase; letter-spacing:1px;
}
.form-label i{margin-right:.4rem; width:16px;}

.form-control{
    background:rgba(10,0,15,0.6);
    border:1px solid rgba(255,47,208,0.25);
    border-radius:8px; color:#fff;
    padding:.65rem .9rem; font-size:.92rem;
    font-family:'Share Tech Mono', monospace;
    transition:all .25s ease;
}
.form-control::placeholder{color:rgba(255,255,255,0.3);}
.form-control:focus{
    background:rgba(15,0,20,0.8);
    border-color:var(--magenta);
    box-shadow:0 0 0 3px rgba(255,47,208,0.15), 0 0 14px rgba(255,47,208,0.35);
    color:#fff; outline:none;
}
.input-group{display:flex; align-items:stretch;}
.input-group .form-control{flex:1; border-top-right-radius:0; border-bottom-right-radius:0;}
.input-group .btn{border-top-left-radius:0; border-bottom-left-radius:0; padding:.6rem .9rem;}

.form-row{display:flex; gap:.75rem;}
.form-col{flex:1; min-width:0;}

/* ---------- NEON GLITCH BUTTON ---------- */
.btn-neon{
    position:relative;
    background:linear-gradient(90deg, var(--magenta), var(--cyan));
    background-size:220% 100%;
    border:none; border-radius:8px;
    padding:.75rem 1.25rem;
    font-family:'Orbitron', sans-serif;
    font-weight:700; font-size:.9rem;
    letter-spacing:2px; text-transform:uppercase;
    color:#020409;
    display:flex; align-items:center; justify-content:center; gap:.5rem;
    overflow:hidden;
    cursor:pointer;
    transition:background-position .5s ease, box-shadow .3s ease, transform .15s ease;
    box-shadow:0 0 12px rgba(255,47,208,0.45), 0 0 28px rgba(0,240,255,0.25);
}
.btn-neon::before{
    content:'';
    position:absolute; top:0; left:-60%;
    width:40%; height:100%;
    background:linear-gradient(120deg, transparent, rgba(255,255,255,0.65), transparent);
    transform:skewX(-20deg);
    transition:left .6s ease;
}
.btn-neon:hover{
    background-position:100% 0;
    box-shadow:0 0 20px rgba(255,47,208,0.7), 0 0 40px rgba(0,240,255,0.45);
    transform:translateY(-2px);
    color:#000;
}
.btn-neon:hover::before{left:130%;}
.btn-neon:active{transform:translateY(0px) scale(.98);}

.toggle-password{
    background:transparent; border:1px solid rgba(255,47,208,0.25);
    border-left:none; color:rgba(255,255,255,0.6); cursor:pointer;
    font-size:.9rem; transition:all .2s ease;
}
.toggle-password:hover{color:var(--magenta); background:rgba(255,47,208,0.08);}

.text-muted a{color:var(--magenta);}
.text-muted a:hover{color:var(--cyan);}

/* ---------- ALERTS ---------- */
.alert-danger{
    background:rgba(255,0,60,0.1); border:1px solid rgba(255,0,60,0.4);
    color:#ff7c93; border-radius:10px; font-size:.85rem;
}
.alert-success{
    background:rgba(0,255,156,0.08); border:1px solid rgba(0,255,156,0.4);
    color:var(--green); border-radius:10px; font-size:.85rem;
}

/* ---------- INFO PANEL ---------- */
.info-container h3{
    display:flex; align-items:center; font-size:1rem; font-weight:700; color:#fff;
    margin-bottom:.85rem; padding-bottom:.5rem;
    border-bottom:1px solid rgba(255,47,208,0.2); flex-shrink:0; letter-spacing:1px;
}
.info-container h3 i{margin-right:.5rem; color:var(--green); text-shadow:0 0 8px var(--green);}
.info-list{flex:1; overflow-y:auto; min-height:0;}
.info-list::-webkit-scrollbar{width:5px;}
.info-list::-webkit-scrollbar-thumb{background:rgba(255,47,208,0.3); border-radius:4px;}

.info-item{display:flex; gap:.75rem; margin-bottom:.85rem; padding-bottom:.85rem; border-bottom:1px solid rgba(255,255,255,0.05);}
.info-item:last-of-type{border-bottom:none;}
.info-item > i{
    font-size:1.05rem; color:var(--cyan); flex-shrink:0;
    width:32px; height:32px; background:rgba(0,240,255,0.08);
    border:1px solid rgba(0,240,255,0.25);
    border-radius:8px; display:flex; align-items:center; justify-content:center;
    text-shadow:0 0 8px var(--cyan);
}
.info-item h4{font-size:.88rem; font-weight:700; color:#fff; margin-bottom:.25rem; font-family:'Orbitron', sans-serif;}
.info-item p{font-size:.76rem; color:#8fa3c9; margin:0; line-height:1.4;}

.info-stats{
    display:flex; justify-content:space-between; margin-top:.85rem; padding-top:.85rem;
    border-top:1px solid rgba(255,47,208,0.2); flex-shrink:0;
}
.info-stats .stat-item{text-align:center;}
.info-stats .stat-value{
    display:block; font-size:1.2rem; font-weight:900; color:var(--magenta);
    font-family:'Orbitron', sans-serif; text-shadow:0 0 8px rgba(255,47,208,.5);
}
.info-stats .stat-label{display:block; font-size:.65rem; color:#8fa3c9; text-transform:uppercase; letter-spacing:.5px;}

@media (max-width:576px){
    .landscape-wrapper{max-width:100%;}
    .register-container, .info-container{margin:.5rem; padding:1rem;}
    .brand-logo span{font-size:1.15rem;}
    .register-header h2{font-size:1rem;}
    .form-row{flex-direction:column; gap:0;}
}
</style>
</head>
<body>

<!-- Boot Screen Transition -->
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
        <div class="register-header text-center mb-4">
            <div class="brand-logo">
                <i class="fas fa-brain"></i>
                <span>KLPS</span>
            </div>
            <h2>JOIN THE KNOWLEDGE NETWORK</h2>
            <p>Create your account to start preserving and sharing knowledge</p>

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

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-circle-check me-2"></i><?= e($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-section">
                <h3 class="form-section-title"><i class="fas fa-user-plus"></i>Create Account</h3>
                <p class="form-section-description">Join thousands of knowledge professionals using KLPS</p>

                <div class="form-row">
                    <div class="form-col">
                        <div class="mb-2">
                            <label class="form-label"><i class="fas fa-id-badge"></i>Full Name</label>
                            <input name="full_name" type="text" class="form-control" required placeholder="Enter your full name">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="mb-2">
                            <label class="form-label"><i class="fas fa-envelope"></i>Email Address</label>
                            <input name="email" type="email" class="form-control" required placeholder="you@domain.com">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="mb-2">
                            <label class="form-label"><i class="fas fa-key"></i>Password</label>
                            <div class="input-group">
                                <input name="password" type="password" class="form-control" required placeholder="Create a password">
                                <button class="btn toggle-password" type="button"><i class="fas fa-eye"></i></button>
                            </div>
                            <small class="text-muted">Min. 8 characters</small>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="mb-2">
                            <label class="form-label"><i class="fas fa-key"></i>Confirm Password</label>
                            <div class="input-group">
                                <input name="confirm_password" type="password" class="form-control" required placeholder="Confirm your password">
                                <button class="btn toggle-password" type="button"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-3">
                    <button class="btn btn-neon" type="submit">
                        <i class="fas fa-user-plus"></i>Create My Account
                    </button>
                </div>
            </div>

            <div class="text-center">
                <p class="mb-0 small text-muted">Already have an account? <a href="login.php" class="fw-medium">Sign in</a></p>
            </div>
        </form>
    </div>

    <div class="info-container">
        <h3><i class="fas fa-circle-info"></i>Why Join KLPS?</h3>

        <div class="info-list">
            <div class="info-item">
                <i class="fas fa-book"></i>
                <div>
                    <h4>Preserve Knowledge</h4>
                    <p>Document and preserve institutional knowledge for future generations</p>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-robot"></i>
                <div>
                    <h4>AI Assistant</h4>
                    <p>Get instant help with technical issues from our intelligent AI system</p>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-diagram-project"></i>
                <div>
                    <h4>Collaborate</h4>
                    <p>Work with colleagues to build a comprehensive knowledge base</p>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-chart-line"></i>
                <div>
                    <h4>Track Progress</h4>
                    <p>Monitor your learning journey and knowledge contributions</p>
                </div>
            </div>
        </div>

        <div class="info-stats">
            <div class="stat-item"><span class="stat-value">10K+</span><span class="stat-label">Active Users</span></div>
            <div class="stat-item"><span class="stat-value">50K+</span><span class="stat-label">Articles</span></div>
            <div class="stat-item"><span class="stat-value">99.9%</span><span class="stat-label">Uptime</span></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const lines = [
        'PREPARING REGISTRATION PROTOCOL',
        'ALLOCATING KNOWLEDGE NODE',
        'ENCRYPTING CREDENTIAL SCHEMA',
        'READY'
    ];
    const el = document.getElementById('bootLine');
    let i = 0;
    const iv = setInterval(() => {
        i++;
        if (i < lines.length) el.textContent = lines[i];
    }, 380);

    setTimeout(() => {
        clearInterval(iv);
        document.getElementById('boot-screen').classList.add('hide');
        document.body.classList.add('revealed');
    }, 1650);

    const icons = ['fa-book', 'fa-microchip', 'fa-atom', 'fa-code-branch', 'fa-diagram-project'];
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

    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function () {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
        });
    });
});
</script>
</body>
</html>