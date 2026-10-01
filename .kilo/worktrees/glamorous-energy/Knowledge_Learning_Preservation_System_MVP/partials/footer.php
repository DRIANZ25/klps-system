</main>
</div>
</div>

<div class="logout-modal-overlay" id="logoutModal">
    <div class="logout-modal">
        <div class="modal-icon"><i class="fas fa-sign-out-alt"></i></div>
        <h3>Sign Out</h3>
        <p>Are you sure you want to sign out of your account? You'll need to sign in again to access your knowledge library.</p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeLogoutModal()"><i class="fas fa-times me-1"></i> Cancel</button>
            <a href="../logout.php" class="btn-logout"><i class="fas fa-right-from-bracket me-1"></i> Sign Out</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    var sidebar = document.getElementById('mainSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        if (sidebar.classList.contains('open')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}

document.addEventListener('click', function(e) {
    var sidebar = document.getElementById('mainSidebar');
    var toggle = document.querySelector('.mobile-menu-toggle');
    if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('active');
            document.body.style.overflow = '';
        }
    }
});

window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        var sidebar = document.getElementById('mainSidebar');
        sidebar.classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
        document.body.style.overflow = '';
    }
});

function toggleNotifications() {
    var dropdown = document.getElementById('notifDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

function markNotificationRead(id) {
    fetch('mark_notification_read.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var badge = document.querySelector('.badge-dot');
                if (badge) {
                    var current = parseInt(badge.textContent) || 0;
                    if (current > 0) {
                        badge.textContent = current - 1;
                        if (badge.textContent === '0' || badge.textContent === '') {
                            badge.style.display = 'none';
                        }
                    }
                }
                var items = document.querySelectorAll('.notif-item.unread');
                items.forEach(function(item) {
                    item.classList.remove('unread');
                });
            }
        })
        .catch(error => console.error('Error:', error));
}

function markAllRead() {
    fetch('mark_notification_read.php?all=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var badge = document.querySelector('.badge-dot');
                if (badge) badge.style.display = 'none';
                var items = document.querySelectorAll('.notif-item.unread');
                items.forEach(function(item) {
                    item.classList.remove('unread');
                });
            }
        })
        .catch(error => console.error('Error:', error));
}

document.addEventListener('click', function(e) {
    var bell = document.querySelector('.notification-bell');
    var dropdown = document.getElementById('notifDropdown');
    if (bell && dropdown && !bell.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var dropdown = document.getElementById('notifDropdown');
        if (dropdown) dropdown.classList.remove('show');
    }
});

function openLogoutModal() {
    document.getElementById('logoutModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('logout-modal-overlay')) {
        closeLogoutModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sidebar-nav a, .logout-btn, .sidebar-brand').forEach(function(link) {
        link.addEventListener('click', function(e) {
            if (this.getAttribute('href') && !this.getAttribute('href').startsWith('#') && !this.getAttribute('onclick')) {
                e.preventDefault();
                var href = this.getAttribute('href');
                document.body.style.opacity = '0';
                document.body.style.transition = 'opacity 0.25s ease';
                setTimeout(function() {
                    window.location.href = href;
                }, 250);
            }
        });
    });
});

setTimeout(function() {
    document.querySelectorAll('.toast-container, [style*="position:fixed"][style*="top:20px"]').forEach(function(el) {
        if (el) {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 500);
        }
    });
}, 4000);
</script>

<style>
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    max-width: 400px;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.toast {
    padding: 0.8rem 1.25rem;
    border-radius: 12px;
    animation: slideIn 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.85rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
}
.toast-success {
    background: rgba(0, 255, 156, 0.12);
    border-color: rgba(0, 255, 156, 0.2);
    color: #00ff9c;
}
.toast-error {
    background: rgba(255, 0, 60, 0.12);
    border-color: rgba(255, 0, 60, 0.2);
    color: #ff7c93;
}
@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
@media (max-width: 576px) {
    .toast-container {
        top: 10px;
        right: 10px;
        left: 10px;
        max-width: none;
    }
    .toast {
        font-size: 0.8rem;
        padding: 0.6rem 1rem;
    }
}
</style>
</body>
</html>