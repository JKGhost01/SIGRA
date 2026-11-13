/** Browser regression for polar clamping, coordinate validation and bounded vertical panning.
 * LEAFLET_JS / LEAFLET_CSS point to local Leaflet 1.9.4 files. CHROME_PATH selects Chromium.
 */
const { chromium } = require('playwright-core');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

async function main() {
    const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH, headless: true, args: ['--no-sandbox'] });
    try {
        const page = await browser.newPage({ viewport: { width: 1178, height: 700 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        // The regression needs real Leaflet projection/rendering, with no external HTTP requests.
        await page.route('**/*', route => route.abort());
        await page.setContent('<html><body style="margin:0"><div id="mapa" style="height:640px;width:100%"></div></body></html>');
        await page.addStyleTag({ path: process.env.LEAFLET_CSS });
        await page.addScriptTag({ path: process.env.LEAFLET_JS });
        await page.addScriptTag({ content: 'window.SIGRA_ICONS={pin:"",home:""}; window.SIGRA_I18N={map:{}};' });
        await page.addScriptTag({ path: path.join(__dirname, '../public/assets/js/mapa-sismos.js') });
        const state = await page.evaluate(async () => {
            const fixtures = [
                { id: 'Colombia synthetic validation', lat: 4.6, lng: -74.1, magnitud: 4 },
                { id: 'Pacific public observation', lat: 18.8925, lng: 145.6025, magnitud: 4.9 },
                { id: 'Invalid legacy Pacific', lat: 145.6025, lng: 18.8925, magnitud: 4.9 },
                { id: 'Malformed', lat: null, lng: -74, magnitud: 4 },
            ];
            inicializarMapaListado('mapa', fixtures);
            const map = estadoMapaListado.mapa;
            // Remove external tiles; the scientific coordinate checks remain real Leaflet rendering.
            map.eachLayer(layer => { if (layer instanceof L.TileLayer) map.removeLayer(layer); });
            const coordinates = estadoMapaListado.capaSismos.getLayers().map(layer => layer.getLatLng());
            map.panBy([0, 2000], { animate: false });
            await new Promise(resolve => setTimeout(resolve, 350));
            const south = map.getPixelBounds();
            map.panBy([0, -4000], { animate: false });
            await new Promise(resolve => setTimeout(resolve, 350));
            const north = map.getPixelBounds();
            map.setView([20, 0], 2, { animate: false });
            return { coordinates, south: { min: south.min.y, max: south.max.y }, north: { min: north.min.y, max: north.max.y } };
        });
        assert.deepEqual(state.coordinates, [{ lat: 4.6, lng: -74.1 }, { lat: 18.8925, lng: 145.6025 }]);
        assert.ok(state.south.max <= 1025 && state.north.min >= -1, JSON.stringify(state));
        await page.setViewportSize({ width: 390, height: 700 });
        await page.evaluate(() => estadoMapaListado.mapa.invalidateSize({ animate: false }));
        const mobile = await page.evaluate(() => estadoMapaListado.capaSismos.getLayers().map(layer => layer.getLatLng()));
        assert.deepEqual(mobile, state.coordinates);
        assert.deepEqual(errors, []);
        console.log('Leaflet browser: Colombia/Pacific placement, invalid records rejected, polar bounds and mobile resize passed.');
    } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exit(1); });
