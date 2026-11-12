# Versionado de SIGRA

## Política

SIGRA usa **versionado semántico** (`MAYOR.MENOR.PARCHE`):

| Parte | Cuándo sube |
|---|---|
| `MAYOR` | Cambios incompatibles en API, base de datos o contratos móviles. |
| `MENOR` | Funciones nuevas compatibles hacia atrás. |
| `PARCHE` | Correcciones, mejoras visuales y cambios internos. |

La versión se define en `app/Config/Sigra.php` y se muestra en:

- el badge junto al logo del navbar,
- el footer,
- la página `/acerca-de`,
- los endpoints `/api/health` y `/api/red/health`,
- la configuración pública `/api/red/config`.

Se puede sobrescribir sin tocar código con:

```ini
sigra.version = '1.1.1'
sigra.build = '20261112.3'
sigra.channel = 'stable'
```

## Versión actual

**v1.1.1 · Reportes contrastados con SGC**

- Fecha: 2026-11-12
- Build: `20261112.3`
- Canal: `stable`
- API: `v1`
- App Android: `v1.0.0`
- Licencia: MIT

La versión de la aplicación (`1.1.1`) es independiente del contrato de la API
(`v1`). Se conservan las rutas y los campos de las respuestas válidas para Android.
Las entradas inválidas ahora reciben errores explícitos en vez de ignorarse.

## Historial

### v1.1.1 · Reportes contrastados con SGC · 2026-11-12

- Reportes privados en una sola tabla nueva de la misma base MySQL.
- Confirmación del conteo con al menos tres participantes distintos y un evento SGC compatible único.
- Conteos agregados en mapa, listado, histórico, detalle y API; ningún reporte crea un epicentro.
- CSRF, tokens de un solo uso, campo trampa y límites de frecuencia por sesión/red.
- Identificadores protegidos con HMAC, deduplicación IPv6 /64 y retención de 30 días.
- Reportes antiguos ocultos y edición/eliminación públicas bloqueadas.
- Migración aditiva: `php spark migrate --all`. No se requiere otra base ni un reset.
- Flujo, configuración, despliegue y pruebas en [REPORTES_CIUDADANOS.md](REPORTES_CIUDADANOS.md).


### v1.1.0 · API y contratos · 2026-11-12

- Validación de coordenadas, radio, orden y parámetros numéricos.
- Validación JSON y de tipos/rangos en el cuerpo de solicitudes de dispositivos.
- Booleanos normalizados; JSON malformado devuelve 400, datos inválidos 422 y tipos de contenido no admitidos 415.
- JSON y `no-store` también en errores de autenticación y de base de datos.
- Cabeceras `X-SIGRA-Version` y `X-API-Version`.
- Claves comparadas con `hash_equals`; sin clave en producción las rutas protegidas rechazan el acceso.
- Tokens opcionales inválidos devuelven 401.
- Documentación en `docs/API_SISMOS.md` y `docs/API_RED.md`.
- Pruebas: `python3 tests/ApiHttpTest.py` y `php tests/ApiResponsesTest.php`.
- Contrato `v1` y rutas/campos de Android conservados.


### v1.0.4 · Filtros y presentación · 2026-11-12

- Radio seleccionable desde el inicio; ubicación solicitada al elegir distancia.
- Consulta por IP con tiempo límite, reintentos y soporte sin almacenamiento local.
- Historial y canal de versiones traducidos a ES/EN.
- Iconos SVG locales en lugar de emojis.
- Caché renovada y actualización por red de scripts y estilos.
- Pruebas deterministas del filtro de distancia.


### v1.0.3 · Alcance global · 2026-11-11

- Ingesta unificada de SGC (Colombia) y USGS (mundo) en una sola tabla, mapa, histórico y API.
- Deduplicación automática entre fuentes para no repetir el mismo sismo.
- Filtro “Cerca de mí” con radio de 50 km a 5.000 km y orden por distancia.
- Filtro por fuente y búsqueda por lugar en todo el mundo.
- Filtro por rango de magnitud y panel de filtros compacto y plegable.
- Interfaz bilingüe español/inglés con selector en el navbar y traducción automática de claves.
- Nuevos iconos y colores para “Reportar sismo” y las alertas del navegador.
- Avisos legales de terceros (OpenStreetMap, SGC, USGS, Esri, Leaflet, etc.).
- Geolocalización opcional con activación automática si el permiso ya estaba concedido.
- Caché del sync y comando `php spark sigra:sync` para cron.
- API `/api/sismos/recent` con `lat`, `lng`, `radius_km` y `order=distancia`.
- Ficha técnica adaptada a USGS (alerta, tsunami, significancia y reporte oficial).
- Geolocalización opcional: el mapa ya no la solicita automáticamente.
- Preparación open source: README bilingüe, licencia, contribución, seguridad y `.env.example`.

### v1.0.2 · Red en vivo · 2026-11-03

- “Red en vivo” como entrada principal del navbar, con contador de teléfonos y estado de alerta.
- Alertas del navegador con Web Notifications, sonido y overlay de emergencia con cuenta atrás.
- PWA instalable: manifest, service worker, iconos y página offline.
- Propagación animada de ondas P/S en `/red`.
- Panel “Tu riesgo ahora” con distancia, ETA y nivel de riesgo.
- Reproducción animada de la actividad sísmica en `/sismos`.
- Alertas compartibles y API con distancia/ETA.
- Eliminación completa de SMS, OTP, Twilio y la tabla `sms_subscriptions`.
- Endurecimiento de `.htaccess`, health check con versión y pruebas web.

### v1.0.1 · Red de teléfonos · 2026-11-03

- Backend de dispositivos, detecciones, alertas y entregas.
- Fusión TDOA, magnitud, confianza y ETA de onda S.
- API `/api/red/*`.
- Panel `/red`.
- App Android con servicio en primer plano y notificaciones.
- FCM opcional y polling de respaldo.

### v1.0.0 · Mapa sísmico · 2026-09-21

- Mapa Leaflet/OpenStreetMap.
- Sincronización con el SGC.
- Histórico, filtros y ficha técnica.
- Reporte ciudadano.
- Temas claro/oscuro y alertas visuales/sonoras.

## Checklist para publicar una versión

1. Actualizar `version`, `releaseName`, `releaseDate`, `build` y `changelog` en `app/Config/Sigra.php`.
2. Actualizar `docs/VERSIONES.md` y el encabezado de `README.md`.
3. Ejecutar `php tests/PwaveFusionTest.php`, `php tests/ApiRedContractTest.php` y `php tests/ApiSismosControllerTest.php`.
4. Ejecutar `node tools/test-map-filters.js`, `php tests/ApiResponsesTest.php` y `python3 tests/ApiHttpTest.py` (requiere PHP-CGI).
5. Ejecutar `node tools/test-web.js` con el servidor local.
6. Probar `/api/health` y confirmar que devuelve la versión nueva.
7. Etiquetar el despliegue con el mismo número de versión.
