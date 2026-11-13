# SIGRA · Monitor sísmico global y red ciudadana

> **v1.3.1.2 · Estado de fuentes y pausas progresivas** — eventos de seis fuentes oficiales en un mapa, con monitoreo ciudadano experimental.

[![Versión](https://img.shields.io/badge/versi%C3%B3n-1.3.1.2-087f78.svg)](#versionado)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://www.php.net/)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.7-ee4323.svg)](https://codeigniter.com/)
[![Licencia](https://img.shields.io/badge/licencia-MIT-blue.svg)](LICENSE)

*Read this in [English](README.md).*

SIGRA es una aplicación PHP de código abierto que unifica los sismos de
**SGC, USGS, EMSC, GeoNet, GEOFON/GFZ e INGV** en un solo mapa consultable, con filtro de cercanía, archivo
histórico, API JSON y un backend experimental para señales de dispositivos.
El cliente Android está en construcción en un repositorio independiente.

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
- **Backend ciudadano experimental**: recibe señales, calcula candidatos TDOA y
  tiempos estimados. No se presenta como alerta validada; Android sigue en desarrollo.
- **Abierto y autoalojable**: sin servicios propietarios. Los mapas usan
  OpenStreetMap/Esri y los datos vienen de agencias públicas.

## Estado y alcance

- **Disponible:** catálogo publicado de varias fuentes, mapa/listado/histórico, filtros, API y reportes ciudadanos privados.
- **Experimental:** fusión de sensores, estimación de magnitud/ubicación, tiempos de llegada y entrega por navegador/FCM. No se afirma validación de campo ni anticipación garantizada.
- **En desarrollo:** Android en un repositorio separado; este backend no incluye una app móvil.
- **Futuro:** evaluación de campo, carga y nuevas fuentes; consulta el [estado de recomendaciones](docs/RECOMMENDATIONS_STATUS.md).

SIGRA sirve para consulta, educación y experimentación técnica. No predice terremotos ni sustituye sistemas oficiales de emergencia.

## Funciones

| Área | Qué hace |
|---|---|
| Mapa global | Leaflet, eventos mundiales, estilo por magnitud/profundidad y cuatro capas base. |
| Ingesta unificada | Seis proveedores oficiales normalizados a un solo esquema, con caché en disco y tolerancia a fallos por fuente. |
| Filtro por radio | Geolocalización opcional; círculo, distancias y orden “más cercanos” calculados en el navegador. |
| Filtros | Búsqueda por lugar, fuente (todas / SGC / USGS / EMSC / GEONET / GEOFON / INGV), magnitud e intensidad. |
| Reproducción | Animación de la actividad de los últimos cinco días. |
| Ficha | Detalle técnico según la fuente, enlace al reporte oficial y distancia al usuario. |
| API | Endpoints JSON para web y móvil con autenticación Bearer. |
| Red ciudadana | Registro de dispositivos, detecciones de onda P, fusión TDOA, alertas y panel público. |
| PWA | App instalable, página offline, notificaciones y overlay de emergencia. |

## Fuentes de datos

| Fuente | Cobertura | Notas |
|---|---|---|
| **SGC** — Servicio Geológico Colombiano | Colombia | Registro local más rico: CDI, MMI, PGA, reportes sentidos, municipios y estaciones. |
| **USGS** — U.S. Geological Survey | Mundo | Feed GeoJSON semanal estático, filtrado localmente a cinco días y magnitud 2.5+ por defecto; FDSN para consultas especiales. |
| **EMSC** | Mundo | FDSN JSON con referencias de procedencia. |
| **GeoNet** | Nueva Zelanda | Hasta 100 observaciones recientes del proveedor, filtradas localmente. |
| **GEOFON / GFZ** | Mundo | Feed limitado de eventos recientes, complementado por otros catálogos. |
| **INGV** | Italia y entorno | Catálogo regional FDSN GeoJSON. |

Los feeds son públicos. SIGRA guarda una copia normalizada para el histórico
y no reclama propiedad sobre los datos originales. En una emergencia consulta
siempre la fuente oficial:

- SGC: <https://www.sgc.gov.co/>
- USGS: <https://earthquake.usgs.gov/>

Los parámetros se cambian en `app/Config/Quakes.php` o con variables de entorno
(`quakes.*`).

## Arquitectura

```
app/
  Modules/SeismicCatalog/        Domain, Application, Infrastructure, Http, Presentation
  Modules/SeismicIngestion/      Sources, deduplicator, sync handler and audit
  Modules/CitizenReports/        Report validation, submission and reconciliation
  Modules/SensorNetwork/         Devices, heartbeats and detection commands
  Modules/Alerting/              Candidate decisions, audit and delivery
  Modules/System/                Health and public information
  Shared/                       Clock, contracts and HTTP boundaries
  Config/Services.php           Dependency composition
  Commands/SigraSync.php         Scheduled ingestion with a process lock
  Views/layouts/                Shared rendering layout
public/assets/js/modules/       Browser behavior
```

- **PHP 8.2+ / CodeIgniter 4.7**
- **MySQL o MariaDB** (utf8mb4)
- **Sin build** para la web: PHP, CSS y JavaScript planos.

## Inicio rápido

### Requisitos

PHP 8.2+ con `curl`, `intl`, `mbstring` y `mysqli`, Composer 2 y una base MySQL/MariaDB propia. CI4 4.7.3 está incluido en `system/`; `vendor/` **no está versionado** y se instala desde `composer.lock`. Node y Python solo se necesitan para las pruebas.

### Instalación

El procedimiento soportado está en [INSTALLATION.md](docs/INSTALLATION.md), con claves, creación de base y resolución de errores:

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
# Crea una base vacía y configura database.default.* en .env.
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

Abre <http://127.0.0.1:8080/index.php/sismos>. En local configura `app.baseURL = 'http://127.0.0.1:8080/'`. Las instalaciones nuevas deben publicar únicamente `public/`; el hosting existente desde la raíz sigue siendo compatible con sus reglas de protección.

El mapa solicita actualizaciones automáticamente, con caché compartida de al menos 300 segundos y bloqueo de ingesta compartido con cron. Los presupuestos por host, caché condicional, Retry-After y pausas 403/429 limitan las consultas externas. Si una fuente falla, conserva los eventos guardados y muestra el estado degradado. Cron es opcional.

### Mantener el archivo actualizado (recomendado)

El mapa actualiza automáticamente las fuentes al vencer `quakes.cacheSeconds`.
Cron es opcional para actualizar también cuando no hay visitantes:

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

## Pruebas automatizadas

Después de `composer install`, instala Node 22+, Python 3 y PHP-CGI de tu versión de PHP; ejecuta `composer test`. Las pruebas no necesitan credenciales de producción. `composer analyse` ejecuta PHPStan de forma progresiva. La integración MySQL exige una base de pruebas explícita e independiente; consulta [las instrucciones](docs/PRUEBAS_WEB.md).

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
| `quakes.cacheSeconds` | Intervalo mínimo entre descargas automáticas compartido por todos los visitantes (60 s). |
| `quakes.dedupeCrossSource` | Correlaciona observaciones de todas las fuentes. |
| `red.*` | Umbrales de la red ciudadana. |
| `translator.provider` | `google`, `mymemory`, `libretranslate` o `deepl` para `sigra:translate`. |
| `translator.apiKey` | Clave de API para DeepL/LibreTranslate (opcional para Google/MyMemory). |

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
| `GET` | `/api/health` | Versión pública y estado de la base de datos. |
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

El cliente Android está en desarrollo en un repositorio separado. Este backend no
incluye fuentes móviles ni compilación de APK. El contrato de integración está
documentado en [OpenAPI](docs/api/openapi.yaml).

## Documentación

- [Guía funcional](README_GUIA.md)
- [Versionado e historial](docs/VERSIONES.md)
- [Avisos de terceros](THIRD_PARTY_NOTICES.md)
- [Red de teléfonos y TDOA](docs/RED_TELEFONOS.md)
- [API de la red](docs/API_RED.md)
- [Base de datos y mantenimiento](docs/BASE_DATOS.md)
- [Pruebas web y API](docs/PRUEBAS_WEB.md)
- [Checklist de producción](docs/PRODUCCION.md)
- [Arquitectura sísmica](docs/ARQUITECTURA_SISMOS.md)

## Contribuir

Las contribuciones son bienvenidas. Lee [CONTRIBUTING.md](CONTRIBUTING.md) y
[CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). No publiques credenciales ni datos privados de usuarios en Issues públicos.

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

## Actualizar a 1.3.0

Después de subir el código, ejecuta `php spark migrate --all` o importa
[deploy/corregir-coordenadas-usgs.sql](deploy/corregir-coordenadas-usgs.sql) para reparar
las coordenadas USGS antiguas. Conserva `writable/cache/providers` entre despliegues.
El detalle de sismos pequeños depende de las redes locales; esta versión no restablece
la disponibilidad del SGC. Ver [fuentes y límites](docs/FUENTES_SISMICAS.md).

## Versionado

SIGRA usa versionado semántico con un cuarto componente opcional para revisiones pequeñas de un parche. La versión actual es **1.3.1.2 · Estado de fuentes y pausas progresivas**
(2026-11-13, build `20261113.4`). Ver [docs/VERSIONES.md](docs/VERSIONES.md).

Contratos API: [Sismos](docs/API_SISMOS.md) · [Red ciudadana](docs/API_RED.md).

## Advertencia

SIGRA es un sistema **experimental**. No predice sismos y no sustituye al SGC,
al USGS, a los organismos de gestión del riesgo ni a los sistemas oficiales de
alerta temprana. En una emergencia sigue las instrucciones de las autoridades.

## Licencia

MIT. Ver [LICENSE](LICENSE) y [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) para las atribuciones de OpenStreetMap, SGC, USGS, Esri, Leaflet, Bootstrap, Google Fonts y Android. CodeIgniter 4 se distribuye bajo su propia licencia MIT y sus avisos de copyright se conservan en `LICENSE`.

Validación de reportes ciudadanos: [flujo y configuración](docs/REPORTES_CIUDADANOS.md).

Consulta la [arquitectura modular](docs/architecture/README.md), los [pasos de migración](docs/architecture/migration.md) y el [contrato OpenAPI](docs/api/openapi.yaml).
