if (window.marked) {
    marked.setOptions({ breaks: true, gfm: true, headerIds: false, mangle: false });
}
function renderMarkdown(el, raw) {
    try {
        const html = window.marked ? marked.parse(raw) : raw.replace(/&/g, '&amp;').replace(/</g, '&lt;');
        el.innerHTML = window.DOMPurify ? DOMPurify.sanitize(html, {
            ALLOWED_TAGS: ['p','br','strong','em','b','i','u','s','del','h1','h2','h3','h4','h5','h6',
                           'ul','ol','li','blockquote','hr','code','pre','a','table','thead','tbody',
                           'tr','th','td','span','div'],
            ALLOWED_ATTR: ['href','title','class','target','rel']
        }) : html;
    } catch (e) {
        el.textContent = raw;
    }
}
function renderAllMarkdown() {
    document.querySelectorAll('.markdown-body[data-raw]').forEach(function (el) {
        renderMarkdown(el, el.getAttribute('data-raw') || '');
    });
}
renderAllMarkdown();

(function initAiHead() {
    const canvas = document.getElementById('aiHeadCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let W = 0, H = 0;
    const DPR = Math.min(window.devicePixelRatio || 1, 2);

    const NODE_COUNT = 70;
    const NODES = [];
    const golden = Math.PI * (3 - Math.sqrt(5));
    for (let i = 0; i < NODE_COUNT; i++) {
        const y = 1 - (i / (NODE_COUNT - 1)) * 2;
        const r = Math.sqrt(1 - y * y);
        const theta = golden * i;
        NODES.push({
            x: Math.cos(theta) * r,
            y: y,
            z: Math.sin(theta) * r,
            pulsePhase: Math.random() * Math.PI * 2,
            pulseSpeed: 0.02 + Math.random() * 0.03,
            baseSize: 1 + Math.random() * 1.4,
        });
    }

    const EDGES = [];
    for (let i = 0; i < NODE_COUNT; i++) {
        for (let j = i + 1; j < NODE_COUNT; j++) {
            const dx = NODES[i].x - NODES[j].x;
            const dy = NODES[i].y - NODES[j].y;
            const dz = NODES[i].z - NODES[j].z;
            const d2 = dx * dx + dy * dy + dz * dz;
            if (d2 < 0.22) EDGES.push({ i, j });
        }
    }

    const SIGNALS = [];
    let signalCooldown = 0;
    function spawnSignal() {
        if (EDGES.length === 0) return;
        const e = EDGES[Math.floor(Math.random() * EDGES.length)];
        SIGNALS.push({
            edge: e, t: 0,
            speed: 0.02 + Math.random() * 0.025,
            color: Math.random() < 0.55 ? 'cyan' : (Math.random() < 0.5 ? 'gold' : 'violet'),
        });
    }

    function resize() {
        const rect = canvas.parentElement.getBoundingClientRect();
        W = rect.width; H = rect.height;
        canvas.width = W * DPR;
        canvas.height = H * DPR;
        ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    }
    resize();
    window.addEventListener('resize', resize);

    function rotate(p, ax, ay) {
        const cx = Math.cos(ax), sx = Math.sin(ax);
        const y1 = p.y * cx - p.z * sx;
        const z1 = p.y * sx + p.z * cx;
        const cy = Math.cos(ay), sy = Math.sin(ay);
        const x2 = p.x * cy + z1 * sy;
        const z2 = -p.x * sy + z1 * cy;
        return { x: x2, y: y1, z: z2 };
    }
    function project(p) {
        const fov = 2.6;
        const scale = Math.min(W, H) * 0.32;
        const z = p.z + fov;
        return { x: W / 2 + (p.x * scale) / z, y: H / 2 + (p.y * scale) / z, depth: z };
    }
    function getSignalColor(color, alpha) {
        switch (color) {
            case 'cyan':   return `rgba(79, 215, 232, ${alpha})`;
            case 'gold':   return `rgba(246, 196, 83, ${alpha})`;
            case 'violet': return `rgba(139, 124, 246, ${alpha})`;
        }
        return `rgba(255,255,255,${alpha})`;
    }

    let t = 0, rotY = 0, rafId = null;
    function draw() {
        t += 1;
        rotY += 0.0055;
        const rotX = 0.32 + Math.sin(t * 0.007) * 0.18;

        ctx.clearRect(0, 0, W, H);

        const proj = NODES.map(n => {
            const rp = rotate(n, rotX, rotY);
            const pp = project(rp);
            return { x: pp.x, y: pp.y, depth: pp.depth, ref: n };
        });

        const auraPulse = 0.55 + 0.45 * Math.sin(t * 0.018);
        const aura = ctx.createRadialGradient(W / 2, H / 2, 0, W / 2, H / 2, Math.min(W, H) * 0.5);
        aura.addColorStop(0, `rgba(79, 215, 232, ${0.14 * auraPulse})`);
        aura.addColorStop(0.4, `rgba(139, 124, 246, ${0.08 * auraPulse})`);
        aura.addColorStop(1, 'rgba(0, 0, 0, 0)');
        ctx.fillStyle = aura;
        ctx.fillRect(0, 0, W, H);

        const edgesSorted = EDGES.map(e => {
            const a = proj[e.i], b = proj[e.j];
            const zAvg = (a.depth + b.depth) / 2;
            return { a, b, zAvg, e };
        }).sort((p, q) => q.zAvg - p.zAvg);

        for (const edge of edgesSorted) {
            const depthAlpha = Math.max(0.05, 1 - (edge.zAvg - 1) * 0.55);
            ctx.beginPath();
            ctx.moveTo(edge.a.x, edge.a.y);
            ctx.lineTo(edge.b.x, edge.b.y);
            ctx.strokeStyle = `rgba(79, 215, 232, ${0.22 * depthAlpha})`;
            ctx.lineWidth = 0.65 * depthAlpha + 0.2;
            ctx.stroke();
        }

        signalCooldown--;
        if (signalCooldown <= 0) { spawnSignal(); signalCooldown = 6 + Math.floor(Math.random() * 10); }

        for (let s = SIGNALS.length - 1; s >= 0; s--) {
            const sig = SIGNALS[s];
            sig.t += sig.speed;
            if (sig.t >= 1) { SIGNALS.splice(s, 1); continue; }
            const a = proj[sig.edge.i], b = proj[sig.edge.j];
            const x = a.x + (b.x - a.x) * sig.t;
            const y = a.y + (b.y - a.y) * sig.t;
            const zAvg = (a.depth + b.depth) / 2;
            const depthAlpha = Math.max(0.15, 1 - (zAvg - 1) * 0.5);

            for (let k = 0; k < 4; k++) {
                const tk = Math.max(0, sig.t - k * 0.05);
                const tx = a.x + (b.x - a.x) * tk;
                const ty = a.y + (b.y - a.y) * tk;
                const tr = (4 - k) / 4;
                ctx.beginPath();
                ctx.arc(tx, ty, 1.8 * tr, 0, Math.PI * 2);
                ctx.fillStyle = getSignalColor(sig.color, depthAlpha * tr * 0.6);
                ctx.fill();
            }

            const grad = ctx.createRadialGradient(x, y, 0, x, y, 10);
            grad.addColorStop(0, getSignalColor(sig.color, 0.9 * depthAlpha));
            grad.addColorStop(0.5, getSignalColor(sig.color, 0.35 * depthAlpha));
            grad.addColorStop(1, getSignalColor(sig.color, 0));
            ctx.beginPath();
            ctx.arc(x, y, 10, 0, Math.PI * 2);
            ctx.fillStyle = grad;
            ctx.fill();

            ctx.beginPath();
            ctx.arc(x, y, 1.8, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255, 255, 255, ${depthAlpha})`;
            ctx.fill();
        }

        const sortedNodes = proj.slice().sort((a, b) => b.depth - a.depth);
        for (const p of sortedNodes) {
            const n = p.ref;
            const pulse = 0.65 + 0.35 * Math.sin(t * n.pulseSpeed + n.pulsePhase);
            const depthAlpha = Math.max(0.15, 1 - (p.depth - 1) * 0.55);
            const radius = n.baseSize * (0.7 + 0.5 * pulse) * (0.6 + 0.6 * depthAlpha);

            const glow = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, radius * 6);
            glow.addColorStop(0, `rgba(79, 215, 232, ${0.35 * depthAlpha})`);
            glow.addColorStop(0.4, `rgba(139, 124, 246, ${0.14 * depthAlpha})`);
            glow.addColorStop(1, 'rgba(79, 215, 232, 0)');
            ctx.beginPath();
            ctx.arc(p.x, p.y, radius * 6, 0, Math.PI * 2);
            ctx.fillStyle = glow;
            ctx.fill();

            ctx.beginPath();
            ctx.arc(p.x, p.y, radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(200, 245, 255, ${0.6 + 0.35 * pulse * depthAlpha})`;
            ctx.fill();

            ctx.beginPath();
            ctx.arc(p.x - radius * 0.35, p.y - radius * 0.35, radius * 0.4, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255, 255, 255, ${0.5 * depthAlpha})`;
            ctx.fill();
        }

        rafId = requestAnimationFrame(draw);
    }
    draw();

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) { if (rafId) { cancelAnimationFrame(rafId); rafId = null; } }
        else if (!rafId) draw();
    });
    window.addEventListener('beforeunload', function () { if (rafId) cancelAnimationFrame(rafId); });
})();

(function initStatus() {
    const statusEl = document.getElementById('aiStatusText');
    if (!statusEl) return;
    const states = ['Listening', 'Ready', 'Standby', 'Awaiting input'];
    let i = 0;
    setInterval(() => {
        i = (i + 1) % states.length;
        statusEl.style.opacity = '0';
        setTimeout(() => {
            statusEl.textContent = states[i];
            statusEl.style.transition = 'opacity 0.35s ease';
            statusEl.style.opacity = '1';
        }, 200);
    }, 4000);
})();

const chatContainer = document.getElementById('chatContainer');
function scrollToBottom(smooth) {
    if (!chatContainer) return;
    chatContainer.scrollTo({
        top: chatContainer.scrollHeight,
        behavior: smooth ? 'smooth' : 'auto'
    });
}
requestAnimationFrame(() => scrollToBottom(false));

const aiMessage = document.getElementById('aiMessage');
aiMessage?.addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

document.querySelectorAll('.ticker-item').forEach(function (item) {
    item.addEventListener('click', function () {
        const prompt = this.getAttribute('data-prompt') || '';
        aiMessage.value = prompt;
        aiMessage.style.height = 'auto';
        aiMessage.style.height = Math.min(aiMessage.scrollHeight, 120) + 'px';
        aiMessage.focus();
        aiMessage.setSelectionRange(prompt.length, prompt.length);
    });
});

// Avatars: the user shows their own profile picture (falling back to an initial),
// the assistant shows a robot icon. Both values come from the server via data
// attributes on #chatContainer so a new picture appears without a code change.
function fillUserAvatar(el) {
    const box = document.getElementById('chatContainer');
    const pic = box ? (box.dataset.userPic || '') : '';
    const initial = (box && box.dataset.userInitial) || 'U';
    if (pic) {
        const img = document.createElement('img');
        img.src = pic;
        img.alt = 'Your profile';
        el.appendChild(img);
    } else {
        el.textContent = initial;
    }
}
function fillAiAvatar(el) {
    el.textContent = '';
    const icon = document.createElement('i');
    icon.className = 'fas fa-robot';
    icon.setAttribute('aria-hidden', 'true');
    el.appendChild(icon);
}

function buildUserMessage(text) {
    const wrap = document.createElement('div');
    wrap.className = 'ai-message user';
    wrap.innerHTML = `
        <div class="msg-avatar"></div>
        <div class="msg-content">
            <div class="msg-bubble"></div>
            <div class="msg-time"><i class="far fa-clock"></i>just now</div>
        </div>
    `;
    fillUserAvatar(wrap.querySelector('.msg-avatar'));
    wrap.querySelector('.msg-bubble').textContent = text;
    return wrap;
}
function buildAiMessage(rawText, timeLabel) {
    const wrap = document.createElement('div');
    wrap.className = 'ai-message ai';
    wrap.innerHTML = `
        <div class="msg-avatar"></div>
        <div class="msg-content">
            <div class="msg-bubble markdown-body"></div>
            <div class="msg-time"><i class="far fa-clock"></i>${timeLabel || 'just now'}</div>
        </div>
    `;
    fillAiAvatar(wrap.querySelector('.msg-avatar'));
    renderMarkdown(wrap.querySelector('.msg-bubble'), rawText);
    return wrap;
}
function buildTypingIndicator() {
    const wrap = document.createElement('div');
    wrap.className = 'ai-message ai';
    wrap.id = 'typingIndicator';
    wrap.innerHTML = `
        <div class="msg-avatar"></div>
        <div class="msg-content">
            <div class="typing-indicator">
                <span>Thinking</span>
                <span class="dots"><span></span><span></span><span></span></span>
            </div>
        </div>
    `;
    fillAiAvatar(wrap.querySelector('.msg-avatar'));
    return wrap;
}

const aiEmptyState = document.getElementById('aiEmptyState');
function hideIntro() {
    if (!aiEmptyState) return;
    aiEmptyState.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
    aiEmptyState.style.opacity = '0';
    aiEmptyState.style.transform = 'translateY(-10px)';
    setTimeout(() => aiEmptyState.remove(), 400);
}

const aiForm = document.getElementById('aiForm');
const sendBtn = document.getElementById('sendBtn');
const conversationIdInput = document.getElementById('conversationIdInput');
const statusText = document.getElementById('aiStatusText');

aiForm?.addEventListener('submit', function (e) {
    e.preventDefault();

    const text = aiMessage.value.trim();
    if (!text) return;

    hideIntro();
    if (statusText) statusText.textContent = 'Processing';

    chatContainer.appendChild(buildUserMessage(text));
    scrollToBottom(true);

    const typing = buildTypingIndicator();
    chatContainer.appendChild(typing);
    scrollToBottom(true);

    aiMessage.value = '';
    aiMessage.style.height = 'auto';
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="btn-send-label">Sending</span>';

    const fd = new FormData();
    fd.append('message', text);
    // Send an empty string for "no conversation". Never '0': in PHP the string
    // '0' is falsy but not empty, so it skipped both the ownership check and
    // the create-new branch and reached the INSERT as a broken foreign key.
    fd.append('conversation_id', conversationIdInput.value || '');

    fetch('ai_chat_send.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        typing.remove();
        if (statusText) statusText.textContent = 'Listening';

        if (!data.success) {
            chatContainer.appendChild(buildAiMessage('**Error:** ' + (data.error || 'Something went wrong.'), 'just now'));
            scrollToBottom(true);
            return;
        }

        if (data.conversation_id) {
            conversationIdInput.value = data.conversation_id;
            const url = new URL(window.location.href);
            url.searchParams.set('conversation_id', data.conversation_id);
            window.history.replaceState({}, '', url.toString());
        }

        chatContainer.appendChild(buildAiMessage(data.ai_message.message, data.ai_message.time));
        scrollToBottom(true);

        if (!document.querySelector('.ai-contribute-btn')) {
            setTimeout(() => window.location.reload(), 500);
        }
    })
    .catch(() => {
        typing.remove();
        if (statusText) statusText.textContent = 'Offline';
        chatContainer.appendChild(buildAiMessage('**Network error:** Could not reach the server. Please try again.', 'just now'));
        scrollToBottom(true);
    })
    .finally(() => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span class="btn-send-label">Send</span>';
        aiMessage.focus();
    });
});

aiMessage?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        aiForm.requestSubmit ? aiForm.requestSubmit() : aiForm.dispatchEvent(new Event('submit', { cancelable: true }));
    }
});

(function generateAiScenery() {
    const scenery = document.getElementById('aiScenery');
    if (!scenery) return;
    const icons = ['fa-robot', 'fa-microchip', 'fa-code', 'fa-wifi', 'fa-shield-halved', 'fa-cloud'];
    for (let n = 0; n < 6; n++) {
        const span = document.createElement('i');
        span.className = 'fas ' + icons[Math.floor(Math.random() * icons.length)] + ' drift';
        span.style.left = Math.random() * 100 + '%';
        span.style.top = Math.random() * 100 + '%';
        span.style.fontSize = (Math.random() * 14 + 12) + 'px';
        span.style.animationDelay = (Math.random() * 6) + 's';
        span.style.animationDuration = (Math.random() * 6 + 8) + 's';
        scenery.appendChild(span);
    }
})();
