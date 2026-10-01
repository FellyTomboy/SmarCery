/**
 * app.js — utility helpers + toast + simple fetch wrapper.
 */
(function () {
    'use strict';

    window.SMC = {
        toast(message, type = 'info', duration = 3000) {
            const stack = document.querySelector('.flash-stack') || (() => {
                const el = document.createElement('div');
                el.className = 'flash-stack';
                document.body.appendChild(el);
                return el;
            })();
            const div = document.createElement('div');
            div.className = `flash flash-${type}`;
            div.textContent = message;
            stack.appendChild(div);
            setTimeout(() => div.remove(), duration);
        },

        async api(path, options = {}) {
            const opts = Object.assign({
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.SMARCERY?.csrf || '',
                },
            }, options);
            if (opts.body instanceof FormData) {
                opts.body.append('_token', window.SMARCERY?.csrf || '');
            }
            const res = await fetch(window.SMARCERY.baseUrl + path, opts);
            const ct = res.headers.get('content-type') || '';
            if (ct.includes('application/json')) return res.json();
            return res.text();
        },

        confirm(message) {
            return window.confirm(message);
        },

        copy(text) {
            navigator.clipboard?.writeText(text).catch(() => {});
        },
    };
})();