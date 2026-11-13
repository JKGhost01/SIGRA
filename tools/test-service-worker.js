/** Static offline assets must exist and authenticated/API requests must bypass caches. */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.join(__dirname, '..');
for (const name of ['sw.js', 'public/sw.js']) {
    const handlers = {};
    const context = {
        self: { location: { origin: 'https://example.test' }, addEventListener(name, action) { handlers[name] = action; } },
        URL,
    };
    const script = fs.readFileSync(path.join(root, name), 'utf8');
    vm.runInNewContext(script + ';this.testCore = CORE;', context);
    for (const url of context.testCore) {
        const file = path.join(root, name === 'public/sw.js' ? 'public' : '', url);
        assert.ok(fs.existsSync(file), `${name}: missing offline asset ${url}`);
    }
    for (const url of ['/api/sismos/recent', '/index.php/api/red/alertas', '/sismos/sync', '/index.php/sismos/sync']) {
        let handled = false;
        handlers.fetch({ request: { method: 'GET', mode: 'navigate', url: 'https://example.test' + url },
            respondWith() { handled = true; } });
        assert.equal(handled, false, `${name}: dynamic request was intercepted`);
    }
}
console.log('PWA: root/public offline assets exist; APIs and refresh requests bypass caches.');
