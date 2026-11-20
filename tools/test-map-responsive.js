/* Real PHP page, Leaflet and optional Cesium, with local assets and no agency requests.
 * CHROME_PATH, LEAFLET_JS, LEAFLET_CSS, BOOTSTRAP_CSS, BOOTSTRAP_JS, CESIUM_DIR.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');
const fixture = locale => process.env['RESPONSIVE_HTML_' + locale.toUpperCase()]
    ? fs.readFileSync(process.env['RESPONSIVE_HTML_' + locale.toUpperCase()], 'utf8')
    : execFileSync('php', ['tests/Fixtures/CesiumMapPage.php', locale], { cwd: root }).toString();
const pages = { es: fixture('es'), en: fixture('en') };
const types = { '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.jpg': 'image/jpeg', '.json': 'application/json', '.wasm': 'application/wasm', '.svg': 'image/svg+xml' };
const cesium = process.env.CESIUM_DIR;
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/') {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(pages[url.searchParams.get('lang') || 'es']
            .replaceAll('http://127.0.0.1:8080', 'http://' + request.headers.host)
            .replace('"engine":"cesium"', '"engine":"' + (url.searchParams.get('engine') || 'leaflet') + '"'));
        return;
    }
    if (url.pathname.includes('/sync')) { response.setHeader('Content-Type', 'application/json'); response.end('{"ok":false}'); return; }
    const file = path.resolve(root, 'public', '.' + url.pathname);
    if (file.startsWith(path.join(root, 'public') + path.sep) && fs.existsSync(file) && fs.statSync(file).isFile()) {
        response.setHeader('Content-Type', types[path.extname(file)] || 'application/octet-stream');
        response.end(fs.readFileSync(file));
    } else { response.statusCode = 404; response.end(); }
});
async function main() {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH, headless: true,
        args: ['--no-sandbox', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
    try {
        const sizes = [[320,568], [360,640], [390,844], [430,932], [768,1024], [844,390], [1024,768], [1366,900]];
        const cases = sizes.map(viewport => ({ viewport, locale: 'es', engine: 'leaflet' }));
        for (const viewport of [[320,568], [844,390], [768,1024]]) cases.push({ viewport, locale: 'en', engine: 'leaflet' });
        if (cesium) for (const viewport of [[320,568], [390,844], [844,390], [768,1024], [1366,900]]) cases.push({ viewport, locale: 'es', engine: 'cesium' });
        const selectedCases = process.argv.includes('--keyboard-only') ? cases.filter(item => item.viewport[0] === 390) : cases;
        for (const { viewport: [width, height], locale, engine } of selectedCases) {
            const context = await browser.newContext({ viewport: { width, height }, hasTouch: width <= 850, isMobile: width <= 850 });
            const page = await context.newPage();
            page.setDefaultTimeout(12000);
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', async route => {
                const url = route.request().url();
                if (url.startsWith(origin)) return route.continue();
                if (url.includes('/Build/Cesium/') && cesium) {
                    const file = path.join(cesium, url.split('/Build/Cesium/')[1]);
                    return route.fulfill({ path: file, contentType: types[path.extname(file)] || 'application/octet-stream' });
                }
                for (const [suffix, file, type] of [
                    ['/leaflet.js', process.env.LEAFLET_JS || '/tmp/sigra-leaflet.js', 'text/javascript'],
                    ['/leaflet.css', process.env.LEAFLET_CSS || '/tmp/sigra-leaflet.css', 'text/css'],
                    ['/bootstrap.min.css', process.env.BOOTSTRAP_CSS || '/tmp/sigra-bootstrap.css', 'text/css'],
                    ['/bootstrap.bundle.min.js', process.env.BOOTSTRAP_JS || '/tmp/sigra-bootstrap.js', 'text/javascript'],
                ]) if (url.endsWith(suffix)) return route.fulfill({ path: file, contentType: type });
                if (/tile\.openstreetmap|arcgisonline|tile\.opentopomap|maps\.openquake/.test(url)) return route.fulfill({
                    path: path.join(root, 'tests/Fixtures/gem-hazard-world.png'), contentType: 'image/png', headers: { 'access-control-allow-origin': '*' },
                });
                return route.abort();
            });
            await page.goto(origin + '/?lang=' + locale + '&engine=' + engine);
            await page.waitForSelector('#mapa[data-engine="' + engine + '"]', { timeout: 30000 });
            await page.locator('#accept-cookies').click();
            const geometry = () => page.evaluate(() => {
                const map = document.querySelector('.map-stage').getBoundingClientRect();
                const footer = document.querySelector('.site-footer').getBoundingClientRect();
                const nav = document.querySelector('.navbar').getBoundingClientRect();
                return { width: innerWidth, height: innerHeight, scrollWidth: document.documentElement.scrollWidth,
                    scrollHeight: document.documentElement.scrollHeight, top: map.top, bottom: map.bottom,
                    footer: footer.top, header: nav.bottom, mapHeight: map.height };
            });
            await page.waitForTimeout(150);
            const layout = await geometry();
            assert.ok(layout.scrollWidth <= layout.width, JSON.stringify(layout));
            assert.ok(layout.bottom <= layout.footer + 1 && layout.top >= layout.header - 1 && layout.mapHeight > 100, JSON.stringify(layout));
            if (width <= 850) {
                assert.equal(await page.locator('#map-tools-options').isVisible(), false, 'Controls start compact on mobile');
                await page.locator('#map-tools-toggle').click();
                for (const button of await page.locator('.map-layer-options button').all()) {
                    const target = await button.boundingBox();
                    assert.ok(target.width >= 44 && target.height >= 44, 'Layer controls must have touch targets of at least 44px');
                }
                await page.locator('#map-hazard-toggle').click();
                assert.equal(await page.locator('#map-hazard-legend').isVisible(), true);
                const tools = await page.locator('.map-engine-toolbar').boundingBox();
                assert.ok(tools.x >= 0 && tools.x + tools.width <= width + 1 && tools.y + tools.height <= layout.bottom, JSON.stringify(tools));
                await page.locator('#map-tools-toggle').click();
                await page.locator('#mobile-events-toggle').click();
                assert.equal(await page.locator('#eventos-detalle').getAttribute('role'), 'dialog');
                assert.equal(await page.locator('#catalog-sync-status').evaluate(el => el.parentElement.id), 'mobile-catalog-status');
                assert.equal(await page.locator('.map-stage').evaluate(el => el.inert), true);
                assert.equal(await page.locator('#mobile-events-close').evaluate(el => el === document.activeElement), true);
                const sheet = await page.locator('#eventos-detalle').boundingBox();
                assert.ok(sheet.x >= 0 && sheet.x + sheet.width <= width + 1 && sheet.y >= layout.header && sheet.y + sheet.height <= layout.footer, JSON.stringify(sheet));
                assert.equal(await page.locator('#eventos-detalle').evaluate(el => el.scrollWidth <= el.clientWidth), true);
                assert.ok(Number(await page.locator('#filtro-municipio').evaluate(el => parseFloat(getComputedStyle(el).fontSize))) >= 16);
                if (await page.locator('#filtros-panel').getAttribute('open') === null) await page.locator('#filtros-panel > summary').click();
                await page.locator('#eventos-detalle').evaluate(sheet => {
                    const controls = Array.from(sheet.querySelectorAll('button, input, select, summary, a[href], [tabindex="0"]'))
                        .filter(control => !control.disabled && control.getClientRects().length > 0);
                    controls.at(-1).focus();
                });
                await page.keyboard.press('Tab');
                assert.equal(await page.locator('#mobile-events-close').evaluate(el => el === document.activeElement), true, 'Focus must remain inside the mobile dialog');
                await page.keyboard.press('Shift+Tab');
                assert.equal(await page.locator('#eventos-detalle').evaluate(el => el.contains(document.activeElement)), true);
                await page.locator('#filtro-municipio').fill('Colombia');
                await page.locator('#eventos-detalle').evaluate(el => el.scrollTop = el.scrollHeight);
                await page.keyboard.press('Escape');
                assert.equal(await page.locator('#mobile-events-toggle').evaluate(el => el === document.activeElement), true);
                assert.equal(await page.locator('.map-stage').evaluate(el => el.inert), false);
                if (width === 390) {
                    await page.screenshot({ path: '/tmp/sigra-mobile-' + engine + '.png' });
                    await page.locator('#mobile-events-toggle').click();
                    await page.locator('#eventos-detalle').evaluate(el => el.scrollTop = 0);
                    await page.screenshot({ path: '/tmp/sigra-mobile-sheet-' + engine + '.png' });
                    await page.evaluate(() => document.documentElement.dataset.bsTheme = 'dark');
                    await page.screenshot({ path: '/tmp/sigra-mobile-sheet-dark-' + engine + '.png' });
                    // Mobile keyboards can shrink visualViewport without changing innerHeight.
                    await page.evaluate(() => {
                        Object.defineProperty(visualViewport, 'height', { configurable: true, get: () => 480 });
                        visualViewport.dispatchEvent(new Event('resize'));
                    });
                    await page.waitForTimeout(100);
                    const visualSheet = await page.locator('#eventos-detalle').boundingBox();
                    const visualFooter = await page.locator('.site-footer').boundingBox();
                    assert.ok(visualSheet.y + visualSheet.height <= visualFooter.y + 1 && visualFooter.y + visualFooter.height <= 481,
                        'A keyboard must not hide the footer or dialog when layout viewport stays unchanged');
                    await page.evaluate(() => {
                        delete visualViewport.height;
                        visualViewport.dispatchEvent(new Event('resize'));
                    });
                    await page.setViewportSize({ width: 390, height: 480 });
                    await page.waitForTimeout(100);
                    const keyboardSheet = await page.locator('#eventos-detalle').boundingBox();
                    assert.ok(keyboardSheet.y + keyboardSheet.height <= (await geometry()).footer + 1, 'Sheet must fit the reduced visible viewport');

                    await page.keyboard.press('Escape');
                    await page.setViewportSize({ width: 932, height: 430 });
                    await page.waitForTimeout(100);
                    assert.equal(await page.locator('#eventos-detalle').getAttribute('aria-modal'), null);
                    assert.equal(await page.locator('#eventos-detalle').evaluate(el => el.inert), false);
                }
            } else assert.equal(await page.locator('#mobile-events-toggle').isVisible(), false);
            assert.deepEqual(errors, [], engine + ' ' + width + 'x' + height + ': page errors');
            console.log('PASS ' + engine + ' ' + locale + ' ' + width + 'x' + height);
            await context.close();
        }
    } finally { await browser.close(); server.close(); }
}
main().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
