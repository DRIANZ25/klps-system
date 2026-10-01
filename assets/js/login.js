document.addEventListener('DOMContentLoaded', function () {
    const lines = [
        'INITIALIZING SECURE TERMINAL',
        'VERIFYING ENCRYPTION KEYS',
        'LOADING KNOWLEDGE INDEX',
        'ACCESS GRANTED'
    ];
    const el = document.getElementById('bootLine');
    let i = 0;
    const iv = setInterval(() => {
        i++;
        if (i < lines.length) el.textContent = lines[i];
    }, 380);

    setTimeout(() => {
        clearInterval(iv);
        document.getElementById('boot-screen').classList.add('hide');
        document.body.classList.add('revealed');
    }, 1650);

    const icons = ['fa-book', 'fa-microchip', 'fa-atom', 'fa-code-branch', 'fa-diagram-project'];
    const container = document.getElementById('particles');
    for (let n = 0; n < 12; n++) {
        const span = document.createElement('i');
        span.className = 'fas ' + icons[Math.floor(Math.random() * icons.length)] + ' knowledge-particle';
        span.style.left = Math.random() * 100 + '%';
        span.style.top = Math.random() * 100 + '%';
        span.style.fontSize = (Math.random() * 16 + 14) + 'px';
        span.style.animationDelay = (Math.random() * 6) + 's';
        span.style.animationDuration = (Math.random() * 5 + 7) + 's';
        container.appendChild(span);
    }

    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function () {
            const input = this.parentElement.querySelector('input');
            const icon = this.querySelector('i');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
        });
    });
});
