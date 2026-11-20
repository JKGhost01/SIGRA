/* Fixed global hazard overlay: projection, lifecycle, filters and failed tiles. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');
const elements = new Map();
const overlays = [];
const errors = [];
let removed = 0;
let moved = 0;
const element = id => {
    if (!elements.has(id)) elements.set(id, { dataset: {}, listeners: {}, classList: { add() {} },
        setAttribute(key, value) { this[key] = value; },
        addEventListener(key, callback) { this.listeners[key] = callback; },
        replaceChildren() {}, querySelector: () => ({ replaceChildren() {}, click() {} }),
    });
    return elements.get(id);
};
const leaflet = { setView() { moved++; }, removeLayer() { removed++; } };
const context = {
    estadoMapaListado: null,
    SIGRA_GLOBE_CONFIG: { engine: 'leaflet', text: { hazardSummary: 'GEM v2026.1', hazardUnavailable: 'Unavailable' } },
    document: { getElementById: element, querySelector: element, querySelectorAll: () => [], addEventListener() {} },
    localStorage: { getItem: () => null, setItem() {} },
    L: { tileLayer(url, options) { return { url, options,
        on(type, callback) { assert.equal(type, 'tileerror'); errors.push(callback); },
        addTo(map) { overlays.push({ url, options, map }); return this; },
    }; } },
    inicializarMapaListado() { context.estadoMapaListado = { mapa: leaflet }; },
    pintarMarcadoresListado() {}, fijarUbicacionListado() {}, dibujarRadioListado() {},
    ajustarVistaListado() {}, actualizarDistanciaListado() {},
};
context.window = context;
vm.createContext(context);
vm.runInContext(read('public/assets/js/mapa-sismos.js').match(/function coordenadasSismoValidas[\s\S]*?\n\}/)[0], context);
vm.runInContext(read('public/assets/js/modules/seismic-map/hazard.js'), context);
vm.runInContext(read('public/assets/js/modules/seismic-map/globe.js').replace(/\}\)\(\);\s*$/, `
    window.testHazard = { updateHazard, clearHazard, setViewer(viewer) { instance = viewer; engine = 'cesium'; } };
})();`), context);
context.inicializarMapaListado('mapa', [], 'distance');
assert.equal(overlays.length, 0, 'No requests before enabling the layer');
element('map-hazard-toggle').listeners.click();
assert.equal(overlays.length, 1, 'Global hazard must render even with an empty recent catalog');
assert.match(overlays[0].url, /^https:\/\/maps\.openquake\.org\/.*v20260\/webmercator\/\{z\}\/\{x\}\/\{y\}\.png$/);
assert.match(overlays[0].options.attribution, /CC BY-NC-SA 4.0/);
assert.equal(overlays[0].options.maxNativeZoom, 8);
assert.equal(overlays[0].options.keepBuffer, 1);
assert.equal(moved, 1, 'The overlay must preserve the startup camera');
context.pintarMarcadoresListado([{ id: 'new', lat: -30, lng: 130 }], false);
assert.equal(overlays.length, 1, 'Filters must not replace or recolor fixed hazard zones');
errors[0]();
assert.equal(element('map-hazard-summary').textContent, 'Unavailable');
element('map-hazard-toggle').listeners.click();
assert.equal(removed, 1);
assert.equal(element('map-hazard-legend').hidden, true);
element('map-hazard-toggle').listeners.click();
assert.equal(overlays.length, 2);
errors[0]();
assert.equal(element('map-hazard-summary').textContent, 'GEM v2026.1', 'Old tile errors must not affect a retry');
context.testHazard.clearHazard();
const providers = [];
const layers = [];
context.Cesium = {
    WebMercatorTilingScheme: function() {},
    UrlTemplateImageryProvider: function(options) { Object.assign(this, options); providers.push(this);
        this.errorEvent = { addEventListener: callback => errors.push(callback) };
    },
};
context.testHazard.setViewer({ imageryLayers: {
    addImageryProvider(provider) { const layer = { provider }; layers.push(layer); return layer; },
    remove() { removed++; },
}, scene: { requestRender() {} } });
context.testHazard.updateHazard();
assert.equal(layers.length, 1);
assert.ok(providers[0].tilingScheme instanceof context.Cesium.WebMercatorTilingScheme);
assert.equal(providers[0].url, overlays[0].url);
assert.equal(providers[0].maximumLevel, 8);
assert.equal(providers[0].enablePickFeatures, false);
assert.equal(layers[0].alpha, 0.72);
context.testHazard.updateHazard();
assert.equal(layers.length, 1, 'Repeated renders must reuse the overlay');
errors.at(-1)();
assert.equal(element('map-hazard-summary').textContent, 'Unavailable');
element('map-hazard-toggle').listeners.click();
assert.equal(removed, 3, 'Every removed overlay must release its resources');
console.log('OK: GEM global source, empty catalog coverage, fixed colors under filters, Web Mercator in 2D/3D, credits, bounded zoom, no camera changes, retries and cleanup.');
