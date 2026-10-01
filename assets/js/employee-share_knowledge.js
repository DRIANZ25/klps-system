(function () {
    function ready(fn) { document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn(); }
    ready(function () {
        var page = document.querySelector('.sk-page'), form = document.getElementById('skForm');
        if (!page || !form) return;
        var $ = function (id) { return document.getElementById(id); };
        var still = matchMedia('(prefers-reduced-motion: reduce)').matches;
        var F = { title: $('fTitle'), cat: $('fCat'), dept: $('fDept'), problem: $('fProblem'), cause: $('fCause'),
                  solution: $('fSolution'), steps: $('skSteps'), best: $('fBest'), tags: $('skTags') };

        (function () {
            var cv = $('skBg'), c = cv.getContext('2d'), d = Math.min(devicePixelRatio || 1, 2), W, H, mx = -999, my = -999, N = [];
            function size() {
                var r = page.getBoundingClientRect(); W = r.width; H = r.height;
                cv.width = W * d; cv.height = H * d; c.setTransform(d, 0, 0, d, 0, 0);
                var n = Math.min(70, Math.round(W * H / 22000)); N = [];
                for (var i = 0; i < n; i++) N.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .25, vy: (Math.random() - .5) * .25, p: Math.random() * 6 });
            }
            size(); addEventListener('resize', size);
            page.addEventListener('pointermove', function (e) { var r = page.getBoundingClientRect(); mx = e.clientX - r.left; my = e.clientY - r.top; });
            function frame(t) {
                c.clearRect(0, 0, W, H);
                N.forEach(function (n) {
                    if (!still) { n.x += n.vx; n.y += n.vy; if (n.x < 0 || n.x > W) n.vx *= -1; if (n.y < 0 || n.y > H) n.vy *= -1; }
                });
                for (var i = 0; i < N.length; i++) {
                    for (var j = i + 1; j < N.length; j++) {
                        var dx = N[i].x - N[j].x, dy = N[i].y - N[j].y, dist = dx * dx + dy * dy;
                        if (dist < 16000) { c.strokeStyle = 'rgba(139,124,246,' + (.16 * (1 - dist / 16000)) + ')'; c.lineWidth = .7; c.beginPath(); c.moveTo(N[i].x, N[i].y); c.lineTo(N[j].x, N[j].y); c.stroke(); }
                    }
                    var mdx = N[i].x - mx, mdy = N[i].y - my, md = mdx * mdx + mdy * mdy;
                    if (md < 26000) { c.strokeStyle = 'rgba(246,196,83,' + (.35 * (1 - md / 26000)) + ')'; c.beginPath(); c.moveTo(N[i].x, N[i].y); c.lineTo(mx, my); c.stroke(); }
                    var glow = .5 + .5 * Math.sin(t * .002 + N[i].p);
                    c.beginPath(); c.arc(N[i].x, N[i].y, 1.5 + glow, 0, 6.3);
                    c.fillStyle = md < 26000 ? '#f6c453' : 'rgba(79,215,232,' + (.35 + glow * .4) + ')'; c.fill();
                }
                if (!still) requestAnimationFrame(frame);
            }
            frame(0);
        })();

        var secs = [].slice.call(document.querySelectorAll('.sk-sec')), links = {};
        document.querySelectorAll('.sk-steps a').forEach(function (a) { links[a.dataset.for] = a; });
        if ('IntersectionObserver' in window && !still) {
            var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) e.target.classList.add('in'); }); }, { threshold: .08 });
            secs.forEach(function (s) { io.observe(s); });
        } else secs.forEach(function (s) { s.classList.add('in'); });
        if ('IntersectionObserver' in window) {
            var vis = {};
            var so = new IntersectionObserver(function (es) {
                es.forEach(function (e) { vis[e.target.id] = e.isIntersecting ? e.intersectionRatio : 0; });
                var best = null; secs.forEach(function (s) { if (!best || (vis[s.id] || 0) > (vis[best.id] || 0)) best = s; });
                secs.forEach(function (s) { links[s.id].classList.toggle('on', s === best); });
            }, { threshold: [0, .25, .5, .75, 1] });
            secs.forEach(function (s) { so.observe(s); });
        }

        (function () {
            var max = +F.title.dataset.max, el = document.createElement('span'); el.className = 'sk-count';
            F.title.parentNode.appendChild(el);
            function u() { var n = F.title.value.length; el.textContent = n + ' / ' + max; el.classList.toggle('warn', n > max * .85); }
            F.title.addEventListener('input', u); u();
        })();

        function grow(t) { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight + 2, 420) + 'px'; }
        form.querySelectorAll('textarea').forEach(function (t) { grow(t); t.addEventListener('input', function () { grow(t); }); });

        var ui = $('skStepsUI'), addBtn = $('skAddStep');
        function syncSteps() {
            F.steps.value = [].map.call(ui.querySelectorAll('input'), function (i) { return i.value.trim(); }).filter(Boolean).join('\n');
            F.steps.dispatchEvent(new Event('input', { bubbles: true }));
        }
        function addStep(text, focus, after) {
            var li = document.createElement('li');
            li.innerHTML = '<input type="text" placeholder="Describe this step" aria-label="Step">' +
                '<button type="button" data-a="up" aria-label="Move up"><i class="fas fa-arrow-up"></i></button>' +
                '<button type="button" data-a="down" aria-label="Move down"><i class="fas fa-arrow-down"></i></button>' +
                '<button type="button" data-a="del" aria-label="Remove step"><i class="fas fa-xmark"></i></button>';
            var inp = li.querySelector('input'); inp.value = text || '';
            after ? after.after(li) : ui.appendChild(li);
            if (focus) inp.focus();
            return li;
        }
        ui.hidden = addBtn.hidden = false; F.steps.style.display = 'none';
        var init = []; try { init = JSON.parse(ui.dataset.initial || '[]'); } catch (e) {}
        (init.length ? init : ['']).forEach(function (t) { addStep(t); });
        addBtn.addEventListener('click', function () { addStep('', true); });
        ui.addEventListener('input', syncSteps);
        ui.addEventListener('keydown', function (e) {
            var li = e.target.closest('li'); if (!li || e.target.tagName !== 'INPUT') return;
            if (e.key === 'Enter') { e.preventDefault(); if (e.ctrlKey || e.metaKey) return; addStep('', true, li); }
            if (e.key === 'Backspace' && !e.target.value && ui.children.length > 1) { e.preventDefault(); var p = li.previousElementSibling || li.nextElementSibling; li.remove(); p.querySelector('input').focus(); syncSteps(); }
        });
        ui.addEventListener('click', function (e) {
            var b = e.target.closest('button'); if (!b) return; var li = b.closest('li'), a = b.dataset.a;
            if (a === 'up' && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
            if (a === 'down' && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
            if (a === 'del') { if (ui.children.length > 1) li.remove(); else li.querySelector('input').value = ''; }
            syncSteps();
        });

        var box = $('skTagBox'), sug = $('skSuggest'), tags = [], tin = document.createElement('input');
        tin.type = 'text'; tin.placeholder = 'Type a tag, press Enter'; tin.setAttribute('aria-label', 'Add tag');
        function normTag(t) { return t.toLowerCase().replace(/\s+/g, ' ').trim().slice(0, 30); }
        function renderTags() {
            box.querySelectorAll('.sk-chip').forEach(function (c) { c.remove(); });
            tags.forEach(function (t) {
                var c = document.createElement('span'); c.className = 'sk-chip'; c.appendChild(document.createTextNode(t));
                var x = document.createElement('button'); x.type = 'button'; x.setAttribute('aria-label', 'Remove ' + t); x.innerHTML = '<i class="fas fa-xmark"></i>';
                x.onclick = function () { tags.splice(tags.indexOf(t), 1); renderTags(); tin.focus(); };
                c.appendChild(x); box.insertBefore(c, tin);
            });
            F.tags.value = tags.join(', '); F.tags.dispatchEvent(new Event('input', { bubbles: true }));
        }
        function addTag(raw) { raw.split(',').forEach(function (r) { var t = normTag(r); if (t && tags.length < 10 && tags.indexOf(t) < 0) tags.push(t); }); renderTags(); }
        box.appendChild(tin); box.hidden = sug.hidden = false; F.tags.style.display = 'none';
        addTag(F.tags.value);
        tin.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); if (tin.value.trim()) { addTag(tin.value); tin.value = ''; } }
            if (e.key === 'Backspace' && !tin.value && tags.length) { tags.pop(); renderTags(); }
        });
        tin.addEventListener('blur', function () { if (tin.value.trim()) { addTag(tin.value); tin.value = ''; } });
        sug.addEventListener('click', function (e) { var b = e.target.closest('[data-tag]'); if (b) addTag(b.dataset.tag); });
        box.addEventListener('click', function () { tin.focus(); });

        var ring = $('skRing'), CIRC = 226.2;
        var tips = ['Write for a teammate who has never seen this problem.', 'Include the exact error message: people search for it.',
            'Short, numbered steps beat long paragraphs.', 'Say what you tried that did not work. It saves others time.',
            'Add 3 to 5 tags so the article shows up in search.', 'Mention versions, models or settings that matter.'];
        function txt(el, v, ph) { if (el.textContent !== (v || ph)) { el.textContent = v || ph; el.classList.remove('tick'); void el.offsetWidth; el.classList.add('tick'); } }
        function update() {
            var v = function (k) { return (F[k].value || '').trim(); };
            var hasSteps = v('steps').length > 0, tagN = tags.length;
            var chk = { title: v('title').length >= 8, category: !!F.cat.value, problem: v('problem').length >= 10, cause: v('cause').length >= 5,
                        solution: v('solution').length >= 10, steps: hasSteps, best: v('best').length >= 5, tags: tagN > 0 };
            var w = { title: 15, category: 5, problem: 20, cause: 10, solution: 20, steps: 15, best: 5, tags: 10 }, pct = 0;
            Object.keys(chk).forEach(function (k) {
                if (chk[k]) pct += w[k];
                var li = document.querySelector('[data-check="' + k + '"]'); if (li) li.classList.toggle('ok', chk[k]);
            });
            ring.style.strokeDashoffset = CIRC * (1 - pct / 100);
            ring.setAttribute('class', 'bar' + (pct >= 85 ? ' hi' : pct >= 50 ? ' mid' : ''));
            $('skPct').textContent = pct + '%';
            $('skMsg').textContent = pct >= 90 ? 'Excellent. This will help a lot of people.' : pct >= 60 ? 'Good. A few more details will make it great.' : pct > 0 ? 'Keep going, add the problem and solution.' : 'Start typing and watch it grow.';
            txt($('pvTitle'), v('title'), 'Your title appears here');
            txt($('pvCat'), F.cat.value ? F.cat.options[F.cat.selectedIndex].text : '', 'Uncategorized');
            txt($('pvProblem'), v('problem'), '…'); txt($('pvSolution'), v('solution'), '…');
            var pt = $('pvTags'); pt.innerHTML = '';
            tags.forEach(function (t) { var s = document.createElement('span'); s.textContent = '#' + t; pt.appendChild(s); });
            var done = { s1: chk.title, s2: chk.problem, s3: chk.solution, s4: chk.tags };
            Object.keys(done).forEach(function (k) { links[k].classList.toggle('done', done[k]); });
        }
        var tipI = 0;
        setInterval(function () {
            var p = $('skTip'); if (document.hidden || still) return; p.style.opacity = 0;
            setTimeout(function () { tipI = (tipI + 1) % tips.length; p.textContent = tips[tipI]; p.style.opacity = 1; }, 400);
        }, 7000);

        var KEY = 'klps_share_draft_' + page.dataset.uid, saveT, saved = $('skSaved'), submitting = false;
        function snapshot() {
            return { title: F.title.value, category_id: F.cat.value, department_id: F.dept.value, problem: F.problem.value, cause: F.cause.value,
                     solution: F.solution.value, procedure_steps: F.steps.value, best_practice: F.best.value, tags: F.tags.value };
        }
        function isEmpty(s) { return !(s.title || s.problem || s.cause || s.solution || s.procedure_steps || s.best_practice || s.tags); }
        function persist() {
            if (submitting) return; var s = snapshot();
            try {
                if (isEmpty(s)) localStorage.removeItem(KEY); else { localStorage.setItem(KEY, JSON.stringify(s)); saved.textContent = 'Draft saved on this device'; saved.classList.add('show'); setTimeout(function () { saved.classList.remove('show'); }, 1800); }
            } catch (e) {}
        }
        form.addEventListener('input', function () { update(); clearTimeout(saveT); saveT = setTimeout(persist, 700); });
        form.addEventListener('change', function () { update(); clearTimeout(saveT); saveT = setTimeout(persist, 300); });

        function fill(s) {
            F.title.value = s.title || ''; F.cat.value = s.category_id || ''; F.dept.value = s.department_id || '';
            F.problem.value = s.problem || ''; F.cause.value = s.cause || ''; F.solution.value = s.solution || ''; F.best.value = s.best_practice || '';
            ui.innerHTML = ''; var st = (s.procedure_steps || '').split(/\n/).filter(Boolean); (st.length ? st : ['']).forEach(function (t) { addStep(t); }); syncSteps();
            tags = []; addTag(s.tags || '');
            form.querySelectorAll('textarea').forEach(grow); F.title.dispatchEvent(new Event('input')); update();
        }
        if (page.dataset.fresh === '1') {
            try {
                var raw = localStorage.getItem(KEY), s0 = raw && JSON.parse(raw), bar = $('skRestore');
                if (s0 && !isEmpty(s0)) {
                    bar.hidden = false;
                    $('skRestoreYes').onclick = function () { fill(s0); bar.hidden = true; };
                    $('skRestoreNo').onclick = function () { localStorage.removeItem(KEY); bar.hidden = true; };
                }
            } catch (e) {}
        }

        form.addEventListener('submit', function (e) {
            syncSteps(); if (tin.value.trim()) { addTag(tin.value); tin.value = ''; }
            var btn = e.submitter, draft = btn && btn.value === 'draft';
            var need = draft ? [[F.title, 1]] : [[F.title, 1], [F.problem, 10], [F.solution, 10]];
            var first = null;
            need.forEach(function (n) {
                var ok = n[0].value.trim().length >= n[1], wrap = n[0].closest('.sk-field');
                wrap.classList.toggle('is-bad', !ok);
                if (!ok) { wrap.classList.remove('shake'); void wrap.offsetWidth; wrap.classList.add('shake'); if (!first) first = n[0]; }
            });
            if (first) { e.preventDefault(); first.scrollIntoView({ behavior: still ? 'auto' : 'smooth', block: 'center' }); first.focus({ preventScroll: true }); return; }
            submitting = true; try { localStorage.removeItem(KEY); } catch (x) {}
            if (btn) { btn.classList.add('busy'); var i = btn.querySelector('i'); if (i) i.className = 'fas fa-spinner'; }
        });
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); form.requestSubmit($('skPublish')); }
        });

        update();
    });
})();