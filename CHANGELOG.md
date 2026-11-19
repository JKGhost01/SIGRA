# Historial de cambios de SIGRA

La documentación principal se mantiene en español. SIGRA usa versionado semántico
con un cuarto componente opcional para revisiones pequeñas. El historial inglés
se conserva en [CHANGELOG.en.md](CHANGELOG.en.md).

## [1.4.0.1-beta.2] · 2026-11-19 · Catálogo unificado y paginación

- El catálogo cruza cada actualización con los eventos guardados, incluidas fuentes tardías y fallos parciales.
- Un sismo reúne las referencias de sus agencias en listado, mapa, detalle y API; los filtros aceptan cualquiera de sus fuentes.
- Los duplicados existentes se conservan como alias fuera del catálogo público; los enlaces anteriores y los reportes ciudadanos apuntan al evento unificado.
- Listado del mapa e histórico con páginas de 20, 50 o 100 sismos, conservando filtros y orden. El mapa mantiene todos los resultados filtrados y la actualización automática conserva la página abierta.
- Histórico paginado desde la base de datos, sin el tope de 500 eventos; conteo completo y orden estable entre páginas.
- Build `20261119.beta2`; canal `beta`. [Despliegue y reversión](docs/BETA_1.4.0.md).
- Nueva migración `20261119000001_AddCanonicalEvent`: ejecutar `php spark migrate --all` antes de servir esta versión. La primera actualización consolida los duplicados de su ventana temporal.

## [1.4.0-beta.1] · 2026-11-19 · Globo 3D experimental

- Globo CesiumJS en el mapa principal, con controles compartidos de vistas y capas a la derecha, modo oscuro y diseño móvil.
- Respaldo Leaflet ante CDN bloqueado o pérdida de WebGL, conservando filtros, ubicación y radio.
- SGC usa el feed completo de cinco días, incluidos eventos menores de magnitud 2.
- Las actualizaciones parciales conservan las referencias históricas de otras agencias sin bloquear revisiones científicas.
- Auditoría de solo lectura con `php tools/audit-source-sync.php`; [diagnóstico](docs/SINCRONIZACION_FUENTES_2026-11-19.md).

- Build `20261119.beta1`; canal `beta`. API v1, sin migraciones nuevas ni cambios de dependencias Composer.
- [Guía de la beta, despliegue y reversión](docs/BETA_1.4.0.md).

## Documentación incorporada con 1.3.1.3

- README principal, guía de contribución, código de conducta y plantillas de GitHub en español.
- Traducciones del README en inglés y siete idiomas adicionales, enlazadas desde la portada.
- La versión 1.3.1.3 sigue identificando la recuperación SGC; esta entrega documental no cambia el contrato ni el comportamiento de la interfaz.

### v1.3.1.3 · Conexión SGC recuperada · 2026-11-19

- User-Agent compatible con SGC conservando identidad, versión y repositorio de SIGRA.
- TLS, caché compartida, intervalo de quince minutos y pausas existentes conservados.
- Prueba local real: HTTP 200, 100 eventos normalizados y reutilización de caché sin escritura en base.
- Funcionamiento en producción confirmado por el usuario tras desplegar el hotfix; no se inspeccionó el servidor directamente.
- Build `20261119.1`; sin migraciones nuevas. API v1 y versión Android independientes.
- [Diagnóstico y despliegue](docs/SGC_CONEXION_2026-11-19.md).

### v1.3.1.2 · Estado de fuentes y pausas progresivas · 2026-11-13

- Panel en Acerca de con estado de las seis fuentes, código HTTP y fechas del último intento, resultado y respuesta válida.
- Próxima consulta permitida visible; actualizar el panel no consulta proveedores ni modifica sus pausas.
- HTTP 401/403: dos horas tras el primer fallo consecutivo y 24 horas desde el segundo, para todas las fuentes.
- Una respuesta válida reinicia el contador; Retry-After y las pausas existentes se respetan.
- Panel en español e inglés, adaptable a móvil, con estados antiguos sin fechas inventadas.
- Pruebas locales de arquitectura, transporte, diagnóstico y contratos HTTP; revisión visual en escritorio y móvil.
- Build `20261113.4`; sin nuevas migraciones. Conservar `writable/cache/providers`.


### v1.3.1.1 · Intervalo de consultas SGC · 2026-11-13

- El intervalo mínimo entre consultas al SGC aumenta de cinco a quince minutos.
- Intervalo de quince minutos por host SGC, compartido por mapa, CLI y procesos PHP.
- Las demás fuentes conservan su intervalo mínimo de cinco minutos; el timeout SGC sigue en diez segundos.
- Pruebas sin red verifican el intervalo SGC y la protección compartida entre URLs.
- Build `20261113.3`; sin nuevas migraciones. Conservar las pausas persistentes existentes.


### v1.3.1 · Cabeceras HTTP y recuperación de fuentes · 2026-11-13

- Lectura de cabeceras Cache-Control repetidas sin rechazar respuestas válidas de los proveedores.
- Respuesta HTTP entrante sin las cabeceras predeterminadas de páginas HTML de CodeIgniter.
- Retry-After múltiple respeta la espera más larga; validadores ETag y Last-Modified contradictorios no se reutilizan.
- Rutas con métodos GET/POST en mayúsculas para eliminar avisos deprecados repetidos.
- Pruebas con respuestas reales de CodeIgniter para cabeceras múltiples, 304 y no-store; pausas y límites conservados.
- Build `20261113.2`; sin nuevas migraciones. Las pausas existentes se conservan hasta su vencimiento.


### v1.3.0 · Fuentes globales, consultas responsables y mapa corregido · 2026-11-13

- EMSC, GeoNet, GEOFON/GFZ e INGV integrados con SGC y USGS en catálogo, API y filtros; referencias conservadas al correlacionar.
- Consultas compartidas entre mapa y cron, mínimo de cinco minutos por proveedor, caché condicional y pausas persistentes ante 403/429.
- Respeto de Retry-After y Cache-Control, espera progresiva ante fallos e identificación pública de SIGRA; USGS usa su feed estático cuando es compatible.
- Latitud y longitud USGS corregidas; migración idempotente y SQL alternativo para reparar datos antiguos conservando auditoría.
- Validación de coordenadas al dibujar y límites verticales del mapa; estado degradado por fuente y traducciones ES/EN actualizados.
- Regresiones de normalización, seis fuentes, pausas HTTP, reparación MySQL y proyección Leaflet en navegador.
- Build `20261113.1`; contrato API `v1`, versión Android `1.0.0`. La cobertura local detallada sigue dependiendo de SGC, cuyo 403 no se ha resuelto.
- Tras subir el código: `php spark migrate --all` o importar `deploy/corregir-coordenadas-usgs.sql`. Conservar `writable/cache/providers`.


### v1.2.1 · Instalación reproducible y robustez del backend · 2026-11-12

- Identidad y autoload Composer corregidos, lock compatible con PHP 8.2 e instalación documentada desde cero.
- Generación local de secretos sin exponer valores ni reemplazar claves existentes; reinicio remoto por HTTP retirado.
- Registro de UUID existente protegido con el token vigente; rotación autenticada que invalida el anterior.
- Validación de campos y enlaces externos, verificación TLS, reintentos acotados y umbral de magnitud en la correlación.
- Estado de actualización del catálogo visible, assets compatibles con ambas raíces web y APIs fuera de la caché PWA.
- Documentación ES/EN coherente, Android separado y experimentalidad explícita; PHPStan y pruebas MySQL aisladas en CI.
- Build `20261112.5`; contrato API `v1`. Re-registrar un UUID existente requiere su token vigente.

### v1.2.0 · Arquitectura modular y sincronización automática · 2026-11-12

- Módulos por capacidad: catálogo, ingesta, reportes ciudadanos, sensores, alertas y sistema, con contratos y dependencias explícitos.
- Controladores ligeros y vistas de presentación; consulta y procesamiento fuera de Views, con JavaScript del catálogo en archivos públicos.
- Actualización automática del mapa con caché compartida de 60 segundos, bloqueo de concurrencia y conservación de datos ante fallos; cron opcional.
- Rutas antiguas conservadas, endpoints /api/v1, errores versionados, IDs de correlación y contrato OpenAPI.
- Auditoría de lotes y decisiones de alerta en dos tablas nuevas, con alerta y decisión en la misma transacción antes de la entrega.
- Simulaciones aisladas, interruptor de publicación y cancelación de alertas desde la consola del servidor.
- Correlación SGC/USGS con referencias de ambas fuentes y conservación de candidatos ambiguos.
- Pruebas de arquitectura, contratos HTTP, actualización automática y persistencia en MySQL aislado, además de documentación de despliegue.
- Build `20261112.4`; contrato API `v1` y versión móvil independientes.
- Despliegue y migración en [architecture/migration.md](docs/architecture/migration.md).

### v1.1.1 · Reportes contrastados con SGC · 2026-11-12

- Reportes privados en una sola tabla nueva de la misma base MySQL.
- Confirmación del conteo con al menos tres participantes distintos y un evento SGC compatible único.
- Conteos agregados en mapa, listado, histórico, detalle y API; ningún reporte crea un epicentro.
- CSRF, tokens de un solo uso, campo trampa y límites de frecuencia por sesión/red.
- Identificadores protegidos con HMAC, deduplicación IPv6 /64 y retención de 30 días.
- Reportes antiguos ocultos y edición/eliminación públicas bloqueadas.
- Migración aditiva: `php spark migrate --all`. No se requiere otra base ni un reset.
- Flujo, configuración, despliegue y pruebas en [REPORTES_CIUDADANOS.md](docs/REPORTES_CIUDADANOS.md).


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
