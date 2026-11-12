# SIGRA · Global Seismic Monitor & Citizen Network

> **v1.1.1 · Reports checked against SGC** — One map for the world's earthquakes, plus a community network that turns phones into early-warning sensors.

[![Version](https://img.shields.io/badge/version-1.1.1-087f78.svg)](#versioning)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://www.php.net/)
[![CodeIgniter](https://img.shields.io/badge/CodeIgniter-4.7-ee4323.svg)](https://codeigniter.com/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

*Read this in [Spanish](README.es.md).*

SIGRA is an open-source PHP application that unifies earthquake feeds from the
**Colombian Geological Survey (SGC)** and the **United States Geological Survey
(USGS)** into a single searchable map, with a “near me” radius filter, a
permanent historical archive, a JSON API, and a citizen P-wave detection network
built on Android phones.

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
- **Citizen network** — Android phones detect P waves with their accelerometers;
  the server triangulates the origin (TDOA), estimates magnitude and alerts
  nearby phones with the seconds remaining until the S wave arrives.
- **Open and self-hostable** — no proprietary services are required. Map tiles
  use OpenStreetMap/Esri and the data comes from public agencies.

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
  Commands/SigraSync.php          CLI sync for cron
  Config/Quakes.php               Global source configuration
  Controllers/Sismos.php          HTTP router + JSON API
  Libraries/SgcClient.php         SGC feed normalizer
  Libraries/UsgsClient.php        USGS FDSN normalizer
  Libraries/QuakeSyncService.php  Fetch + dedupe + persist
  Models/SismoModel.php           Data access only
  Views/sismos/list/              Map, list and radius filter
  Views/sismos/sync/              Cached JSON sync endpoint
public/assets/js/mapa-sismos.js   Leaflet rendering and geolocation helpers
mobile/                           Android app (Kotlin + Jetpack Compose)
```

- **PHP 8.2+ / CodeIgniter 4.7**
- **MySQL or MariaDB** (utf8mb4)
- **No build step** for the web app; assets are plain PHP, CSS and JavaScript.

## Quick start

### Requirements

- PHP 8.2 or newer with `intl`, `mbstring`, `mysqli` and `curl`.
- MySQL 5.7+ / MariaDB 10.3+.
- Composer is only needed if you want to install the framework dependencies
  separately; this repository ships with `system/` and `vendor/`.

### Installation

```bash
# 1. Clone
git clone https://github.com/TU-USUARIO/sigra.git
cd sigra

# 2. Create the database
mysql -u root -e "CREATE DATABASE sigra CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

# 3. Configure
cp .env.example .env
# Edit .env: baseURL, database, APP_API_TOKEN and encryption.key

# 4. Schema
php spark migrate --all

# 5. First sync (SGC + USGS)
php spark sigra:sync

# 6. Run
php spark serve
```

Open <http://localhost:8080/sismos>.

> The first sync downloads the last five days from both sources (~350 events in
> a typical run). The map also syncs every 15 seconds while it is open, but the
> server caches responses for `quakes.cacheSeconds` to protect the public APIs.

### Keep the archive up to date (recommended)

The page only syncs while someone has it open. For a production deployment, run
the command from cron every 2–5 minutes:

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
| `red.*` | Citizen-network thresholds. |`n| `translator.provider` | `google`, `mymemory`, `libretranslate` or `deepl` for `sigra:translate`. |`n| `translator.apiKey` | API key for DeepL/LibreTranslate (optional for Google/MyMemory). |

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
| `GET` | `/api/health` | Version, environment and database status. |
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

```bash
cd mobile
cp local.properties.example local.properties
# edit sdk.dir, sigra.baseUrl and sigra.apiKey
./gradlew :app:assembleDebug
```

APK output: `mobile/app/build/outputs/apk/debug/app-debug.apk`.

## Documentation

- [Functional guide (Spanish)](README_GUIA.md)
- [Versioning and changelog](docs/VERSIONES.md)`n- [Third-party notices](THIRD_PARTY_NOTICES.md)
- [Citizen network and TDOA algorithm](docs/RED_TELEFONOS.md)
- [Citizen network API](docs/API_RED.md)
- [Database and maintenance](docs/BASE_DATOS.md)
- [Web and API tests](docs/PRUEBAS_WEB.md)
- [Production checklist](docs/PRODUCCION.md)
- [Seismic architecture](docs/ARQUITECTURA_SISMOS.md)

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) and
[CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md). For security reports, follow
[SECURITY.md](SECURITY.md).

Good first issues:

- Add another public source (EMSC, GEOFON) behind the existing normalizer.
- Improve the global deduplication heuristic.
- Add integration tests for the radius API.
- Translate the UI to English and Portuguese.
- Improve accessibility of the map and emergency overlay.

## Versioning

SIGRA follows semantic versioning. The current release is **1.1.1 · Reports checked against SGC** (2026-11-12, build `20261112.3`). See [docs/VERSIONES.md](docs/VERSIONES.md).

API contracts: [Earthquakes](docs/API_SISMOS.md) · [Citizen network](docs/API_RED.md).

## Disclaimer

SIGRA is an **experimental** system. It does not predict earthquakes and does
not replace the SGC, USGS, civil-protection agencies or official warning
systems. In an emergency, follow the instructions of local authorities.

## License

MIT. See [LICENSE](LICENSE) and [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) for OpenStreetMap, SGC, USGS, Esri, Leaflet, Bootstrap, Google Fonts and Android attributions. CodeIgniter 4 is distributed under its own MIT license and its copyright notices are preserved in `LICENSE`.

Citizen report verification: [flow and setup](docs/REPORTES_CIUDADANOS.md).
