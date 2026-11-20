'use strict';
(() => {
    document.addEventListener('DOMContentLoaded', () => {
        const shell = document.querySelector('.dashboard-shell');
        const panel = document.getElementById('catalog-panel');
        const handle = document.getElementById('catalog-panel-resizer');
        const toggle = document.getElementById('catalog-panel-toggle');
        if (!shell || !panel || !handle || !toggle) return;
        const desktop = window.matchMedia('(min-width: 851px)');
        const stage = document.querySelector('.map-stage');
        const key = 'sigra-catalog-panel';
        let width = 360;
        let collapsed = false;
        let drag;
        try {
            const saved = JSON.parse(localStorage.getItem(key));
            if (Number.isFinite(saved?.width)) width = saved.width;
            collapsed = saved?.collapsed === true;
        } catch (_) { /* El panel funciona sin almacenamiento local. */ }
        const maximum = () => Math.max(300, Math.min(640, shell.clientWidth - 360));
        const clamp = value => Math.round(Math.max(300, Math.min(maximum(), value)));
        const save = () => {
            try { localStorage.setItem(key, JSON.stringify({ width, collapsed })); }
            catch (_) { /* Preferencias opcionales. */ }
        };
        function measureViewport() {
            const viewport = window.visualViewport;
            const height = viewport?.height || window.innerHeight;
            const header = document.querySelector('.navbar')?.getBoundingClientRect().bottom || 0;
            const footer = document.querySelector('.site-footer')?.getBoundingClientRect().height || 0;
            const root = document.documentElement.style;
            root.setProperty('--catalog-header-height', Math.max(0, header) + 'px');
            root.setProperty('--catalog-footer-height', footer + 'px');
            root.setProperty('--catalog-stage-height', Math.max(0, height - header - footer) + 'px');
            root.setProperty('--catalog-viewport-height', height + 'px');
            root.setProperty('--catalog-keyboard-gap', Math.max(0, window.innerHeight - height) + 'px');
        }
        window.plegarControlesMapa = collapsed => {
            const tools = document.querySelector('.map-engine-toolbar');
            const button = document.getElementById('map-tools-toggle');
            if (!tools || !button) return;
            tools.classList.toggle('is-collapsed', collapsed);
            document.getElementById('map-tools-options').hidden = collapsed;
            button.setAttribute('aria-expanded', String(!collapsed));
            button.setAttribute('aria-label', window.SIGRA_GLOBE_CONFIG.text[collapsed ? 'expand' : 'collapse']);
        };
        function apply() {
            measureViewport();
            const hidden = desktop.matches && collapsed;
            shell.style.setProperty('--catalog-panel-width', (hidden ? 0 : clamp(width)) + 'px');
            shell.classList.toggle('catalog-panel-collapsed', hidden);
            if (hidden && panel.contains(document.activeElement)) toggle.focus();
            panel.inert = hidden;
            if (hidden) panel.setAttribute('aria-hidden', 'true');
            else panel.removeAttribute('aria-hidden');
            toggle.setAttribute('aria-expanded', String(!hidden));
            toggle.setAttribute('aria-label', hidden ? toggle.dataset.expandLabel : toggle.dataset.collapseLabel);
            toggle.title = toggle.getAttribute('aria-label');
            handle.setAttribute('aria-valuemax', String(maximum()));
            handle.setAttribute('aria-valuenow', String(clamp(width)));
            const status = document.getElementById('catalog-sync-status');
            const statusHost = desktop.matches ? panel.querySelector(':scope > div:first-child')
                : document.getElementById('mobile-catalog-status');
            if (status && statusHost && status.parentElement !== statusHost) statusHost.append(status);
        }
        toggle.addEventListener('click', () => {
            collapsed = !collapsed;
            apply();
            save();
        });
        handle.addEventListener('pointerdown', event => {
            if (!desktop.matches || event.button !== 0 || drag) return;
            event.preventDefault();
            handle.focus();
            drag = { id: event.pointerId, start: event.clientX, width: clamp(width) };
            handle.setPointerCapture(event.pointerId);
            shell.classList.add('catalog-panel-resizing');
        });
        handle.addEventListener('pointermove', event => {
            if (!drag || drag.id !== event.pointerId) return;
            width = clamp(drag.width + event.clientX - drag.start);
            apply();
        });
        function finish(cancelled) {
            if (!drag) return;
            const previous = drag;
            drag = null;
            if (cancelled) width = previous.width;
            if (handle.hasPointerCapture(previous.id)) handle.releasePointerCapture(previous.id);
            shell.classList.remove('catalog-panel-resizing');
            apply();
            save();
        }
        handle.addEventListener('pointerup', () => finish(false));
        handle.addEventListener('pointercancel', () => finish(true));
        handle.addEventListener('lostpointercapture', () => finish(true));
        handle.addEventListener('keydown', event => {
            if (event.key === 'Escape' && drag) { finish(true); return; }
            const values = { ArrowLeft: clamp(width) - 24, ArrowRight: clamp(width) + 24,
                Home: 300, End: maximum() };
            if (!desktop.matches || !(event.key in values)) return;
            event.preventDefault();
            width = clamp(values[event.key]);
            apply();
            save();
        });
        window.addEventListener('resize', () => {
            if (drag) finish(true);
            apply();
        });
        desktop.addEventListener('change', () => {
            window.plegarControlesMapa(!desktop.matches);
            apply();
        });
        window.visualViewport?.addEventListener('resize', measureViewport);
        const chromeObserver = new ResizeObserver(measureViewport);
        for (const element of document.querySelectorAll('.navbar, .site-footer')) chromeObserver.observe(element);
        const observer = new ResizeObserver(() => window.redimensionarMapaListado?.());
        observer.observe(stage);
        window.plegarControlesMapa(!desktop.matches);
        apply();
    });
})();
