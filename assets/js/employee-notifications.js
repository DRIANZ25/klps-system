document.addEventListener('DOMContentLoaded', function () {

    const tabs  = document.querySelectorAll('.nt-tab');
    const items = document.querySelectorAll('.nt-item');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const filter = tab.dataset.filter;

            items.forEach(item => {
                const type   = item.dataset.type;
                const unread = item.dataset.unread === '1';
                let show = true;
                if (filter === 'unread') show = unread;
                else if (filter !== 'all') show = (type === filter);

                if (show) {
                    item.style.display = '';
                    item.style.animation = 'none';
                    void item.offsetWidth;
                    item.style.animation = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });

    const toast = document.getElementById('ntToast');
    if (toast) {
        setTimeout(() => {
            toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(30px) scale(0.95)';
            setTimeout(() => toast.remove(), 500);
        }, 3500);
    }

    (function generateNotifScenery() {
        const scenery = document.getElementById('ntScenery');
        if (!scenery) return;
        scenery.style.position = 'absolute';
        scenery.style.inset = '0';
        scenery.style.zIndex = '0';
        scenery.style.pointerEvents = 'none';
        scenery.style.overflow = 'hidden';

        const icons = ['fa-bell', 'fa-comment', 'fa-thumbs-up', 'fa-bookmark', 'fa-at'];
        for (let i = 0; i < 12; i++) {
            const node = document.createElement('div');
            node.style.position = 'absolute';
            node.style.left = Math.random() * 100 + '%';
            node.style.top = Math.random() * 100 + '%';
            const s = (Math.random() * 7 + 4) + 'px';
            node.style.width = s;
            node.style.height = s;
            node.style.borderRadius = '50%';
            node.style.background = 'rgba(139,124,246,0.12)';
            node.style.border = '1px solid rgba(246,196,83,0.3)';
            node.style.boxShadow = '0 0 15px rgba(246,196,83,0.2)';
            node.style.animation = `ntFloat ${6 + Math.random() * 4}s ease-in-out ${Math.random() * 6}s infinite`;
            scenery.appendChild(node);
        }

        for (let n = 0; n < 6; n++) {
            const span = document.createElement('i');
            span.className = 'fas ' + icons[Math.floor(Math.random() * icons.length)];
            span.style.position = 'absolute';
            span.style.left = Math.random() * 100 + '%';
            span.style.top = Math.random() * 100 + '%';
            span.style.fontSize = (Math.random() * 14 + 12) + 'px';
            span.style.color = 'rgba(139,124,246,0.1)';
            span.style.animation = `ntDrift ${8 + Math.random() * 6}s ease-in-out ${Math.random() * 6}s infinite`;
            scenery.appendChild(span);
        }

        if (!document.getElementById('ntSceneryKeyframes')) {
            const style = document.createElement('style');
            style.id = 'ntSceneryKeyframes';
            style.textContent = `
                @keyframes ntFloat {
                    0%,100% { transform: translateY(0) rotate(0deg); opacity: .7; }
                    50%     { transform: translateY(-16px) rotate(180deg); opacity: 1; }
                }
                @keyframes ntDrift {
                    0%,100% { transform: translateY(0) rotate(0deg); opacity: .35; }
                    50%     { transform: translateY(-20px) rotate(-6deg); opacity: .85; }
                }
            `;
            document.head.appendChild(style);
        }
    })();

});
