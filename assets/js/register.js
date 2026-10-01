document.addEventListener('DOMContentLoaded', function () {

    const lines = ['PREPARING REGISTRATION PROTOCOL','ALLOCATING KNOWLEDGE NODE','ENCRYPTING CREDENTIAL SCHEMA','READY'];
    const el = document.getElementById('bootLine');
    let i = 0;
    const iv = setInterval(() => { i++; if (i < lines.length) el.textContent = lines[i]; }, 380);
    setTimeout(() => {
        clearInterval(iv);
        document.getElementById('boot-screen').classList.add('hide');
        document.body.classList.add('revealed');
    }, 1650);

    const icons = ['fa-book','fa-microchip','fa-atom','fa-code-branch','fa-diagram-project'];
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

    const sendBtn       = document.getElementById('sendOtpBtn');
    const emailInput    = document.getElementById('emailInput');
    const fullNameInput = document.getElementById('fullNameInput');
    const departmentInput = document.getElementById('departmentInput');
    const otpInput      = document.getElementById('otpInput');
    const verifyBtn     = document.getElementById('verifyBtn');
    const otpHint       = document.getElementById('otpHint');
    const revealSection = document.getElementById('revealSection');
    const passwordInput = document.getElementById('passwordInput');
    const form          = document.getElementById('registerForm');

    if (!sendBtn || !form) return;

    let isVerified = false;
    let currentEmail = '';

    function shake(el) {
        if (!el) return;
        const seq = [8, -8, 6, -6, 4, -4, 0];
        el.style.transition = 'transform 0.1s ease';
        seq.forEach((v, idx) => {
            setTimeout(() => { el.style.transform = 'translateX(' + v + 'px)'; }, idx * 50);
        });
        setTimeout(() => { el.style.transform = ''; }, 500);
    }
    function setHint(html) { otpHint.innerHTML = html; }

    async function sendOtp() {
        const email = emailInput.value.trim();

        if (departmentInput && !departmentInput.value) {
            setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> Please select your department first.');
            shake(departmentInput);
            departmentInput.focus();
            return;
        }
        if (!email) {
            setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> Please enter your email first.');
            shake(emailInput);
            emailInput.focus();
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> Please enter a valid email address.');
            shake(emailInput);
            return;
        }

        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin btn-icon"></i> <span class="btn-label">Sending...</span>';

        const fd = new FormData();
        fd.append('action', 'send_otp_ajax');
        fd.append('email', email);
        fd.append('full_name', fullNameInput ? fullNameInput.value : '');

        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });

            let data;
            try {
                data = await res.json();
            } catch (parseErr) {
                console.error('Non-JSON response:', await res.text());
                setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> Server error. Check logs.');
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane btn-icon"></i> <span class="btn-label">Retry</span>';
                return;
            }

            if (!data.success) {
                setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> ' + (data.error || 'Failed to send code.'));
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane btn-icon"></i> <span class="btn-label">Retry</span>';
                shake(emailInput);
                return;
            }

            currentEmail = email;
            setHint('<i class="fas fa-circle-check" style="color:var(--green);"></i> Code sent to <strong>' + email + '</strong>');
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane btn-icon"></i> <span class="btn-label">Resend Code</span>';
            otpInput.focus();

        } catch (err) {
            console.error(err);
            setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> Network error. Please try again.');
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane btn-icon"></i> <span class="btn-label">Retry</span>';
        }
    }
    sendBtn.addEventListener('click', sendOtp);

    otpInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
        if (this.value.length === 6 && !isVerified) {
            verifyBtn.classList.add('ready');
        } else if (this.value.length < 6) {
            verifyBtn.classList.remove('ready');
        }
    });

    otpInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (this.value.length === 6 && !isVerified) {
                verifyBtn.click();
            }
        }
    });

    verifyBtn.addEventListener('click', async function () {
        const otp = otpInput.value.trim();
        if (otp.length !== 6) return;

        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

        const fd = new FormData();
        fd.append('action', 'verify_otp_ajax');
        fd.append('email', currentEmail || emailInput.value.trim());
        fd.append('otp', otp);

        try {
            const res = await fetch(window.location.href, { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                isVerified = true;
                otpInput.setAttribute('readonly', true);
                otpInput.classList.add('verified');
                verifyBtn.innerHTML = '<i class="fas fa-circle-check"></i> Verified';
                verifyBtn.classList.remove('ready');
                verifyBtn.classList.add('verified-state');
                verifyBtn.disabled = true;
                setHint('<i class="fas fa-circle-check" style="color:var(--green);"></i> Email verified! Set your password below.');
                revealSection.classList.add('open');
                setTimeout(() => {
                    if (passwordInput) {
                        passwordInput.focus();
                        passwordInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 350);
            } else {
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-circle-check"></i> Verify Code';
                setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> ' + (data.error || 'Verification failed.'));
                shake(otpInput);
            }
        } catch (err) {
            console.error(err);
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<i class="fas fa-circle-check"></i> Verify Code';
            setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> Network error. Please try again.');
        }
    });

    form.addEventListener('submit', function (e) {
        if (departmentInput && !departmentInput.value) {
            e.preventDefault();
            alert('Please select your department.');
            return;
        }
        if (!isVerified) {
            e.preventDefault();
            setHint('<i class="fas fa-triangle-exclamation" style="color:#ff7c93;"></i> Please verify your email code first.');
            shake(otpInput);
            return;
        }
        const p1 = document.getElementById('passwordInput').value;
        const p2 = document.getElementById('confirmInput').value;
        if (p1.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters.');
            return;
        }
        if (p1 !== p2) {
            e.preventDefault();
            alert('Passwords do not match.');
            return;
        }
        const createBtn = document.getElementById('createBtn');
        createBtn.disabled = true;
        createBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
    });
});
