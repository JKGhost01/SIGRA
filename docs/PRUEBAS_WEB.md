# Pruebas web de SIGRA

Última ejecución: 2026-11-03, servidor local `http://localhost:8080`.

## Herramienta

Pruebas de reportes ciudadanos de 1.1.1:

```bash
php tests/CitizenReportLogicTest.php
python3 tests/CitizenReportHttpTest.py
php tests/CitizenReportsTest.php
```

La última usa MySQL y la base existente con filas temporales revertidas; requiere
la migración de reportes y acceso efectivo a MySQL. Las otras comprueban lógica
y protección HTTP sin escribir registros. Alcance y resultados en
[REPORTES_CIUDADANOS.md](REPORTES_CIUDADANOS.md).

Pruebas de regresión del filtro de distancia, sin navegador ni MySQL:

```bash
node tools/test-map-filters.js
```

Ejecutan el JavaScript del listado con un DOM simulado: selección inicial del
radio, filtrado por distancia, restauración con permiso concedido, ubicación
denegada, reintento, almacenamiento bloqueado y limpieza durante una solicitud
pendiente. También comprueban que la consulta por IP termina al agotar su tiempo.
No sustituyen las pruebas de Leaflet y geolocalización en un navegador real.

```bash
cd tools
npm install
node test-web.js
```

Usa Chrome o Edge mediante `playwright-core` (no descarga navegadores). Si el navegador no está en
una ruta estándar:

```powershell
$env:CHROME_PATH = 'C:\ruta\a\chrome.exe'
node test-web.js
```

La prueba no crea alertas ni datos en la base de datos: recorre las vistas y comprueba el estado real.
Si hay una alerta activa, también valida el overlay y la cuenta atrás. Capturas en `writable/test-web/`.

## Cobertura

| Prueba | Verifica |
|---|---|
| `/sismos` escritorio | Mapa Leaflet, tarjetas, teselas, tema claro/oscuro, filtro, orden y reproducción de actividad. |
| `/red` escritorio | Mapa, estadísticas, riesgo personal, alertas, ondas P/S y service worker. |
| Overlay de alerta | Si hay una alerta activa, muestra magnitud, distancia y cuenta atrás hasta la onda S. |
| Alertas del navegador | Botón de campana, permiso, estado “Activas” y activación del canal. |
| `/sismos/historico` | Tabla con eventos. |
| `/sismos/globales` | Redirección 301 a `/sismos` (compatibilidad). |
| `/acerca-de` | Secciones informativas, versión v1.0.3, estado del sistema y changelog con 4 releases. |
| Detalle | Navegación desde una tarjeta y mapa del evento. |
| `/sismos` móvil (390×844) | Botón de eventos, panel desplegable y tarjetas. |
| `/red` móvil | Estadísticas, mapa, riesgo y layout responsive. |

La prueba falla si hay errores de consola, `pageerror`, peticiones fallidas al mismo origen o
respuestas HTTP >= 400.

## Resultado

- 8 vistas/procesos: **0 fallos**.
- **0 errores de consola** y **0 errores JavaScript**.
- `/red`: campana activa (`aria-pressed=true`), estado “Activas”, trazados de ondas P/S y
  1 service worker registrado.
- `/acerca-de`: badge `v1.0.3` junto al logo, estado “Sistema operativo · base de datos OK · v1.0.3”,
  4 releases en el changelog y build `20261111.1`.
- `/sismos`: reproducción de actividad y filtros por lugar, fuente, magnitud y radio sin errores.
- Selector ES/EN del navbar: cambia `<html lang>`, los textos y conserva la página actual.
- Móvil: panel de eventos y mapa responsive correctos.

## Correcciones y mejoras derivadas de las pruebas

- Se corrigió `ordenarEventos is not defined` en `/sismos`.
- Se corrigió el pie de página móvil que partía los enlaces letra por letra.
- Se ajustó el panel del mapa de `/red` para que el mapa llene el panel sin dejar un bloque blanco.
- Se movió la leyenda de ondas P/S dentro del mapa.
- Se subió el toast de alertas para que no choque con el aviso de cookies.
- Se añadió la espera del overlay en la prueba para que una alerta recién creada no bloquee los
  clics de la prueba.

## Pruebas de API

```bash
# Salud
curl http://localhost:8080/api/health

# Sin clave: 401
curl -i http://localhost:8080/api/sismos/recent

# Con clave
curl -H "Authorization: Bearer local-test-key" \
     "http://localhost:8080/api/sismos/recent?hours=120&limit=2&min_magnitude=3&lat=4.6&lng=-74.1"

# Red con ubicación
curl -H "X-SIGRA-KEY: local-test-key" \
     "http://localhost:8080/api/red/red?lat=5.10&lng=-75.30"

# Alertas cercanas
curl -H "X-SIGRA-KEY: local-test-key" \
     "http://localhost:8080/api/red/alertas?lat=4.6&lng=-74.1"
```

Comprobado:

- `/api/health`: 200, base de datos `ok`.
- `/api/red/config`: 200.
- `/api/red/red?lat&lng`: incluye `distance_km` y `seconds_to_s_wave`.
- `/api/sismos/recent` sin token: 401.
- `/api/red/registro` sin clave: 401.
- `/api/red/deteccion` sin token: 401.
- `/api/red/alertas` sin coordenadas: 422.
- `/api/red/alertas` con clave incorrecta: 401.
- Detección con 3 teléfonos de prueba en el test de fusión: epicentro `4.5994, -74.1008`,
  error < 1 km, magnitud 3.7 y confianza 0.881.

## Pruebas de fusión

```bash
php tests/PwaveFusionTest.php
php tests/ApiRedContractTest.php
php tests/ApiSismosControllerTest.php
```

Resultado: todas las comprobaciones pasan.
