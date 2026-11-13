# Fuentes sísmicas · 13 de noviembre de 2026

SGC consulta únicamente el feed configurado, una vez por turno. Un rechazo no
activa otro endpoint ni un reintento inmediato. La última comprobación real
recibió HTTP 403; ese código no acredita por sí solo un bloqueo por tráfico.
No se hicieron nuevas consultas reales durante las pruebas de protección.

## Frecuencia y pausas persistentes

- Mapa y CLI usan el mismo coordinador, con un mínimo de 300 segundos incluso
  si un `.env` anterior mantiene `quakes.cacheSeconds = 60`.
- El transporte añade un bloqueo y presupuesto por hostname, compartidos por
  procesos PHP y por las distintas URLs del proveedor. También cubre llamadas
  directas del adaptador heredado y ejecuciones forzadas del coordinador.
- Cada turno hace como máximo una petición HTTP por host, sin seguir redirecciones
  ni reintentar dentro de la misma llamada. TLS permanece verificado.
- HTTP 401/403: pausa de al menos 24 horas, con una pequeña variación aleatoria.
- HTTP 429: pausa inicial de al menos 15 minutos. Otros fallos: al menos 10 minutos.
  Los fallos repetidos duplican la espera hasta seis horas, más variación aleatoria.
- `Retry-After`, en segundos o fecha HTTP, siempre puede ampliar esas pausas.
  `Cache-Control: max-age` también puede ampliar la vigencia tras un éxito.
- Se guarda el JSON para compartirlo entre procesos y se envían `If-None-Match`
  y `If-Modified-Since` cuando hay validadores y se repite la misma URL. Una
  respuesta 304 reutiliza el JSON local. `no-store` no persiste el cuerpo y
  `no-cache` impide reutilizarlo sin validación.
- SIGRA se identifica con su nombre, versión y repositorio público. No modifica
  su identidad para superar una denegación del proveedor.

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
