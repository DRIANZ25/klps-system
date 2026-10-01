<?php
$page_title = 'Employee Dashboard';
require_once __DIR__ . '/../partials/header.php';

$pdo = db();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge WHERE created_by = ?');
$stmt->execute([$user['id']]);
$myKnowledge = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM ai_conversations WHERE user_id = ?');
$stmt->execute([$user['id']]);
$myChats = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM knowledge_saves WHERE user_id = ?');
$stmt->execute([$user['id']]);
$bookmarks = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT k.*, u.full_name FROM knowledge k JOIN users u ON k.created_by = u.id ORDER BY k.created_at DESC LIMIT 5');
$stmt->execute();
$recentKnowledge = $stmt->fetchAll();

$xp = ($myKnowledge * 10) + ($myChats * 3) + ($bookmarks * 2);
$xpPerLevel = 50;
$level = intdiv($xp, $xpPerLevel) + 1;
$xpIntoLevel = $xp % $xpPerLevel;
$xpProgressPct = (int) round(($xpIntoLevel / $xpPerLevel) * 100);
$xpToNext = $xpPerLevel - $xpIntoLevel;

$ranks = [
    1 => ['title' => 'Seeker', 'icon' => 'fa-magnifying-glass'],
    2 => ['title' => 'Apprentice', 'icon' => 'fa-book'],
    3 => ['title' => 'Scholar', 'icon' => 'fa-graduation-cap'],
    4 => ['title' => 'Archivist', 'icon' => 'fa-book-atlas'],
    5 => ['title' => 'Sage', 'icon' => 'fa-brain'],
];
$rankKey = min($level, 5);
$rankTitle = $ranks[$rankKey]['title'];
$rankIcon = $ranks[$rankKey]['icon'];

function klps_initials_avatar(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(substr($parts[0] ?? '?', 0, 1) . substr($parts[1] ?? '', 0, 1));
    return $initials ?: '?';
}
?>
<style>
:root{
    --bg:#0a0e1a;
    --panel:#11172a;
    --panel-2:#151d34;
    --border:rgba(255,255,255,0.07);
    --gold:#f6c453;
    --violet:#8b7cf6;
    --cyan:#4fd7e8;
    --text:#e7ecf7;
    --text-dim:#8892b0;
}
*{box-sizing:border-box;}
body{
    background:radial-gradient(ellipse at 20% -10%, #1a2140 0%, var(--bg) 45%, #050710 100%);
    position:relative;
    overflow-x:hidden;
    font-family:'Inter', sans-serif;
    color:var(--text);
}
h1,h2,h3,h4,h5,h6,.brand{font-family:'Orbitron', sans-serif;}

.welcome-section{
    position:relative;
    overflow:hidden;
    background:linear-gradient(135deg, #0a0e1a 0%, #0d1224 50%, #1a0e2e 100%);
    border:1px solid rgba(139,124,246,0.15);
    border-radius:20px;
    padding:2rem 2.25rem;
    margin-bottom:1.75rem;
    box-shadow:0 20px 40px -20px rgba(139,124,246,0.35);
    min-height:200px;
}
.welcome-section .glow-orb {
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
    z-index: 0;
    filter: blur(60px);
}
.welcome-section .glow-orb.orb1 {
    width: 250px;
    height: 250px;
    background: rgba(139,124,246,0.08);
    top: -80px;
    right: -30px;
    animation: orbFloat 8s ease-in-out infinite;
}
.welcome-section .glow-orb.orb2 {
    width: 150px;
    height: 150px;
    background: rgba(0,240,255,0.06);
    bottom: -60px;
    right: 80px;
    animation: orbFloat 10s ease-in-out infinite reverse;
}
@keyframes orbFloat {
    0%, 100% { transform: translateY(0px) scale(1); }
    50% { transform: translateY(-20px) scale(1.05); }
}
#neonBrainCanvas {
    position: absolute;
    top: 0;
    right: 0;
    width: 350px;
    height: 100%;
    pointer-events: none;
    opacity: 0.6;
    z-index: 0;
}
.welcome-section .content-wrapper {
    position: relative;
    z-index: 1;
}
.welcome-section .welcome-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    flex-wrap:wrap;
    gap:1.25rem;
}
.welcome-section h2{
    font-size:1.5rem;
    font-weight:700;
    margin-bottom:.4rem;
    color:#fff;
}
.welcome-section h2 .wave {
    display:inline-block;
    animation:wave 2s ease-in-out infinite;
}
@keyframes wave {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(20deg); }
    75% { transform: rotate(-10deg); }
}
.welcome-section p{
    color:var(--text-dim);
    margin-bottom:0;
    font-size:.95rem;
    max-width:550px;
}
.rank-badge{
    display:flex;
    align-items:center;
    gap:.75rem;
    background:rgba(0,0,0,0.25);
    border:1px solid rgba(246,196,83,0.35);
    border-radius:14px;
    padding:.75rem 1.1rem;
    min-width:180px;
    backdrop-filter:blur(10px);
    position:relative;
    z-index:1;
}
.rank-badge .rank-icon{
    width:46px;
    height:46px;
    border-radius:50%;
    background:linear-gradient(135deg, var(--gold), #d99a2b);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.2rem;
    color:#20160a;
    flex-shrink:0;
    box-shadow:0 0 16px rgba(246,196,83,0.5);
    animation:rankPulse 2s ease-in-out infinite;
}
@keyframes rankPulse {
    0%, 100% { box-shadow: 0 0 16px rgba(246,196,83,0.5); }
    50% { box-shadow: 0 0 25px rgba(246,196,83,0.8); }
}
.rank-badge .rank-title{
    font-weight:700;
    color:#fff;
    font-size:1rem;
    font-family:'Orbitron',sans-serif;
}
.rank-badge .rank-sub{
    font-size:.72rem;
    color:var(--text-dim);
    text-transform:uppercase;
    letter-spacing:.5px;
}
.xp-track{margin-top:1.25rem; position:relative; z-index:1;}
.xp-track-labels{display:flex; justify-content:space-between; font-size:.75rem; color:var(--text-dim); margin-bottom:.35rem;}
.xp-bar{
    height:8px;
    border-radius:6px;
    background:rgba(255,255,255,0.06);
    overflow:hidden;
    position:relative;
}
.xp-bar .xp-bar-fill{
    height:100%;
    border-radius:6px;
    background:linear-gradient(90deg, var(--violet), var(--gold));
    box-shadow:0 0 20px rgba(246,196,83,0.3);
    transition:width 1.2s cubic-bezier(0.4, 0, 0.2, 1);
    position:relative;
}
.xp-bar .xp-bar-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: shimmer 2s infinite;
}
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.welcome-actions{
    margin-top:1.5rem;
    position:relative;
    z-index:1;
    display:flex;
    gap:.6rem;
    flex-wrap:wrap;
}
.btn-quest{
    border:none;
    border-radius:12px;
    padding:.6rem 1.3rem;
    font-weight:600;
    font-size:.88rem;
    display:inline-flex;
    align-items:center;
    gap:.5rem;
    text-decoration:none;
    transition:all .25s ease;
    cursor:pointer;
}
.btn-quest-primary{
    background:linear-gradient(90deg, var(--violet), #6c5ce7);
    color:#fff;
    box-shadow:0 8px 20px -6px rgba(139,124,246,0.6);
}
.btn-quest-primary:hover{
    transform:translateY(-2px);
    box-shadow:0 12px 26px -6px rgba(139,124,246,0.75);
    color:#fff;
}
.btn-quest-outline{
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.12);
    color:var(--text);
}
.btn-quest-outline:hover{
    background:rgba(255,255,255,0.09);
    color:#fff;
    transform:translateY(-2px);
}
.stats-card{
    background:linear-gradient(160deg, var(--panel) 0%, var(--panel-2) 100%);
    border:1px solid var(--border);
    border-radius:16px;
    padding:1.4rem;
    height:100%;
    transition:all .3s ease;
    position:relative;
    overflow:hidden;
}
.stats-card:hover{
    transform:translateY(-4px);
    border-color:rgba(246,196,83,0.35);
    box-shadow:0 14px 28px -12px rgba(0,0,0,0.5);
}
.stats-card::after{
    content:'';
    position:absolute;
    right:-30px;
    bottom:-30px;
    width:110px;
    height:110px;
    border-radius:50%;
    background:radial-gradient(circle, var(--stat-glow, rgba(139,124,246,0.15)) 0%, transparent 70%);
}
.stats-icon{
    width:46px;
    height:46px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.3rem;
    margin-bottom:1rem;
    color:#0a0e1a;
}
.stats-icon-knowledge{background:linear-gradient(135deg, var(--violet), #6c5ce7);}
.stats-icon-chats{background:linear-gradient(135deg, var(--cyan), #2ba9bb);}
.stats-icon-bookmarks{background:linear-gradient(135deg, var(--gold), #d99a2b);}
.stats-label{font-size:.78rem; font-weight:600; text-transform:uppercase; letter-spacing:.6px; color:var(--text-dim); margin-bottom:.4rem;}
.stats-value{font-size:2.3rem; font-weight:800; color:#fff; line-height:1; font-family:'Orbitron',sans-serif;}
.stats-foot{font-size:.78rem; color:var(--text-dim); margin-top:.5rem; display:flex; align-items:center; gap:.35rem;}
.stats-foot i{color:var(--gold);}
.card{
    border:1px solid var(--border);
    border-radius:16px;
    background:linear-gradient(160deg, var(--panel) 0%, #0e1326 100%);
    box-shadow:0 8px 24px -18px rgba(0,0,0,0.7);
}
.card-header{background:transparent; border-bottom:1px solid var(--border); padding:1.1rem 1.25rem .9rem;}
.card-title{font-size:1.05rem; font-weight:700; color:#fff; display:flex; align-items:center; margin-bottom:0; letter-spacing:.3px;}
.card-title i{margin-right:.55rem; color:var(--gold);}
.card-body{padding:1.25rem;}
.timeline{position:relative; padding-left:1.6rem;}
.timeline::before{
    content:'';
    position:absolute;
    left:6px;
    top:4px;
    bottom:4px;
    width:2px;
    background:linear-gradient(180deg, var(--gold), rgba(139,124,246,0.15));
}
.timeline-item{position:relative; margin-bottom:1.35rem;}
.timeline-item:last-child{margin-bottom:0;}
.timeline-item::before{
    content:'';
    position:absolute;
    left:-1.6rem;
    top:.25rem;
    width:12px;
    height:12px;
    border-radius:50%;
    background:var(--gold);
    box-shadow:0 0 10px rgba(246,196,83,0.7);
    border:2px solid #0e1326;
}
.timeline-title{font-weight:700; color:#fff; margin-bottom:.25rem; font-size:.95rem;}
.timeline-meta{display:flex; flex-wrap:wrap; gap:.9rem; font-size:.8rem; color:var(--text-dim);}
.timeline-meta i{color:var(--violet); margin-right:.25rem;}
.knowledge-grid{display:grid; grid-template-columns:repeat(auto-fill, minmax(270px, 1fr)); gap:1.35rem; margin-top:.25rem;}
.knowledge-card{
    background:linear-gradient(160deg, #141b34 0%, #0e1326 100%);
    border:1px solid var(--border);
    border-radius:14px;
    overflow:hidden;
    height:100%;
    display:flex;
    flex-direction:column;
    transition:all .3s ease;
    position:relative;
}
.knowledge-card:hover{
    transform:translateY(-5px);
    border-color:rgba(246,196,83,0.4);
    box-shadow:0 16px 30px -16px rgba(0,0,0,0.7);
}
.knowledge-card-spine{height:4px; background:linear-gradient(90deg, var(--violet), var(--gold));}
.knowledge-card-header{padding:1.1rem 1.15rem .5rem;}
.knowledge-card-title{font-size:1rem; font-weight:700; color:#fff; margin-bottom:.5rem; display:flex; align-items:start; gap:.5rem;}
.knowledge-card-title i{color:var(--gold); margin-top:.15rem; flex-shrink:0;}
.knowledge-card-excerpt{
    color:var(--text-dim);
    font-size:.84rem;
    line-height:1.5;
    margin-bottom:1rem;
    display:-webkit-box;
    -webkit-line-clamp:3;
    -webkit-box-orient:vertical;
    overflow:hidden;
}
.knowledge-card-footer{
    margin-top:auto;
    padding:.9rem 1.15rem;
    border-top:1px solid var(--border);
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.knowledge-card-author{display:flex; align-items:center; gap:.5rem; font-size:.8rem; color:var(--text-dim);}
.avatar-sm{
    width:26px;
    height:26px;
    border-radius:50%;
    flex-shrink:0;
    background:linear-gradient(135deg, var(--cyan), var(--violet));
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:.68rem;
    font-weight:700;
    color:#0a0e1a;
}
.knowledge-card-date{font-size:.72rem; color:#5c6786;}
.empty-state{text-align:center; padding:3rem 1.5rem; color:var(--text-dim);}
.empty-state i{font-size:2.6rem; margin-bottom:1.25rem; color:rgba(139,124,246,0.4);}
.empty-state h3{font-size:1.25rem; font-weight:700; margin-bottom:.5rem; color:#fff;}

@media (max-width: 768px) {
    .welcome-section { padding: 1.25rem; min-height: auto; }
    .welcome-section h2 { font-size: 1.1rem; }
    .welcome-section p { font-size: 0.85rem; }
    .rank-badge { min-width: auto; padding: 0.5rem 0.75rem; }
    .rank-badge .rank-icon { width: 36px; height: 36px; font-size: 1rem; }
    .stats-value { font-size: 1.5rem; }
    .knowledge-grid { grid-template-columns: 1fr; }
    .timeline { padding-left: 1.2rem; }
    .timeline-item::before { width: 10px; height: 10px; left: -1.2rem; }
    #neonBrainCanvas { width: 120px; opacity: 0.3; }
}
@media (max-width: 576px) {
    .welcome-section { padding: 0.75rem; }
    .welcome-section h2 { font-size: 0.95rem; }
    .welcome-actions { flex-direction: column; }
    .welcome-actions .btn-quest { width: 100%; justify-content: center; }
    .stats-card { padding: 1rem; }
    .stats-icon { width: 36px; height: 36px; font-size: 1rem; }
    .stats-value { font-size: 1.3rem; }
    .rank-badge { flex-direction: row; padding: 0.4rem 0.6rem; }
    .rank-badge .rank-icon { width: 30px; height: 30px; font-size: 0.8rem; }
    #neonBrainCanvas { display: none; }
}
</style>

<div class="container-fluid">
    <div class="row">
        <main class="col-12 p-4">

            <div class="welcome-section">
                <div class="glow-orb orb1"></div>
                <div class="glow-orb orb2"></div>
                <canvas id="neonBrainCanvas"></canvas>
                <div class="content-wrapper">
                    <div class="welcome-top">
                        <div>
                            <h2><span class="wave">👋</span> Welcome back, <?= e($user['full_name']) ?>!</h2>
                            <p>Every question asked and every answer shared expands the archive. Where will your search for knowledge take you today?</p>
                        </div>
                        <div class="rank-badge">
                            <div class="rank-icon"><i class="fas <?= e($rankIcon) ?>"></i></div>
                            <div>
                                <div class="rank-title"><?= e($rankTitle) ?></div>
                                <div class="rank-sub">Level <?= (int)$level ?> · <?= (int)$xp ?> XP</div>
                            </div>
                        </div>
                    </div>
                    <div class="xp-track">
                        <div class="xp-track-labels">
                            <span>Level <?= (int)$level ?> progress</span>
                            <span><?= (int)$xpToNext ?> XP to Level <?= (int)$level + 1 ?></span>
                        </div>
                        <div class="xp-bar">
                            <div class="xp-bar-fill" style="width:<?= $xpProgressPct ?>%"></div>
                        </div>
                    </div>
                    <div class="welcome-actions">
                        <a href="ai_chat.php" class="btn-quest btn-quest-primary"><i class="fas fa-robot"></i>Start AI Chat</a>
                        <a href="knowledge.php" class="btn-quest btn-quest-outline"><i class="fas fa-book-open"></i>Explore Knowledge</a>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stats-card" style="--stat-glow:rgba(139,124,246,0.18)">
                        <div class="stats-icon stats-icon-knowledge"><i class="fas fa-book"></i></div>
                        <div class="stats-label">Knowledge Contributed</div>
                        <div class="stats-value"><?= $myKnowledge ?></div>
                        <div class="stats-foot"><i class="fas fa-seedling"></i>Articles you've added to the archive</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card" style="--stat-glow:rgba(79,215,232,0.18)">
                        <div class="stats-icon stats-icon-chats"><i class="fas fa-comments"></i></div>
                        <div class="stats-label">AI Conversations</div>
                        <div class="stats-value"><?= $myChats ?></div>
                        <div class="stats-foot"><i class="fas fa-wand-magic-sparkles"></i>Questions explored with your AI assistant</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card" style="--stat-glow:rgba(246,196,83,0.2)">
                        <div class="stats-icon stats-icon-bookmarks"><i class="fas fa-bookmark"></i></div>
                        <div class="stats-label">Saved Knowledge</div>
                        <div class="stats-value"><?= $bookmarks ?></div>
                        <div class="stats-foot"><i class="fas fa-map-pin"></i>Articles bookmarked for later reading</div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title"><i class="fas fa-timeline"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recentKnowledge)): ?>
                                <div class="timeline">
                                    <?php foreach ($recentKnowledge as $item): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-title"><?= e($item['title']) ?></div>
                                            <div class="timeline-meta">
                                                <span><i class="fas fa-user"></i><?= e($item['full_name']) ?></span>
                                                <span><i class="fas fa-calendar"></i><?= date('M d, Y', strtotime($item['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-compass-drafting"></i>
                                    <h3>No recent activity</h3>
                                    <p>Start contributing knowledge to see your activity here.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title"><i class="fas fa-layer-group"></i>Featured Knowledge</h5>
                        </div>
                        <div class="card-body">
                            <div class="knowledge-grid">
                                <?php if (!empty($recentKnowledge)): ?>
                                    <?php foreach ($recentKnowledge as $item): ?>
                                        <div class="knowledge-card">
                                            <div class="knowledge-card-spine"></div>
                                            <div class="knowledge-card-header">
                                                <h6 class="knowledge-card-title"><i class="fas fa-book-open"></i><?= e($item['title']) ?></h6>
                                                <p class="knowledge-card-excerpt"><?= e(substr($item['problem'] ?? $item['content'] ?? '', 0, 150)) ?>...</p>
                                            </div>
                                            <div class="knowledge-card-footer">
                                                <div class="knowledge-card-author">
                                                    <div class="avatar-sm"><?= e(klps_initials_avatar($item['full_name'])) ?></div>
                                                    <span><?= e($item['full_name']) ?></span>
                                                </div>
                                                <div class="knowledge-card-date"><?= date('M d', strtotime($item['created_at'])) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-12">
                                        <div class="empty-state">
                                            <i class="fas fa-folder-open"></i>
                                            <h3>No knowledge available yet</h3>
                                            <p>Be the first to contribute knowledge to the system!</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('neonBrainCanvas');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    let width, height;
    let animationId = null;
    let nodes = [];
    let connections = [];

    function resize() {
        const rect = canvas.parentElement.getBoundingClientRect();
        width = canvas.width = Math.min(rect.width, 350);
        height = canvas.height = rect.height || 200;
    }
    resize();
    window.addEventListener('resize', resize);

    function createNodes() {
        nodes = [];
        const numNodes = 25;
        const centerX = width * 0.5;
        const centerY = height * 0.5;
        
        for (let i = 0; i < numNodes; i++) {
            const angle = Math.random() * Math.PI * 2;
            const radius = 20 + Math.random() * 70;
            const cx = centerX + Math.cos(angle) * radius;
            const cy = centerY + Math.sin(angle) * radius * 0.6;
            nodes.push({
                x: cx,
                y: cy,
                baseX: cx,
                baseY: cy,
                radius: 1.5 + Math.random() * 2.5,
                phase: Math.random() * Math.PI * 2,
                speed: 0.005 + Math.random() * 0.01,
                amplitude: 4 + Math.random() * 10,
                pulseSpeed: 0.02 + Math.random() * 0.02
            });
        }
    }
    createNodes();

    function updateConnections() {
        connections = [];
        const maxDist = 70;
        for (let i = 0; i < nodes.length; i++) {
            for (let j = i + 1; j < nodes.length; j++) {
                const dx = nodes[i].x - nodes[j].x;
                const dy = nodes[i].y - nodes[j].y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < maxDist && Math.random() < 0.3) {
                    connections.push({
                        i: i,
                        j: j,
                        dist: dist,
                        maxDist: maxDist
                    });
                }
            }
        }
    }
    updateConnections();

    let time = 0;
    let frameCount = 0;

    function animate() {
        frameCount++;
        time += 0.008;
        
        if (document.hidden) {
            animationId = requestAnimationFrame(animate);
            return;
        }

        ctx.clearRect(0, 0, width, height);

        for (let i = 0; i < nodes.length; i++) {
            const node = nodes[i];
            const floatX = Math.sin(time * node.speed + node.phase) * node.amplitude;
            const floatY = Math.cos(time * node.speed * 0.7 + node.phase * 1.3) * node.amplitude * 0.6;
            node.x = node.baseX + floatX;
            node.y = node.baseY + floatY;
            const pulse = 0.6 + 0.4 * Math.sin(time * node.pulseSpeed + node.phase);
            node.currentRadius = node.radius * pulse;
        }

        if (frameCount % 30 === 0) {
            updateConnections();
        }

        for (let c = 0; c < connections.length; c++) {
            const conn = connections[c];
            const alpha = 0.1 + 0.15 * (1 - conn.dist / conn.maxDist);
            const nodeI = nodes[conn.i];
            const nodeJ = nodes[conn.j];
            
            ctx.beginPath();
            ctx.moveTo(nodeI.x, nodeI.y);
            ctx.lineTo(nodeJ.x, nodeJ.y);
            ctx.strokeStyle = `rgba(0, 240, 255, ${alpha})`;
            ctx.lineWidth = 0.5 + (1 - conn.dist / conn.maxDist);
            ctx.shadowColor = 'rgba(0, 240, 255, 0.05)';
            ctx.shadowBlur = 4;
            ctx.stroke();
        }

        for (let i = 0; i < nodes.length; i++) {
            const node = nodes[i];
            const pulse = 0.6 + 0.4 * Math.sin(time * node.pulseSpeed + node.phase);
            
            const grad = ctx.createRadialGradient(
                node.x, node.y, 0,
                node.x, node.y, node.currentRadius * 4
            );
            grad.addColorStop(0, `rgba(0, 240, 255, ${0.2 + 0.3 * pulse})`);
            grad.addColorStop(0.5, `rgba(139, 124, 246, ${0.1 + 0.15 * pulse})`);
            grad.addColorStop(1, 'rgba(0, 240, 255, 0)');
            
            ctx.beginPath();
            ctx.arc(node.x, node.y, node.currentRadius * 4, 0, Math.PI * 2);
            ctx.fillStyle = grad;
            ctx.fill();

            ctx.beginPath();
            ctx.arc(node.x, node.y, node.currentRadius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(0, 240, 255, ${0.5 + 0.4 * pulse})`;
            ctx.shadowColor = `rgba(0, 240, 255, ${0.1 + 0.2 * pulse})`;
            ctx.shadowBlur = 10;
            ctx.fill();
        }

        ctx.shadowBlur = 0;
        animationId = requestAnimationFrame(animate);
    }

    animate();

    window.addEventListener('beforeunload', function() {
        if (animationId) {
            cancelAnimationFrame(animationId);
        }
    });

    window.addEventListener('resize', function() {
        resize();
        createNodes();
        updateConnections();
    });
});
</script>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>