/* Initial camera regression using the real globe controller, without network. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/assets/js/modules/seismic-map/globe.js'), 'utf8');
const writes = [];
const views = [];
const elements = new Map();
const element = id => {
    if (!elements.has(id)) elements.set(id, {
        dataset: {}, classList: { add() {} }, setAttribute() {}, addEventListener() {},
        replaceChildren() {}, querySelector: () => ({ replaceChildren() {}, click() {} }),
    });
    return elements.get(id);
};
const leaflet = { setView: (...args) => views.push(args), remove() {} };
const context = {
    estadoMapaListado: null,
    SIGRA_GLOBE_CONFIG: { engine: 'leaflet', text: {} },
    localStorage: { getItem: () => null, setItem: (...args) => writes.push(args) },
    document: { getElementById: element, querySelector: element, querySelectorAll: () => [], addEventListener() {} },
    coordenadasSismoValidas: ({ lat, lng }) => Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180,
    inicializarMapaListado() { context.estadoMapaListado = { mapa: leaflet }; },
    pintarMarcadoresListado() {}, fijarUbicacionListado() {}, dibujarRadioListado() {},
    ajustarVistaListado() { throw Error('Location must take precedence over catalog bounds'); },
    actualizarDistanciaListado() {},
};
context.window = context;
vm.createContext(context);
// Expose the real centering routine only inside this test's VM.
vm.runInContext(source.replace(/\}\)\(\);\s*$/, `
    window.testCamera = { centerMap, setViewer(viewer, active) { instance = viewer; engine = active; } };
})();`), context);
// A disabled radius may arrive while the viewer is still loading.
context.dibujarRadioListado({ lat: 4.6, lng: -74.1 }, 0, false);
context.inicializarMapaListado('mapa', [], 'distance');
assert.deepEqual(Array.from(views.at(-1)[0]), [4.6, -74.1]);
context.fijarUbicacionListado(40.4, -3.7, 20);
assert.deepEqual(Array.from(views.at(-1)[0]), [40.4, -3.7]);
context.ajustarVistaListado([{ lat: -30, lng: 130 }]);
assert.deepEqual(Array.from(views.at(-1)[0]), [40.4, -3.7]);
assert.equal(views.at(-1)[1], 7);

// Location can arrive before Cesium finishes loading; retain it only in memory.
context.testCamera.setViewer(null, 'cesium');
context.fijarUbicacionListado(-33.4, -70.6, 10);
const globeViews = [];
context.Cesium = { Cartesian3: { fromDegrees: (...args) => args } };
context.testCamera.setViewer({ camera: { setView: view => globeViews.push(view) }, scene: { requestRender() {} } }, 'cesium');
context.testCamera.centerMap();
assert.deepEqual(Array.from(globeViews.at(-1).destination), [-70.6, -33.4, 1800000]);
assert.equal(globeViews.at(-1).orientation.pitch, -Math.PI / 2);
assert.deepEqual(writes.map(([key]) => key).filter(key => key !== 'sismos-mapa-capa'), []);
assert.doesNotMatch(source, /buildingsSession|crearEdificiosAbiertos|sigra-buildings|fitPending/);
const view = fs.readFileSync(path.join(root, 'app/Modules/SeismicCatalog/Presentation/Views/sismos/list/index.php'), 'utf8');
assert.doesNotMatch(view, /map-buildings-|seismic-map\/buildings\.js|buildingsUrl|map-terrain-toggle/);
console.log('OK: default Colombia, current location in 2D/3D, delayed viewer loading, location over remote events, no stored camera, no buildings or terrain controls.');
