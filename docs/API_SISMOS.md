# API de sismos · SIGRA 1.3.1.2 · contrato v1

Las rutas y los campos usados por Android se conservan. `1.3.1.2` identifica la
aplicación; `v1` identifica el contrato. Todas las respuestas de los endpoints
listados son JSON y llevan:

```http
Cache-Control: no-store, max-age=0
X-SIGRA-Version: 1.3.1.2
X-API-Version: v1
```

## Autenticación

Las rutas `/api/sismos/*` requieren `Authorization: Bearer <APP_API_TOKEN>`.
El esquema Bearer admite mayúsculas/minúsculas. Se usa comparación de tiempo
constante. Se conservan los alias de configuración `app.apiToken` y `app_apiToken`.
Sin token configurado, producción devuelve 401; desarrollo permite el acceso.
`/api/health` es público.

## Rutas

| Método | Ruta | Respuesta válida |
|---|---|---|
| GET | `/api/sismos/recent` | `status`, `count`, `hours`, `origin`, `generated_at`, `events` |
| GET | `/api/sismos/ultimo` | `status`, `event` (o `null`), `generated_at` |
| GET | `/api/sismos/alertas` | `status`, `alerta`, `ultimo` (o `null`), `generated_at` |
| GET | `/api/sismos/detalle/{id}` | `status`, `event`, `generated_at`; 404 si no existe |
| GET | `/api/health` | Estado de la aplicación y de MySQL; 200 o 503 |

Los eventos públicos incluyen únicamente fuentes oficiales SGC, USGS, EMSC, GEONET, GEOFON e INGV.
Los reportes ciudadanos permanecen privados; nunca se devuelven como eventos.
`fuente` permite seleccionar la fuente en `/recent`.

## Parámetros de `/recent`

| Parámetro | Predeterminado | Validación |
|---|---|---|
| `hours` | 120 | Entero entre 1 y 336 |
| `limit` | 300 | Entero entre 1 y 1000 |
| `min_magnitude` | Sin mínimo | Número finito entre -2 y 10 |
| `fuente` | Todas | Texto, `SGC`, `USGS`, `EMSC`, `GEONET`, `GEOFON` o `INGV` |
| `lat`, `lng` | Sin origen | Se envían juntos; latitud -90…90 y longitud -180…180 |
| `radius_km` | Sin filtro | Número finito entre 0 y 20000; requiere origen. 0 conserva todos |
| `order` | `fecha` | `fecha`, `distancia` o `magnitud`; distancia requiere origen |

`lat` y `lng` también se admiten en detalle, último y alertas para calcular
`distancia_km`. Una pareja incompleta o fuera de rango devuelve 422 en todas
estas rutas. No se guardan las coordenadas de consulta.

Ejemplo:

```bash
curl -H 'Authorization: Bearer <token>' \
  'http://localhost:8080/api/sismos/recent?hours=120&limit=20&lat=4.6&lng=-74.1&radius_km=500&order=distancia'
```

Las respuestas válidas mantienen los campos del evento: `id`, `source_id`,
`ubicacion`, `latitud`, `longitud`, `magnitud`, `profundidad`, `fecha`, `fecha_iso`,
`distancia_km`, `alerta`, `nivel`, `estado`, `fuente`, `cdi`, `mmi`, `pga`,
`mag_type`, `agency`, `felt`, `tsunami`, `significance`, `closer_towns`,
`official_url`, `detalle_url`, `mapa_sgc_url` y `citizen_reports_count`.
El último es un entero agregado de participantes contrastados con SGC, 0 cuando
no se ha alcanzado el umbral; no expone ubicaciones ni identificadores privados.
Es independiente de `felt`, que procede de la fuente oficial. Los metadatos no disponibles
son `null`; `mapa_sgc_url` solo se publica para SGC.

Una lista sin eventos devuelve 200 con `count: 0` y `events: []`.
El último evento ausente devuelve 200 con `event: null`.

`/api/sismos/alertas` resume la magnitud del último evento almacenado; no es
el endpoint de alertas activas de la red. Para esas alertas usa
[`/api/red/alertas`](API_RED.md).

## Errores y salud

```json
{"status":"error","message":"El radio y el orden por distancia requieren lat y lng."}
```

| Código | Significado |
|---|---|
| 401 | Token inválido/faltante o token no configurado en producción |
| 404 | Evento solicitado inexistente |
| 422 | Tipo, rango o combinación de parámetros inválidos |
| 500 | Fallo interno, incluidos fallos al consultar datos; mensaje público genérico |
| 503 | `/api/health` no puede consultar MySQL; `status: degraded`, `database: error` |

Los detalles de errores internos quedan en los logs. Las entradas inválidas
dejan de convertirse silenciosamente en consultas sin filtro. Los clientes
deben manejar 422 y corregir sus parámetros.

## Verificación

```bash
php tests/ApiResponsesTest.php
python3 tests/ApiHttpTest.py
```

La primera prueba usa respuestas de persistencia aisladas y comprueba el contrato
de los controladores. La segunda ejecuta rutas, filtros y controladores mediante
PHP-CGI (`PHP_CGI=/ruta/php-cgi`), sin abrir puertos ni escribir registros de
negocio; fuerza una conexión de prueba inaccesible para comprobar errores.
No verifican escrituras reales en MySQL, FCM ni un dispositivo Android.

## Arquitectura y rutas versionadas

La API se compone desde los módulos y comparte autenticación y manejo de errores.
Las rutas equivalentes `/api/v1/sismos/*`, `/api/v1/red/*` y `/api/v1/health`
están descritas en [OpenAPI](api/openapi.yaml). Los errores legacy se conservan;
los versionados contienen `error.code`, `error.message` y `request_id`.
`X-Request-ID` se genera en el servidor para correlación. Health ya no expone `environment`.
Las lecturas API no descargan fuentes ni escriben entregas. La ruta de compatibilidad
`GET /sismos/sync` sí solicita la ingesta al vencer la caché compartida; no necesita cron.
