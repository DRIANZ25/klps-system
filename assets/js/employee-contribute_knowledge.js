document.getElementById('aiDraftForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('aiDraftBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Drafting...';
});
(function generateContributeScenery() {
    const scenery = document.getElementById('ckScenery');
    if (!scenery) return;
    const icons = ['fa-book', 'fa-wand-magic-sparkles', 'fa-robot', 'fa-lightbulb', 'fa-pen-fancy'];
    for (let n = 0; n < 6; n++) {
        const span = document.createElement('i');
        span.className = 'fas ' + icons[Math.floor(Math.random() * icons.length)] + ' drift';
        span.style.position = 'absolute';
        span.style.left = Math.random() * 100 + '%';
        span.style.top = Math.random() * 100 + '%';
        span.style.fontSize = (Math.random() * 14 + 12) + 'px';
        scenery.appendChild(span);
    }
})();
