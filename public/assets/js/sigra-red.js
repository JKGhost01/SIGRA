/**
 * sigra-red.js
 * -------------------------------------------------------------------
 * Capa compartida de la Red SIGRA en todo el sitio:
 *   - badge "Red en vivo" del navbar
 *   - alertas del navegador (sin SMS)
 *   - overlay de alerta a pantalla completa con cuenta atrás
 *   - preferencia de ubicación y notificaciones
 *   - instalación de la PWA
 *
 * No usa frameworks. Se apoya en /api/red/red, que es público.
 */
(function () {
    'use strict';

    const API = window.SIGRA_RED_API || '/api/red/red';
    const RED_URL = window.SIGRA_RED_URL || '/red';
    const LS_ALERTS = 'sigra_web_alerts_v1';
    const LS_LOCATION = 'sigra_my_location_v1';
    const LS_SEEN = 'sigra_seen_alerts_v1';
    const LS_SEEN_INIT = 'sigra_seen_alerts_init_v1';
    const RED_I18N = (window.SIGRA_I18N && window.SIGRA_I18N.red) || {};

    function trRed(clave) {
        let texto = RED_I18N[clave] || clave;
        for (let i = 1; i < arguments.length; i += 1) {
            texto = texto.replace(new RegExp('\\{' + (i - 1) + '\\}', 'g'), arguments[i]);
        }
        return texto;
    }

    let audioContext = null;
    let overlayTimer = null;
    let alertaActual = null;
    let deferredPrompt = null;
    let polling = false;

    function safeGet(key) {
        try { return window.localStorage.getItem(key); } catch (_) { return null; }
    }

    function safeSet(key, value) {
        try { window.localStorage.setItem(key, value); } catch (_) { /* modo privado */ }
    }

    function safeJson(key, fallback) {
        try {
            const raw = safeGet(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (_) {
            return fallback;
        }
    }

    function parseDate(value) {
        if (!value) return null;
        const normalizado = String(value).replace(' ', 'T');
        const fecha = new Date(normalizado);
        return Number.isNaN(fecha.getTime()) ? null : fecha;
    }

    function distanciaKm(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2 +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function formatoDistancia(km) {
        if (km === null || km === undefined || !Number.isFinite(Number(km))) return null;
        const valor = Number(km);
        return valor < 10 ? valor.toFixed(1) + ' km' : Math.round(valor) + ' km';
    }

    function etiquetaSeveridad(sev) {
        return ({ roja: 'Muy alta', naranja: 'Alta', amarilla: 'Moderada', informativa: 'Informativa' })[sev] || 'Informativa';
    }

    function getLocation() {
        const guardada = safeJson(LS_LOCATION, null);
        if (guardada && Number.isFinite(guardada.lat) && Number.isFinite(guardada.lng)) {
            return guardada;
        }
        return null;
    }

    function saveLocation(lat, lng, accuracy) {
        const datos = { lat: Number(lat), lng: Number(lng), accuracy: Number(accuracy) || null, ts: Date.now() };
        safeSet(LS_LOCATION, JSON.stringify(datos));
        document.dispatchEvent(new CustomEvent('sigra:location', { detail: datos }));
        return datos;
    }

    function requestLocation() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error(trRed('geoUnsupported')));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const datos = saveLocation(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy);
                    resolve(datos);
                },
                (error) => reject(new Error(error.message || trRed('locationError'))),
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });
    }

    function alertasActivas(datos) {
        if (!datos || !Array.isArray(datos.recent_alerts)) return [];
        const ahora = Date.now();
        return datos.recent_alerts.filter((alerta) => {
            if (!alerta || !alerta.uuid) return false;
            if (alerta.status && alerta.status !== 'active') return false;
            const expira = parseDate(alerta.expires_at);
            if (expira && expira.getTime() < ahora) return false;
            if (alerta.distance_km != null && alerta.radius_km != null &&
                Number(alerta.distance_km) > Number(alerta.radius_km)) return false;
            return true;
        });
    }

    function seenSet() {
        const valores = safeJson(LS_SEEN, []);
        return new Set(Array.isArray(valores) ? valores : []);
    }

    function guardarSeen(seen) {
        safeSet(LS_SEEN, JSON.stringify(Array.from(seen).slice(-80)));
    }

    function ensureAudio() {
        if (!('AudioContext' in window || 'webkitAudioContext' in window)) return null;
        if (!audioContext) {
            const Constructor = window.AudioContext || window.webkitAudioContext;
            audioContext = new Constructor();
        }
        if (audioContext.state === 'suspended') {
            audioContext.resume().catch(() => {});
        }
        return audioContext;
    }

    function playAlertSound() {
        const ctx = ensureAudio();
        if (!ctx || ctx.state !== 'running') return;
        const ahora = ctx.currentTime;
        [660, 880, 1180].forEach((frecuencia, indice) => {
            const oscilador = ctx.createOscillator();
            const ganancia = ctx.createGain();
            oscilador.type = 'sine';
            oscilador.frequency.value = frecuencia;
            const inicio = ahora + indice * 0.18;
            ganancia.gain.setValueAtTime(0.0001, inicio);
            ganancia.gain.exponentialRampToValueAtTime(0.26, inicio + 0.02);
            ganancia.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.16);
            oscilador.connect(ganancia);
            ganancia.connect(ctx.destination);
            oscilador.start(inicio);
            oscilador.stop(inicio + 0.17);
        });
    }

    function webAlertsEnabled() {
        return safeGet(LS_ALERTS) === '1' && ('Notification' in window) && Notification.permission === 'granted';
    }

    function updateAlertControls() {
        const soportado = 'Notification' in window;
        const permiso = soportado ? Notification.permission : 'unsupported';
        const activo = webAlertsEnabled();
        const bell = document.getElementById('web-alerts-toggle');

        if (bell) {
            bell.setAttribute('aria-pressed', String(activo));
            bell.classList.toggle('is-blocked', permiso === 'denied');
            bell.setAttribute(
                'title',
                permiso === 'denied'
                    ? trRed('notificationsBlockedShort')
                    : (activo ? trRed('navAlertsOn') : trRed('navAlerts'))
            );
            bell.setAttribute(
                'aria-label',
                permiso === 'denied'
                    ? trRed('notificationsBlockedShort')
                    : (activo ? trRed('alertsDisable') : trRed('navAlerts'))
            );
        }

        document.querySelectorAll('[data-sigra-alerts-toggle]').forEach((boton) => {
            boton.setAttribute('aria-pressed', String(activo));
            if (permiso === 'unsupported') {
                boton.textContent = trRed('notificationsUnsupported');
                boton.disabled = true;
            } else if (permiso === 'denied') {
                boton.textContent = trRed('notificationsBlockedText');
            } else if (activo) {
                boton.textContent = trRed('alertsActiveDisable');
            } else {
                boton.textContent = trRed('navAlerts');
            }
        });

        document.querySelectorAll('[data-sigra-alert-status]').forEach((estado) => {
            estado.textContent = permiso === 'unsupported'
                ? trRed('notSupportedShort')
                : (permiso === 'denied' ? trRed('blockedShort') : (activo ? trRed('active') : trRed('inactive')));
            estado.classList.toggle('text-success', activo);
        });
    }

    function toast(mensaje) {
        let contenedor = document.getElementById('sigra-toast');
        if (!contenedor) {
            contenedor = document.createElement('div');
            contenedor.id = 'sigra-toast';
            contenedor.className = 'sigra-toast';
            contenedor.setAttribute('role', 'status');
            contenedor.setAttribute('aria-live', 'polite');
            document.body.appendChild(contenedor);
        }
        contenedor.textContent = mensaje;
        contenedor.classList.add('is-visible');
        window.clearTimeout(contenedor._timer);
        contenedor._timer = window.setTimeout(() => contenedor.classList.remove('is-visible'), 4200);
    }

    function toggleAlerts() {
        if (!('Notification' in window)) {
            toast('Tu navegador no soporta notificaciones. Usa la app Android.');
            updateAlertControls();
            return Promise.resolve(false);
        }

        if (webAlertsEnabled()) {
            safeSet(LS_ALERTS, '0');
            updateAlertControls();
            toast(trRed('alertsOff'));
            return Promise.resolve(false);
        }

        if (Notification.permission === 'denied') {
            toast(trRed('notificationsBlocked'));
            updateAlertControls();
            return Promise.resolve(false);
        }

        ensureAudio();

        return Notification.requestPermission().then((permiso) => {
            const activo = permiso === 'granted';
            safeSet(LS_ALERTS, activo ? '1' : '0');
            updateAlertControls();
            if (activo) {
                toast(trRed('alertsOnToast'));
                playAlertSound();
            } else {
                toast(trRed('notificationsDenied'));
            }
            return activo;
        }).catch(() => {
            updateAlertControls();
            return false;
        });
    }

    function showBrowserNotification(alerta, distancia, eta) {
        if (!webAlertsEnabled()) return;

        const magnitud = Number(alerta.magnitude || 0).toFixed(1);
        const titulo = eta !== null && eta > 0
            ? trRed('quake') + ' M' + magnitud + ' · ' + Math.round(eta) + ' s'
            : trRed('quake') + ' M' + magnitud + ' · ' + trRed('now');
        const distanciaTexto = formatoDistancia(distancia);
        const cuerpo = (distanciaTexto ? trRed('distanceFromYou', distanciaTexto) + '. ' : '') + trRed('alertSpeech');
        const opciones = {
            body: cuerpo,
            icon: '/public/assets/images/icon-192.png',
            badge: '/public/assets/images/icon-192.png',
            tag: 'sigra-' + alerta.uuid,
            renotify: true,
            requireInteraction: true,
            vibrate: [0, 350, 200, 350, 200, 800],
            data: { url: RED_URL, uuid: alerta.uuid }
        };

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready
                .then((registro) => registro.showNotification(titulo, opciones))
                .catch(() => {
                    try {
                        const n = new Notification(titulo, opciones);
                        n.onclick = () => { window.focus(); window.location.href = RED_URL; };
                    } catch (_) { /* sin permiso efectivo */ }
                });
        } else {
            try {
                const n = new Notification(titulo, opciones);
                n.onclick = () => { window.focus(); window.location.href = RED_URL; };
            } catch (_) { /* sin permiso efectivo */ }
        }
    }

    function mostrarOverlay(alerta) {
        const overlay = document.getElementById('sigra-alert-overlay');
        if (!overlay) return;

        alertaActual = alerta;
        const ubicacion = getLocation();
        let distancia = alerta.distance_km;
        if ((distancia === null || distancia === undefined) && ubicacion) {
            distancia = distanciaKm(ubicacion.lat, ubicacion.lng, alerta.latitude, alerta.longitude);
        }

        let eta = alerta.seconds_to_s_wave;
        if ((eta === null || eta === undefined) && Number.isFinite(Number(alerta.origin_time_ms)) && Number.isFinite(Number(distancia))) {
            const llegada = Number(alerta.origin_time_ms) + (Number(distancia) / 3.5) * 1000;
            eta = (llegada - Date.now()) / 1000;
        }

        const magnitud = document.getElementById('sigra-alert-mag');
        const severidad = document.getElementById('sigra-alert-sev');
        const lugar = document.getElementById('sigra-alert-place');
        const segundos = document.getElementById('sigra-alert-seconds');
        const titulo = document.getElementById('sigra-alert-title');
        const meta = document.getElementById('sigra-alert-meta');
        const kicker = document.getElementById('sigra-alert-kicker');

        if (magnitud) magnitud.textContent = 'M ' + Number(alerta.magnitude || 0).toFixed(1);
        if (severidad) severidad.textContent = etiquetaSeveridad(alerta.severity);
        if (lugar) {
            lugar.textContent = alerta.source === 'sgc'
                ? trRed('officialEvent')
                : trRed('networkEvent');
        }
        if (kicker) {
            kicker.textContent = alerta.source === 'sgc' ? trRed('infoAlert') : trRed('earlyWarning');
        }

        const actualizarCuenta = () => {
            if (!segundos || !titulo) return;
            if (eta === null || eta === undefined) {
                segundos.textContent = '—';
                titulo.textContent = trRed('enableLocationS');
                return;
            }
            const restante = Math.round(eta);
            segundos.textContent = restante > 0 ? String(restante) : trRed('now');
            titulo.textContent = restante > 0 ? trRed('secondsUntilS') : trRed('sArrivingShort');
        };

        actualizarCuenta();
        window.clearInterval(overlayTimer);
        overlayTimer = window.setInterval(() => {
            if (eta !== null && eta !== undefined) eta -= 1;
            actualizarCuenta();
        }, 1000);

        if (meta) {
            const partes = [];
            if (formatoDistancia(distancia)) partes.push(trRed('distanceFromYou', formatoDistancia(distancia)));
            partes.push(trRed('confidence') + ' ' + Math.round(Number(alerta.confidence || 0) * 100) + '%');
            if (alerta.device_count) partes.push(alerta.device_count + ' ' + trRed('phones'));
            meta.textContent = partes.join(' · ');
        }

        overlay.hidden = false;
        overlay.classList.add('is-open');
        document.body.classList.add('sigra-alert-open');
    }

    function cerrarOverlay() {
        const overlay = document.getElementById('sigra-alert-overlay');
        if (overlay) {
            overlay.classList.remove('is-open');
            overlay.hidden = true;
        }
        document.body.classList.remove('sigra-alert-open');
        window.clearInterval(overlayTimer);
        overlayTimer = null;
        alertaActual = null;
    }

    function procesarAlertas(datos) {
        const activas = alertasActivas(datos);
        const seen = seenSet();
        const primeraVez = safeGet(LS_SEEN_INIT) !== '1';
        const ahora = Date.now();
        const nuevas = [];

        activas.forEach((alerta) => {
            if (seen.has(alerta.uuid)) return;
            const creada = parseDate(alerta.created_at);
            if (primeraVez && creada && (ahora - creada.getTime()) > 120000) {
                seen.add(alerta.uuid);
                return;
            }
            seen.add(alerta.uuid);
            nuevas.push(alerta);
        });

        guardarSeen(seen);
        safeSet(LS_SEEN_INIT, '1');

        if (nuevas.length === 0) return;

        const principal = nuevas.slice().sort((a, b) => Number(b.magnitude) - Number(a.magnitude))[0];
        const ubicacion = getLocation();
        let distancia = principal.distance_km;
        if ((distancia === null || distancia === undefined) && ubicacion) {
            distancia = distanciaKm(ubicacion.lat, ubicacion.lng, principal.latitude, principal.longitude);
        }
        let eta = principal.seconds_to_s_wave;
        if ((eta === null || eta === undefined) && Number.isFinite(Number(principal.origin_time_ms)) && Number.isFinite(Number(distancia))) {
            eta = (Number(principal.origin_time_ms) + (Number(distancia) / 3.5) * 1000 - Date.now()) / 1000;
        }

        if (Number(principal.magnitude) >= 2.5) {
            mostrarOverlay(principal);
            if (webAlertsEnabled()) {
                playAlertSound();
                showBrowserNotification(principal, distancia, eta);
            }
        }
    }

    function updateNav(datos) {
        const nav = document.querySelector('.nav-red-live');
        const contador = document.getElementById('nav-red-count');
        const online = Number(datos && datos.stats ? datos.stats.devices_online : 0);
        const activas = alertasActivas(datos);

        if (contador) contador.textContent = String(online);
        if (nav) {
            nav.classList.toggle('is-online', Boolean(datos && (datos.operational || online > 0)));
            nav.classList.toggle('has-alert', activas.length > 0);
            nav.setAttribute(
                'title',
                trRed('phonesOnline', online) + (activas.length ? ' · ' + trRed('alertsSuffix', activas.length) : '')
            );
        }

        document.querySelectorAll('[data-sigra-online]').forEach((el) => { el.textContent = online; });
        document.querySelectorAll('[data-sigra-active-alerts]').forEach((el) => { el.textContent = activas.length; });
    }

    function fetchState() {
        if (polling) return Promise.resolve();
        polling = true;
        const ubicacion = getLocation();
        let url = API;
        if (ubicacion) {
            url += '?lat=' + encodeURIComponent(ubicacion.lat) + '&lng=' + encodeURIComponent(ubicacion.lng);
        }

        return fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' })
            .then((respuesta) => respuesta.ok ? respuesta.json() : null)
            .then((datos) => {
                if (!datos || datos.status !== 'ok') return;
                updateNav(datos);
                procesarAlertas(datos);
                document.dispatchEvent(new CustomEvent('sigra:red', { detail: datos }));
            })
            .catch(() => { /* la red conserva el último estado */ })
            .finally(() => { polling = false; });
    }

    function registrarServiceWorker() {
        if (!('serviceWorker' in navigator)) return;
        navigator.serviceWorker.register('/sw.js').catch(() => { /* sin PWA */ });
    }

    function initPwa() {
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredPrompt = event;
            document.querySelectorAll('[data-pwa-install]').forEach((boton) => { boton.hidden = false; });
        });

        document.addEventListener('click', (event) => {
            const boton = event.target.closest('[data-pwa-install]');
            if (!boton || !deferredPrompt) return;
            event.preventDefault();
            deferredPrompt.prompt();
            deferredPrompt.userChoice.finally(() => {
                deferredPrompt = null;
                document.querySelectorAll('[data-pwa-install]').forEach((el) => { el.hidden = true; });
            });
        });

        if (window.matchMedia('(display-mode: standalone)').matches) {
            document.querySelectorAll('[data-pwa-install]').forEach((el) => { el.hidden = true; });
        }
    }

    function init() {
        registrarServiceWorker();
        initPwa();
        updateAlertControls();
        fetchState();
        window.setInterval(fetchState, 10000);

        const bell = document.getElementById('web-alerts-toggle');
        if (bell) {
            bell.addEventListener('click', () => { toggleAlerts(); });
        }

        document.addEventListener('click', (event) => {
            const boton = event.target.closest('[data-sigra-alerts-toggle]');
            if (!boton) return;
            event.preventDefault();
            toggleAlerts();
        });

        const dismiss = document.getElementById('sigra-alert-dismiss');
        if (dismiss) {
            dismiss.addEventListener('click', cerrarOverlay);
        }

        const overlay = document.getElementById('sigra-alert-overlay');
        if (overlay) {
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) cerrarOverlay();
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') cerrarOverlay();
        });

        document.addEventListener('pointerdown', ensureAudio, { once: true });
        document.addEventListener('sigra:location', () => { fetchState(); });
    }

    window.SigraRed = {
        fetchState,
        requestLocation,
        getLocation,
        saveLocation,
        distanciaKm,
        formatoDistancia,
        etiquetaSeveridad,
        toggleAlerts,
        cerrarOverlay,
        get alertaActual() { return alertaActual; }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
