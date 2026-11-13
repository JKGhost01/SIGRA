/*
 * Service worker de SIGRA.
 * Cachea solo estáticos y sirve una pantalla offline. Las APIs y páginas
 * dinámicas siempre van a la red para no mostrar datos sísmicos obsoletos.
 */
const CACHE = 'sigra-static-1.3.0';
const CORE = [
  '/public/assets/images/sigra-logo.png',
  '/public/assets/images/icon-192.png',
  '/public/assets/images/icon-512.png',
  '/public/assets/js/mapa-sismos.js',
  '/public/assets/js/modules/seismic-map/catalog.js',
  '/public/assets/js/sigra-red.js',
  '/offline.html'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(CORE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key.startsWith('sigra-static-') && key !== CACHE).map((key) => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (/^\/(?:index\.php\/)?api(?:\/|$)/.test(url.pathname)) return;
  if (/^\/(?:index\.php\/)?sismos\/sync(?:\/|$)/.test(url.pathname)) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  if (url.pathname.startsWith('/public/assets/')) {
    // Scripts y estilos deben recibir las correcciones sin conservar versiones antiguas.
    const refresh = () => fetch(request).then((response) => {
      if (response.ok) {
        const copy = response.clone();
        event.waitUntil(caches.open(CACHE).then((cache) => cache.put(request, copy)));
      }
      return response;
    });
    if (/\.(js|css)$/.test(url.pathname)) {
      event.respondWith(refresh().catch(() => caches.match(request)));
    } else {
      event.respondWith(caches.match(request).then((cached) => cached || refresh()));
    }
  }
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const destino = (event.notification.data && event.notification.data.url) || '/red';
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientes) => {
      for (const cliente of clientes) {
        if ('focus' in cliente) {
          cliente.navigate(destino);
          return cliente.focus();
        }
      }
      if (self.clients.openWindow) return self.clients.openWindow(destino);
    })
  );
});

