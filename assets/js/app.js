/* ============================================================
   TernakTrack — UI helpers
   ============================================================ */
(function () {
    'use strict';

    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.getElementById('menuToggle');
    const backdrop = document.getElementById('sidebarBackdrop');

    function openSidebar() {
        sidebar?.classList.add('open');
        backdrop?.classList.add('show');
    }

    function closeSidebar() {
        sidebar?.classList.remove('open');
        backdrop?.classList.remove('show');
    }

    menuToggle?.addEventListener('click', () => {
        if (sidebar?.classList.contains('open')) closeSidebar();
        else openSidebar();
    });
    backdrop?.addEventListener('click', closeSidebar);

    // ---------------------------------------------------------
    // Jam berjalan
    // ---------------------------------------------------------
    const clock = document.getElementById('liveClock');
    if (clock) {
        const label = clock.querySelector('span');
        setInterval(() => {
            if (label) {
                label.textContent = new Date().toLocaleTimeString('id-ID', { hour12: false });
            }
        }, 1000);
    }

    // ---------------------------------------------------------
    // Modal
    // ---------------------------------------------------------
    function openModal(target) {
        const modal = typeof target === 'string' ? document.getElementById(target) : target;
        if (!modal) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        modal.dispatchEvent(new CustomEvent('modal:open'));
        const focusable = modal.querySelector('input:not([type="hidden"]), select, textarea');
        if (focusable && window.matchMedia('(min-width: 769px)').matches) {
            setTimeout(() => focusable.focus(), 120);
        }
    }

    function closeModal(target) {
        const modal = typeof target === 'string' ? document.getElementById(target) : target;
        if (!modal) return;
        modal.classList.remove('open');
        if (!document.querySelector('.modal.open')) {
            document.body.style.overflow = '';
        }
    }

    window.openModal = openModal;
    window.closeModal = closeModal;

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-modal-open]');
        if (opener) {
            event.preventDefault();
            openModal(opener.dataset.modalOpen);
            return;
        }

        const closer = event.target.closest('[data-modal-close]');
        if (closer) {
            event.preventDefault();
            closeModal(closer.closest('.modal'));
            return;
        }

        if (event.target.classList.contains('modal')) {
            closeModal(event.target);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const modal = document.querySelector('.modal.open');
            if (modal) closeModal(modal);
        }
    });

    // ---------------------------------------------------------
    // Flash message
    // ---------------------------------------------------------
    document.querySelectorAll('.flash-close').forEach((btn) => {
        btn.addEventListener('click', () => btn.closest('.flash')?.remove());
    });

    setTimeout(() => {
        document.querySelectorAll('.flash').forEach((flash) => {
            flash.style.transition = 'opacity .3s, transform .3s';
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-6px)';
            setTimeout(() => flash.remove(), 320);
        });
    }, 6500);

    // ---------------------------------------------------------
    // Toast
    // ---------------------------------------------------------
    const toastStack = document.getElementById('toastStack');

    window.showToast = function (title, message = '', type = 'success', timeout = 5000) {
        if (!toastStack) return;

        const icons = {
            success: 'fa-circle-check',
            danger: 'fa-triangle-exclamation',
            warning: 'fa-bell',
            info: 'fa-circle-info'
        };

        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.innerHTML = `
            <span class="toast-icon"><i class="fa-solid ${icons[type] || icons.info}"></i></span>
            <div>
                <strong></strong>
                <p></p>
            </div>
            <button type="button" class="toast-close" aria-label="Tutup">&times;</button>
        `;
        toast.querySelector('strong').textContent = title;
        const p = toast.querySelector('p');
        if (message) p.textContent = message;
        else p.remove();

        toast.querySelector('.toast-close').addEventListener('click', () => toast.remove());
        toastStack.appendChild(toast);

        if (timeout > 0) {
            setTimeout(() => {
                toast.style.transition = 'opacity .3s, transform .3s';
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                setTimeout(() => toast.remove(), 320);
            }, timeout);
        }
    };

    // ---------------------------------------------------------
    // Copy ke clipboard
    // ---------------------------------------------------------
    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-copy]');
        if (!btn) return;

        event.preventDefault();
        const text = btn.dataset.copy || '';

        try {
            await navigator.clipboard.writeText(text);
            window.showToast('Disalin', 'Teks berhasil disalin ke clipboard.');
        } catch (error) {
            const area = document.createElement('textarea');
            area.value = text;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
            window.showToast('Disalin', 'Teks berhasil disalin.');
        }
    });

    // ---------------------------------------------------------
    // Konfirmasi form
    // ---------------------------------------------------------
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        const message = form.dataset.confirm;
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // ---------------------------------------------------------
    // Cegah submit ganda
    // ---------------------------------------------------------
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.noLock !== undefined) return;
        if (form.dataset.confirm && event.defaultPrevented) return;

        const submit = form.querySelector('button[type="submit"]');
        if (submit) {
            setTimeout(() => {
                submit.disabled = true;
                submit.dataset.originalText = submit.innerHTML;
                submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan...';
            }, 10);
        }
    });

    // ---------------------------------------------------------
    // Preview foto
    // ---------------------------------------------------------
    document.querySelectorAll('[data-preview]').forEach((input) => {
        input.addEventListener('change', () => {
            const target = document.querySelector(input.dataset.preview);
            if (!target || !input.files || !input.files[0]) return;

            const reader = new FileReader();
            reader.onload = (e) => {
                if (target.tagName === 'IMG') {
                    target.src = e.target.result;
                } else {
                    target.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover">`;
                }
            };
            reader.readAsDataURL(input.files[0]);
        });
    });
})();
