# Changelog

All notable changes to SIGRA are documented here. The project follows
[Semantic Versioning](https://semver.org/).

## [1.2.1] — 2026-11-12 · “Instalación reproducible y robustez del backend”

- Reproducible Composer installation, project metadata, minimal PHP 8.2-compatible lock and progressive PHPStan analysis.
- Safe local secret initialization, additive deployment instructions and retirement of the HTTP database-reset helper.
- Require the current device token before re-registration; rotate it and reject the previous token.
- Validate provider fields and external links, verify TLS, bound HTTP retries and expose the last successful map refresh.
- Support assets and offline pages with traditional and public document roots; never cache API/refresh requests in the service worker.
- Clarify experimental limitations and the separate Android development repository in both READMEs.
- Application build: `20261112.5`. API contract remains `v1`; existing device re-registration now requires its current token.

## [1.2.0] — 2026-11-12 · “Arquitectura modular y sincronización automática”

- Capability modules for catalog, ingestion, citizen reports, sensors, alerting and system, with explicit contracts and dependencies.
- Thin controllers and presentation-only views; queries and processing outside Views, with catalog JavaScript in public assets.
- Automatic map updates with a shared 60-second cache, concurrency locking and stored-data fallback; cron is optional.
- Preserved legacy routes, /api/v1 endpoints, versioned errors, correlation IDs and an OpenAPI contract.
- Ingestion and alert-decision audits in two new tables, committing each alert and its decision together before delivery.
- Isolated simulations, a publication kill switch and server-console alert cancellation.
- SGC/USGS correlation retains both source references and preserves ambiguous candidates.
- Architecture, HTTP contract, automatic refresh and isolated MySQL persistence tests, with deployment documentation.
- Requires additive migration `20261112000002_CreateArchitectureAudit`, or the equivalent generated deployment SQL; no database reset.
- Application build: `20261112.4`. Existing API contract remains `v1`.

## [1.1.1] — 2026-11-12 · “Reportes contrastados con SGC”

- Store citizen submissions privately in one new `citizen_reports` table in the existing MySQL database.
- Require at least three distinct participants and a unique SGC match within configurable time/distance tolerances before publishing a count on the official event.
- Keep unconfirmed and ambiguous reports off event lists, maps, history and API; exclude legacy citizen rows too.
- Add `citizen_reports_count` without changing official magnitudes or the SGC `felt` metric.
- Add CSRF, single-use session challenges, honeypot, body/type validation and session/network rate limits.
- Deduplicate networks including IPv6 /64, protect identifiers with HMAC and expire/purge private records during reconciliation.
- Serialize rate checking/insertion and reconciliation with MySQL advisory locks and InnoDB transactions.
- Block unauthenticated public editing/deletion of official events.
- Re-read public events through the model even when external downloads are cached.
- Add logic/CGI security tests and a MySQL test using rolled-back fixtures in the existing database.
- Requires the additive migration `20261112000001_CreateCitizenReports` (`php spark migrate --all`).

## [1.1.0] — 2026-11-12 · “API y contratos”

- Validate numeric query parameters, coordinate pairs, radius and sorting before reading data.
- Reject malformed JSON (400), invalid field types/ranges (422) and unsupported body types (415).
- Normalize device booleans so `"false"` is not interpreted as enabled.
- Return JSON errors for service construction, device authentication and database failures.
- Apply `Cache-Control: no-store`, `X-SIGRA-Version` and `X-API-Version` to both APIs.
- Compare API keys with `hash_equals`; protected endpoints reject missing configuration in production.
- Reject invalid optional device tokens with 401.
- Preserve Android routes and successful response fields. API contract stays `v1`.
- Document earthquake and network endpoints and add CGI and isolated controller response tests.

## [1.0.4] — 2026-11-12 · “Filtros y presentación”

- Enable radius selection on load and request location when a distance is selected.
- Bound IP geolocation requests and allow retry after failures.
- Preserve filter behavior when local storage is blocked or cleared during a request.
- Translate release history and channel labels into Spanish and English.
- Replace interface emojis with local SVG icons.
- Refresh the static cache and retrieve updated scripts and styles from the network.
- Add deterministic distance-filter regression tests (`node tools/test-map-filters.js`).

## [1.0.3] — 2026-11-11 · “Alcance global”

### Added

- Unified ingestion of **SGC (Colombia)** and **USGS (worldwide)** events into a
  single normalized table, map, history and API.
- New `Config\Quakes` with environment overrides for source thresholds, window,
  cache and cross-source deduplication.
- `UsgsClient` normalizer and `QuakeSyncService` orchestrator with per-source
  fault tolerance.
- “Cerca de mí” radius filter (50 km → 5,000 km), distance-aware list and
  “nearest first” ordering. Geolocation is opt-in and stays in the browser.
- Source filter and global place search.
- Magnitude-range filter (M2.5+ → M6+) and a compact, collapsible filter panel.
- Bilingual Spanish/English interface with a navbar switcher and automatic key translation.
- New icons and colors for “Report quake” and browser alerts.
- Third-party legal notices (OpenStreetMap, SGC, USGS, Esri, Leaflet, etc.).
- Cross-source deduplication (same event reported by SGC and USGS).
- `php spark sigra:sync` command for cron-based background sync.
- Radius parameters in `/api/sismos/recent` (`lat`, `lng`, `radius_km`,
  `order=distancia`).
- USGS technical sheet: alert level, tsunami flag, significance and official
  report link.
- Open-source project files: English/Spanish README, `LICENSE`, `CONTRIBUTING`,
  `CODE_OF_CONDUCT`, `SECURITY`, `.env.example` and CI lint workflow.

### Changed

- `/sismos` is now the global map. `/sismos/globales` redirects to it for
  backwards compatibility.
- `/sismos/historico` reads only from the database; the live USGS request was
  removed.
- Version displayed in navbar, footer, `/acerca-de`, `/api/health` and
  `/api/red/health`.

### Fixed

- Removed the hardcoded Carto tile key from `mapa-sismos.js`.
- Escaped external feed data in map popups to prevent HTML injection.
- Removed the `https://localhost:8080` base URL trap in local development.
- Map and list no longer request geolocation without user action.

## [1.0.2] — 2026-11-03 · “Red en vivo”

- Citizen network entry in the navbar with phone count and alert state.
- Browser notifications, emergency overlay with S-wave countdown.
- Installable PWA, manifest, service worker, offline page.
- Animated P/S wave propagation on `/red`.
- Personal risk panel with distance, ETA and risk level.
- Animated replay of seismic activity on `/sismos`.
- Shareable alerts and push/polling delivery.
- Removal of SMS/OTP/Twilio and the `sms_subscriptions` table.

## [1.0.1] — 2026-11-03 · “Red de teléfonos”

- Citizen network backend: devices, detections, alerts and deliveries.
- TDOA fusion, magnitude estimation, confidence and S-wave ETA.
- `/api/red/*` endpoints and public `/red` dashboard.
- Android app with foreground service, sensor and notifications.

## [1.0.0] — 2026-09-21 · “Mapa sísmico”

- Leaflet/OpenStreetMap interactive map.
- SGC feed synchronization.
- Searchable history, filters and event detail.
- Citizen location reports.
- Light/dark themes and visual/sound alerts.
