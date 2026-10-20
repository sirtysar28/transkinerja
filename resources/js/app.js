// TransKinerja — interaksi UI (sidebar, dropdown, toast, modal konfirmasi, captcha, loading bar)

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initDropdowns();
    initToasts();
    initConfirm();
    initPasswordToggle();
    initCaptcha();
    initPageLoader();
    initAutoSubmitFilters();
    initTabs();
    initMultiCheckFilters();
    initStatCards();
});

/* ---------- Kartu statistik: angka berhitung (count-up) saat terlihat ---------- */
function initStatCards() {
    const kurangiGerak = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    const animasi = (el) => {
        const target = parseInt(el.dataset.countup, 10);
        if (Number.isNaN(target)) return;
        if (kurangiGerak) { el.textContent = target.toLocaleString('id-ID'); return; }

        const durasi = 850;
        const mulai = performance.now();
        const langkah = (t) => {
            const p = Math.min(1, (t - mulai) / durasi);
            const ease = 1 - Math.pow(1 - p, 3); // ease-out cubic
            el.textContent = Math.round(target * ease).toLocaleString('id-ID');
            if (p < 1) requestAnimationFrame(langkah);
        };
        requestAnimationFrame(langkah);
    };

    const targetSemua = document.querySelectorAll('[data-countup]');
    if (!('IntersectionObserver' in window)) {
        targetSemua.forEach(animasi);
        return;
    }
    const io = new IntersectionObserver((entries) => {
        entries.forEach((en) => {
            if (!en.isIntersecting) return;
            io.unobserve(en.target);
            animasi(en.target);
        });
    }, { threshold: 0.35 });
    targetSemua.forEach((el) => io.observe(el));
}

/* ---------- Dropdown filter multi-centang ---------- */
function initMultiCheckFilters() {
    const semua = () => document.querySelectorAll('[data-multicheck]');

    const tutupSemua = (kecuali) => {
        semua().forEach((w) => {
            if (w !== kecuali) w.querySelector('[data-mf-panel]')?.classList.add('hidden');
        });
    };

    document.addEventListener('click', () => tutupSemua());
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') tutupSemua(); });

    semua().forEach((wrap) => {
        const btn = wrap.querySelector('[data-mf-toggle]');
        const panel = wrap.querySelector('[data-mf-panel]');
        const labelEl = wrap.querySelector('[data-mf-label]');
        const countEl = wrap.querySelector('[data-mf-count]');
        if (!btn || !panel) return;
        const labelDasar = labelEl.textContent.trim();

        const boxes = () => Array.from(wrap.querySelectorAll('input[type="checkbox"]'));

        const segarkan = () => {
            const n = boxes().filter((b) => b.checked).length;
            if (n > 0) {
                countEl.textContent = n;
                countEl.classList.remove('hidden');
                btn.classList.add('!border-navy-400', '!text-navy-800', '!bg-navy-50/50');
            } else {
                countEl.classList.add('hidden');
                btn.classList.remove('!border-navy-400', '!text-navy-800', '!bg-navy-50/50');
            }
            labelEl.textContent = labelDasar;
        };

        btn.addEventListener('click', (e) => {
            if (btn.disabled) return;
            e.stopPropagation();
            const akanBuka = panel.classList.contains('hidden');
            tutupSemua();
            panel.classList.toggle('hidden', !akanBuka);
            if (akanBuka) wrap.querySelector('[data-mf-search]')?.focus();
        });

        // Klik di dalam panel tidak menutup panel
        panel.addEventListener('click', (e) => e.stopPropagation());

        // Cari opsi
        const cari = wrap.querySelector('[data-mf-search]');
        cari?.addEventListener('input', () => {
            const q = cari.value.toLowerCase();
            wrap.querySelectorAll('.mf-item').forEach((item) => {
                item.classList.toggle('hidden', !item.textContent.toLowerCase().includes(q));
            });
        });
        cari?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.preventDefault();
        });

        // Pilih semua yang terlihat / hapus semua
        wrap.querySelector('[data-mf-all]')?.addEventListener('click', () => {
            wrap.querySelectorAll('.mf-item:not(.hidden) input[type="checkbox"]').forEach((b) => { b.checked = true; });
            segarkan();
        });
        wrap.querySelector('[data-mf-clear]')?.addEventListener('click', () => {
            boxes().forEach((b) => { b.checked = false; });
            segarkan();
        });

        boxes().forEach((b) => b.addEventListener('change', segarkan));
        segarkan();
    });
}

/* Tab antar-bagian pada satu halaman (mis. Pengaturan) — tanpa reload halaman.
   Tab aktif tersimpan di hash URL + localStorage agar tetap terbuka setelah submit form. */
function initTabs() {
    const wrap = document.querySelector('[data-tabs]');
    if (!wrap) return;

    const tombol = Array.from(wrap.querySelectorAll('.tab-btn'));
    const panes = Array.from(document.querySelectorAll('.tab-pane'));
    const kunci = 'tk-tab-' + location.pathname;

    const aktifkan = (nama, simpan = true) => {
        tombol.forEach(b => {
            const on = b.dataset.tab === nama;
            b.classList.toggle('tab-aktif', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panes.forEach(p => p.classList.toggle('hidden', p.id !== 'tab-' + nama));
        if (simpan) {
            history.replaceState(null, '', '#tab-' + nama);
            try { localStorage.setItem(kunci, nama); } catch (e) {}
        }
    };

    tombol.forEach(b => b.addEventListener('click', () => aktifkan(b.dataset.tab)));

    // Prioritas: hash URL → localStorage → tab dari server
    const hash = location.hash.replace('#tab-', '');
    const tersimpan = (() => { try { return localStorage.getItem(kunci); } catch (e) { return null; } })();
    const awal = panes.some(p => p.id === 'tab-' + hash) ? hash
        : (tersimpan && panes.some(p => p.id === 'tab-' + tersimpan) ? tersimpan : null);
    if (awal) aktifkan(awal, false);

    // Flash message tampil saat submit — pastikan pane tak tersembunyi oleh hidden bawaan
    if (document.querySelector('.toast-root, [data-flash]')) {
        const terlihat = panes.some(p => !p.classList.contains('hidden')) || !awal;
        if (!terlihat && panes[0]) panes[0].classList.remove('hidden');
    }
}

/* ---------- Sidebar: collapsible (desktop) + drawer (mobile) ---------- */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const labels = document.querySelectorAll('.sidebar-label');

    // restore state (desktop)
    if (localStorage.getItem('tk-sidebar') === 'mini') {
        sidebar.classList.add('sidebar-mini');
    }

    document.getElementById('btn-sidebar-toggle')?.addEventListener('click', () => {
        if (window.innerWidth >= 1024) {
            sidebar.classList.toggle('sidebar-mini');
            localStorage.setItem('tk-sidebar', sidebar.classList.contains('sidebar-mini') ? 'mini' : 'full');
        } else {
            openDrawer(sidebar, backdrop, true);
        }
    });

    document.getElementById('btn-sidebar-open')?.addEventListener('click', () => openDrawer(sidebar, backdrop, true));

    // Tombol "Menu" pada navigasi bawah mobile (bottom-nav)
    document.querySelectorAll('[data-drawer-open]').forEach((b) => {
        b.addEventListener('click', () => openDrawer(sidebar, backdrop, true));
    });

    backdrop?.addEventListener('click', () => openDrawer(sidebar, backdrop, false));
    document.getElementById('btn-sidebar-close')?.addEventListener('click', () => openDrawer(sidebar, backdrop, false));

    // Label tooltip saat mini
    labels.forEach((l) => {
        const link = l.closest('a');
        link?.addEventListener('mouseenter', () => {
            if (sidebar.classList.contains('sidebar-mini') && window.innerWidth >= 1024) {
                showTooltip(link, l.textContent.trim());
            }
        });
        link?.addEventListener('mouseleave', () => hideTooltip());
    });
}

function openDrawer(sidebar, backdrop, open) {
    if (open) {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
    }
}

function showTooltip(el, text) {
    hideTooltip();
    const t = document.createElement('div');
    t.id = 'tk-tooltip';
    t.className = 'fixed z-[95] bg-navy-900 text-white text-xs font-medium px-2.5 py-1.5 rounded-lg shadow-lg whitespace-nowrap pointer-events-none';
    t.textContent = text;
    document.body.appendChild(t);
    const r = el.getBoundingClientRect();
    t.style.left = r.right + 8 + 'px';
    t.style.top = r.top + r.height / 2 - t.offsetHeight / 2 + 'px';
}

function hideTooltip() {
    document.getElementById('tk-tooltip')?.remove();
}

/* ---------- Dropdown (notifikasi & profil) ---------- */
function initDropdowns() {
    document.querySelectorAll('[data-dropdown-btn]').forEach((btn) => {
        const menu = document.querySelector(btn.getAttribute('data-dropdown-btn'));
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-menu.open').forEach((m) => { if (m !== menu) m.classList.remove('open'); });
            menu.classList.toggle('open');
        });
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown-menu.open').forEach((m) => m.classList.remove('open'));
    });
}

/* ---------- Toast notifikasi (flash) ---------- */
function initToasts() {
    document.querySelectorAll('.toast').forEach((t) => {
        requestAnimationFrame(() => t.classList.add('show'));
        setTimeout(() => {
            t.classList.remove('show');
            setTimeout(() => t.remove(), 400);
        }, 4500);
        t.querySelector('[data-toast-close]')?.addEventListener('click', () => t.remove());
    });
}

/* ---------- Konfirmasi tindakan (modal) ---------- */
function initConfirm() {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            const msg = form.getAttribute('data-confirm');
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    // Modal khusus (data-modal-target)
    document.querySelectorAll('[data-modal-target]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.getAttribute('data-modal-target'));
            modal?.classList.remove('hidden');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach((btn) => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-root')?.classList.add('hidden');
        });
    });
}

/* ---------- Tombol intip password ---------- */
function initPasswordToggle() {
    document.querySelectorAll('[data-password-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.getAttribute('data-password-toggle'));
            const iconOpen = btn.querySelector('.icon-eye-open');
            const iconClose = btn.querySelector('.icon-eye-closed');
            if (input.type === 'password') {
                input.type = 'text';
                iconOpen.classList.add('hidden');
                iconClose.classList.remove('hidden');
            } else {
                input.type = 'password';
                iconOpen.classList.remove('hidden');
                iconClose.classList.add('hidden');
            }
        });
    });
}

/* ---------- CAPTCHA: tombol ganti ---------- */
function initCaptcha() {
    const btn = document.getElementById('btn-captcha-ganti');
    const img = document.getElementById('img-captcha');
    btn?.addEventListener('click', () => {
        img.src = img.dataset.src + '?r=' + Date.now();
    });
    img?.addEventListener('click', () => {
        img.src = img.dataset.src + '?r=' + Date.now();
    });
}

/* ---------- Loading bar saat navigasi form ---------- */
function initPageLoader() {
    const bar = document.getElementById('page-loader');
    document.querySelectorAll('form[method="get"], form[method="post"]').forEach((f) => {
        if (f.hasAttribute('data-no-loader')) return;
        f.addEventListener('submit', () => {
            bar?.classList.add('active');
        });
    });
    document.querySelectorAll('a[href]').forEach((a) => {
        a.addEventListener('click', () => {
            if (a.getAttribute('href')?.startsWith('#') || a.target === '_blank') return;
            bar?.classList.add('active');
            setTimeout(() => bar?.classList.remove('active'), 2500);
        });
    });
}

/* ---------- Filter periode otomatis submit ---------- */
function initAutoSubmitFilters() {
    document.querySelectorAll('[data-autosubmit]').forEach((el) => {
        el.addEventListener('change', () => el.closest('form')?.submit());
    });
}

/* ---------- Util durasi ---------- */
window.fmtDurasi = (menit) => {
    if (menit == null) return '-';
    const j = Math.floor(menit / 60);
    const m = menit % 60;
    return j > 0 ? `${j} j ${m} mnt` : `${m} mnt`;
};
