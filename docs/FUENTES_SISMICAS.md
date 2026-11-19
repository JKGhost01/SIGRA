# Fuentes sísmicas · 19 de noviembre de 2026

En la pre-release local se usa el [resumen completo SGC de cinco días](https://archive.sgc.gov.co/feed/v1.0.1/summary/five_days_all.json),
incluidos sismos menores de magnitud 2. El resumen anterior `five_days_2.json`
dejaba esos eventos fuera. Consulta la [revisión de sincronización](SINCRONIZACION_FUENTES_2026-11-19.md)
para comparar feed, catálogo y referencias de las seis fuentes sin nuevas consultas externas.

SGC consulta únicamente el feed configurado, una vez por turno. Un rechazo no
activa otro endpoint ni un reintento inmediato. La comparación con `sigra_old`
aisló una incompatibilidad del `User-Agent`: el mismo feed devolvió HTTP 403
con la identidad SIGRA sola y HTTP 200 con las cabeceras de compatibilidad de
navegador de la versión antigua. No fue necesario cambiar la URL, añadir
`Referer`, evitar la caché ni desactivar la verificación TLS.

El cliente SGC restaura esos tokens de compatibilidad y conserva `SIGRA/versión`
y el repositorio en la identidad enviada. La comprobación real con el cliente
PHP corregido obtuvo HTTP 200 y 100 eventos normalizados el 19 de noviembre;
una segunda llamada reutilizó la caché. Se utilizó un directorio temporal,
sin escribir en la base ni alterar las pausas de una instalación existente.
Esto acredita la conexión desde el entorno local de prueba, no desde el hosting.
El diagnóstico comparativo, los límites de la evidencia y los pasos de despliegue
están en [Recuperación de SGC](SGC_CONEXION_2026-11-19.md).

## Un sismo con varias fuentes

Cada sismo tiene una fuente preferida para sus parámetros científicos y una lista
de referencias de otras agencias. Se prioriza SGC, seguido de USGS, EMSC, GeoNet,
GEOFON e INGV. El catálogo compara las observaciones con las ya guardadas, también
cuando una agencia llega más tarde o falta durante una actualización. Una revisión
de la fuente preferida actualiza sus datos sin borrar las otras referencias.

La correlación exige una coincidencia única en ambos sentidos, hasta 120 segundos,
75 km y 1 unidad de magnitud según la configuración. Dos IDs distintos de una
misma agencia y las coincidencias ambiguas se mantienen separados. Una referencia
ya conocida identifica el mismo evento aunque el proveedor revise sus parámetros.
Las referencias son observaciones históricas; no certifican una confirmación
independiente ni que todas las agencias sigan publicando el evento ahora.

Listado, mapa e histórico muestran las agencias juntas. El detalle incluye sus
identificadores y los enlaces oficiales disponibles. La API conserva `fuente`
como fuente preferida y añade `fuentes` y `source_references`. El filtro `fuente`
encuentra el sismo si cualquiera de sus agencias coincide.

Antes de desplegar este cambio se necesita `php spark migrate --all`. La migración
añade `sismos.canonical_event_id`; la siguiente sincronización consolida los
duplicados dentro de la ventana procesada. Las filas originales se conservan
como alias para auditoría, se excluyen de las consultas públicas y sus URLs
resuelven al sismo unificado. Los reportes ciudadanos se trasladan en la misma
transacción. La escritura comparte el bloqueo de conciliación de reportes para
evitar que dos actualizaciones creen eventos separados o pierdan evidencia.

## Estado de las conexiones

En `/acerca-de#sources-health` se muestra una tarjeta por fuente con el estado
local, último intento, último resultado, última respuesta válida, código HTTP y
próxima consulta permitida. Las fechas se muestran en UTC. El botón «Actualizar
estado» recarga la información guardada; no contacta a los proveedores ni levanta
sus pausas. Una comprobación correcta de transporte no certifica la cobertura
ni la actualidad del catálogo sísmico.

Los registros anteriores a este panel pueden carecer de fechas o código HTTP:
se muestran como «Sin registro», sin inferir fechas a partir de la pausa.
Las siguientes consultas registran esos datos automáticamente. Un éxito cuyo
intervalo ya venció se presenta como pendiente de actualización. La próxima
consulta requiere actividad del mapa o cron; no es una ejecución programada.
Los fallos de conexión y las respuestas inválidas se distinguen de los errores
HTTP sin publicar mensajes de excepción ni credenciales de configuración.

## Frecuencia y pausas persistentes

- Mapa y CLI usan el mismo coordinador, con un mínimo de 300 segundos incluso
  si un `.env` anterior mantiene `quakes.cacheSeconds = 60`.
- El transporte añade un bloqueo y presupuesto por hostname, compartidos por
  procesos PHP y por las distintas URLs del proveedor. También cubre llamadas
  directas del adaptador heredado y ejecuciones forzadas del coordinador.
- Para SGC (`archive.sgc.gov.co`), el intervalo mínimo entre consultas es de
  15 minutos; las demás fuentes conservan el mínimo de cinco minutos.
- Cada turno hace como máximo una petición HTTP por host, sin seguir redirecciones
  ni reintentar dentro de la misma llamada. TLS permanece verificado.
- HTTP 401/403, en todas las fuentes: pausa de al menos dos horas en el primer
  fallo consecutivo y de al menos 24 horas desde el segundo. Una respuesta válida
  reinicia el contador de fallos. Se añade una pequeña variación aleatoria.
  Las pausas ya guardadas conservan su vencimiento.
- HTTP 429: pausa inicial de al menos 15 minutos. Otros fallos: al menos 10 minutos.
  Los fallos repetidos duplican la espera hasta seis horas, más variación aleatoria.
- `Retry-After`, en segundos o fecha HTTP, siempre puede ampliar esas pausas.
  `Cache-Control: max-age` también puede ampliar la vigencia tras un éxito.
- Se guarda el JSON para compartirlo entre procesos y se envían `If-None-Match`
  y `If-Modified-Since` cuando hay validadores y se repite la misma URL. Una
  respuesta 304 reutiliza el JSON local. `no-store` no persiste el cuerpo y
  `no-cache` impide reutilizarlo sin validación.
- SIGRA se identifica con su nombre, versión y repositorio público. SGC recibe
  además los tokens de compatibilidad de navegador heredados; las demás fuentes
  conservan su `User-Agent` habitual.

El estado vive en `writable/cache/providers`. Debe persistir entre despliegues:
**no borrarlo para intentar reconectar**. Un estado corrupto suspende la consulta
hasta su revisión. Si hay varias réplicas/servidores, necesitan compartir ese
volumen y bloqueo; los archivos locales no coordinan instalaciones distintas.
El intervalo mayor puede retrasar la incorporación de eventos al catálogo.

El catálogo incorpora EMSC, GeoNet, GEOFON e INGV, activados por defecto. Se pueden desactivar
independientemente con `quakes.emscEnabled`, `quakes.geonetEnabled`, `quakes.geofonEnabled` y
`quakes.ingvEnabled`. La reparación de datos USGS sí requiere una migración o su SQL
alternativo. La propiedad heredada `quakes.globalEnabled` controla solo USGS.

| Fuente | Consulta | Alcance y límites |
|---|---|---|
| SGC | Un feed oficial de cinco días | Colombia; conserva datos almacenados ante fallos |
| USGS | Feed estático `2.5_week.geojson` para consultas compatibles; FDSN para otras | SIGRA aplica ventana, magnitud y límite de `Config\Quakes` localmente al feed semanal |
| EMSC | FDSN JSON, `seismicportal.eu` | Misma ventana, magnitud y límite que USGS |
| GEOFON | GeoJSON estático GFZ/GEOFON | Cobertura mundial; el feed de la prueba contiene 40 eventos recientes y puede abarcar menos de cinco días |
| INGV | FDSN GeoJSON | Italia y su entorno; ventana, magnitud y límite compartidos |
| GEONET | API v2, `/quake?MMI=-1` | Hasta 100 eventos recientes del proveedor; SIGRA filtra por ventana y magnitud. No representa un catálogo completo de cinco días |

Se descartan eventos eliminados de GeoNet, eventos no sísmicos de EMSC, fechas
imposibles o futuras, coordenadas y mediciones inválidas. EMSC usa profundidad en
km desde `properties.depth`, no la elevación negativa de su geometría. Los IDs
nuevos llevan prefijo por proveedor; el ID original se conserva en metadata.

La correlación compara tiempo, distancia y magnitud, con prioridad SGC, USGS,
EMSC, GeoNet, GEOFON e INGV. Preserva referencias de todas las fuentes que coinciden sin
ambigüedad. Las cantidades `sources`/`fuentes` son observaciones normalizadas
recibidas antes de correlacionar; `eventos` contiene los eventos resultantes.
La correlación heurística no demuestra identidad científica. Tampoco retira
filas históricas de otra fuente que se guardaron durante una caída anterior.

Las nuevas fuentes aparecen en lista, histórico, detalle, API y filtro del mapa.
Las alertas informativas y la confirmación de reportes ciudadanos conservan su
criterio SGC. Un evento externo no se presenta como confirmación del SGC.

Prueba real anterior a la protección, mediante los clientes PHP, sin escribir en la base: SGC HTTP 403;
USGS 210, EMSC 1090 y GeoNet 20 observaciones normalizadas. Los conteos cambian
con el tiempo. Esta prueba no acredita conectividad desde el hosting.

Documentación primaria:
- [USGS: recomendación de feeds para aplicaciones automáticas](https://earthquake.usgs.gov/fdsnws/event/1/wsdl).
- [EMSC FDSN y licencia](https://www.seismicportal.eu/fdsn-wsevent.html).
- [GeoNet API v2 y límite de 100 eventos](https://api.geonet.org.nz/).
- [GeoNet: atribución y política](https://www.geonet.org.nz/policy).

Validación determinista: `bash tools/test-architecture.sh` incluye una sola consulta SGC,
protección compartida, 304, 403, 429, Retry-After, caché y fallos de red,
fallo total, campos científicos, fechas, cuatro fuentes, procedencia, caída SGC,
avisos del mapa y contratos HTTP. El paquete para hosting se genera con
`python3 tools/build-upload.py`; no incluye `.env` ni datos de servidor.

## Reparación del mapa y alcance de Colombia

El adaptador USGS anterior validaba correctamente GeoJSON `[longitud, latitud]`,
pero escribía ambas columnas invertidas. Leaflet proyectaba las latitudes fuera
rango en los extremos del mapa. El normalizador ahora asigna explícitamente
`latitud = coordinates[1]` y `longitud = coordinates[0]` y marca sus datos con
`coordinate_order: lat-lon-v2`.

La migración `20261113000001_RepairUsgsCoordinates` repara las filas USGS heredadas
sin esa marca, incluso si ambos números caben entre -90 y 90 (caso Colombia).
Conserva las coordenadas y metadata anteriores como auditoría, protege actualizaciones
concurrentes y no vuelve a invertir filas ya corregidas. No modifica SGC ni otras
fuentes. La alternativa para phpMyAdmin es `deploy/corregir-coordenadas-usgs.sql`;
usa una tabla temporal para conservar los valores originales durante el UPDATE.

El mapa descarta coordenadas inválidas y limita el desplazamiento vertical al
mundo representable por Web Mercator. Las pruebas de navegador comprueban Colombia,
el Pacífico, datos inválidos, desplazamiento hacia ambos polos y tamaño móvil.
Los puntos de Colombia en esa prueba son sintéticos, no observaciones oficiales.

Colombia está dentro del alcance geográfico de los catálogos mundiales, pero eso
no garantiza todos los sismos locales. En las muestras de cinco/siete días consultadas
el 13 de noviembre no hubo eventos etiquetados como Colombia; la consulta regional
EMSC devolvió sobre todo Panamá. No se atribuyen esos eventos al SGC ni se simula
cobertura local completa mientras el SGC no esté disponible. GEOFON aporta cobertura
mundial adicional e INGV complementa la regional europea.

Fuentes primarias adicionales:
- [GEOFON: cobertura, atribución y frecuencia recomendada](https://geofon.gfz.de/eqinfo/faq/).
- [INGV FDSN](https://webservices.ingv.it/fdsnws/event/1/).
- [INGV Data Policy](https://data.ingv.it/docs/implementation/).
