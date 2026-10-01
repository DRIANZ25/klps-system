document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        document.querySelectorAll('.mc-toast').forEach(function (el) {
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateX(30px) scale(0.95)';
            setTimeout(function () { el.remove(); }, 500);
        });
    }, 3500);

    (function generateContributionsScenery() {
        const scenery = document.getElementById('mcScenery');
        if (!scenery) return;
        const icons = ['fa-star', 'fa-pen-fancy', 'fa-trophy', 'fa-book', 'fa-chart-line'];
        for (let n = 0; n < 7; n++) {
            const span = document.createElement('i');
            span.className = 'fas ' + icons[Math.floor(Math.random() * icons.length)] + ' drift';
            span.style.left = Math.random() * 100 + '%';
            span.style.top = Math.random() * 100 + '%';
            span.style.fontSize = (Math.random() * 15 + 13) + 'px';
            span.style.animationDelay = (Math.random() * 6) + 's';
            span.style.animationDuration = (Math.random() * 6 + 8) + 's';
            scenery.appendChild(span);
        }
    })();
});
