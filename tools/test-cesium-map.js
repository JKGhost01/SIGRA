/* Motor Cesium real, HTML PHP real, catálogo y recursos locales; sin bases ni agencias.
 * CESIUM_DIR, LEAFLET_JS, LEAFLET_CSS, CHROME_PATH seleccionan dependencias locales.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const cesium = process.env.CESIUM_DIR;
const mime = { '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json',
    '.xml': 'application/xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.wasm': 'application/wasm' };
const fixture = execFileSync('php', ['tests/Fixtures/CesiumMapPage.php'], { cwd: root }).toString();
const english = execFileSync('php', ['tests/Fixtures/CesiumMapPage.php', 'en'], { cwd: root }).toString();
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/') { response.setHeader('Content-Type', 'text/html; charset=utf-8'); response.end((url.searchParams.has('en') ? english : fixture).replaceAll('http://127.0.0.1:8080', 'http://' + request.headers.host)); return; }
    const asset = url.pathname.startsWith('/cesium/')
        ? path.join(cesium, url.pathname.slice(8)) : path.join(root, 'public', url.pathname);
    if (fs.existsSync(asset) && fs.statSync(asset).isFile()) {
        response.setHeader('Content-Type', mime[path.extname(asset)] || 'application/octet-stream');
        response.end(fs.readFileSync(asset));
    } else { response.statusCode = 404; response.end(); }
});

async function main() {
    assert.ok(cesium && fs.existsSync(path.join(cesium, 'Cesium.js')), 'CESIUM_DIR must contain Cesium 1.146');
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = `http://127.0.0.1:${server.address().port}`;
    const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH, headless: true,
        args: ['--no-sandbox', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
    try {
        async function pageWithRoutes(options = {}) {
            const context = await browser.newContext({ viewport: { width: 1366, height: 850 }, ...options });
            const page = await context.newPage();
            const errors = [];
            const ion = [];
            const closedMaps = [];
            page.on('pageerror', error => errors.push(error.message));

            await page.route('**/*', async route => {
                const url = route.request().url();
                if (url.startsWith(origin)) return route.continue();
                if (/gibs\.earthdata\.nasa\.gov|elevation3d\.arcgis\.com|tile\.googleapis\.com/.test(url)) closedMaps.push(url);
                if (url.includes('api.cesium.com') || url.includes('assets.cesium.com')) ion.push(url);
                if (url.includes('/1.146/Build/Cesium/')) {
                    if (options.blockCesium) return route.abort();
                    const file = path.join(cesium, url.split('/Build/Cesium/')[1]);
                    if (url.endsWith('/Cesium.js')) {
                        const instrument = ';{const resize=Cesium.Viewer.prototype.resize; Cesium.Viewer.prototype.resize=function(){window.testViewer=this;return resize.apply(this,arguments);};}';
                        return route.fulfill({ body: fs.readFileSync(file, 'utf8') + instrument, contentType: 'text/javascript' });
                    }
                    return route.fulfill({ path: file, contentType: mime[path.extname(file)] || 'application/octet-stream' });
                }
                if (url.startsWith('https://maps.openquake.org/mapproxy/')) return route.fulfill({ path: process.env.GEM_TILE || path.join(root, 'tests/Fixtures/gem-hazard-world.png'), contentType: 'image/png', headers: { 'access-control-allow-origin': '*' } });
                if (/tile\.openstreetmap\.org|tile\.opentopomap\.org|server\.arcgisonline\.com|gibs\.earthdata\.nasa\.gov/.test(url)) return route.fulfill({ path: path.join(cesium, 'Assets/Textures/NaturalEarthII/0/0/0.jpg'), contentType: 'image/jpeg', headers: { 'access-control-allow-origin': '*' } });
                if (url.endsWith('/bootstrap.min.css')) return route.fulfill({ path: process.env.BOOTSTRAP_CSS || '/tmp/sigra-bootstrap.css', contentType: 'text/css' });
                if (url.endsWith('/leaflet.js')) return route.fulfill({ path: process.env.LEAFLET_JS, contentType: 'text/javascript' });
                if (url.endsWith('/leaflet.css')) return route.fulfill({ path: process.env.LEAFLET_CSS, contentType: 'text/css' });
                return route.abort();
            });
            return { page, context, errors, ion, closedMaps };
        }
        const normal = await pageWithRoutes();
        const page = normal.page;
        await page.goto(origin);
        await page.waitForSelector('#mapa[data-engine="cesium"]', { timeout: 30000 });
        assert.equal(await page.locator('#mapa canvas').count(), 1);
        assert.equal(await page.locator('#map-terrain-toggle').count(), 0);
        assert.equal(await page.locator('#map-buildings-toggle').count(), 0);
        assert.match(await page.locator('#map-engine-status').innerText(), /experimental/);
        assert.equal(await page.locator('.evento-item').count(), 3);
        const handle = page.locator('#catalog-panel-resizer');
        const tab = page.locator('#catalog-panel-toggle');
        const panelWidth = () => page.locator('.event-sidebar').evaluate(el => Math.round(el.getBoundingClientRect().width));
        const edge = await handle.boundingBox();
        await page.mouse.move(edge.x + edge.width / 2, edge.y + 200);
        await page.mouse.down();
        await page.mouse.move(edge.x + edge.width / 2 + 160, edge.y + 200, { steps: 10 });
        await page.mouse.up();
        assert.equal(await panelWidth(), 520, 'Drag must resize the list');
        await tab.click();
        assert.equal(await panelWidth(), 0);
        assert.equal(await page.locator('#catalog-panel').evaluate(el => el.inert), true);
        await page.reload();
        await page.waitForSelector('#mapa[data-engine="cesium"]');
        assert.equal(await panelWidth(), 0, 'Collapsed preference must survive reload');
        await tab.click();
        assert.equal(await panelWidth(), 520, 'Expanding must restore the saved width');
        await handle.focus();
        await page.keyboard.press('Home');
        assert.equal(await panelWidth(), 300);
        await page.keyboard.press('ArrowRight');
        assert.equal(await panelWidth(), 324);
        await page.keyboard.press('End');
        assert.equal(await panelWidth(), 640);
        // Return to a practical width for the remaining globe tests.
        await page.keyboard.press('Home');
        await page.keyboard.press('ArrowRight');
        await page.keyboard.press('ArrowRight');
        const resizedCanvas = await page.locator('#mapa canvas').evaluate(el => ({ width: el.clientWidth, host: el.parentElement.clientWidth }));
        assert.equal(resizedCanvas.width, resizedCanvas.host);

        const alignment = await page.evaluate(() => {
            const tools=document.querySelector('.map-engine-toolbar').getBoundingClientRect();
            const stage=document.querySelector('.map-stage').getBoundingClientRect();
            return { gap: stage.right-tools.right, width: tools.width, stageWidth:stage.width };
        });
        assert.ok(alignment.gap >= 12 && alignment.gap <= 20, 'Map controls are not aligned right');
        for (const layer of ['satellite', 'dark', 'topo', 'osm']) {
            await page.locator(`[data-map-layer="${layer}"]`).click();
            await page.waitForTimeout(150);
            assert.equal(await page.evaluate(() => testViewer.imageryLayers.length), 1);
        }
        await page.locator('#map-hazard-toggle').click();
        assert.equal(await page.locator('#map-hazard-legend').isVisible(), true);
        assert.match(await page.locator('#map-hazard-summary').innerText(), /GEM v2026.1/);
        assert.equal(await page.evaluate(() => testViewer.imageryLayers.length), 2);
        await page.locator('[data-map-layer="satellite"]').click();
        assert.equal(await page.evaluate(() => testViewer.imageryLayers.length), 2, 'Changing the base layer must preserve hazard without accumulating overlays');
        await page.locator('#map-hazard-toggle').click();
        assert.equal(await page.evaluate(() => testViewer.imageryLayers.length), 1);
        assert.equal(await page.locator('[data-map-layer="satellite"]').getAttribute('aria-pressed'), 'true');
        await page.locator('[data-map-layer="osm"]').click();
        await page.locator('#map-tools-toggle').click();
        assert.equal(await page.locator('#map-tools-options').isVisible(), false);
        await page.locator('#map-tools-toggle').click();
        assert.equal(await page.locator('#map-tools-options').isVisible(), true);
        await page.screenshot({ path: '/tmp/sigra-cesium-controls-desktop.png' });
        // The fixture instruments resize in the real library; production exposes no viewer globals.
        const coordinates = await page.evaluate(() => {
            const collections = [];
            for (let i = 0; i < testViewer.scene.primitives.length; i++) {
                const p = testViewer.scene.primitives.get(i);
                if (p instanceof Cesium.PointPrimitiveCollection) collections.push(p);
            }
            return { token: Cesium.Ion.defaultAccessToken,
                ellipsoid: testViewer.terrainProvider instanceof Cesium.EllipsoidTerrainProvider,
                entities: testViewer.entities.values.length,
                positions: Array.from({ length: collections[0].length }, (_, i) => {
                    const p = Cesium.Cartographic.fromCartesian(collections[0].get(i).position);
                    return [Cesium.Math.toDegrees(p.latitude), Cesium.Math.toDegrees(p.longitude), p.height];
                }) };
        });
        assert.equal(coordinates.token, undefined);
        assert.equal(coordinates.ellipsoid, true);
        assert.equal(coordinates.entities, 0);
        assert.equal(coordinates.positions.length, 3);
        assert.ok(coordinates.positions.some(p => Math.abs(p[0]-4.6)<0.000001 && Math.abs(p[1]+74.1)<0.000001));
        assert.ok(Math.abs(coordinates.positions[0][2]+12000)<0.01);
        // Regression for the crescents: probe the disk in all four directions over
        // the depth-tested ellipsoid AND the translucent near-me radius overlay.
        await page.evaluate(() => {
            pintarMarcadoresListado([
                { id: 'disk-0', lat: 3, lng: -76, magnitud: 4, profundidad: 12 },
                { id: 'disk-1', lat: 5, lng: -74, magnitud: 4, profundidad: 140 },
                { id: 'disk-2', lat: 7, lng: -72, magnitud: 4, profundidad: 3 },
                { id: 'far-side', lat: -5, lng: 106, magnitud: 6, profundidad: 140 },
            ], false);
            dibujarRadioListado({ lat: 5, lng: -74 }, 1000, false);
            testViewer.camera.lookAt(Cesium.Cartesian3.fromDegrees(-74, 5),
                new Cesium.HeadingPitchRange(0, -Math.PI / 2, 1800000));
            testViewer.camera.lookAtTransform(Cesium.Matrix4.IDENTITY);
            testViewer.scene.requestRender();
        });
        await page.waitForFunction(() => !testViewer.scene.globe.translucency.enabled);
        const disks = await page.evaluate(async () => {
            const scene = testViewer.scene;
            await new Promise(resolve => {
                const remove = scene.postRender.addEventListener(() => { remove(); resolve(); });
                scene.requestRender();
            });
            const samples = [[3,-76], [5,-74], [7,-72]].map(([lat,lng],i) => {
                const pos = Cesium.SceneTransforms.worldToWindowCoordinates(scene,
                    Cesium.Cartesian3.fromDegrees(lng, lat));
                return [[-5,0], [5,0], [0,-5], [0,5]].map(([x,y]) =>
                    scene.pick(new Cesium.Cartesian2(pos.x+x,pos.y+y),1,1)?.id === 'quake:disk-'+i);
            });
            let farSide;
            for(let i=0;i<scene.primitives.length;i++) {
                const p=scene.primitives.get(i);
                if(p instanceof Cesium.PointPrimitiveCollection) {
                    for(let j=0;j<p.length;j++) if(p.get(j).id==='quake:far-side' && p.get(j).disableDepthTestDistance===Infinity)
                        farSide=p.get(j).show;
                }
            }
            return { samples, farSide };
        });
        assert.deepEqual(disks.samples, Array.from({length:3},()=>[true,true,true,true]), 'Surface disks must remain complete over the globe and radius');
        assert.equal(disks.farSide, false, 'Surface symbols must not shine through the far hemisphere');
        await page.screenshot({path:'/tmp/sigra-cesium-complete-disks.png'});
        await page.evaluate(() => dibujarRadioListado(null, 0, false));
        // Exercise real GPU collections with a catalog larger than the reported production load.
        const bulk = await page.evaluate(async () => {
            const items = Array.from({ length: 1800 }, (_, i) => ({ id: 'bulk-' + i,
                lat: 4.6 + (i % 30) * 0.02, lng: -74.1 + Math.floor(i / 30) * 0.02,
                magnitud: 3, profundidad: [0, 15, 140, 634, null, 'invalid', -2][i % 7] }));
            pintarMarcadoresListado(items, true);
            testViewer.camera.setView({ destination: Cesium.Cartesian3.fromDegrees(-74, 4.8, 1800000),
                orientation: { heading: 0, pitch: -Math.PI / 4, roll: 0 } });
            await new Promise(resolve => setTimeout(resolve, 500));
            const collections = [];
            let lines;
            for (let i = 0; i < testViewer.scene.primitives.length; i++) {
                const p = testViewer.scene.primitives.get(i);
                if (p instanceof Cesium.PointPrimitiveCollection) collections.push(p);
                if (p instanceof Cesium.PolylineCollection) lines = p;
            }
            const result = { count: collections[0].length, entities: testViewer.entities.values.length,
                translucent: testViewer.scene.globe.translucency.enabled, stems: lines.show,
                interior: testViewer.scene.globe.undergroundColor.red,
                skyHidden: !testViewer.scene.skyBox.show,
                brightness: testViewer.imageryLayers.get(0).brightness,
                heights: Array.from({ length: 7 }, (_, i) => Cesium.Cartographic.fromCartesian(collections[0].get(i).position).height),
                ends: lines.get(3).positions.map(p => Cesium.Cartographic.fromCartesian(p).height) };
            const original = collections[0].get(3);
            pintarMarcadoresListado(items.slice(0, 10), false);
            result.reused = original === collections[0].get(3);
            result.remaining = collections[0].length;
            result.linesRemaining = lines.length;
            testViewer.camera.setView({ orientation: { heading: 0, pitch: -Math.PI / 2, roll: 0 } });
            await new Promise(resolve => setTimeout(resolve, 300));
            result.opaqueAgain = !testViewer.scene.globe.translucency.enabled && !lines.show;
            result.surfaceRestored = testViewer.scene.skyBox.show && testViewer.imageryLayers.get(0).brightness === 1;
            pintarMarcadoresListado([
                {id:'SGC-demo',lat:4.6,lng:-74.1,magnitud:4.2,profundidad:12,fuente:'SGC'},
                {id:'USGS-demo',lat:18.8925,lng:145.6025,magnitud:5.1,profundidad:12,fuente:'USGS'},
                {id:'EMSC-demo',lat:-16.4,lng:-71.5,magnitud:3.5,profundidad:12,fuente:'EMSC'},
            ], true);
            return result;
        });
        assert.equal(bulk.count, 1800);
        assert.equal(bulk.entities, 0);
        assert.equal(bulk.translucent, true);
        assert.equal(bulk.stems, true);
        assert.ok(bulk.interior > 0.5, 'Depth mode must use a bright atmospheric interior');
        assert.equal(bulk.skyHidden, true);
        assert.ok(bulk.brightness > 1);
        [0, -15000, -140000, -634000, 0, 0, 2000].forEach((height, i) => assert.ok(Math.abs(bulk.heights[i] - height) < 0.01));
        assert.ok(Math.abs(bulk.ends[0]) < 0.01 && Math.abs(bulk.ends[1] + 634000) < 0.01);
        assert.equal(bulk.reused, true);
        assert.equal(bulk.remaining, 10);
        assert.equal(bulk.linesRemaining, 10);
        assert.equal(bulk.opaqueAgain, true);
        assert.equal(bulk.surfaceRestored, true);
        await page.selectOption('#filtro-fuente', 'SGC');
        assert.equal(await page.locator('.evento-item').count(), 1);
        await page.selectOption('#filtro-fuente', '');
        await page.evaluate(() => {
            pintarMarcadoresListado([
                { id: 'shallow', lat: 4, lng: -75, magnitud: 4, profundidad: 15 },
                { id: 'deep', lat: 6, lng: -73, magnitud: 5, profundidad: 140 },
                { id: 'very-deep', lat: 5, lng: -71, magnitud: 5.5, profundidad: 634 },
            ], false);
            testViewer.camera.lookAt(Cesium.Cartesian3.fromDegrees(-73, 5),
                new Cesium.HeadingPitchRange(0, -Math.PI / 4, 1800000));
            testViewer.camera.lookAtTransform(Cesium.Matrix4.IDENTITY);
            testViewer.scene.requestRender();
        });
        await page.waitForFunction(() => {
            const scene = testViewer.scene;
            const position = Cesium.SceneTransforms.worldToWindowCoordinates(scene,
                Cesium.Cartesian3.fromDegrees(-73, 6, -70000));
            return position && scene.pick(position, 5, 5)?.id === 'quake:deep';
        });
        const visibleDepth = await page.evaluate(() => {
            const C = Cesium;
            const project = height => C.SceneTransforms.worldToWindowCoordinates(testViewer.scene,
                C.Cartesian3.fromDegrees(-73, 6, height));
            const hit = height => {
                const pos = project(height);
                const picked = testViewer.scene.pick(pos, 3, 3);
                return { id: picked?.id, kind: picked?.primitive?.constructor?.name,
                    x: pos.x, y: pos.y };
            };
            return { midpoint: hit(-70000), hypocenter: hit(-140000) };
        });
        assert.equal(visibleDepth.midpoint.id, 'quake:deep', 'Underground line must actually be pickable on screen');
        assert.equal(visibleDepth.hypocenter.id, 'quake:deep', 'Hypocenter must actually be visible through the globe');
        await page.locator('#mapa canvas').click({ position: {
            x: visibleDepth.hypocenter.x, y: visibleDepth.hypocenter.y } });
        await page.waitForSelector('#map-globe-popup:not([hidden])');
        assert.match(await page.locator('#map-globe-popup a').last().getAttribute('href'), /detalle\/deep$/);
        await page.keyboard.press('Escape');
        await page.locator('#accept-cookies').click();
        await page.screenshot({ path: '/tmp/sigra-cesium-hypocenters.png' });
        // Use the public control, including repeated toggles and a normal camera drag.
        await page.locator('#map-depth-toggle').click();
        await page.waitForTimeout(200);
        assert.equal(await page.locator('#map-depth-toggle').getAttribute('aria-pressed'), 'false');
        const surfaceMode = await page.evaluate(() => {
            const collections = [];
            for (let i = 0; i < testViewer.scene.primitives.length; i++) {
                const p = testViewer.scene.primitives.get(i);
                if (p instanceof Cesium.PointPrimitiveCollection) collections.push(p);
            }
            return { hidden: !collections[0].show, size: collections[1].get(1).pixelSize,
                alpha: testViewer.scene.globe.translucency.enabled };
        });
        assert.equal(surfaceMode.hidden, true);
        assert.equal(surfaceMode.alpha, false);
        assert.ok(surfaceMode.size > 6, 'Surface markers must retain magnitude styling');
        await page.locator('#map-depth-toggle').click();
        await page.waitForTimeout(200);
        assert.equal(await page.locator('#map-depth-toggle').getAttribute('aria-pressed'), 'true');
        await page.locator('#map-depth-toggle').click();
        await page.waitForTimeout(200);
        for (const [degrees, expected] of [[14, false], [16, true], [12, true], [7, false]]) {
            await page.evaluate(degrees => {
                testViewer.camera.setView({ orientation: { heading: 0,
                    pitch: -Math.PI / 2 + degrees * Math.PI / 180, roll: 0 } });
                testViewer.scene.requestRender();
            }, degrees);
            await page.waitForFunction(expected => document.getElementById('map-depth-toggle').getAttribute('aria-pressed') === String(expected), expected);
            assert.equal(await page.locator('#map-depth-toggle').getAttribute('aria-pressed'), String(expected));
        }
        await page.evaluate(() => {
            fijarUbicacionListado(4.8, -74.1, 10);
            dibujarRadioListado({ lat: 4.8, lng: -74.1 }, 500, true);
            pintarMarcadoresListado([
                { id: 'valid', lat: 4.6, lng: -74.1, magnitud: 4.2, profundidad: 12, ubicacion: '<img src=x onerror=alert(1)>', url: 'javascript:alert(1)' },
                { id: 'invalid', lat: 145, lng: 18, magnitud: 5 },
            ], true);
        });
        const house = await page.evaluate(() => {
            const location = testViewer.entities.getById('sigra-user');
            const now = Cesium.JulianDate.now();
            return { point: Boolean(location.point), image: location.billboard.image.getValue(now),
                width: location.billboard.width.getValue(now), height: location.billboard.height.getValue(now),
                depth: location.billboard.disableDepthTestDistance.getValue(now) };
        });
        assert.equal(house.point, false);
        assert.match(decodeURIComponent(house.image), /<svg.*viewBox="0 0 48 56"/);
        assert.equal(house.width, 40);
        assert.equal(house.height, 48);
        assert.equal(house.depth, Infinity);
        const bubble = await page.evaluate(() => {
            const ellipse = testViewer.entities.getById('sigra-radius').ellipse;
            const now = Cesium.JulianDate.now();
            return { major: ellipse.semiMajorAxis.getValue(now), minor: ellipse.semiMinorAxis.getValue(now),
                image: ellipse.material.image.getValue(now), transparent: ellipse.material.transparent.getValue(now) };
        });
        assert.equal(bubble.major, 500000);
        assert.equal(bubble.minor, 500000);
        assert.equal(bubble.transparent, true);
        assert.match(decodeURIComponent(bubble.image), /radialGradient/);
        const volumes = await page.evaluate(() => {
            const result = [];
            for (let i=0;i<testViewer.scene.primitives.length;i++) {
                const p=testViewer.scene.primitives.get(i);
                if(p.appearance?.material?.type==='SigraRadiusGlass') result.push({ picking:p.allowPicking, ready:p.ready });
            }
            return result;
        });
        assert.equal(volumes.length, 1, 'Radius updates must leave exactly one real 3D glass volume');
        assert.equal(volumes[0].picking, false, 'The glass must allow earthquake/house clicks through');


        await page.waitForFunction(() => {
            const scene = testViewer.scene;
            const pos = Cesium.SceneTransforms.worldToWindowCoordinates(scene,
                Cesium.Cartesian3.fromDegrees(-74.1, 4.8));
            scene.requestRender();
            return pos && scene.pick(new Cesium.Cartesian2(pos.x, pos.y - 24))?.id?.id === 'sigra-user';
        });
        const houseCamera = await page.evaluate(() => {
            const camera = testViewer.camera;
            const location = Cesium.SceneTransforms.worldToWindowCoordinates(testViewer.scene,
                Cesium.Cartesian3.fromDegrees(-74.1, 4.8));
            const at = new Cesium.Cartesian2(location.x, location.y - 24);
            const picked = testViewer.scene.pick(at);
            return { x: at.x, y: at.y, picked: picked?.id?.id,
                position: Cesium.Cartesian3.pack(camera.positionWC, []),
                direction: Cesium.Cartesian3.pack(camera.directionWC, []),
                up: Cesium.Cartesian3.pack(camera.upWC, []) };
        });
        assert.equal(houseCamera.picked, 'sigra-user', 'Double click must hit the actual house');
        await page.locator('#mapa canvas').dblclick({ position: { x: houseCamera.x, y: houseCamera.y } });
        await page.waitForTimeout(500);
        const afterDouble = await page.evaluate(() => ({
            tracked: Boolean(testViewer.trackedEntity),
            position: Cesium.Cartesian3.pack(testViewer.camera.positionWC, []),
            direction: Cesium.Cartesian3.pack(testViewer.camera.directionWC, []),
            transform: Cesium.Matrix4.equals(testViewer.camera.transform, Cesium.Matrix4.IDENTITY),
        }));
        assert.equal(afterDouble.tracked, false, 'The house must never start entity tracking');
        assert.equal(afterDouble.transform, true);
        afterDouble.position.forEach((value, i) => assert.ok(Math.abs(value - houseCamera.position[i]) < 0.00001));
        afterDouble.direction.forEach((value, i) => assert.ok(Math.abs(value - houseCamera.direction[i]) < 1e-12));
        await page.locator('#map-depth-toggle').click();
        assert.equal(await page.locator('#map-depth-toggle').getAttribute('aria-pressed'), 'true');
        await page.locator('#map-depth-toggle').click();
        assert.equal(await page.locator('#map-depth-toggle').getAttribute('aria-pressed'), 'false');
        assert.equal(await page.evaluate(() => Boolean(testViewer.trackedEntity)), false);
        await page.evaluate(saved => {
            testViewer.camera.setView({ destination: Cesium.Cartesian3.fromArray(saved.position),
                orientation: { direction: Cesium.Cartesian3.fromArray(saved.direction),
                    up: Cesium.Cartesian3.fromArray(saved.up) } });
        }, houseCamera);
        // Center point is visible after fitting a single epicenter; click it.
        const canvas = page.locator('#mapa canvas');
        const box = await canvas.boundingBox();
        await page.waitForTimeout(600);
        await canvas.click({ position: { x: box.width / 2, y: box.height / 2 }, force: true });
        await page.waitForSelector('#map-globe-popup:not([hidden])');
        assert.equal(await page.evaluate(() => testViewer.entities.values.filter(e => e.id === 'sigra-selected').length), 1);
        assert.equal(await page.locator('#map-globe-popup img').count(), 0);
        assert.equal(await page.locator('#map-globe-popup a[href^="javascript:"]').count(), 0);
        assert.match(await page.locator('#map-globe-popup a').last().getAttribute('href'), /\/sismos\/detalle\/valid$/);
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#map-globe-popup').isVisible(), false);
        await page.locator('[data-map-engine="leaflet"]').click();
        await page.waitForSelector('#mapa[data-engine="leaflet"]');
        const classic = await page.evaluate(() => ({ points: estadoMapaListado.capaSismos.getLayers().map(m => m.getLatLng()),
            user: estadoMapaListado.posicionUsuario, radius: estadoMapaListado.circuloRadio.getRadius() }));
        assert.deepEqual(classic.points, [{ lat: 4.6, lng: -74.1 }]);
        assert.deepEqual(classic.user, { lat: 4.8, lng: -74.1 });
        assert.equal(classic.radius, 500000);
        assert.equal(await page.locator('#mapa path.sigra-radius-bubble').count(), 1);
        assert.match(await page.locator('#mapa path.sigra-radius-bubble').getAttribute('fill'), /url\(#sigra-radius-/);
        await page.evaluate(() => dibujarRadioListado({lat:4.8,lng:-74.1},250,true));
        await page.waitForTimeout(300);
        await page.screenshot({path:'/tmp/sigra-radius-bubble-leaflet.png'});

        assert.deepEqual(await page.evaluate(() => estadoMapaListado.usuarioMarker.options.icon.options.iconSize), [40, 48]);
        assert.equal(await page.locator('#mapa .icono-ubicacion-usuario svg').count(), 1);
        assert.equal(await page.locator('#mapa .map-layer-switch').isVisible(), false);
        await tab.click();
        assert.equal(await panelWidth(), 0);
        await page.waitForTimeout(200);
        assert.equal(await page.evaluate(() => estadoMapaListado.mapa.getSize().x === document.getElementById('mapa').clientWidth), true);
        await tab.click();
        await page.locator('[data-map-layer="satellite"]').click();
        assert.equal(await page.evaluate(() => localStorage.getItem('sismos-mapa-capa')), 'satelite');
        await page.locator('[data-map-engine="cesium"]').click();
        await page.waitForSelector('#mapa[data-engine="cesium"]');
        assert.equal(await page.locator('#mapa canvas').count(), 1, 'Switch leaked a canvas');
        await page.waitForTimeout(700);
        await page.screenshot({path:'/tmp/sigra-radius-bubble-cesium.png'});
        await page.evaluate(() => {
            testViewer.camera.lookAt(Cesium.Cartesian3.fromDegrees(-74.1,4.8,100000),
                new Cesium.HeadingPitchRange(0,-Math.PI/4,1200000));
            testViewer.camera.lookAtTransform(Cesium.Matrix4.IDENTITY);
            testViewer.scene.requestRender();
        });
        await page.waitForTimeout(500);
        await page.screenshot({path:'/tmp/sigra-radius-bubble-volume.png'});
        await page.evaluate(() => {
            dibujarRadioListado(null,0,false);
            let volumes=0;
            for(let i=0;i<testViewer.scene.primitives.length;i++)
                if(testViewer.scene.primitives.get(i).appearance?.material?.type==='SigraRadiusGlass') volumes++;
            if(volumes!==0) throw Error('Clearing the filter leaked its 3D bubble');
            dibujarRadioListado({lat:4.8,lng:-74.1},250,true);
        });


        await tab.click();
        await page.setViewportSize({ width: 390, height: 844 });
        assert.equal(await tab.isVisible(), false, 'Desktop tab must not cover the mobile map');
        await page.waitForFunction(() => !document.getElementById('catalog-panel').inert);
        assert.equal(await page.locator('#catalog-panel').evaluate(el => el.inert), false, 'Desktop collapse must not disable the mobile list');
        await page.locator('#mobile-events-toggle').click();
        assert.equal(await page.locator('#eventos-detalle').isVisible(), true);
        await page.locator('#mobile-events-close').click();

        await page.waitForTimeout(300);
        assert.equal(await page.locator('#map-tools-options').isVisible(), false, 'Mobile controls should start collapsed');
        await page.locator('#map-tools-toggle').click();
        assert.ok(await page.locator('[data-map-engine="leaflet"]').isVisible());
        await page.locator('#map-tools-toggle').click();
        assert.equal(await page.locator('#map-tools-options').isVisible(), false);
        await page.screenshot({ path: '/tmp/sigra-cesium-controls-mobile-collapsed.png' });
        await page.locator('#map-tools-toggle').click();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
        assert.equal(overflow, false, 'Mobile view overflows');
        await page.screenshot({ path: '/tmp/sigra-cesium-mobile.png' });
        await page.evaluate(() => document.documentElement.dataset.bsTheme = 'dark');
        await page.screenshot({ path: '/tmp/sigra-cesium-controls-mobile-dark.png' });
        assert.deepEqual(normal.errors, []);
        assert.deepEqual(normal.ion, [], 'Globe unexpectedly requires Cesium ion');
        assert.deepEqual(normal.closedMaps, [], 'The restored map must not request NASA, ArcGIS terrain or Google tiles');
        await normal.context.close();

        const denied = await pageWithRoutes({ blockCesium: true });
        await denied.page.goto(origin + '/?en=1');
        await denied.page.waitForSelector('#mapa[data-engine="leaflet"]');
        assert.match(await denied.page.locator('#map-engine-status').innerText(), /unavailable/);
        assert.equal(await denied.page.locator('.evento-item').count(), 3);
        assert.equal(await denied.page.locator('[data-map-engine="cesium"]').isDisabled(), true);
        assert.deepEqual(denied.errors, []);
        await denied.context.close();

        const lost = await pageWithRoutes();
        await lost.page.goto(origin);
        await lost.page.waitForSelector('#mapa[data-engine="cesium"]');
        await lost.page.evaluate(() => document.querySelector('#mapa canvas').dispatchEvent(new Event('webglcontextlost', { cancelable: true })));
        await lost.page.waitForSelector('#mapa[data-engine="leaflet"]');
        assert.equal(await lost.page.locator('.evento-item').count(), 3);
        assert.deepEqual(lost.errors, []);
        await lost.context.close();
        console.log('Cesium real: hypocenters, four base layers, filters, radius/location, picking, 3D/2D switching, mobile and fallback checks passed; ellipsoid only, no ion requests.');
    } finally { await browser.close(); server.close(); }
}
main().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
