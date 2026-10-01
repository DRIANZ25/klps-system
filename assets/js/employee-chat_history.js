document.addEventListener('DOMContentLoaded', function () {

    const grid           = document.getElementById('chGrid');
    const searchInput    = document.getElementById('chSearchInput');
    const sortSelect     = document.getElementById('chSortSelect');
    const visibleCount   = document.getElementById('chVisibleCount');
    const noResults      = document.getElementById('chNoResults');
    const deleteModal    = document.getElementById('chDeleteModal');
    const deleteTitleEl  = document.getElementById('chDeleteTitle');
    const deleteCancel   = document.getElementById('chDeleteCancel');
    const deleteConfirm  = document.getElementById('chDeleteConfirm');

    let pendingDeleteId = null;
    let pendingDeleteCard = null;

    document.querySelectorAll('.ch-card').forEach(card => {
        card.addEventListener('click', (e) => {
            if (e.target.closest('.ch-card-actions')) return;
            const href = card.dataset.href;
            if (href) window.location.href = href;
        });
    });

    function applyFilters() {
        const q    = (searchInput?.value || '').trim().toLowerCase();
        const sort = sortSelect?.value || 'recent';
        const cards = Array.from(document.querySelectorAll('.ch-card'));

        let visible = 0;
        cards.forEach(card => {
            const haystack = [
                card.dataset.title || '',
                card.dataset.preview || '',
                card.dataset.topic || ''
            ].join(' ');
            const match = q === '' || haystack.includes(q);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        const sorted = cards.slice().sort((a, b) => {
            switch (sort) {
                case 'oldest':
                    return (+a.dataset.updated) - (+b.dataset.updated);
                case 'messages':
                    return (+b.dataset.messages) - (+a.dataset.messages);
                case 'az':
                    return (a.dataset.title || '').localeCompare(b.dataset.title || '');
                case 'recent':
                default:
                    return (+b.dataset.updated) - (+a.dataset.updated);
            }
        });

        if (grid) {
            sorted.forEach(card => grid.appendChild(card));
        }

        if (visibleCount) visibleCount.textContent = visible;
        if (noResults) noResults.style.display = visible === 0 ? '' : 'none';
    }

    searchInput?.addEventListener('input', applyFilters);
    sortSelect?.addEventListener('change', applyFilters);

    document.querySelectorAll('[data-delete-id]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            pendingDeleteId = this.dataset.deleteId;
            pendingDeleteCard = this.closest('.ch-card');
            if (deleteTitleEl) {
                deleteTitleEl.textContent = '"' + (this.dataset.deleteTitle || 'this conversation') + '"';
            }
            deleteModal?.classList.add('active');
        });
    });

    function closeDeleteModal() {
        deleteModal?.classList.remove('active');
        pendingDeleteId = null;
        pendingDeleteCard = null;
        if (deleteConfirm) {
            deleteConfirm.disabled = false;
            deleteConfirm.innerHTML = '<i class="fas fa-trash"></i> Delete';
        }
    }

    deleteCancel?.addEventListener('click', closeDeleteModal);
    deleteModal?.addEventListener('click', (e) => {
        if (e.target === deleteModal) closeDeleteModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && deleteModal?.classList.contains('active')) {
            closeDeleteModal();
        }
    });

    deleteConfirm?.addEventListener('click', function () {
        if (!pendingDeleteId) return;

        deleteConfirm.disabled = true;
        deleteConfirm.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

        const card = pendingDeleteCard;

        fetch('delete_conversation.php?id=' + encodeURIComponent(pendingDeleteId), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'Delete failed');
            }

            if (card) {
                card.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateY(-10px) scale(0.95)';
                setTimeout(() => {
                    card.remove();
                    applyFilters();
                }, 350);
            }

            closeDeleteModal();
            showToast('Conversation deleted successfully.', 'success');
        })
        .catch(err => {
            console.error(err);
            showToast(err.message || 'Failed to delete conversation.', 'error');
            closeDeleteModal();
        });
    });

    function showToast(text, type) {
        const toast = document.createElement('div');
        toast.className = 'ch-toast ' + type;
        toast.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'circle-check' : 'circle-exclamation') + '"></i><span>' + text + '</span>';
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(30px) scale(0.95)';
            setTimeout(() => toast.remove(), 500);
        }, 3500);
    }

    (function generateChatHistoryScenery() {
        const scenery = document.getElementById('chScenery');
        if (!scenery) return;
        const icons = ['fa-comments', 'fa-robot', 'fa-clock-rotate-left', 'fa-message', 'fa-history'];

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
