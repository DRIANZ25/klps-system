function doSearch() {
        const q    = document.getElementById('searchInput').value;
        const cat  = document.getElementById('catFilter').value;
        const dept = document.getElementById('deptFilter').value;
        const sort = document.getElementById('sortFilter').value;
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (cat) params.set('cat', cat);
        if (dept) params.set('dept', dept);
        if (sort && sort !== 'recent') params.set('sort', sort);
        window.location.href = '?' + params.toString();
    }

    function openModal(id) {
        var modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeModal(id) {
        var modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    function confirmDelete(id, title) {
        if (confirm('Delete "' + title + '"? This cannot be undone.')) {
            window.location.href = '?action=delete&id=' + id;
        }
    }
    function openEdit(id, title, cat, dept, problem, cause, solution, steps, practice, tags) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_title').value = title || '';
        document.getElementById('edit_category_id').value = cat || '';
        document.getElementById('edit_department_id').value = dept || '';
        document.getElementById('edit_problem').value = problem || '';
        document.getElementById('edit_cause').value = cause || '';
        document.getElementById('edit_solution').value = solution || '';
        document.getElementById('edit_procedure_steps').value = steps || '';
        document.getElementById('edit_best_practice').value = practice || '';
        document.getElementById('edit_tags').value = tags || '';
        openModal('editModal');
    }

    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('kl-modal-overlay')) {
            document.querySelectorAll('.kl-modal-overlay').forEach(function (m) {
                m.classList.remove('active');
            });
            document.body.style.overflow = '';
        }
    });

    setTimeout(function () {
        document.querySelectorAll('.kl-toast').forEach(function (el) {
            if (el) {
                el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                el.style.opacity = '0';
                el.style.transform = 'translateX(30px) scale(0.95)';
                setTimeout(function () { el.remove(); }, 500);
            }
        });
    }, 3500);

    (function () {
        const grid = document.querySelector('.kl-grid');
        const gridBtn = document.getElementById('viewGrid');
        const listBtn = document.getElementById('viewList');
        if (!grid || !gridBtn || !listBtn) return;
        gridBtn.addEventListener('click', function () {
            grid.classList.remove('list-view');
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');
        });
        listBtn.addEventListener('click', function () {
            grid.classList.add('list-view');
            listBtn.classList.add('active');
            gridBtn.classList.remove('active');
        });
    })();

    (function generateKnowledgeScenery() {
        const scenery = document.getElementById('klScenery');
        if (!scenery) return;
        const icons = ['fa-book', 'fa-lightbulb', 'fa-scroll', 'fa-star', 'fa-graduation-cap'];
        for (let n = 0; n < 6; n++) {
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
