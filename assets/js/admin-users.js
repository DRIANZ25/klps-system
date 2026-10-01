(function () {
    var ov = document.getElementById('amEdit'), $ = function (id) { return document.getElementById(id); };
    function close() { ov.classList.remove('open'); document.body.style.overflow = ''; }
    document.querySelectorAll('[data-user]').forEach(function (b) {
        b.addEventListener('click', function () {
            var u = JSON.parse(b.dataset.user);
            $('euId').value = u.id; $('euName').value = u.full_name; $('euEmail').value = u.email;
            $('euRole').value = u.role; $('euDept').value = u.department_id || 0; $('euActive').checked = !!u.is_active;
            $('euRole').disabled = $('euActive').disabled = u.self;
            $('euSelfNote').hidden = !u.self;
            ov.classList.add('open'); document.body.style.overflow = 'hidden'; $('euName').focus();
        });
    });
    ov.addEventListener('click', function (e) { if (e.target === ov || e.target.closest('[data-close]')) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
