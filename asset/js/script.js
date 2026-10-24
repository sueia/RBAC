/* ============================================================
   RBAC — Scripts Globais
   Carregado em TODAS as páginas via includes/footer.php
   ============================================================ */

(function () {
    'use strict';

    /* ============================================================
       1. TOAST
       ============================================================ */
    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    function showToast(type, message, duration) {
        duration = duration || 4500;
        const container = document.getElementById('toastCenterContainer');
        if (!container) return;

        const configs = {
            success: { cls: 'toast-success', icon: 'bi-check-circle-fill',    color: '#10b981' },
            danger:  { cls: 'toast-danger',  icon: 'bi-exclamation-triangle-fill', color: '#ef4444' },
            warning: { cls: 'toast-warning', icon: 'bi-exclamation-circle-fill',   color: '#f59e0b' },
            info:    { cls: 'toast-info',    icon: 'bi-info-circle-fill',    color: '#06b6d4' }
        };
        const cfg = configs[type] || configs.info;
        const id  = 'toast_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);

        container.insertAdjacentHTML('beforeend',
            '<div id="' + id + '" class="toast-center ' + cfg.cls + '" role="alert">' +
                '<div class="toast-body-custom">' +
                    '<div class="toast-icon" style="color:' + cfg.color + ';">' +
                        '<i class="bi ' + cfg.icon + '"></i>' +
                    '</div>' +
                    '<div class="toast-message">' + escapeHtml(message) + '</div>' +
                    '<button type="button" class="toast-close" aria-label="Fechar">&times;</button>' +
                '</div>' +
                '<div class="toast-progress">' +
                    '<div class="toast-progress-bar" style="width:100%;"></div>' +
                '</div>' +
            '</div>'
        );

        const el  = document.getElementById(id);
        const bar = el.querySelector('.toast-progress-bar');
        const btn = el.querySelector('.toast-close');
        let raf = null, tId = null, dead = false;

        function destroy() {
            if (dead) return;
            dead = true;
            if (raf) cancelAnimationFrame(raf);
            if (tId) clearTimeout(tId);
            el.classList.add('toast-fade-out');
            setTimeout(function () { if (el.parentNode) el.remove(); }, 300);
        }

        function tick(start) {
            const elapsed = performance.now() - start;
            bar.style.width = Math.max(0, 100 - (elapsed / duration) * 100) + '%';
            if (elapsed < duration) raf = requestAnimationFrame(function () { tick(start); });
            else destroy();
        }

        raf = requestAnimationFrame(function () { tick(performance.now()); });
        tId = setTimeout(destroy, duration + 100);
        btn.addEventListener('click', destroy);
    }

    window.showToast = showToast;
    window.escapeHtml = escapeHtml;

    /* ============================================================
       2. INICIALIZAÇÃO
       ============================================================ */
    document.addEventListener('DOMContentLoaded', function () {

        /* -------------------- 2.1 SIDEBAR -------------------- */
        const sidebar = document.getElementById('sidebar');
        const toggle  = document.getElementById('sidebarToggle');

        if (sidebar && toggle) {
            const overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            document.body.appendChild(overlay);

            toggle.addEventListener('click', function () {
                if (window.innerWidth <= 767.98) {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('show', sidebar.classList.contains('active'));
                } else {
                    sidebar.classList.toggle('minimizado');
                }
            });

            overlay.addEventListener('click', function () {
                sidebar.classList.remove('active');
                overlay.classList.remove('show');
            });
        }

        /* -------------------- 2.2 SUBMENUS -------------------- */
        document.querySelectorAll('.submenu-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const wrap = this.closest('.has-submenu');
                if (!wrap) return;
                const sub = wrap.querySelector('.sidebar-submenu');
                if (!sub) return;
                sub.classList.toggle('show');
                const icon = this.querySelector('.bi-chevron-down, .bi-chevron-up');
                if (icon) {
                    icon.classList.toggle('bi-chevron-down');
                    icon.classList.toggle('bi-chevron-up');
                }
            });
        });

        /* -------------------- 2.3 FORM SENHA -------------------- */
        const formSenha = document.getElementById('formMudarSenha');
        if (formSenha) {
            formSenha.addEventListener('submit', function (e) {
                e.preventDefault();
                showToast('success', 'Senha alterada com sucesso (demo).');
                const modalEl = document.getElementById('senhaModal');
                if (modalEl && window.bootstrap) {
                    const m = bootstrap.Modal.getInstance(modalEl);
                    if (m) m.hide();
                }
                this.reset();
            });
        }

        /* -------------------- 2.4 ACESSIBILIDADE -------------------- */
        const a11yFab     = document.getElementById('a11yFab');
        const a11yPanel   = document.getElementById('a11yPanel');
        const fontMinus   = document.getElementById('fontMinus');
        const fontPlus    = document.getElementById('fontPlus');
        const fontValue   = document.getElementById('fontValue');
        const themeSwitch = document.getElementById('themeSwitch');
        const resetAll    = document.getElementById('resetAll');
        const html        = document.documentElement;

        if (!a11yFab || !a11yPanel) {
            // Página não tem painel de a11y (ex.: login) — ignora silenciosamente
            return;
        }

        const FONT_MIN  = 0.8;
        const FONT_MAX  = 1.5;
        const FONT_STEP = 0.1;
        const state = { fontScale: 1, theme: 'light' };

        try {
            const s = JSON.parse(localStorage.getItem('rbac-a11y') || '{}');
            if (s.fontScale) state.fontScale = parseFloat(s.fontScale) || 1;
            if (s.theme)     state.theme     = (s.theme === 'dark') ? 'dark' : 'light';
        } catch (_) {}

        function savePrefs() {
            try { localStorage.setItem('rbac-a11y', JSON.stringify(state)); } catch (_) {}
        }

        function applyA11y() {
            html.style.setProperty('--font-scale', state.fontScale);
            if (fontValue) fontValue.textContent = Math.round(state.fontScale * 100) + '%';
            if (fontMinus) fontMinus.disabled = state.fontScale <= FONT_MIN + 0.001;
            if (fontPlus)  fontPlus.disabled  = state.fontScale >= FONT_MAX - 0.001;
            html.setAttribute('data-theme', state.theme);
            if (themeSwitch) {
                themeSwitch.classList.toggle('on', state.theme === 'dark');
                themeSwitch.setAttribute('aria-pressed', state.theme === 'dark');
            }
            // Se a tela atual expôs refresh de gráficos, usa
            if (typeof window.__refreshChartColors === 'function') {
                try { window.__refreshChartColors(state.theme === 'dark'); } catch (e) {}
            }
            savePrefs();
        }

        function openPanel(open) {
            a11yPanel.classList.toggle('open', open);
            a11yFab.setAttribute('aria-expanded', open ? 'true' : 'false');
            a11yFab.innerHTML = open
                ? '<i class="bi bi-x-lg"></i>'
                : '<i class="bi bi-universal-access"></i>';
        }

        a11yFab.addEventListener('click', function () {
            openPanel(!a11yPanel.classList.contains('open'));
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && a11yPanel.classList.contains('open')) openPanel(false);
        });

        document.addEventListener('click', function (e) {
            if (!a11yPanel.classList.contains('open')) return;
            if (a11yPanel.contains(e.target) || a11yFab.contains(e.target)) return;
            openPanel(false);
        });

        if (fontMinus) fontMinus.addEventListener('click', function () {
            state.fontScale = Math.max(FONT_MIN, +(state.fontScale - FONT_STEP).toFixed(2));
            applyA11y();
        });
        if (fontPlus) fontPlus.addEventListener('click', function () {
            state.fontScale = Math.min(FONT_MAX, +(state.fontScale + FONT_STEP).toFixed(2));
            applyA11y();
        });
        if (themeSwitch) themeSwitch.addEventListener('click', function () {
            state.theme = state.theme === 'dark' ? 'light' : 'dark';
            applyA11y();
            showToast('info', state.theme === 'dark' ? 'Modo escuro ativado.' : 'Modo claro ativado.');
        });
        if (resetAll) resetAll.addEventListener('click', function () {
            state.fontScale = 1;
            state.theme = 'light';
            applyA11y();
            showToast('success', 'Preferências restauradas.');
        });

        applyA11y();
    });

})();