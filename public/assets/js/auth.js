/**
 * auth.js — basic client-side validation hints.
 */
(function () {
    'use strict';

    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (e) => {
            const email = form.querySelector('input[type="email"]');
            if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                e.preventDefault();
                window.SMC.toast('Format email tidak valid', 'error');
                email.focus();
            }
            const pw = form.querySelector('input[name="password"]');
            const pw2 = form.querySelector('input[name="password_confirm"]');
            if (pw && pw.value && pw.value.length < 8) {
                e.preventDefault();
                window.SMC.toast('Password minimal 8 karakter', 'error');
                pw.focus();
            }
            if (pw && pw2 && pw.value !== pw2.value) {
                e.preventDefault();
                window.SMC.toast('Konfirmasi password tidak cocok', 'error');
                pw2.focus();
            }
        });
    });
})();