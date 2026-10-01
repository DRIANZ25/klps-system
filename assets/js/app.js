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
