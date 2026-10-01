(function () {
    setTimeout(function () {
        document.querySelectorAll('.am-toast').forEach(function (t) { t.classList.add('out'); setTimeout(function () { t.remove(); }, 500); });
    }, 3500);

    /* row action menus: fixed-position so the table never clips them */
    var openBtn = null;
    function closeMenus() {
        document.querySelectorAll('.am-menu-list.open').forEach(function (m) { m.classList.remove('open'); });
        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
        openBtn = null;
    }
    document.querySelectorAll('.am-menu-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var list = btn.nextElementSibling, wasOpen = list.classList.contains('open');
            closeMenus();
            if (wasOpen) return;
            list.classList.add('open');
            var r = btn.getBoundingClientRect(), h = list.offsetHeight, w = list.offsetWidth;
            list.style.left = Math.max(8, Math.min(r.right - w, innerWidth - w - 8)) + 'px';
            list.style.top = (r.bottom + h + 12 > innerHeight ? Math.max(8, r.top - h - 6) : r.bottom + 6) + 'px';
            btn.setAttribute('aria-expanded', 'true');
            openBtn = btn;
        });
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('.am-menu-list')) closeMenus(); });
    addEventListener('scroll', closeMenus, true);
    addEventListener('resize', closeMenus);
document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenus(); });

    /* live relative-time labels: recompute so "3 min ago" advances without a reload */
    function agoLabel(iso) {
        var t = new Date(iso).getTime();
        if (isNaN(t)) return null;
        var d = Math.max(0, Math.floor((Date.now() - t) / 60000));
        if (d < 1) return 'just now';
        if (d < 60) return d + ' min ago';
        var h = Math.floor(d / 60);
        if (h < 24) return h + ' hour' + (h === 1 ? '' : 's') + ' ago';
        var y = Math.floor(h / 24);
        if (y <= 7) return y + ' day' + (y === 1 ? '' : 's') + ' ago';
        return new Date(t).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }
    // Mirrors klps_presence() in config/config.php so the dot colour also ages live.
    function presenceOf(iso) {
        var t = new Date(iso).getTime();
        if (isNaN(t)) return null;
        var d = Math.max(0, Math.floor((Date.now() - t) / 60000));
        if (d < 1) return ['online', 'Active just now'];
        if (d < 60) return ['recent', 'Active ' + agoLabel(iso)];
        var h = Math.floor(d / 60);
        if (h < 24) return ['today', 'Active ' + agoLabel(iso)];
        var y = Math.floor(h / 24);
        if (y <= 7) return ['old', 'Active ' + agoLabel(iso)];
        return ['stale', 'Last seen ' + agoLabel(iso)];
    }
    function tickAgo() {
        document.querySelectorAll('[data-ts]').forEach(function (el) {
            var next = agoLabel(el.getAttribute('data-ts'));
            if (next && el.textContent !== next) el.textContent = next;
        });
        document.querySelectorAll('[data-pres]').forEach(function (el) {
            var p = presenceOf(el.getAttribute('data-ts'));
            if (!p) return;
            el.className = el.className.replace(/\bis-\w+\b/, 'is-' + p[0]);
            if (el.lastChild && el.lastChild.nodeType === 3) el.lastChild.nodeValue = p[1];
        });
    }
    if (document.querySelector('[data-ts]')) {
        tickAgo();
        setInterval(tickAgo, 30000);
    }
})();
