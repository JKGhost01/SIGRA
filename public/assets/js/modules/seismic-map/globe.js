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
    const base = config.cesiumBaseUrl || 'https://cesium.com/downloads/cesiumjs/releases/1.146/Build/Cesium/';
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
    let selected;
    let layer = 'osm';
    let failed = false;
    let hazardEnabled = false;
    let hazardOverlay;
    let hazardRevision = 0;
    const markers = new Map();
    let points;
    let epicenters;
    let stems;
    let radiusVolume;
    let underground = false;
    let requestedDepth = false;
    let horizon;
    let horizonCamera;
    let visibilityDirty = true;
    const eventIndex = new Map();

    function surfacePosition(event) {
        const C = window.Cesium;
        return C.Cartesian3.fromDegrees(event.lng, event.lat);
    }

    function updateVisibility() {
        const C = window.Cesium;
        const camera = instance.camera.positionWC;
        if (!visibilityDirty && C.Cartesian3.equals(camera, horizonCamera)) return;
        horizon.cameraPosition = camera;
        horizonCamera = C.Cartesian3.clone(camera, horizonCamera);
        visibilityDirty = false;
        for (const marker of markers.values()) {
            // Surface symbols are screen-facing disks, not pieces of terrain. Keep
            // their entire disk above the depth buffer, but still hide the far side.
            const visible = horizon.isPointVisible(marker.surface.position);
            marker.surface.show = visible;
        }
        const location = instance.entities.getById('sigra-user');
        if (location && user) location.show = horizon.isPointVisible(C.Cartesian3.fromDegrees(user.lng, user.lat));
        updateHighlight();
    }

    function eventHeight(event) {
        const depth = event.profundidad;
        return depth !== null && depth !== undefined && String(depth).trim() !== '' && Number.isFinite(Number(depth))
            ? -Number(depth) * 1000 : 0;
    }

    function updateUnderground() {
        const tilt = Math.abs(instance.camera.pitch + Math.PI / 2);
        // Hysteresis prevents flickering between modes while orbiting near the threshold.
        const tilted = requestedDepth || tilt > (underground ? Math.PI / 22.5 : Math.PI / 12);
        const translucency = instance.scene.globe.translucency;
        if (underground !== tilted) {
            underground = tilted;
            translucency.enabled = underground;
            depthAmbience();
            points.show = stems.show = underground;
            document.getElementById('map-depth-toggle').setAttribute('aria-pressed', String(underground));
            document.getElementById('map-depth-legend').hidden = !underground;
            for (const [id, marker] of markers) styleSurface(marker, eventIndex.get(id));
            updateHighlight();
            instance.scene.requestRender();
        }
    }

    function depthAmbience() {
        const C = window.Cesium;
        const scene = instance.scene;
        // Translucency over Cesium's default black interior crushes satellite
        // detail. A pale atmospheric interior keeps the subsurface readable.
        scene.backgroundColor = underground ? C.Color.fromCssColorString('#c7dee8') : C.Color.BLACK;
        if (scene.skyBox) scene.skyBox.show = !underground;
        if (scene.sun) scene.sun.show = !underground;
        if (scene.moon) scene.moon.show = !underground;
        for (let i = 0; i < instance.imageryLayers.length; i++) {
            const imagery = instance.imageryLayers.get(i);
            imagery.brightness = underground ? 1.08 : 1;
            imagery.gamma = underground ? 1.12 : 1;
            imagery.contrast = underground ? 0.98 : 1;
        }
    }

    function styleSurface(marker, event) {
        const style = estiloEpicentro(event);
        const anchor = underground && eventHeight(event) !== 0;
        marker.surface.pixelSize = anchor ? 4 : style.radius * 2;
        marker.surface.color = anchor ? window.Cesium.Color.fromCssColorString('#e5f2f4').withAlpha(0.85)
            : window.Cesium.Color.fromCssColorString(style.fillColor);
        marker.surface.outlineColor = anchor ? window.Cesium.Color.fromCssColorString('#173a49') : marker.point.outlineColor;
        marker.surface.outlineWidth = anchor ? 0.75 : style.weight;
    }

    function updateHighlight() {
        const event = eventIndex.get(selected);
        const highlight = instance.entities.getById('sigra-selected');
        if (event && highlight) {
            highlight.position = underground ? window.Cesium.Cartesian3.fromDegrees(event.lng, event.lat, eventHeight(event)) : surfacePosition(event);
            highlight.show = markers.get(selected)?.surface.show ?? false;
            highlight.point.disableDepthTestDistance = underground ? 0 : Number.POSITIVE_INFINITY;
        }
    }

    function toggleDepth() {
        if (!instance || engine !== 'cesium') return;
        const C = window.Cesium;
        requestedDepth = !underground;
        const camera = instance.camera;
        const center = camera.pickEllipsoid(new C.Cartesian2(instance.canvas.clientWidth / 2,
            instance.canvas.clientHeight / 2), instance.scene.globe.ellipsoid);
        let event = eventIndex.get(selected);
        if (!event && events.length) {
            const distance = item => C.Cartesian3.distanceSquared(center, C.Cartesian3.fromDegrees(item.lng, item.lat));
            event = center ? events.reduce((best, item) => distance(item) < distance(best) ? item : best) : events[0];
        }
        const target = event ? C.Cartesian3.fromDegrees(event.lng, event.lat,
            requestedDepth ? eventHeight(event) / 2 : 0) : center;
        if (target) {
            camera.lookAt(target, new C.HeadingPitchRange(camera.heading, requestedDepth ? -Math.PI / 4 : -Math.PI / 2,
                Math.max(300000, Math.min(2000000, C.Cartesian3.distance(camera.positionWC, target)))));
            camera.lookAtTransform(C.Matrix4.IDENTITY);
        }
        updateUnderground();
        instance.scene.requestRender();
    }

    function status(message) {
        document.getElementById('map-engine-status').textContent = message;
        document.querySelector('.map-engine-toolbar').dataset.status = message === text.loading ? 'loading'
            : [text.fallback, text.imageryUnavailable].includes(message) ? 'warning' : 'ready';
    }

    function clearHazard() {
        hazardRevision++;
        if (!hazardOverlay || !instance) return;
        if (engine === 'leaflet') instance.removeLayer(hazardOverlay);
        else instance.imageryLayers.remove(hazardOverlay, true);
        hazardOverlay = null;
    }

    function updateHazard() {
        const legend = document.getElementById('map-hazard-legend');
        document.getElementById('map-hazard-toggle').setAttribute('aria-pressed', String(hazardEnabled));
        legend.hidden = !hazardEnabled;
        if (!hazardEnabled) { clearHazard(); return; }
        if (!instance || hazardOverlay) return;
        const viewer = instance;
        const revision = ++hazardRevision;
        document.getElementById('map-hazard-summary').textContent = text.hazardSummary;
        const unavailable = () => {
            if (instance === viewer && hazardEnabled && hazardOverlay && revision === hazardRevision) {
                document.getElementById('map-hazard-summary').textContent = text.hazardUnavailable;
            }
        };
        if (engine === 'leaflet') {
            hazardOverlay = window.SIGRA_HAZARD.leaflet(L, unavailable).addTo(instance);
        } else {
            hazardOverlay = instance.imageryLayers.addImageryProvider(window.SIGRA_HAZARD.cesium(window.Cesium, unavailable));
            hazardOverlay.alpha = 0.72;
            instance.scene.requestRender();
        }
    }

    function controls(active) {
        document.getElementById('map-navigation').hidden = active !== 'cesium';
        const depth = document.getElementById('map-depth-toggle');
        depth.hidden = active !== 'cesium';
        depth.disabled = active !== 'cesium' || !points;
        depth.setAttribute('aria-pressed', String(underground));
        document.getElementById('map-depth-legend').hidden = active !== 'cesium' || !underground;
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
        emphasizeSelection(selected, false);
        selected = null;
        if (engine === 'cesium' && instance && !instance.isDestroyed()) {
            instance.entities.removeById('sigra-selected');
            instance.scene.requestRender();
        }
        const popup = document.getElementById('map-globe-popup');
        popup.hidden = true;
        popup.querySelector('[data-popup-content]').replaceChildren();
    }

    function showPopup(event) {
        if (selected !== String(event.id)) emphasizeSelection(selected, false);
        selected = String(event.id);
        emphasizeSelection(selected, true);
        if (engine === 'cesium' && instance) {
            const C = window.Cesium;
            let highlight = instance.entities.getById('sigra-selected');
            if (!highlight) highlight = instance.entities.add({ id: 'sigra-selected',
                point: { pixelSize: 22, color: C.Color.TRANSPARENT, outlineColor: C.Color.WHITE, outlineWidth: 2 },
            });
            updateHighlight();
            instance.scene.requestRender();
        }
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
        if (window.matchMedia?.('(max-width: 850px)').matches) window.plegarControlesMapa?.(true);
    }

    function emphasizeSelection(id, active) {
        const marker = markers.get(id);
        if (!marker) return;
        marker.stem.width = active ? 2.5 : 1.25;
        marker.stem.material.uniforms.color = window.Cesium.Color.fromCssColorString('#ffdc80').withAlpha(active ? 1 : 0.6);
    }

    function dispose() {
        clearHazard();
        closePopup();
        if (instance) {
            if (engine === 'cesium' && !instance.isDestroyed()) instance.destroy();
            else if (engine === 'leaflet') instance.remove();
        }
        instance = null;
        estadoMapaListado = null;
        markers.clear();
        eventIndex.clear();
        points = epicenters = stems = null;
        radiusVolume = null;
        underground = false;
        requestedDepth = false;
        horizon = horizonCamera = null;
        visibilityDirty = true;
        host.replaceChildren();
        delete host.dataset.engine;
    }

    function provider(C, name) {
        const base = capasBaseMapa()[name];
        const imagery = new C.UrlTemplateImageryProvider({ url: base.url, credit: base.credit, maximumLevel: base.level });
        return imagery;
    }

    function changeLayer() {
        controls(engine);
        try {
            localStorage.setItem('sismos-mapa-capa', { osm: 'osm', satellite: 'satelite', dark: 'cartoDark', topo: 'topo' }[layer]);
        } catch (_) { /* Las capas funcionan sin almacenamiento local. */ }
        if (!instance) return;
        if (engine === 'leaflet') {
            const names = { osm: 'osm', satellite: 'satelite', dark: 'cartoDark', topo: 'topo' };
            host.querySelector('[data-capa="' + names[layer] + '"]')?.click();
            return;
        }
        const C = window.Cesium;
        const imagery = provider(C, layer);
        imagery.errorEvent.addEventListener(() => {
            if (engine === 'cesium' && instance.imageryLayers.contains(imageryLayer)) status(text.imageryUnavailable);
        });
        clearHazard();
        instance.imageryLayers.removeAll();
        const imageryLayer = instance.imageryLayers.addImageryProvider(imagery);
        depthAmbience();
        updateHazard();
        instance.scene.requestRender();
        status(text.ready);
    }

    function paintGlobe() {
        if (!instance || engine !== 'cesium') return;
        const C = window.Cesium;
        const present = new Set();
        eventIndex.clear();
        events.forEach(event => {
            const id = String(event.id);
            present.add(id);
            eventIndex.set(id, event);
            const style = estiloEpicentro(event);
            const surface = surfacePosition(event);
            const height = eventHeight(event);
            const position = C.Cartesian3.fromDegrees(event.lng, event.lat, height);
            let marker = markers.get(id);
            if (!marker) {
                marker = {
                    point: points.add({ id: 'quake:' + id }),
                    surface: epicenters.add({ id: 'quake:' + id, disableDepthTestDistance: Number.POSITIVE_INFINITY }),
                    stem: stems.add({ id: 'quake:' + id, width: 1.25,
                        material: C.Material.fromType('Color'),
                    }),
                };
                markers.set(id, marker);
            }
            if (!C.Cartesian3.equals(marker.point.position, position)) {
                marker.point.position = position;
                marker.surface.position = surface;
                marker.stem.positions = [surface, position];
            }
            marker.point.pixelSize = Math.max(7, Math.min(14, style.radius * 1.5));
            const color = style.fillColor === '#1019d6' ? '#718cff' : style.fillColor;
            marker.point.color = C.Color.fromCssColorString(color);
            marker.point.outlineColor = C.Color.fromCssColorString('#edf6ff');
            marker.point.outlineWidth = 1;
            // Small surface anchors keep the epicenter selectable in the vertical view.
            marker.point.show = height !== 0;
            styleSurface(marker, event);
            marker.stem.show = height !== 0;
            marker.stem.material.uniforms.color = C.Color.fromCssColorString('#ffdc80').withAlpha(0.6);
        });
        for (const [id, marker] of markers) {
            if (!present.has(id)) {
                points.remove(marker.point);
                epicenters.remove(marker.surface);
                stems.remove(marker.stem);
                markers.delete(id);
            }
        }
        visibilityDirty = true;
        updateVisibility();
        if (selected !== null && selected !== undefined) {
            const current = eventIndex.get(selected);
            current ? showPopup(current) : closePopup();
        }
        instance.scene.requestRender();
    }

    // La cámara se calcula de nuevo en cada carga; no se persiste su posición.
    function centerMap() {
        if (!instance) return;
        const center = user || { lat: 4.6, lng: -74.1 };
        if (engine === 'leaflet') {
            instance.setView([center.lat, center.lng], user ? 7 : 5, { animate: false });
        } else {
            const C = window.Cesium;
            instance.camera.setView({
                destination: C.Cartesian3.fromDegrees(center.lng, center.lat, user ? 1800000 : 6000000),
                orientation: { heading: 0, pitch: -Math.PI / 2, roll: 0 },
            });
            instance.scene.requestRender();
        }
    }

    function fitGlobe(items) {
        if (!instance || engine !== 'cesium') return;
        const C = window.Cesium;
        const points = items.filter(coordenadasSismoValidas).flatMap(event => {
            const surface = C.Cartesian3.fromDegrees(event.lng, event.lat);
            return requestedDepth ? [surface, C.Cartesian3.fromDegrees(event.lng, event.lat, eventHeight(event))] : [surface];
        });
        if (!points.length) {
            centerMap();
            return;
        }
        const sphere = C.BoundingSphere.fromPoints(points);
        instance.camera.flyToBoundingSphere(sphere, {
            duration: 0,
            offset: new C.HeadingPitchRange(0, requestedDepth ? -Math.PI / 4 : -Math.PI / 2, Math.max(250000, sphere.radius * 2.6)),
        });
    }

    function userGlobe() {
        if (!instance || engine !== 'cesium' || !user) return;
        const C = window.Cesium;
        const old = instance.entities.getById('sigra-user');
        if (old) instance.entities.remove(old);
        instance.entities.add({ id: 'sigra-user', position: C.Cartesian3.fromDegrees(user.lng, user.lat),
            billboard: {
                image: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgUbicacionUsuario()),
                width: 40, height: 48, verticalOrigin: C.VerticalOrigin.BOTTOM,
                heightReference: C.HeightReference.CLAMP_TO_GROUND,
                disableDepthTestDistance: Number.POSITIVE_INFINITY,
            },
        });
        visibilityDirty = true;
        updateVisibility();
        instance.scene.requestRender();
    }

    function radiusGlobe(adjust) {
        if (!instance || engine !== 'cesium') return;
        const C = window.Cesium;
        const old = instance.entities.getById('sigra-radius');
        if (old) instance.entities.remove(old);
        if (radiusVolume) {
            instance.scene.primitives.remove(radiusVolume);
            radiusVolume = null;
        }
        if (radius && radius.km > 0 && radius.center) {
            const center = radius.center;
            instance.entities.add({ id: 'sigra-radius', position: C.Cartesian3.fromDegrees(center.lng, center.lat),
                ellipse: { semiMajorAxis: radius.km * 1000, semiMinorAxis: radius.km * 1000,
                    // A finely tessellated overlay above the ellipsoid avoids
                    // coplanar cuts in the translucent rim; filtering stays geodesic.
                    height: 200, granularity: 0.002,
                    material: new C.ImageMaterialProperty({
                        image: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgRadioCercania()),
                        transparent: true,
                    }),
                    outline: true, outlineColor: C.Color.fromCssColorString('#91f0e6').withAlpha(0.85),
                },
            });
            const meters = radius.km * 1000;
            const material = new C.Material({
                fabric: {
                    type: 'SigraRadiusGlass',
                    source: `czm_material czm_getMaterial(czm_materialInput inputData) {
                        czm_material material = czm_getDefaultMaterial(inputData);
                        vec3 normal = normalize(inputData.normalEC);
                        vec3 eye = normalize(inputData.positionToEyeEC);
                        float rim = pow(1.0 - abs(dot(normal, eye)), 2.8);
                        float reflection = pow(max(dot(reflect(-eye, normal), normalize(vec3(-0.4, 0.65, 1.0))), 0.0), 32.0);
                        material.diffuse = mix(vec3(0.12, 0.65, 0.63), vec3(0.65, 1.0, 0.95), rim);
                        material.emission = vec3(0.18, 0.55, 0.52) * rim * 0.35 + vec3(reflection * 0.3);
                        material.alpha = 0.018 + rim * 0.32 + reflection * 0.12;
                        return material;
                    }`,
                },
                translucent: true,
            });
            radiusVolume = instance.scene.primitives.add(new C.Primitive({
                geometryInstances: new C.GeometryInstance({
                    id: 'sigra-radius-volume',
                    geometry: new C.EllipsoidGeometry({ radii: new C.Cartesian3(meters, meters, meters),
                        stackPartitions: 48, slicePartitions: 96,
                        vertexFormat: C.MaterialAppearance.MaterialSupport.BASIC.vertexFormat,
                    }),
                }),
                appearance: new C.MaterialAppearance({ material, translucent: true, closed: false,
                    faceForward: true, materialSupport: C.MaterialAppearance.MaterialSupport.BASIC,
                }),
                // Glass must never intercept clicks on earthquakes or the house.
                allowPicking: false, asynchronous: false,
                modelMatrix: C.Transforms.eastNorthUpToFixedFrame(surfacePosition(center)),
            }));
            if (adjust) instance.camera.setView({
                destination: C.Cartesian3.fromDegrees(center.lng, center.lat, Math.max(120000, radius.km * 3500)),
                orientation: { heading: 0, pitch: -Math.PI / 2, roll: 0 },
            });
        } else if (adjust) {
            if (user) centerMap();
            else fitGlobe(events);
        }
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
            if (radius?.km > 0) legacy.radius(radius.center, radius.km, true);
            else centerMap();
            host.dataset.engine = 'leaflet';
            status(message || text.classic);
            updateHazard();
            return;
        }
        status(text.loading);
        try {
            const C = await loadCesium();
            if (ticket !== generation) return;
            C.Ion.defaultAccessToken = undefined;
            const viewer = new C.Viewer(host, {
                baseLayer: false, baseLayerPicker: false, terrainProvider: new C.EllipsoidTerrainProvider(),
                geocoder: false, animation: false, timeline: false, homeButton: false,
                sceneModePicker: false, navigationHelpButton: false, fullscreenButton: false,
                selectionIndicator: false, infoBox: false, shouldAnimate: false,
                requestRenderMode: true, maximumRenderTimeChange: Infinity,
                contextOptions: { webgl: { alpha: false, powerPreference: 'low-power' } },
            });
            instance = viewer;
            points = viewer.scene.primitives.add(new C.PointPrimitiveCollection({ blendOption: C.BlendOption.OPAQUE, show: false }));
            epicenters = viewer.scene.primitives.add(new C.PointPrimitiveCollection());
            stems = viewer.scene.primitives.add(new C.PolylineCollection({ show: false }));
            horizon = new C.EllipsoidalOccluder(viewer.scene.globe.ellipsoid, viewer.camera.positionWC);
            viewer.scene.globe.depthTestAgainstTerrain = true;
            viewer.scene.globe.translucency.frontFaceAlphaByDistance = new C.NearFarScalar(150000, 0.28, 10000000, 0.52);
            viewer.scene.globe.undergroundColor = C.Color.fromCssColorString('#9ec6cf');
            viewer.scene.globe.undergroundColorAlphaByDistance = new C.NearFarScalar(150000, 0.85, 10000000, 0.65);
            viewer.camera.percentageChanged = 0.01;
            viewer.scene.preRender.addEventListener(updateUnderground);
            viewer.scene.preRender.addEventListener(updateVisibility);
            controls(engine);
            viewer.useBrowserRecommendedResolution = false;
            viewer.resolutionScale = Math.min(window.devicePixelRatio || 1, 1.5) / (window.devicePixelRatio || 1);
            viewer.scene.screenSpaceCameraController.minimumZoomDistance = 10;
            viewer.scene.screenSpaceCameraController.maximumZoomDistance = 45000000;
            viewer.scene.screenSpaceCameraController.enableCollisionDetection = true;
            viewer.scene.globe.maximumScreenSpaceError = 1.5;
            viewer.scene.globe.tileCacheSize = 128;
            viewer.scene.globe.enableLighting = false;
            const fallback = () => {
                if (ticket === generation) { failed = true; switchEngine('leaflet', text.fallback); }
            };
            viewer.scene.renderError.addEventListener(fallback);
            viewer.canvas.addEventListener('webglcontextlost', event => { event.preventDefault(); fallback(); });
            // Viewer tracks entities on double click by default. A location or
            // selection marker must not replace our globe/depth camera controls.
            viewer.screenSpaceEventHandler.removeInputAction(C.ScreenSpaceEventType.LEFT_DOUBLE_CLICK);
            viewer.screenSpaceEventHandler.setInputAction(click => {
                const picked = viewer.scene.pick(click.position);
                const id = typeof picked?.id === 'string' ? picked.id : picked?.id?.id;
                const event = id === 'sigra-selected' ? eventIndex.get(selected)
                    : typeof id === 'string' && id.startsWith('quake:') ? eventIndex.get(id.slice(6)) : null;
                event ? showPopup(event) : closePopup();
            }, C.ScreenSpaceEventType.LEFT_CLICK);
            changeLayer();
            paintGlobe();
            userGlobe();
            if (radius?.km > 0) radiusGlobe(true);
            else centerMap();
            updateUnderground();
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
        document.getElementById('map-depth-toggle').addEventListener('click', toggleDepth);
        document.getElementById('map-hazard-toggle').addEventListener('click', () => {
            hazardEnabled = !hazardEnabled;
            updateHazard();
        });
        document.querySelectorAll('[data-map-zoom]').forEach(button => button.addEventListener('click', () => {
            if (!instance || engine !== 'cesium') return;
            const C = window.Cesium;
            const camera = instance.camera;
            const center = new C.Cartesian2(instance.canvas.clientWidth / 2, instance.canvas.clientHeight / 2);
            const target = instance.scene.globe.pick(camera.getPickRay(center), instance.scene);
            const ground = instance.scene.globe.getHeight(camera.positionCartographic) || 0;
            const clearance = Math.max(10, camera.positionCartographic.height - ground);
            const range = target ? C.Cartesian3.distance(camera.positionWC, target) : clearance;
            if (button.dataset.mapZoom === 'in') camera.zoomIn(Math.max(0, Math.min(range * 0.4, range - 10, clearance - 10)));
            else camera.zoomOut(Math.max(0, Math.min(range * 0.4, 45000000 - camera.positionCartographic.height)));
            instance.scene.requestRender();
        }));
        document.getElementById('map-north').addEventListener('click', () => {
            if (!instance || engine !== 'cesium') return;
            instance.camera.setView({ orientation: { heading: 0, pitch: instance.camera.pitch, roll: 0 } });
            instance.scene.requestRender();
        });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') closePopup(); });
        switchEngine(config.engine);
    };
    window.pintarMarcadoresListado = (items, adjust) => {
        events = (items || []).filter(coordenadasSismoValidas);
        if (engine === 'leaflet') legacy.paint(events, adjust);
        else { paintGlobe(); if (adjust) fitGlobe(events); }
        updateHazard();
    };
    window.redimensionarMapaListado = () => {
        if (!instance) return;
        if (engine === 'leaflet') instance.invalidateSize({ pan: false });
        else {
            instance.resize();
            instance.scene.requestRender();
        }
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
        centerMap();
        window.actualizarDistanciaListado();
    };
    window.ajustarVistaListado = items => {
        if (user) centerMap();
        else if (engine === 'leaflet') legacy.fit(items);
        else fitGlobe(items);
    };
    window.dibujarRadioListado = (center, km, adjust) => {
        radius = { center, km };
        engine === 'leaflet' ? legacy.radius(center, km, adjust) : radiusGlobe(adjust);
    };
})();
