# SIGRA · Global Seismic Monitor & Citizen Network

> **v1.2.1 · Reproducible installation and backend robustness** — Published SGC/USGS earthquakes in one map, with experimental citizen monitoring.

[![Version](https://img.shields.io/badge/version-1.2.1-087f78.svg)](#versioning)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://www.php.net/)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.7-ee4323.svg)](https://codeigniter.com/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

*Read this in [Spanish](README.es.md).*

SIGRA is an open-source PHP application that unifies earthquake feeds from the
**Colombian Geological Survey (SGC)** and the **United States Geological Survey
(USGS)** into a single searchable map, with a “near me” radius filter, a
historical archive, a JSON API and an experimental server-side sensor network.
The Android client is still under development in a separate repository.

It is not tied to a single country: the map, the history, the API and the radius
filter work anywhere on Earth.

---

## Why SIGRA

Most public seismic viewers are either global and impersonal, or local and
limited. SIGRA combines both:

- **One global map** — SGC events for Colombia and USGS events worldwide in the
  same table, the same list and the same API.
- **“Near me” like a seismic detector** — share your location in the browser and
  filter events within 50 km, 100 km, 250 km, 500 km, 1,000 km, 2,000 km or
  5,000 km. Sort by distance and see how far each epicenter is from you.
- **Cross-source deduplication** — if the same earthquake appears in SGC and
  USGS, only the richer local record is kept.
- **Historical archive** — every event is stored in MySQL and can be queried by
  date, magnitude, depth, place and source.
- **Experimental citizen backend** — accepts device signals; the Android client is in development;
  the backend evaluates experimental TDOA candidates and estimated arrival times.
  These estimates are not validated early warnings and do not replace authorities.
- **Open and self-hostable** — no proprietary services are required. Map tiles
  use OpenStreetMap/Esri and the data comes from public agencies.

## Status and scope

- **Available:** published SGC/USGS catalog, map/list/history, filters, API and private citizen reports.
- **Experimental:** sensor fusion, inferred magnitude/location, estimated arrival times and browser/FCM delivery. No field validation or warning-time guarantee is claimed.
- **In development:** Android in a separate repository; this backend does not ship a mobile app.
- **Future work:** field evaluation, load benchmarks and further sources; see the [tracked recommendations](docs/RECOMMENDATIONS_STATUS.md).

SIGRA is for exploration, education and technical experimentation. It does not predict earthquakes or replace official emergency systems.

## Features

| Area | What it does |
|---|---|
| Global map | Leaflet map with worldwide events, magnitude/depth styling and four base layers. |
| Unified ingestion | SGC + USGS normalized to one schema, with disk cache and per-source fault tolerance. |
| Radius filter | Geolocation is opt-in; the radius circle, distances and “nearest first” order are computed in the browser. |
| Filters | Free-text place search, source filter (all / SGC / USGS), magnitude range (M2.5+ ? M6+) and intensity ordering. |
| Replay | Animated playback of the last five days of activity. |
| Detail page | Technical sheet for SGC and USGS, official report link and distance from the user. |
| API | JSON endpoints for web and mobile clients, with Bearer authentication. |
| Citizen network | Device registry, P-wave detections, TDOA fusion, alert delivery and public dashboard. |
| PWA | Installable app, offline page, browser notifications and emergency overlay. |

## Data sources

| Source | Coverage | Notes |
|---|---|---|
| **SGC** — Servicio Geológico Colombiano | Colombia | Richer local records: CDI, MMI, PGA, felt reports, nearby towns and station metadata. |
| **USGS** — U.S. Geological Survey | Worldwide | FDSN GeoJSON feed, magnitude 2.5+ over the last five days by default. |

Both feeds are public. SIGRA stores a normalized copy for history and never
claims ownership over the original data. Always check the official source in an
emergency:

- SGC: <https://www.sgc.gov.co/>
- USGS: <https://earthquake.usgs.gov/>

The default feed parameters can be changed in `app/Config/Quakes.php` or with
environment variables (`quakes.*`).

## Architecture

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
- **MySQL or MariaDB** (utf8mb4)
- **No build step** for the web app; assets are plain PHP, CSS and JavaScript.

## Quick start

### Requirements

PHP 8.2+ with `curl`, `intl`, `mbstring` and `mysqli`, Composer 2, and a dedicated MySQL/MariaDB database. The bundled framework is CI4 4.7.3 under `system/`; `vendor/` is **not** committed and is installed from `composer.lock`. Node and Python are only required for automated tests.

### Installation

The supported procedure is in [INSTALLATION.md](docs/INSTALLATION.md), including secrets, database creation and troubleshooting:

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
# Create an empty database and configure database.default.* in .env.
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

Open <http://127.0.0.1:8080/index.php/sismos>. Use `app.baseURL = 'http://127.0.0.1:8080/'` locally. New deployments should expose only `public/`; existing Hostinger root deployments remain compatible with their protection rules.

The map requests updates automatically, sharing a 60-second cache and ingestion lock. Provider failure keeps stored events visible and displays a degraded status. Cron remains optional.

### Keep the archive up to date (recommended)

The map automatically refreshes sources while it is open, at most once every
`quakes.cacheSeconds` across visitors. Optionally run cron to update without visitors:

```cron
*/5 * * * * cd /var/www/sigra && php spark sigra:sync >> writable/logs/sync.log 2>&1
```

## Languages

The interface ships in **Spanish (source language)** and **English**. Views are
written once with keys (`lang('Sigra.*')`); the English file is generated and
updated automatically:

```bash
php spark sigra:translate                 # fill missing English keys
php spark sigra:translate --dry-run       # preview
php spark sigra:translate --force         # retranslate everything
php spark sigra:translate --key navMap    # retranslate one key
```

The provider is configured in `.env` (`translator.provider` = `google`,
`mymemory`, `libretranslate` or `deepl`). Translations are cached in
`writable/cache/translations/`. To add a new language, create
`app/Language/<locale>/Sigra.php` and run the command with `--locale <locale>`.

## Automated checks

After `composer install`, install Node 22+, Python 3 and PHP-CGI for your PHP version, then run `composer test`. The tests do not require production credentials. `composer analyse` runs progressive PHPStan checks. MySQL integration uses a separate, explicitly selected test database; see [test instructions](docs/PRUEBAS_WEB.md).

## Configuration

Copy `.env.example` and adjust:

| Variable | Purpose |
|---|---|
| `CI_ENVIRONMENT` | `development` locally, `production` on the server. |
| `app.baseURL` | Public URL with a trailing slash. Use `http://` only for local development. |
| `APP_API_TOKEN` | Bearer token required by the mobile/API endpoints. |
| `database.default.*` | MySQL connection. |
| `encryption.key` | 32 random bytes, `hex2bin:` format. **Required in production.** |
| `quakes.sgcEnabled` | Enable the SGC source. |
| `quakes.globalEnabled` | Enable the USGS global source. |
| `quakes.usgsMinMagnitude` | Global magnitude threshold (default `2.5`). |
| `quakes.windowDays` | Shared time window (default `5`). |
| `quakes.cacheSeconds` | Sync cache TTL (default `60`). |
| `quakes.dedupeCrossSource` | Remove SGC/USGS duplicates. |
| `red.*` | Citizen-network thresholds. |
| `translator.provider` | `google`, `mymemory`, `libretranslate` or `deepl` for `sigra:translate`. |
| `translator.apiKey` | API key for DeepL/LibreTranslate (optional for Google/MyMemory). |

Generate an encryption key:

```bash
php -r "echo 'hex2bin:' . bin2hex(random_bytes(32)) . PHP_EOL;"
```

## API

All `/api/sismos/*` endpoints accept `Authorization: Bearer <APP_API_TOKEN>`.

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/sismos/recent` | Recent events. Filters: `hours`, `limit`, `min_magnitude`, `fuente`, `order`. |
| `GET` | `/api/sismos/recent?lat=&lng=&radius_km=500&order=distancia` | Events inside a radius, nearest first, with `distancia_km`. |
| `GET` | `/api/sismos/detalle/{id}` | One event. |
| `GET` | `/api/sismos/ultimo` | Latest event. |
| `GET` | `/api/sismos/alertas` | Alert level for the latest event. |
| `GET` | `/api/health` | Public version and database status. |
| `GET` | `/api/red/config` | Public citizen-network configuration. |
| `POST` | `/api/red/registro` | Register an Android device. |
| `POST` | `/api/red/deteccion` | Report a P-wave detection. |
| `GET` | `/api/red/alertas` | Alerts near a device. |

Example:

```bash
curl -H "Authorization: Bearer $APP_API_TOKEN" \
  "https://tu-dominio/api/sismos/recent?lat=4.6097&lng=-74.0817&radius_km=500&limit=10&order=distancia"
```

## Privacy and the radius filter

Geolocation is **opt-in** and stays in the browser:

- The list page never asks for your location automatically.
- Pressing **Mi ubicación** calls `navigator.geolocation` and draws a circle on
  the map. Nothing is sent to the server or stored in the database.
- If the browser denies precise geolocation, SIGRA can fall back to an IP-based
  approximate location (`ipapi.co`) to keep the distance feature usable.
- The chosen radius is saved in `localStorage`; the coordinates are not.
- The public citizen-network dashboard rounds phone coordinates to ~1 km and
  never exposes device tokens.

## Android app

The Android client is under development in a separate repository. This backend does
not include mobile sources or an APK build. Its integration contract is documented
in [OpenAPI](docs/api/openapi.yaml).

## Documentation

- [Functional guide (Spanish)](README_GUIA.md)
- [Versioning and changelog](docs/VERSIONES.md)
- [Third-party notices](THIRD_PARTY_NOTICES.md)
- [Citizen network and TDOA algorithm](docs/RED_TELEFONOS.md)
- [Citizen network API](docs/API_RED.md)
- [Database and maintenance](docs/BASE_DATOS.md)
- [Web and API tests](docs/PRUEBAS_WEB.md)
- [Production checklist](docs/PRODUCCION.md)
- [Seismic architecture](docs/ARQUITECTURA_SISMOS.md)

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) and
[CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). Do not publish credentials or private user data in public issues.

Good first issues:

- Add another public source (EMSC, GEOFON) behind the existing normalizer.
- Improve the global deduplication heuristic.
- Add integration tests for the radius API.
- Translate the UI to English and Portuguese.
- Improve accessibility of the map and emergency overlay.

## Donations

If SIGRA is useful to you, you can support its development and maintenance with
a voluntary donation. Thank you for supporting the project!

| Method | Donation details |
|---|---|
| **Nequi** | `315 9287929` |
| **Bancolombia** | Account `912-982627-76` |

## Versioning

SIGRA follows semantic versioning. The current release is **1.2.1 · Reproducible installation and backend robustness** (2026-11-12, build `20261112.5`). See [docs/VERSIONES.md](docs/VERSIONES.md).

API contracts: [Earthquakes](docs/API_SISMOS.md) · [Citizen network](docs/API_RED.md).

## Disclaimer

SIGRA is an **experimental** system. It does not predict earthquakes and does
not replace the SGC, USGS, civil-protection agencies or official warning
systems. In an emergency, follow the instructions of local authorities.

## License

MIT. See [LICENSE](LICENSE) and [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) for OpenStreetMap, SGC, USGS, Esri, Leaflet, Bootstrap, Google Fonts and Android attributions. CodeIgniter 4 is distributed under its own MIT license and its copyright notices are preserved in `LICENSE`.

Citizen report verification: [flow and setup](docs/REPORTES_CIUDADANOS.md).

Consulta la [arquitectura modular](docs/architecture/README.md), los [pasos de migración](docs/architecture/migration.md) y el [contrato OpenAPI](docs/api/openapi.yaml).
