document.querySelectorAll('.spark canvas').forEach(function (cv) {
    var v = (cv.dataset.values || '').split(',').map(Number);
    if (v.length < 2) return;
    function draw() {
        var r = cv.getBoundingClientRect(); if (!r.width) return;
        var d = Math.min(devicePixelRatio || 1, 2), c = cv.getContext('2d'), w = r.width, h = r.height, p = 3;
        cv.width = w * d; cv.height = h * d; c.setTransform(d, 0, 0, d, 0, 0);
        var max = Math.max.apply(null, v.concat(1)), sx = (w - 2 * p) / (v.length - 1);
        var pts = v.map(function (n, i) { return [p + i * sx, h - p - (n / max) * (h - 2 * p)]; });
        c.beginPath(); c.moveTo(pts[0][0], h - p);
        pts.forEach(function (q) { c.lineTo(q[0], q[1]); });
        c.lineTo(pts[pts.length - 1][0], h - p); c.closePath();
        var g = c.createLinearGradient(0, 0, 0, h); g.addColorStop(0, 'rgba(79,215,232,.3)'); g.addColorStop(1, 'rgba(79,215,232,0)');
        c.fillStyle = g; c.fill();
        c.beginPath(); c.moveTo(pts[0][0], pts[0][1]);
        for (var i = 1; i < pts.length; i++) { var m = (pts[i - 1][0] + pts[i][0]) / 2; c.bezierCurveTo(m, pts[i - 1][1], m, pts[i][1], pts[i][0], pts[i][1]); }
        c.strokeStyle = '#4fd7e8'; c.lineWidth = 1.8; c.lineCap = 'round'; c.stroke();
        var l = pts[pts.length - 1]; c.beginPath(); c.arc(l[0], l[1], 2.6, 0, 6.29); c.fillStyle = '#4fd7e8'; c.fill();
    }
draw(); addEventListener('resize', draw);
});

/* System health modal */
(function () {
    var modal = document.getElementById('sysModal');
    if (!modal) return;
    var lastFocus = null;

    window.openSystemModal = function () {
        lastFocus = document.activeElement;
        modal.classList.add('open');
        var x = modal.querySelector('.adm-x');
        if (x) x.focus();
    };
    window.closeSystemModal = function () {
        modal.classList.remove('open');
        if (lastFocus) lastFocus.focus();
    };
    // Click on the backdrop (outside the dialog box) closes it.
    modal.addEventListener('click', function (ev) { if (ev.target === modal) closeSystemModal(); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && modal.classList.contains('open')) closeSystemModal(); });
})();
