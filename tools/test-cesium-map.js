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
            page.on('pageerror', error => errors.push(error.message));

            await page.route('**/*', async route => {
                const url = route.request().url();
                if (url.startsWith(origin)) return route.continue();
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
                if (/tile\.openstreetmap\.org|tile\.opentopomap\.org|server\.arcgisonline\.com/.test(url)) return route.fulfill({ path: path.join(cesium, 'Assets/Textures/NaturalEarthII/0/0/0.jpg'), contentType: 'image/jpeg', headers: { 'access-control-allow-origin': '*' } });
                if (url.endsWith('/bootstrap.min.css')) return route.fulfill({ path: process.env.BOOTSTRAP_CSS || '/tmp/sigra-bootstrap.css', contentType: 'text/css' });
                if (url.endsWith('/leaflet.js')) return route.fulfill({ path: process.env.LEAFLET_JS, contentType: 'text/javascript' });
                if (url.endsWith('/leaflet.css')) return route.fulfill({ path: process.env.LEAFLET_CSS, contentType: 'text/css' });
                return route.abort();
            });
            return { page, context, errors, ion };
        }
        const normal = await pageWithRoutes();
        const page = normal.page;
        await page.goto(origin);
        await page.waitForSelector('#mapa[data-engine="cesium"]', { timeout: 30000 });
        assert.equal(await page.locator('#mapa canvas').count(), 1);
        assert.match(await page.locator('#map-engine-status').innerText(), /experimental/);
        assert.equal(await page.locator('.evento-item').count(), 3);
        const alignment = await page.evaluate(() => {
            const tools=document.querySelector('.map-engine-toolbar').getBoundingClientRect();
            const stage=document.querySelector('.map-stage').getBoundingClientRect();
            return { gap: stage.right-tools.right, width: tools.width, stageWidth:stage.width };
        });
        assert.ok(alignment.gap >= 12 && alignment.gap <= 20, 'Map controls are not aligned right');
        await page.locator('[data-map-layer="satellite"]').click();
        assert.equal(await page.locator('[data-map-layer="satellite"]').getAttribute('aria-pressed'), 'true');
        await page.locator('[data-map-layer="osm"]').click();
        await page.locator('#map-tools-toggle').click();
        assert.equal(await page.locator('#map-tools-options').isVisible(), false);
        await page.locator('#map-tools-toggle').click();
        assert.equal(await page.locator('#map-tools-options').isVisible(), true);
        await page.screenshot({ path: '/tmp/sigra-cesium-controls-desktop.png' });
        // The fixture instruments resize in the real library; production exposes no viewer globals.
        const coordinates = await page.evaluate(() => testViewer.entities.values.filter(e => e.id.startsWith('quake:')).map(e => {
            const p=Cesium.Cartographic.fromCartesian(e.position.getValue(Cesium.JulianDate.now()));
            return [Cesium.Math.toDegrees(p.latitude),Cesium.Math.toDegrees(p.longitude)];
        }));
        assert.equal(coordinates.length, 3);
        assert.ok(Math.abs(coordinates[0][0]-4.6)<0.000001 && Math.abs(coordinates[0][1]+74.1)<0.000001);
        await page.selectOption('#filtro-fuente', 'SGC');
        assert.equal(await page.locator('.evento-item').count(), 1);
        await page.selectOption('#filtro-fuente', '');
        await page.evaluate(() => {
            fijarUbicacionListado(4.8, -74.1, 10);
            dibujarRadioListado({ lat: 4.8, lng: -74.1 }, 500, true);
            pintarMarcadoresListado([
                { id: 'valid', lat: 4.6, lng: -74.1, magnitud: 4.2, profundidad: 12, ubicacion: '<img src=x onerror=alert(1)>', url: 'javascript:alert(1)' },
                { id: 'invalid', lat: 145, lng: 18, magnitud: 5 },
            ], true);
        });
        // Center point is visible after fitting a single epicenter; click it.
        const canvas = page.locator('#mapa canvas');
        const box = await canvas.boundingBox();
        await page.waitForTimeout(600);
        await canvas.click({ position: { x: box.width / 2, y: box.height / 2 }, force: true });
        await page.waitForSelector('#map-globe-popup:not([hidden])');
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
        assert.equal(await page.locator('#mapa .map-layer-switch').isVisible(), false);
        await page.locator('[data-map-layer="satellite"]').click();
        assert.equal(await page.evaluate(() => localStorage.getItem('sismos-mapa-capa')), 'satelite');
        await page.locator('[data-map-engine="cesium"]').click();
        await page.waitForSelector('#mapa[data-engine="cesium"]');
        assert.equal(await page.locator('#mapa canvas').count(), 1, 'Switch leaked a canvas');
        await page.setViewportSize({ width: 390, height: 844 });
        await page.waitForTimeout(300);
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
        console.log('Cesium real: 3D startup, filters, radius/location, picking, XSS, 3D/2D switching, mobile, blocked CDN and WebGL loss passed; no ion requests.');
    } finally { await browser.close(); server.close(); }
}
main().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
