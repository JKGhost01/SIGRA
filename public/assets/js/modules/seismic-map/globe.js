/* CesiumJS para el catálogo principal. Conserva el contrato del mapa Leaflet. */
'use strict';
(() => {
    const config = window.SIGRA_GLOBE_CONFIG;
    if (!config) return;
    const text = config.text;
    const legacy = {
        init: inicializarMapaListado, paint: pintarMarcadoresListado,
        user: fijarUbicacionListado, radius: dibujarRadioListado,
        fit: ajustarVistaListado, distances: actualizarDistanciaListado,
    };
    const base = 'https://cesium.com/downloads/cesiumjs/releases/1.146/Build/Cesium/';
    let loading;
    let generation = 0;
    let engine;
    let instance;
    let host;
    let elementId;
    let distanceId;
    let events = [];
    let user;
    let radius;
    let fitPending = false;
    let selected;
    let layer = 'osm';
    let failed = false;
    const markers = new Map();

    function status(message) {
        document.getElementById('map-engine-status').textContent = message;
        document.querySelector('.map-engine-toolbar').dataset.status = message === text.loading ? 'loading'
            : [text.fallback, text.imageryUnavailable].includes(message) ? 'warning' : 'ready';
    }

    function controls(active) {
        document.querySelectorAll('[data-map-engine]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.mapEngine === active));
            button.disabled = failed && button.dataset.mapEngine === 'cesium';
        });
        document.querySelectorAll('[data-map-layer]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.mapLayer === layer));
        });
    }

    function loadCesium() {
        if (loading) return loading;
        window.CESIUM_BASE_URL = base;
        loading = new Promise((resolve, reject) => {
            let scriptReady = Boolean(window.Cesium);
            let styleReady = false;
            const timer = setTimeout(() => reject(new Error('Cesium load timeout')), 12000);
            const done = () => {
                if (scriptReady && styleReady) { clearTimeout(timer); resolve(window.Cesium); }
            };
            const fail = () => { clearTimeout(timer); reject(new Error('Cesium assets unavailable')); };
            const style = document.createElement('link');
            style.rel = 'stylesheet';
            style.href = base + 'Widgets/widgets.css';
            style.onload = () => { styleReady = true; done(); };
            style.onerror = fail;
            document.head.appendChild(style);
            if (!scriptReady) {
                const script = document.createElement('script');
                script.src = base + 'Cesium.js';
                script.async = true;
                script.onload = () => { scriptReady = Boolean(window.Cesium); scriptReady ? done() : fail(); };
                script.onerror = fail;
                document.head.appendChild(script);
            }
        });
        return loading;
    }

    function closePopup() {
        selected = null;
        const popup = document.getElementById('map-globe-popup');
        popup.hidden = true;
        popup.querySelector('[data-popup-content]').replaceChildren();
    }

    function showPopup(event) {
        selected = String(event.id);
        const popup = document.getElementById('map-globe-popup');
        const safeEvent = { ...event, url: typeof event.url === 'string' && /^https?:\/\//i.test(event.url) ? event.url : null };
        const distance = user ? mt('kmFromYou', calcularDistanciaKm(user.lat, user.lng, event.lat, event.lng).toFixed(1)) : '';
        const content = popup.querySelector('[data-popup-content]');
        content.innerHTML = popupSismo(safeEvent, distance);
        const detail = document.createElement('a');
        detail.href = config.detail + '/' + encodeURIComponent(event.id);
        detail.textContent = text.details;
        content.appendChild(document.createElement('br'));
        content.appendChild(detail);
        popup.hidden = false;
    }

    function dispose() {
        closePopup();
        if (instance) {
            if (engine === 'cesium' && !instance.isDestroyed()) instance.destroy();
            else if (engine === 'leaflet') instance.remove();
        }
        instance = null;
        estadoMapaListado = null;
        markers.clear();
        host.replaceChildren();
        delete host.dataset.engine;
    }

    function provider(C, name) {
        if (name === 'osm') return new C.OpenStreetMapImageryProvider({
            url: 'https://tile.openstreetmap.org/', maximumLevel: 18,
        });
        const options = {
            satellite: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', 'Tiles © Esri', 16],
            dark: ['https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}', 'Tiles © Esri, DeLorme, NAVTEQ', 16],
            topo: ['https://tile.opentopomap.org/{z}/{x}/{y}.png', '© OpenStreetMap contributors, SRTM | © OpenTopoMap', 17],
        }[name];
        return new C.UrlTemplateImageryProvider({ url: options[0], credit: options[1], maximumLevel: options[2] });
    }

    function changeLayer() {
        controls(engine);
        if (!instance) return;
        if (engine === 'leaflet') {
            const names = { osm: 'osm', satellite: 'satelite', dark: 'cartoDark', topo: 'topo' };
            host.querySelector('[data-capa="' + names[layer] + '"]')?.click();
            return;
        }
        const C = window.Cesium;
        const imagery = provider(C, layer);
        imagery.errorEvent.addEventListener(() => {
            if (engine === 'cesium') status(text.imageryUnavailable);
        });
        instance.imageryLayers.removeAll();
        instance.imageryLayers.addImageryProvider(imagery);
        instance.scene.requestRender();
        status(text.ready);
    }

    function paintGlobe() {
        if (!instance || engine !== 'cesium') return;
        const C = window.Cesium;
        const present = new Set();
        instance.entities.suspendEvents();
        try {
            events.forEach(event => {
                const id = String(event.id);
                present.add(id);
                const style = estiloEpicentro(event);
                const position = C.Cartesian3.fromDegrees(event.lng, event.lat);
                let entity = markers.get(id);
                if (!entity) {
                    entity = instance.entities.add({ id: 'quake:' + id, position, point: {} });
                    markers.set(id, entity);
                }
                entity.position = position;
                entity.point.pixelSize = style.radius * 2;
                entity.point.color = C.Color.fromCssColorString(style.fillColor).withAlpha(0.95);
                entity.point.outlineColor = C.Color.fromCssColorString(style.color);
                entity.point.outlineWidth = style.weight;
                // Height zero deliberately locates the epicenter on the ellipsoid.
                // Scientific depth is displayed in the popup, never reinterpreted.
            });
            for (const [id, entity] of markers) {
                if (!present.has(id)) { instance.entities.remove(entity); markers.delete(id); }
            }
        } finally { instance.entities.resumeEvents(); }
        if (selected !== null && selected !== undefined) {
            const current = events.find(event => String(event.id) === selected);
            current ? showPopup(current) : closePopup();
        }
        instance.scene.requestRender();
    }

    function fitGlobe(items) {
        if (!instance || engine !== 'cesium') return;
        const C = window.Cesium;
        const points = items.filter(coordenadasSismoValidas).map(event => C.Cartesian3.fromDegrees(event.lng, event.lat));
        if (!points.length) {
            instance.camera.setView({ destination: C.Cartesian3.fromDegrees(-74, 18, 22000000) });
            return;
        }
        const sphere = C.BoundingSphere.fromPoints(points);
        instance.camera.flyToBoundingSphere(sphere, {
            duration: 0,
            offset: new C.HeadingPitchRange(0, -Math.PI / 2, Math.max(250000, sphere.radius * 2.6)),
        });
    }

    function userGlobe() {
        if (!instance || engine !== 'cesium' || !user) return;
        const C = window.Cesium;
        const old = instance.entities.getById('sigra-user');
        if (old) instance.entities.remove(old);
        instance.entities.add({ id: 'sigra-user', position: C.Cartesian3.fromDegrees(user.lng, user.lat),
            point: { pixelSize: 15, color: C.Color.fromCssColorString('#087f78'), outlineColor: C.Color.WHITE, outlineWidth: 3 },
        });
        instance.scene.requestRender();
    }

    function radiusGlobe(adjust) {
        if (!instance || engine !== 'cesium') return;
        const C = window.Cesium;
        const old = instance.entities.getById('sigra-radius');
        if (old) instance.entities.remove(old);
        if (radius && radius.km > 0 && radius.center) {
            const center = radius.center;
            instance.entities.add({ id: 'sigra-radius', position: C.Cartesian3.fromDegrees(center.lng, center.lat),
                ellipse: { semiMajorAxis: radius.km * 1000, semiMinorAxis: radius.km * 1000,
                    material: C.Color.fromCssColorString('#087f78').withAlpha(0.18),
                    outline: true, outlineColor: C.Color.fromCssColorString('#087f78'),
                },
            });
            if (adjust) instance.camera.setView({
                destination: C.Cartesian3.fromDegrees(center.lng, center.lat, Math.max(120000, radius.km * 3500)),
                orientation: { heading: 0, pitch: -Math.PI / 2, roll: 0 },
            });
        } else if (adjust) fitGlobe(events);
        instance.scene.requestRender();
    }

    async function switchEngine(target, message) {
        const ticket = ++generation;
        dispose();
        engine = target;
        controls(target);
        if (target === 'leaflet') {
            legacy.init(elementId, events, distanceId);
            instance = estadoMapaListado.mapa;
            changeLayer();
            if (user) legacy.user(user.lat, user.lng, user.accuracy);
            if (radius) legacy.radius(radius.center, radius.km, true);
            else if (fitPending) legacy.fit(events);
            host.dataset.engine = 'leaflet';
            status(message || text.classic);
            return;
        }
        status(text.loading);
        try {
            const C = await loadCesium();
            if (ticket !== generation) return;
            const viewer = new C.Viewer(host, {
                baseLayer: false, baseLayerPicker: false, terrainProvider: new C.EllipsoidTerrainProvider(),
                geocoder: false, animation: false, timeline: false, homeButton: false,
                sceneModePicker: false, navigationHelpButton: false, fullscreenButton: false,
                selectionIndicator: false, infoBox: false, shouldAnimate: false,
                requestRenderMode: true, maximumRenderTimeChange: Infinity,
                contextOptions: { webgl: { alpha: false, powerPreference: 'low-power' } },
            });
            instance = viewer;
            viewer.useBrowserRecommendedResolution = false;
            viewer.resolutionScale = Math.min(window.devicePixelRatio || 1, 1.5) / (window.devicePixelRatio || 1);
            viewer.scene.screenSpaceCameraController.minimumZoomDistance = 1000;
            viewer.scene.screenSpaceCameraController.maximumZoomDistance = 45000000;
            viewer.scene.globe.enableLighting = false;
            const fallback = () => {
                if (ticket === generation) { failed = true; switchEngine('leaflet', text.fallback); }
            };
            viewer.scene.renderError.addEventListener(fallback);
            viewer.canvas.addEventListener('webglcontextlost', event => { event.preventDefault(); fallback(); });
            viewer.screenSpaceEventHandler.setInputAction(click => {
                const picked = viewer.scene.pick(click.position);
                const entity = picked && picked.id;
                const event = entity && typeof entity.id === 'string' && entity.id.startsWith('quake:')
                    ? events.find(item => String(item.id) === entity.id.slice(6)) : null;
                event ? showPopup(event) : closePopup();
            }, C.ScreenSpaceEventType.LEFT_CLICK);
            changeLayer();
            paintGlobe();
            userGlobe();
            if (radius) radiusGlobe(true);
            else fitGlobe(fitPending ? events : []);
            host.dataset.engine = 'cesium';
            status(text.ready);
        } catch (_) {
            if (ticket === generation) { failed = true; switchEngine('leaflet', text.fallback); }
        }
    }

    window.inicializarMapaListado = (id, items, distance) => {
        elementId = id;
        distanceId = distance;
        host = document.getElementById(id);
        host.classList.add('map-with-unified-controls');
        try {
            const saved = localStorage.getItem('sismos-mapa-capa');
            layer = { osm: 'osm', satelite: 'satellite', cartoDark: 'dark', topo: 'topo' }[saved] || 'osm';
        } catch (_) { /* Los controles funcionan sin almacenamiento local. */ }
        events = (items || []).filter(coordenadasSismoValidas);
        document.querySelectorAll('[data-map-engine]').forEach(button => {
            button.addEventListener('click', () => {
                const target = button.dataset.mapEngine;
                if (target !== engine && !(target === 'cesium' && failed)) switchEngine(target);
            });
        });
        document.querySelectorAll('[data-map-layer]').forEach(button => {
            button.addEventListener('click', () => { layer = button.dataset.mapLayer; changeLayer(); });
        });
        const tools = document.querySelector('.map-engine-toolbar');
        const toggle = document.getElementById('map-tools-toggle');
        toggle.addEventListener('click', () => {
            const collapsed = tools.classList.toggle('is-collapsed');
            document.getElementById('map-tools-options').hidden = collapsed;
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.setAttribute('aria-label', collapsed ? text.expand : text.collapse);
        });
        document.getElementById('map-globe-close').addEventListener('click', closePopup);
        document.addEventListener('keydown', event => { if (event.key === 'Escape') closePopup(); });
        switchEngine(config.engine);
    };
    window.pintarMarcadoresListado = (items, adjust) => {
        events = (items || []).filter(coordenadasSismoValidas);
        if (engine === 'leaflet') legacy.paint(events, adjust);
        else { paintGlobe(); if (adjust) fitGlobe(events); }
    };
    window.actualizarDistanciaListado = () => {
        if (engine === 'leaflet') { legacy.distances(); return; }
        document.querySelectorAll('.evento-item[data-lat][data-lng] .distancia-evento').forEach(element => {
            const item = element.closest('.evento-item');
            element.textContent = user && item
                ? mt('kmFromYou', calcularDistanciaKm(user.lat, user.lng, Number(item.dataset.lat), Number(item.dataset.lng)).toFixed(1))
                : mt('activateLocation');
        });
    };
    window.fijarUbicacionListado = (lat, lng, accuracy) => {
        if (!coordenadasSismoValidas({ lat, lng })) return;
        user = { lat, lng, accuracy };
        engine === 'leaflet' ? legacy.user(lat, lng, accuracy) : userGlobe();
        window.actualizarDistanciaListado();
    };
    window.ajustarVistaListado = items => {
        fitPending = true;
        engine === 'leaflet' ? legacy.fit(items) : fitGlobe(items);
    };
    window.dibujarRadioListado = (center, km, adjust) => {
        radius = { center, km };
        engine === 'leaflet' ? legacy.radius(center, km, adjust) : radiusGlobe(adjust);
    };
})();
