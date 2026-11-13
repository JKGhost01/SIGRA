/** Regression tests for the actual map page scripts, without MySQL or a browser. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const dictionary = Object.fromEntries([...fs.readFileSync(
    path.join(root, 'app/Language/en/Sigra.php'), 'utf8')
    .matchAll(/'([^']+)'\s*=>\s*'((?:\\.|[^'\\])*)'/g)]
    .map(([, key, value]) => [key, value.replace(/\\'/g, "'").replace(/\\\\/g, '\\')]));
const text = { ...dictionary, locale: 'en-US', months: [] };
const pageScript = fs.readFileSync(path.join(root, 'public/assets/js/modules/seismic-map/catalog.js'), 'utf8');
const mapScript = fs.readFileSync(path.join(root, 'public/assets/js/mapa-sismos.js'), 'utf8');

function page({ saved = null, storageBlocked = false, permission = 'prompt', locate, syncResult = null } = {}) {
    const elements = new Map();
    const required = ['radio-filtro', 'btn-mi-ubicacion', 'estado-ubicacion', 'orden-eventos',
        'filtro-municipio', 'eventos-lista', 'contador-eventos', 'filtro-fuente', 'filtro-magnitud',
        'btn-limpiar-filtros', 'filtros-resumen', 'catalog-sync-status', 'catalog-source-indicator'];
    for (const id of required) elements.set(id, {
        value: id === 'radio-filtro' ? '0' : '', textContent: '', disabled: false,
        innerHTML: '', listeners: {},
        classList: {
            values: new Set(),
            toggle(name, enabled) { if (enabled) this.values.add(name); else this.values.delete(name); },
            contains(name) { return this.values.has(name); },
        },
        addEventListener(type, callback) { this.listeners[type] = callback; },
        querySelectorAll() { return []; },
        querySelector() { return this.label ||= { textContent: '' }; },
    });
    const ready = [];
    const store = new Map(saved === null ? [] : [['sigra-radio-km', saved]]);
    let requests = 0;
    let visible = [];
    const sandbox = {
        SIGRA_MAP_TEXT: text, SIGRA_MAP_URLS: { sync: "/sismos/sync", detail: "/sismos/detalle" },
        SISMOS: [{ id: 'near', lat: 4.6, lng: -74.1, ubicacion: 'Nearby', magnitud: 3 },
            { id: 'far', lat: 45, lng: 20, ubicacion: 'Far away', magnitud: 4 }],
        navigator: { geolocation: {}, permissions: { query: async () => ({ state: permission }) } },
        localStorage: {
            getItem(key) { if (storageBlocked) throw Error('Blocked'); return store.get(key) ?? null; },
            setItem(key, value) { if (storageBlocked) throw Error('Blocked'); store.set(key, value); },
            removeItem(key) { if (storageBlocked) throw Error('Blocked'); store.delete(key); },
        },
        document: {
            getElementById: id => elements.get(id) || null,
            querySelector: () => null, querySelectorAll: () => [],
            addEventListener(type, callback) { if (type === 'DOMContentLoaded') ready.push(callback); },
        },
        setTimeout: () => 1, clearTimeout() {}, setInterval: () => 1, clearInterval() {},
        matchMedia: () => ({ matches: false }),
        fetch: async () => ({ json: async () => syncResult || ({ ok: false }) }),
        inicializarMapaListado() {}, fijarUbicacionListado() {}, dibujarRadioListado() {},
        ajustarVistaListado() {}, actualizarDistanciaListado() {},
        pintarMarcadoresListado(events) { visible = events; },
        obtenerUbicacionUsuario() {
            requests++;
            return locate ? locate() : Promise.resolve({ coords: { latitude: 4.6, longitude: -74.1 } });
        },
    };
    sandbox.window = sandbox;
    vm.createContext(sandbox);
    // Use the real distance function and all of the real list/filter handlers.
    vm.runInContext(mapScript.match(/function calcularDistanciaKm[\s\S]*?\n\}/)[0], sandbox);
    vm.runInContext(pageScript, sandbox);
    ready.forEach(callback => callback());
    return {
        elements, store, get requests() { return requests; }, get visible() { return visible; },
        change(radius) { const el = elements.get('radio-filtro'); el.value = radius; el.listeners.change(); },
        click(id) { elements.get(id).listeners.click(); },
    };
}

const flush = () => new Promise(resolve => setImmediate(resolve));

async function main() {
    const partial = page({ syncResult: {
        ok: true, eventos: [{ id: 'near', lat: 4.6, lng: -74.1, ubicacion: 'Stored SGC', magnitud: 3 }],
        errors: { SGC: 'Source unavailable' }, last_success_at: 1800000000,
    } });
    await flush();
    assert.match(partial.elements.get('catalog-sync-status').textContent, /^SGC: update unavailable/);
    assert.equal(partial.elements.get('catalog-source-indicator').classList.contains('bg-warning'), true);
    assert.deepEqual(Array.from(partial.visible, event => event.id), ['near'], 'Partial failure hides stored data');
    for (const source of ['EMSC', 'GEONET', 'GEOFON', 'INGV']) {
        const degraded = page({ syncResult: { ok: true, eventos: [], errors: { [source]: 'Unavailable' } } });
        await flush();
        assert.ok(degraded.elements.get('catalog-sync-status').textContent.startsWith(source + ':'));
        assert.equal(degraded.elements.get('catalog-source-indicator').classList.contains('bg-warning'), true);
    }
    const healthy = page({ syncResult: { ok: true, eventos: [], errors: {}, last_success_at: 1800000000 } });
    await flush();
    assert.match(healthy.elements.get('catalog-sync-status').textContent, /^Last source update:/);
    assert.equal(healthy.elements.get('catalog-source-indicator').classList.contains('bg-success'), true);
    const multiple = page({ syncResult: { ok: true, errors: {}, eventos: [
        { id: 'emsc', fuente: 'EMSC', lat: 4, lng: -74, magnitud: 4 },
        { id: 'geonet', fuente: 'GEONET', lat: -40, lng: 174, magnitud: 4 },
        { id: 'geofon', fuente: 'GEOFON', lat: -36, lng: -97, magnitud: 5 },
        { id: 'ingv', fuente: 'INGV', lat: 40, lng: 14, magnitud: 3 },
    ] } });
    await flush();
    for (const source of ['EMSC', 'GEONET', 'GEOFON', 'INGV']) {
        const selector = multiple.elements.get('filtro-fuente');
        selector.value = source;
        selector.listeners.change();
        assert.deepEqual(Array.from(multiple.visible, event => event.fuente), [source]);
    }
    const p = page();
    await flush();
    assert.equal(p.elements.get('radio-filtro').disabled, false);
    assert.equal(p.requests, 0, 'Opening the page must not request location without permission');
    p.change('100');
    assert.equal(p.elements.get('estado-ubicacion').textContent, text.gettingLocation);
    await flush();
    assert.deepEqual(Array.from(p.visible, event => event.id), ['near']);
    assert.equal(p.elements.get('btn-mi-ubicacion').disabled, false);
    p.change('0');
    assert.equal(p.visible.length, 2, 'Worldwide restores all events');

    const restored = page({ saved: '250', permission: 'granted' });
    await flush();
    assert.equal(restored.requests, 1);
    assert.equal(restored.elements.get('radio-filtro').value, '250');
    assert.equal(restored.visible.length, 1);

    let fail = true;
    const retry = page({ permission: 'denied', locate: () => fail
        ? Promise.reject(Error('Location unavailable'))
        : Promise.resolve({ coords: { latitude: 4.6, longitude: -74.1 } }) });
    await flush();
    assert.equal(retry.requests, 0);
    retry.change('50');
    await flush();
    assert.equal(retry.elements.get('estado-ubicacion').textContent, text.locationError);
    assert.equal(retry.elements.get('btn-mi-ubicacion').disabled, false);
    assert.equal(retry.elements.get('radio-filtro').disabled, false);
    fail = false;
    retry.click('btn-mi-ubicacion');
    await flush();
    assert.equal(retry.visible.length, 1, 'Retry applies the chosen radius');

    const blocked = page({ storageBlocked: true });
    blocked.change('100');
    await flush();
    assert.equal(blocked.visible.length, 1, 'Filters work with blocked storage');

    let finish;
    const pending = page({ storageBlocked: true,
        locate: () => new Promise(resolve => { finish = resolve; }) });
    pending.change('50');
    pending.change('250');
    assert.equal(pending.requests, 1, 'Only one location request may be pending');
    pending.click('btn-limpiar-filtros');
    finish({ coords: { latitude: 4.6, longitude: -74.1 } });
    await flush();
    assert.equal(pending.elements.get('radio-filtro').value, '0');
    assert.equal(pending.visible.length, 2, 'A pending location must not undo clear filters');

    // Test the real geolocation fallback with an abortable, stalled IP provider.
    let abort;
    const geo = { navigator: { geolocation: { getCurrentPosition(ok, fail) { fail(Error('Denied')); } } },
        AbortController, mt: key => key,
        setTimeout(callback) { abort = callback; return 1; }, clearTimeout() {},
        fetch(url, { signal }) { return new Promise((resolve, reject) => {
            signal.addEventListener('abort', () => reject(Error('Aborted')));
        }); },
    };
    geo.window = geo;
    vm.createContext(geo);
    vm.runInContext(mapScript, geo);
    const location = vm.runInContext('obtenerUbicacionUsuario()', geo);
    const rejected = assert.rejects(location, /Aborted/);
    await flush();
    abort();
    await rejected;

    geo.fetch = async () => ({ ok: true, json: async () => ({ latitude: 4.6, longitude: -74.1 }) });
    const approximate = await vm.runInContext('obtenerUbicacionUsuario()', geo);
    assert.equal(approximate.coords.latitude, 4.6, 'IP fallback can recover from denied GPS');
    geo.navigator.geolocation = null;
    assert.equal((await vm.runInContext('obtenerUbicacionUsuario()', geo)).coords.longitude, -74.1);
    geo.fetch = async () => ({ ok: true, json: async () => ({ latitude: 200, longitude: 0 }) });
    await assert.rejects(vm.runInContext('obtenerUbicacionUsuario()', geo));
    console.log('OK: source-specific partial refresh, stored-data fallback, healthy refresh, initial load, radius filtering, saved preferences, denied location, retry, blocked storage, pending clear and IP timeout.');
}

main().catch(error => { console.error(error); process.exitCode = 1; });
