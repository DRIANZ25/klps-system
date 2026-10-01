(function initMarkdown() {
    if (!window.marked) return;
    marked.setOptions({ breaks: true, gfm: true, headerIds: false, mangle: false });

    document.querySelectorAll('.markdown-body[data-raw]').forEach(function (el) {
        const raw = el.getAttribute('data-raw') || '';
        try {
            const html = marked.parse(raw);
            el.innerHTML = window.DOMPurify ? DOMPurify.sanitize(html, {
                ALLOWED_TAGS: ['p','br','strong','em','b','i','u','s','del','h1','h2','h3','h4','h5','h6',
                               'ul','ol','li','blockquote','hr','code','pre','a','table','thead','tbody',
                               'tr','th','td','span','div'],
                ALLOWED_ATTR: ['href','title','class','target','rel']
            }) : html;
            el.querySelectorAll('a[href^="http"]').forEach(a => {
                a.setAttribute('target', '_blank');
                a.setAttribute('rel', 'noopener noreferrer');
            });
        } catch (e) {
            el.textContent = raw;
        }
    });
})();

(function initProgress() {
    const bar = document.getElementById('kvProgress');
    if (!bar) return;
    window.addEventListener('scroll', function () {
        const h = document.documentElement;
        const pct = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100;
        bar.style.width = Math.min(100, Math.max(0, pct)) + '%';
    }, { passive: true });
})();

setTimeout(function () {
    document.querySelectorAll('.kv-toast').forEach(function (el) {
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateX(30px) scale(0.95)';
        setTimeout(function () { el.remove(); }, 500);
    });
}, 3500);

function handleVote(id, vote, back) {
    showToast('⏳ Processing your vote...', 'success');
    fetch('knowledge_vote_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id + '&vote=' + vote + '&back=' + encodeURIComponent(back)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('❌ ' + (data.error || 'Vote failed'), 'error');
        }
    })
    .catch(() => showToast('❌ Something went wrong.', 'error'));
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = 'kv-toast ' + (type || 'success');
    toast.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'circle-exclamation' : 'circle-check') + '"></i><span>' + message + '</span>';
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(30px) scale(0.95)';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

function showReplyForm(commentId) {
    const form = document.getElementById('reply-form-' + commentId);
    if (form) {
        form.style.display = 'block';
        const ta = form.querySelector('textarea');
        if (ta) ta.focus();
    }
}
function hideReplyForm(commentId) {
    const form = document.getElementById('reply-form-' + commentId);
    if (form) form.style.display = 'none';
}

/* =============== ANCHOR SAFE NET =============== */
/* Posting / replying / deleting redirects to #comment-… , #reply-… or #comments
   so the page stays where the user was working. Smooth scrolling is handled by
   CSS (scroll-behavior: smooth), but if it has not settled — slow device, images
   still sizing, reduced-motion edge cases — make sure the target is actually in
   view, otherwise the user would still be stranded at the top. */
(function anchorSafeNet() {
    if (!location.hash) return;
    let el = null;
    try { el = document.getElementById(decodeURIComponent(location.hash.slice(1))); } catch (e) { return; }
    if (!el) return;
    setTimeout(function () {
        var r = el.getBoundingClientRect();
        var offscreen = r.bottom < 0 || r.top > window.innerHeight;
        if (offscreen) {
            el.scrollIntoView({ block: 'center', behavior: 'auto' });
        }
    }, 350);
})();

(function generateScenery() {
    const scenery = document.getElementById('kvScenery');
    if (!scenery) return;
    const icons = ['fa-book', 'fa-lightbulb', 'fa-scroll', 'fa-star', 'fa-wrench'];
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
