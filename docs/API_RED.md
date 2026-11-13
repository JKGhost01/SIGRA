# API de la Red SIGRA de teléfonos · SIGRA 1.3.1.2 · contrato v1

Base: `https://tu-dominio/` (o tu dominio).
Todas las respuestas son JSON y usan `Cache-Control: no-store`.
También incluyen `X-SIGRA-Version: 1.3.1.2` y `X-API-Version: v1`.
La versión de aplicación y el contrato son independientes; se conservan rutas
y campos de respuestas válidas usados por Android.

## Autenticación

| Cabecera | Uso |
|---|---|
| `X-SIGRA-KEY` | Clave de la aplicación Android. Se compara con `red.appKey` o `APP_API_TOKEN`. |
| `Authorization: Bearer <clave>` | Alternativa a `X-SIGRA-KEY`; esta última tiene prioridad. |
| `X-Device-Token` | Token entregado por `/api/red/registro`. Identifica al teléfono. |

La clave de app no es un secreto criptográfico: va dentro del APK y puede extraerse. La defensa real es la
correlación de varios teléfonos, la tasa máxima de detecciones y la validación temporal/espacial.

Si `red.appKey` y `APP_API_TOKEN` están vacíos, las rutas protegidas rechazan
el acceso en producción con 401. En desarrollo se permite el acceso sin clave.
Las claves se comparan con `hash_equals`.

## Resumen de rutas

| Método | Ruta | Autenticación | Función |
|---|---|---|---|
| `GET` | `/api/red/config` | Pública | Umbrales y configuración de la red. |
| `GET` | `/api/red/red` | Pública | Estadísticas y mapa público (coordenadas redondeadas). |
| `GET` | `/api/red/health` | Pública | Proceso activo y versión; no comprueba MySQL. |
| `POST` | `/api/red/registro` | App key | Registra el teléfono y entrega token. |
| `POST` | `/api/red/estado` | App key + device | Latido, ubicación, batería y preferencias. |
| `POST` | `/api/red/deteccion` | App key + device | Reporta una onda P detectada. |
| `GET` | `/api/red/alertas?lat=&lng=` | App key | Alertas activas que cubren esa posición. |
| `POST` | `/api/red/desactivar` | App key + device | Baja y borrado del token. |
| `GET` | `/api/sismos/recent` | `Authorization: Bearer` | Eventos recientes, SGC + USGS, con filtros de distancia. |
| `GET` | `/api/sismos/ultimo` | `Authorization: Bearer` | Último evento almacenado. |
| `GET` | `/api/sismos/alertas` | `Authorization: Bearer` | Resumen por magnitud del último evento. |
| `GET` | `/api/sismos/detalle/{id}` | `Authorization: Bearer` | Ficha de un evento. |
| `GET` | `/api/health` | Pública | Salud de la app principal y MySQL. |

Los parámetros y las respuestas de sismos se describen en [API_SISMOS.md](API_SISMOS.md).
`/api/health` devuelve 503 si no puede consultar MySQL. `/api/red/health` es una
comprobación de proceso activo y puede devolver 200 con MySQL inaccesible.

## Validación de solicitudes

- Los cuerpos JSON deben ser objetos; JSON malformado devuelve 400 y listas o
  valores escalares en la raíz devuelven 422.
- Se admiten `application/json`, tipos con sufijo `+json` y formularios. Otros
  tipos de contenido devuelven 415 al leer un cuerpo de registro, estado o detección.
- Los campos deben ser escalares; listas/objetos anidados devuelven 422.
- Coordenadas finitas y en rango: latitud -90…90, longitud -180…180; se envían juntas.
- Radio del dispositivo: 25…1000 km; magnitud mínima: 1…9; batería: 0…100;
  confianza: 0…1; precisión, duración y medidas de sensores: valores no negativos.
- Booleanos: `true`/`false`, `1`/`0` y sus representaciones textuales. La cadena
  `"false"` se interpreta como falso.
- `/api/red/red` admite `lat` y `lng` opcionales juntos. `/api/red/alertas` los
  exige. Coordenadas inválidas devuelven 422 antes de consultar datos.
- En alertas, omitir `X-Device-Token` conserva la consulta con clave de app;
  enviar un token inválido devuelve 401 y no se trata como una consulta anónima.

## Registro

```http
POST /api/red/registro
X-SIGRA-KEY: <clave>
Content-Type: application/json

{
  "device_uuid": "8f2c0d7e-...",
  "platform": "android",
  "manufacturer": "Samsung",
  "model": "SM-A546E",
  "os_version": "14",
  "app_version": "1.0.0",
  "fcm_token": null,
  "latitude": 4.6097,
  "longitude": -74.0817,
  "accuracy_m": 12,
  "monitoring_enabled": true,
  "notifications_enabled": true,
  "alert_radius_km": 250,
  "min_magnitude": 3.0
}
```

Respuesta:

```json
{
  "status": "ok",
  "device_token": "64 caracteres hexadecimales",
  "device": {
    "id": 12,
    "device_uuid": "8f2c0d7e-...",
    "platform": "android",
    "model": "SM-A546E",
    "monitoring_enabled": true,
    "notifications_enabled": true,
    "alert_radius_km": 250,
    "min_magnitude": 3.0,
    "last_seen_at": "2026-11-03 14:00:00"
  },
  "config": { "min_devices": 3, "...": "..." }
}
```

El token se guarda en el servidor como hash SHA-256. El teléfono debe guardarlo y enviarlo en
`X-Device-Token`. Si se pierde, se vuelve a llamar a `/registro` (rota el token).

## Latido

Cada 30 segundos mientras el monitoreo está activo:

```http
POST /api/red/estado
X-SIGRA-KEY: <clave>
X-Device-Token: <token>

{
  "latitude": 4.6097,
  "longitude": -74.0817,
  "accuracy_m": 12,
  "monitoring_enabled": true,
  "notifications_enabled": true,
  "battery_level": 78,
  "alert_radius_km": 250,
  "min_magnitude": 3.0
}
```

La respuesta incluye `server_time_ms` (para comparar relojes), la configuración vigente y el número de
alertas activas.

## Detección de onda P

La app detecta la llegada de la onda P con su acelerómetro:

```http
POST /api/red/deteccion
X-SIGRA-KEY: <clave>
X-Device-Token: <token>

{
  "detected_at_ms": 1793731094722,
  "latitude": 4.6500,
  "longitude": -74.1000,
  "accuracy_m": 8,
  "sta_lta_ratio": 5.2,
  "peak_accel_mps2": 0.085,
  "dominant_freq_hz": 2.4,
  "duration_ms": 420,
  "confidence": 0.82
}
```

Respuesta cuando la red aún no tiene suficientes teléfonos:

```json
{
  "status": "ok",
  "accepted": true,
  "detection_id": 31,
  "event": { "devices": 1, "needed": 3 },
  "alert": null,
  "message": "Detección registrada. La red necesita más teléfonos para confirmar el evento."
}
```

Respuesta cuando se confirma el evento:

```json
{
  "status": "ok",
  "accepted": true,
  "detection_id": 33,
  "event": {
    "latitude": 4.5994,
    "longitude": -74.1008,
    "origin_time_ms": 1793731093785,
    "magnitude": 3.7,
    "confidence": 0.881,
    "devices": 3,
    "detections": 3,
    "residual_s": 0.005,
    "uncertainty_km": 0.0
  },
  "alert": {
    "uuid": "93121f22-8001-403a-b951-8e25521c4209",
    "source": "crowd",
    "magnitude": 3.7,
    "latitude": 4.599402,
    "longitude": -74.10081,
    "radius_km": 283.5,
    "confidence": 0.881,
    "detection_count": 3,
    "device_count": 3,
    "seconds_to_s_wave": null,
    "severity": "amarilla",
    "estimated": true
  },
  "message": "Evento confirmado por la red de teléfonos."
}
```

### Reglas de aceptación

- `confidence >= red.minDetectionConfidence` (0.35 por defecto).
- Edad máxima 120 s; reloj adelantado máximo 30 s.
- Una detección cada 2 s y máximo 30 por minuto por teléfono.
- Al menos 3 teléfonos distintos dentro de 80 km y 25 s.
- Magnitud estimada >= 3.0 y confianza fusionada >= 0.45.

Todos estos valores se pueden ajustar en `.env`:

```ini
red.minDevices = 3
red.minAlertMagnitude = 3.0
red.minAlertConfidence = 0.45
red.clusterWindowSeconds = 25
red.clusterRadiusKm = 80
red.alertTtlMinutes = 10
```

## Alertas cercanas

```http
GET /api/red/alertas?lat=4.6097&lng=-74.0817
X-SIGRA-KEY: <clave>
X-Device-Token: <token opcional>
```

```json
{
  "status": "ok",
  "server_time_ms": 1793731100000,
  "alerts": [
    {
      "uuid": "93121f22-8001-403a-b951-8e25521c4209",
      "magnitude": 3.7,
      "latitude": 4.599402,
      "longitude": -74.10081,
      "radius_km": 283.5,
      "confidence": 0.881,
      "device_count": 3,
      "distance_km": 2.4,
      "seconds_to_s_wave": -10.4,
      "severity": "amarilla",
      "estimated": true,
      "disclaimer": "Estimación experimental de SIGRA. No sustituye a las autoridades."
    }
  ]
}
```

`seconds_to_s_wave` se calcula con `V_s = 3.5 km/s` y el instante de origen estimado. Si es negativo,
la onda S ya está llegando.

## Errores

```json
{ "status": "error", "message": "Token de dispositivo inválido. Vuelve a registrar la app." }
```

| Código | Significado |
|---|---|
| 400 | JSON malformado. |
| 401 | Clave de app o token de dispositivo inválido. |
| 415 | Tipo de contenido no admitido. |
| 422 | Datos inválidos, detección antigua o reloj desfasado. |
| 429 | Demasiadas detecciones. |
| 500 | Error interno (se registra en `writable/logs`). |

Los errores de construcción del servicio y autenticación de dispositivos también
devuelven JSON con un mensaje genérico si falla la base de datos, sin HTML de depuración.

Pruebas: `php tests/ApiResponsesTest.php` y `python3 tests/ApiHttpTest.py`.
La cobertura y sus límites se describen en [API_SISMOS.md](API_SISMOS.md#verificación).

## Push FCM (opcional)

La app funciona con polling cada 5 s. Para push en segundo plano:

1. Crea un proyecto Firebase y agrega una app Android con paquete `com.sigra.app`.
2. Configura el cliente Firebase en el futuro repositorio Android independiente; este backend no contiene ese archivo móvil.
3. Descarga la cuenta de servicio y configura en `.env`:

```ini
red.fcmEnabled = true
red.fcmProjectId = 'tu-proyecto'
red.fcmCredentials = '/ruta/segura/service-account.json'
```

4. Recompila la app. Sin `google-services.json`, el servicio de Firebase no recibe mensajes, pero el
   polling sigue funcionando.

## Privacidad

- El teléfono se identifica con un UUID y un token; no se pide nombre, correo ni cuenta.
- El panel público redondea las coordenadas de teléfonos a 2 decimales (~1 km) y no publica tokens.
- Las coordenadas exactas solo se usan para calcular distancias y entregar alertas.
- La baja en `/api/red/desactivar` borra el token y desactiva el dispositivo.

## Arquitectura y rutas versionadas

La API se compone desde los módulos y comparte autenticación y manejo de errores.
Las rutas equivalentes `/api/v1/sismos/*`, `/api/v1/red/*` y `/api/v1/health`
están descritas en [OpenAPI](api/openapi.yaml). Los errores legacy se conservan;
los versionados contienen `error.code`, `error.message` y `request_id`.
`X-Request-ID` se genera en el servidor para correlación. Health ya no expone `environment`.
Las lecturas API no descargan fuentes ni escriben entregas. La ruta de compatibilidad
`GET /sismos/sync` sí solicita la ingesta al vencer la caché compartida; no necesita cron.

## Nuevo registro y rotación de identidad

El primer registro de un UUID nuevo usa la clave de aplicación. Volver a registrar un
UUID existente exige además su token actual en `X-Device-Token`; sin él devuelve 401.
La clave de aplicación por sí sola no puede reemplazar las credenciales de otro
dispositivo. Una rotación aceptada devuelve un token nuevo y deja inválido el anterior.
No existe un endpoint público de recuperación de tokens perdidos.
