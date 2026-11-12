# SIGRA · Monitor sísmico global y red ciudadana

> **v1.1.1 · Reportes contrastados con SGC** — todos los sismos del mundo en un solo mapa, más una red de teléfonos que funciona como sensor de alerta temprana.

[![Versión](https://img.shields.io/badge/versi%C3%B3n-1.1.1-087f78.svg)](#versionado)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://www.php.net/)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.7-ee4323.svg)](https://codeigniter.com/)
[![Licencia](https://img.shields.io/badge/licencia-MIT-blue.svg)](LICENSE)

*Read this in [English](README.md).*

SIGRA es una aplicación PHP de código abierto que unifica los sismos del
**Servicio Geológico Colombiano (SGC)** y del **Servicio Geológico de Estados
Unidos (USGS)** en un solo mapa consultable, con filtro de cercanía, archivo
histórico, API JSON y una red ciudadana de detección de ondas P con teléfonos
Android.

No está atada a un solo país: el mapa, el histórico, la API y el filtro por
radio funcionan en cualquier parte del mundo.

---

## Qué aporta

- **Un mapa global**: eventos del SGC para Colombia y del USGS para el mundo en
  la misma tabla, el mismo listado y la misma API.
- **“Cerca de mí” estilo detector sísmico**: comparte tu ubicación en el
  navegador y filtra eventos en 50, 100, 250, 500, 1.000, 2.000 o 5.000 km.
  Ordena por distancia y consulta qué tan lejos está cada epicentro.
- **Deduplicación entre fuentes**: si el mismo sismo aparece en SGC y USGS, se
  conserva el registro local, que trae más detalle.
- **Archivo histórico**: cada evento queda en MySQL y se puede consultar por
  fecha, magnitud, profundidad, lugar y fuente.
- **Red ciudadana**: los teléfonos Android detectan ondas P con el
  acelerómetro; el servidor triangula el origen (TDOA), estima la magnitud y
  alerta a teléfonos cercanos con los segundos hasta la onda S.
- **Abierto y autoalojable**: sin servicios propietarios. Los mapas usan
  OpenStreetMap/Esri y los datos vienen de agencias públicas.

## Funciones

| Área | Qué hace |
|---|---|
| Mapa global | Leaflet, eventos mundiales, estilo por magnitud/profundidad y cuatro capas base. |
| Ingesta unificada | SGC + USGS normalizados a un solo esquema, con caché en disco y tolerancia a fallos por fuente. |
| Filtro por radio | Geolocalización opcional; círculo, distancias y orden “más cercanos” calculados en el navegador. |
| Filtros | Búsqueda por lugar, fuente (todas / SGC / USGS), magnitud e intensidad. |
| Reproducción | Animación de la actividad de los últimos cinco días. |
| Ficha | Detalle técnico para SGC y USGS, enlace al reporte oficial y distancia al usuario. |
| API | Endpoints JSON para web y móvil con autenticación Bearer. |
| Red ciudadana | Registro de dispositivos, detecciones de onda P, fusión TDOA, alertas y panel público. |
| PWA | App instalable, página offline, notificaciones y overlay de emergencia. |

## Fuentes de datos

| Fuente | Cobertura | Notas |
|---|---|---|
| **SGC** — Servicio Geológico Colombiano | Colombia | Registro local más rico: CDI, MMI, PGA, reportes sentidos, municipios y estaciones. |
| **USGS** — U.S. Geological Survey | Mundo | Feed FDSN GeoJSON, magnitud 2.5+ para los últimos cinco días por defecto. |

Ambos feeds son públicos. SIGRA guarda una copia normalizada para el histórico
y no reclama propiedad sobre los datos originales. En una emergencia consulta
siempre la fuente oficial:

- SGC: <https://www.sgc.gov.co/>
- USGS: <https://earthquake.usgs.gov/>

Los parámetros se cambian en `app/Config/Quakes.php` o con variables de entorno
(`quakes.*`).

## Arquitectura

```
app/
  Commands/SigraSync.php          Sincronización CLI para cron
  Config/Quakes.php               Configuración de fuentes globales
  Controllers/Sismos.php          Router HTTP + API JSON
  Libraries/SgcClient.php         Normalizador del feed SGC
  Libraries/UsgsClient.php        Normalizador del feed FDSN de USGS
  Libraries/QuakeSyncService.php  Descarga + deduplicación + guardado
  Models/SismoModel.php           Solo acceso a datos
  Views/sismos/list/              Mapa, listado y filtro por radio
  Views/sismos/sync/              Endpoint JSON cacheado
public/assets/js/mapa-sismos.js   Render Leaflet y geolocalización
mobile/                           App Android (Kotlin + Jetpack Compose)
```

- **PHP 8.2+ / CodeIgniter 4.7**
- **MySQL o MariaDB** (utf8mb4)
- **Sin build** para la web: PHP, CSS y JavaScript planos.

## Inicio rápido

### Requisitos

- PHP 8.2 o superior con `intl`, `mbstring`, `mysqli` y `curl`.
- MySQL 5.7+ / MariaDB 10.3+.

### Instalación

```bash
# 1. Clonar
git clone https://github.com/TU-USUARIO/sigra.git
cd sigra

# 2. Crear la base de datos
mysql -u root -e "CREATE DATABASE sigra CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

# 3. Configurar
cp .env.example .env
# Edita .env: baseURL, base de datos, APP_API_TOKEN y encryption.key

# 4. Esquema
php spark migrate --all

# 5. Primera sincronización (SGC + USGS)
php spark sigra:sync

# 6. Servidor local
php spark serve
```

Abre <http://localhost:8080/sismos>.

> La primera sincronización descarga los últimos cinco días de ambas fuentes
> (~350 eventos en una ejecución típica). El mapa también sincroniza cada 15
> segundos mientras está abierto, pero el servidor cachea las respuestas
> `quakes.cacheSeconds` para proteger las APIs públicas.

### Mantener el archivo actualizado (recomendado)

La página solo sincroniza mientras alguien la tiene abierta. En producción,
ejecuta el comando desde cron cada 2–5 minutos:

```cron
*/5 * * * * cd /var/www/sigra && php spark sigra:sync >> writable/logs/sync.log 2>&1
```

## Idiomas

La interfaz está en **español (idioma fuente)** e **inglés**. Las vistas se
escriben una sola vez con claves (`lang('Sigra.*')`); el archivo inglés se genera
y actualiza automáticamente:

```bash
php spark sigra:translate                 # completa las claves que falten
php spark sigra:translate --dry-run       # previsualiza
php spark sigra:translate --force         # retraduce todo
php spark sigra:translate --key navMap    # retraduce una clave
```

El proveedor se configura en `.env` (`translator.provider` = `google`,
`mymemory`, `libretranslate` o `deepl`). Las traducciones se cachean en
`writable/cache/translations/`. Para añadir otro idioma, crea
`app/Language/<locale>/Sigra.php` y ejecuta el comando con `--locale <locale>`.

## Configuración

Copia `.env.example` y ajusta:

| Variable | Para qué sirve |
|---|---|
| `CI_ENVIRONMENT` | `development` en local, `production` en el servidor. |
| `app.baseURL` | URL pública con barra final. Usa `http://` solo en local. |
| `APP_API_TOKEN` | Token Bearer que exigen la app y la API. |
| `database.default.*` | Conexión MySQL. |
| `encryption.key` | 32 bytes aleatorios en formato `hex2bin:`. **Obligatorio en producción.** |
| `quakes.sgcEnabled` | Activa la fuente SGC. |
| `quakes.globalEnabled` | Activa la fuente global USGS. |
| `quakes.usgsMinMagnitude` | Umbral global de magnitud (por defecto `2.5`). |
| `quakes.windowDays` | Ventana temporal compartida (por defecto `5`). |
| `quakes.cacheSeconds` | TTL de la caché del sync (por defecto `60`). |
| `quakes.dedupeCrossSource` | Elimina duplicados SGC/USGS. |
| `red.*` | Umbrales de la red ciudadana. |`n| `translator.provider` | `google`, `mymemory`, `libretranslate` o `deepl` para `sigra:translate`. |`n| `translator.apiKey` | Clave de API para DeepL/LibreTranslate (opcional para Google/MyMemory). |

Genera una clave de cifrado:

```bash
php -r "echo 'hex2bin:' . bin2hex(random_bytes(32)) . PHP_EOL;"
```

## API

Los endpoints `/api/sismos/*` aceptan `Authorization: Bearer <APP_API_TOKEN>`.

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/api/sismos/recent` | Eventos recientes. Filtros: `hours`, `limit`, `min_magnitude`, `fuente`, `order`. |
| `GET` | `/api/sismos/recent?lat=&lng=&radius_km=500&order=distancia` | Eventos dentro de un radio, con `distancia_km`. |
| `GET` | `/api/sismos/detalle/{id}` | Un evento. |
| `GET` | `/api/sismos/ultimo` | Último evento. |
| `GET` | `/api/sismos/alertas` | Nivel de alerta del último evento. |
| `GET` | `/api/health` | Versión, entorno y estado de la base de datos. |
| `GET` | `/api/red/config` | Configuración pública de la red. |
| `POST` | `/api/red/registro` | Registro de un Android. |
| `POST` | `/api/red/deteccion` | Reporte de onda P. |
| `GET` | `/api/red/alertas` | Alertas cercanas a un dispositivo. |

Ejemplo:

```bash
curl -H "Authorization: Bearer $APP_API_TOKEN" \
  "https://tu-dominio/api/sismos/recent?lat=4.6097&lng=-74.0817&radius_km=500&limit=10&order=distancia"
```

## Privacidad y filtro por radio

La geolocalización es **opcional** y se queda en el navegador:

- El listado nunca pide la ubicación automáticamente.
- Al pulsar **Mi ubicación** se llama a `navigator.geolocation` y se dibuja un
  círculo en el mapa. Nada se envía al servidor ni se guarda en la base de datos.
- Si el navegador niega la ubicación precisa, SIGRA puede usar una aproximación
  por IP (`ipapi.co`) para que la distancia siga funcionando.
- El radio elegido se guarda en `localStorage`; las coordenadas no.
- El panel público de la red redondea las coordenadas de los teléfonos a ~1 km y
  nunca expone tokens de dispositivo.

## App Android

```bash
cd mobile
cp local.properties.example local.properties
# edita sdk.dir, sigra.baseUrl y sigra.apiKey
./gradlew :app:assembleDebug
```

APK: `mobile/app/build/outputs/apk/debug/app-debug.apk`.

## Documentación

- [Guía funcional](README_GUIA.md)
- [Versionado e historial](docs/VERSIONES.md)`n- [Avisos de terceros](THIRD_PARTY_NOTICES.md)
- [Red de teléfonos y TDOA](docs/RED_TELEFONOS.md)
- [API de la red](docs/API_RED.md)
- [Base de datos y mantenimiento](docs/BASE_DATOS.md)
- [Pruebas web y API](docs/PRUEBAS_WEB.md)
- [Checklist de producción](docs/PRODUCCION.md)
- [Arquitectura sísmica](docs/ARQUITECTURA_SISMOS.md)

## Contribuir

Las contribuciones son bienvenidas. Lee [CONTRIBUTING.md](CONTRIBUTING.md) y
[CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Para reportes de seguridad sigue
[SECURITY.md](SECURITY.md).

Buenos primeros aportes:

- Añadir otra fuente pública (EMSC, GEOFON) usando el normalizador existente.
- Mejorar la heurística de deduplicación global.
- Añadir pruebas de integración para la API de radio.
- Traducir la interfaz a inglés y portugués.
- Mejorar la accesibilidad del mapa y del overlay de emergencia.

## Donaciones

Si SIGRA te resulta útil, puedes apoyar su desarrollo y mantenimiento con una
donación voluntaria. ¡Gracias por aportar al proyecto!

| Medio | Número para donar |
|---|---|
| **Nequi** | `315 9287929` |
| **Bancolombia** | Cuenta `912-982627-76` |

## Versionado

SIGRA usa versionado semántico. La versión actual es **1.1.1 · Reportes contrastados con SGC**
(2026-11-12, build `20261112.3`). Ver [docs/VERSIONES.md](docs/VERSIONES.md).

Contratos API: [Sismos](docs/API_SISMOS.md) · [Red ciudadana](docs/API_RED.md).

## Advertencia

SIGRA es un sistema **experimental**. No predice sismos y no sustituye al SGC,
al USGS, a los organismos de gestión del riesgo ni a los sistemas oficiales de
alerta temprana. En una emergencia sigue las instrucciones de las autoridades.

## Licencia

MIT. Ver [LICENSE](LICENSE) y [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) para las atribuciones de OpenStreetMap, SGC, USGS, Esri, Leaflet, Bootstrap, Google Fonts y Android. CodeIgniter 4 se distribuye bajo su propia licencia MIT y sus avisos de copyright se conservan en `LICENSE`.

Validación de reportes ciudadanos: [flujo y configuración](docs/REPORTES_CIUDADANOS.md).
