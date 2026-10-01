/**
 * bookmark.js — toggle like/unlike on product cards + bookmark toggle button.
 */
(function () {
    'use strict';

    async function toggleBookmark(btn) {
        const id = btn.dataset.id;
        if (!id) return;

        const formData = new FormData();
        formData.append('product_id', id);
        formData.append('action', 'toggle');

        btn.disabled = true;
        try {
            const data = await window.SMC.api('/api/bookmark.php', {
                method: 'POST',
                body: formData,
            });
            if (data.ok) {
                btn.classList.toggle('is-active', data.bookmarked);
                if (btn.classList.contains('bookmark-toggle')) {
                    btn.textContent = data.bookmarked ? '♥ Favorit' : '♡ Tambah ke favorit';
                    btn.dataset.bookmarked = data.bookmarked ? '1' : '0';
                } else {
                    btn.textContent = data.bookmarked ? '♥' : '♡';
                }
                // Sync all other bookmark buttons for same product on page
                document.querySelectorAll(`.bookmark-btn[data-id="${id}"], .bookmark-toggle[data-id="${id}"]`)
                    .forEach((b) => {
                        if (b === btn) return;
                        b.classList.toggle('is-active', data.bookmarked);
                        if (b.classList.contains('bookmark-toggle')) {
                            b.textContent = data.bookmarked ? '♥ Favorit' : '♡ Tambah ke favorit';
                        } else {
                            b.textContent = data.bookmarked ? '♥' : '♡';
                        }
                    });
                window.SMC.toast(data.bookmarked ? 'Ditambahkan ke favorit' : 'Dihapus dari favorit', 'success', 1800);
            } else {
                window.SMC.toast(data.error || 'Gagal', 'error');
            }
        } catch (err) {
            window.SMC.toast('Gagal menghubungi server', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.bookmark-btn, .bookmark-toggle');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        toggleBookmark(btn);
    });
})();