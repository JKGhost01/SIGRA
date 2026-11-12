/**
 * Pruebas de humo de SIGRA con un navegador real.
 *
 * Requisitos:
 *   cd tools
 *   npm install
 *   node test-web.js
 *
 * Variables:
 *   SIGRA_BASE_URL   URL base (por defecto http://localhost:8080)
 *   CHROME_PATH      Ruta a Chrome/Edge (opcional)
 *   SIGRA_HEADLESS   "false" para ver el navegador
 */
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');

const BASE = (process.env.SIGRA_BASE_URL || 'http://localhost:8080').replace(/\/$/, '');
const OUT = path.join(__dirname, '..', 'writable', 'test-web');
fs.mkdirSync(OUT, { recursive: true });

const candidatos = [
  process.env.CHROME_PATH,
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  '/usr/bin/google-chrome',
  '/usr/bin/chromium',
  '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
].filter(Boolean);
const chrome = candidatos.find((p) => fs.existsSync(p));

if (!chrome) {
  console.error('No se encontró Chrome/Edge. Define CHROME_PATH.');
  process.exit(2);
}

const resultados = [];
let browser;

async function abrir(viewport) {
  const context = await browser.newContext({ viewport, locale: 'es-CO', ignoreHTTPSErrors: true });
  await context.grantPermissions(['notifications'], { origin: BASE }).catch(() => {});
  await context.addInitScript(() => {
    try {
      localStorage.setItem('sigra_my_location_v1', JSON.stringify({
        lat: 5.10,
        lng: -75.30,
        accuracy: 15,
        ts: Date.now(),
      }));
      localStorage.removeItem('sigra_seen_alerts_v1');
      localStorage.removeItem('sigra_seen_alerts_init_v1');
    } catch (_) {
      // almacenamiento bloqueado
    }
  });

  const page = await context.newPage();
  const errores = [];
  page.on('pageerror', (e) => errores.push('PAGEERROR: ' + e.message));
  page.on('console', (m) => {
    if (m.type() === 'error') errores.push('CONSOLE: ' + m.text());
  });
  page.on('requestfailed', (r) => {
    if (r.url().startsWith(BASE)) errores.push('REQUESTFAIL: ' + r.url());
  });
  page.on('response', (r) => {
    if (r.url().startsWith(BASE) && r.status() >= 400) {
      errores.push('HTTP ' + r.status() + ': ' + r.url());
    }
  });

  return { context, page, errores };
}

async function cerrarOverlay(page, nombre) {
  const overlay = page.locator('#sigra-alert-overlay.is-open');
  await overlay.waitFor({ state: 'visible', timeout: 2500 }).catch(() => {});
  if ((await overlay.count()) === 0) return null;

  await page.waitForTimeout(350);
  const visible = await overlay.isVisible().catch(() => false);
  if (!visible) return null;

  const magnitud = await page.locator('#sigra-alert-mag').innerText().catch(() => '');
  const segundos = await page.locator('#sigra-alert-seconds').innerText().catch(() => '');
  await page.screenshot({ path: path.join(OUT, nombre + '-overlay.png'), fullPage: false }).catch(() => {});

  const boton = page.locator('#sigra-alert-dismiss');
  if ((await boton.count()) > 0) {
    await boton.click();
    await page.waitForTimeout(300);
  }

  return magnitud + ' · ' + segundos;
}

async function probar(nombre, ruta, viewport, selector, espera, accion) {
  const { context, page, errores } = await abrir(viewport);
  let estado = 'ok';
  let detalle = '';
  let overlay = null;

  try {
    const respuesta = await page.goto(BASE + ruta, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.waitForTimeout(espera);

    if (!respuesta || respuesta.status() >= 400) {
      estado = 'http';
      detalle = 'HTTP ' + (respuesta ? respuesta.status() : 'sin respuesta');
    }

    if ((await page.locator('body').innerText()).trim().length < 50) {
      estado = 'vacio';
      detalle = 'body vacío';
    }

    overlay = await cerrarOverlay(page, nombre);

    if (selector && (await page.locator(selector).count()) === 0) {
      estado = 'selector';
      detalle = 'Falta ' + selector;
    }

    if (accion) {
      detalle = (await accion(page)) || detalle;
    }

    await page.screenshot({ path: path.join(OUT, nombre + '.png'), fullPage: true });
  } catch (e) {
    estado = 'error';
    detalle = e.message.split('\n')[0];
    try {
      await page.screenshot({ path: path.join(OUT, nombre + '.png'), fullPage: true });
    } catch {
      // sin captura
    }
  } finally {
    resultados.push({ nombre, ruta, estado, detalle, overlay, errores });
    await context.close();
  }
}

(async () => {
  browser = await chromium.launch({
    executablePath: chrome,
    headless: process.env.SIGRA_HEADLESS !== 'false',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });

  const escritorio = { width: 1440, height: 900 };
  const movil = { width: 390, height: 844 };

  await probar('01-sismos', '/sismos', escritorio, '#mapa', 3500, async (page) => {
    const eventos = await page.locator('.evento-item[data-id]').count();
    const tiles = await page.locator('#mapa .leaflet-tile-loaded').count();
    await page.locator('#theme-toggle').click();
    const tema = await page.locator('html').getAttribute('data-bs-theme');
    await page.locator('#theme-toggle').click();
    await page.locator('#filtro-municipio').fill('Chaparral');
    await page.waitForTimeout(300);
    const filtrados = await page.locator('.evento-item[data-id]').count();
    await page.locator('#filtro-municipio').fill('');
    await page.locator('#orden-eventos').selectOption('magnitud-desc');
    await page.locator('#replay-actividad').click();
    await page.waitForTimeout(900);
    const replay = await page.locator('#replay-estado').innerText();
    const botonReplay = await page.locator('#replay-actividad').innerText();
    if (botonReplay.includes('Detener')) {
      await page.locator('#replay-actividad').click();
    }
    const version = await page.locator('.sigra-version').innerText();
    return `eventos=${eventos} tiles=${tiles} tema=${tema} filtrados=${filtrados} replay=${replay} version=${version}`;
  });

  await probar('02-red', '/red', escritorio, '#mapa-red', 4000, async (page) => {
    await page.waitForFunction(() => {
      const el = document.getElementById('stat-online');
      return el && el.textContent !== '—';
    }, { timeout: 15000 }).catch(() => {});

    const botonAlertas = page.locator('[data-sigra-alerts-toggle]').first();
    if (await botonAlertas.count()) {
      await botonAlertas.click();
      await page.waitForTimeout(500);
    }
    const estadoAlertas = await page.locator('[data-sigra-alert-status]').first().innerText().catch(() => '');
    const bell = await page.locator('#web-alerts-toggle').getAttribute('aria-pressed').catch(() => '');
    const riesgo = await page.locator('#riesgo-nivel').innerText().catch(() => '');
    const riesgoTexto = await page.locator('#riesgo-texto').innerText().catch(() => '');
    const ondas = await page.locator('#mapa-red path').count();
    const sw = await page.evaluate(async () => {
      if (!('serviceWorker' in navigator)) return 0;
      const registros = await navigator.serviceWorker.getRegistrations();
      return registros.length;
    }).catch(() => 0);

    return `online=${await page.locator('#stat-online').innerText()} alertas=${await page.locator('.red-alert-item').count()} estadoAlertas=${estadoAlertas} bell=${bell} riesgo=${riesgo} ondas=${ondas} sw=${sw} ${riesgoTexto.slice(0, 70)}`;
  });

  await probar('03-historico', '/sismos/historico', escritorio, 'table tbody tr', 2500, async (page) => {
    return `filas=${await page.locator('table tbody tr').count()}`;
  });

  await probar('04-globales-compat', '/sismos/globales', escritorio, '.evento-item[data-id]', 2500, async (page) => {
    return `url=${new URL(page.url()).pathname} eventos=${await page.locator('.evento-item[data-id]').count()}`;
  });

  await probar('05-acerca', '/acerca-de', escritorio, '.about-page', 2500, async (page) => {
    await page.waitForFunction(() => {
      const el = document.getElementById('about-status-text');
      return el && !el.textContent.includes('Comprobando');
    }, { timeout: 8000 }).catch(() => {});
    const version = await page.locator('.about-version-pill').first().innerText();
    const estado = await page.locator('#about-status-text').innerText();
    const versiones = await page.locator('.about-release').count();
    return `secciones=${await page.locator('.about-section').count()} version=${version} estados=${estado} releases=${versiones}`;
  });

  await probar('06-detalle', '/sismos', escritorio, '.evento-item[data-id]', 2500, async (page) => {
    const id = await page.locator('.evento-item[data-id]').first().getAttribute('data-id');
    await page.locator('.evento-item[data-id]').first().click();
    await page.waitForURL(/\/sismos\/detalle\//, { timeout: 15000 });
    await page.waitForTimeout(2500);
    return `id=${id} tiles=${await page.locator('#mapa .leaflet-tile-loaded').count()}`;
  });

  await probar('07-movil-sismos', '/sismos', movil, '#mapa', 3500, async (page) => {
    const cookies = page.locator('#accept-cookies');
    if (await cookies.isVisible().catch(() => false)) {
      await cookies.click();
      await page.waitForTimeout(300);
    }
    const boton = page.locator('#mobile-events-toggle');
    const visible = await boton.isVisible();
    if (visible) {
      await boton.click();
      await page.waitForTimeout(400);
    }
    return `boton=${visible} panel=${(await page.locator('.event-sidebar.details-open').count()) > 0} eventos=${await page.locator('.evento-item[data-id]').count()}`;
  });

  await probar('08-movil-red', '/red', movil, '#mapa-red', 4000, async (page) => {
    await page.waitForTimeout(1000);
    return `online=${await page.locator('#stat-online').innerText()} riesgo=${await page.locator('#riesgo-nivel').innerText()}`;
  });

  const fallos = resultados.filter((r) => r.estado !== 'ok');
  const errores = resultados.flatMap((r) => r.errores.map((e) => `${r.nombre}: ${e}`));

  await browser.close();

  console.log(JSON.stringify({
    base: BASE,
    capturas: OUT,
    total: resultados.length,
    fallos: fallos.length,
    errores,
    resultados,
  }, null, 2));

  process.exit(fallos.length === 0 && errores.length === 0 ? 0 : 1);
})();
