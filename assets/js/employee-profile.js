document.addEventListener('DOMContentLoaded', function () {

    const ring       = document.getElementById('pfAvatarRing');
    const fileInput  = document.getElementById('pf-file-input');
    const photoForm  = document.getElementById('pfPhotoForm');
    const avatarImg  = document.getElementById('pfAvatarImg');
    const avatarInit = document.getElementById('pfAvatarInitial');

    if (ring && fileInput && photoForm) {
        ring.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', function () {
            if (!this.files || !this.files[0]) return;
            previewAndSubmit(this.files[0]);
        });

        ['dragenter', 'dragover'].forEach(ev => {
            ring.addEventListener(ev, e => {
                e.preventDefault(); e.stopPropagation();
                ring.classList.add('drag-over');
            });
        });
        ['dragleave', 'drop'].forEach(ev => {
            ring.addEventListener(ev, e => {
                e.preventDefault(); e.stopPropagation();
                ring.classList.remove('drag-over');
            });
        });
        ring.addEventListener('drop', e => {
            const files = e.dataTransfer && e.dataTransfer.files;
            if (files && files[0]) {
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                fileInput.files = dt.files;
                previewAndSubmit(files[0]);
            }
        });
    }

    function previewAndSubmit(file) {
        if (!file.type.startsWith('image/')) {
            alert('Only image files are allowed.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('Image is larger than 5MB.');
            return;
        }
        const reader = new FileReader();
        reader.onload = ev => {
            if (avatarImg) {
                avatarImg.src = ev.target.result;
            } else if (avatarInit) {
                const img = document.createElement('img');
                img.id = 'pfAvatarImg';
                img.src = ev.target.result;
                img.alt = 'Preview';
                avatarInit.replaceWith(img);
            } else {
                const inner = ring.querySelector('.inner');
                if (inner) {
                    inner.innerHTML = `<img id="pfAvatarImg" src="${ev.target.result}" alt="Preview">`;
                }
            }
            photoForm.submit();
        };
        reader.readAsDataURL(file);
    }

    const bio       = document.getElementById('pf-bio');
    const counter   = document.getElementById('pf-bio-counter');
    function updateCounter() {
        if (!bio || !counter) return;
        const len = bio.value.length;
        counter.textContent = `${len} / 500`;
        counter.classList.remove('warn', 'over');
        if (len > 450) counter.classList.add('over');
        else if (len > 350) counter.classList.add('warn');
    }
    if (bio) {
        updateCounter();
        bio.addEventListener('input', updateCounter);
    }

    const bioForm = document.getElementById('pfBioForm');
    const saveBtn = document.getElementById('pfSaveBtn');
    if (bioForm && saveBtn) {
        bioForm.addEventListener('submit', function () {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        });
    }

    (function generateProfileScenery() {
        const scenery = document.getElementById('pfScenery');
        if (!scenery) return;
        const icons = ['fa-user', 'fa-id-badge', 'fa-microchip', 'fa-book', 'fa-star', 'fa-atom'];

        for (let i = 0; i < 12; i++) {
            const node = document.createElement('div');
            node.className = 'node';
            node.style.left = Math.random() * 100 + '%';
            node.style.top = Math.random() * 100 + '%';
            node.style.width = (Math.random() * 7 + 4) + 'px';
            node.style.height = node.style.width;
            node.style.animationDelay = (Math.random() * 6) + 's';
            node.style.animationDuration = (Math.random() * 4 + 6) + 's';
            scenery.appendChild(node);
        }

        const nodes = scenery.querySelectorAll('.node');
        const sceneryRect = scenery.getBoundingClientRect();
        nodes.forEach((node, index) => {
            const connections = Math.floor(Math.random() * 2) + 1;
            for (let j = 0; j < connections; j++) {
                const targetIndex = Math.floor(Math.random() * nodes.length);
                if (targetIndex !== index) {
                    const connection = document.createElement('div');
                    connection.className = 'connection';
                    const rect1 = node.getBoundingClientRect();
                    const rect2 = nodes[targetIndex].getBoundingClientRect();
                    const x1 = rect1.left + rect1.width / 2 - sceneryRect.left;
                    const y1 = rect1.top + rect1.height / 2 - sceneryRect.top;
                    const x2 = rect2.left + rect2.width / 2 - sceneryRect.left;
                    const y2 = rect2.top + rect2.height / 2 - sceneryRect.top;
                    const length = Math.sqrt(Math.pow(x2 - x1, 2) + Math.pow(y2 - y1, 2));
                    const angle = Math.atan2(y2 - y1, x2 - x1) * 180 / Math.PI;
                    connection.style.width = length + 'px';
                    connection.style.left = x1 + 'px';
                    connection.style.top = y1 + 'px';
                    connection.style.transform = 'rotate(' + angle + 'deg)';
                    connection.style.transformOrigin = '0 0';
                    connection.style.animationDelay = (Math.random() * 3) + 's';
                    scenery.appendChild(connection);
                }
            }
        });

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

});
