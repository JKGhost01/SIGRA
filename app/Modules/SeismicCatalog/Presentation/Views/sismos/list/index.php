<?php
$sismos = $vm->events;
$eventosIniciales = $vm->initialEvents;
ob_start();
?>
<link rel="stylesheet" href="<?= sigra_public_url('assets/css/seismic-globe.css') ?>">

<?php if ($message): ?>
    <div class="alert alert-success mb-3"><?= esc($message) ?></div>
<?php endif; ?>

<div class="dashboard-shell">
    <div id="mobile-events-backdrop" class="mobile-events-backdrop" hidden aria-hidden="true"></div>
    <aside class="event-sidebar" id="catalog-panel">
        <div class="p-4 border-bottom">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h4 mb-1"><?= esc(lang('Sigra.listTitle')) ?></h1>
                    <p class="small text-secondary mb-0"><span id="contador-eventos"><?= count($sismos) ?></span> <?= esc(lang('Sigra.eventsLast5Days')) ?></p>
                </div>
                <span id="catalog-source-indicator" class="rounded-circle bg-success p-2" title="<?= esc(lang('Sigra.dataActive')) ?>"></span>
            </div>
            <p id="catalog-sync-status" class="small text-secondary mt-3 mb-0" role="status" aria-live="polite"><?= esc(lang('Sigra.catalogLoading')) ?></p>
            <button id="mobile-events-toggle" class="mobile-events-toggle" type="button" aria-controls="eventos-detalle" aria-expanded="false"><span class="list-icon"><?= sigra_icon('list') ?></span><span><?= esc(lang('Sigra.mobileEvents')) ?></span></button>
        </div>

        <div id="eventos-detalle" class="event-details" aria-labelledby="mobile-events-title">
            <div class="event-details-header">
                <div>
                    <h2 class="h6 mb-1" id="mobile-events-title"><?= esc(lang('Sigra.listTitle')) ?></h2>
                    <p class="small text-secondary mb-0"><span id="mobile-contador-eventos"><?= count($sismos) ?></span> <?= esc(lang('Sigra.eventsLast5Days')) ?></p>
                </div>
                <button id="mobile-events-close" class="mobile-events-close" type="button" aria-label="<?= esc(lang('Sigra.mobileEventsClose')) ?>">×</button>
            </div>
            <div id="mobile-catalog-status" class="mobile-catalog-status"></div>
            <details class="event-filters border-bottom" id="filtros-panel" open>
                <summary class="event-filters-summary"><?= esc(lang('Sigra.filtersAndNearby')) ?> <span id="filtros-resumen" class="fw-normal text-secondary"></span></summary>
                <div class="p-3 pb-2">
                    <div class="row g-2">
                        <div class="col-12">
                            <label for="orden-eventos" class="form-label small fw-bold mb-1"><?= esc(lang('Sigra.listOrder')) ?></label>
                            <select id="orden-eventos" class="form-select form-select-sm">
                                <option value="fecha-desc"><?= esc(lang('Sigra.orderNewest')) ?></option>
                                <option value="fecha-asc"><?= esc(lang('Sigra.orderOldest')) ?></option>
                                <option value="magnitud-desc"><?= esc(lang('Sigra.orderMagDesc')) ?></option>
                                <option value="magnitud-asc"><?= esc(lang('Sigra.orderMagAsc')) ?></option>
                                <option value="cdi-desc"><?= esc(lang('Sigra.orderCdi')) ?></option>
                                <option value="mmi-desc"><?= esc(lang('Sigra.orderMmi')) ?></option>
                                <option value="pga-desc"><?= esc(lang('Sigra.orderPga')) ?></option>
                                <option value="pga-exists"><?= esc(lang('Sigra.orderPgaExists')) ?></option>
                                <option value="distancia-asc"><?= esc(lang('Sigra.orderDistance')) ?></option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label for="filtro-fuente" class="form-label small fw-bold mb-1"><?= esc(lang('Sigra.filterSource')) ?></label>
                            <select id="filtro-fuente" class="form-select form-select-sm">
                                <option value=""><?= esc(lang('Sigra.allSources')) ?></option>
                                <option value="SGC">SGC</option>
                                <option value="USGS">USGS</option>
                                <option value="EMSC">EMSC</option>
                                <option value="GEONET">GeoNet</option>
                                <option value="GEOFON">GEOFON · GFZ</option>
                                <option value="INGV">INGV</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label for="filtro-magnitud" class="form-label small fw-bold mb-1"><?= esc(lang('Sigra.filterMagnitude')) ?></label>
                            <select id="filtro-magnitud" class="form-select form-select-sm">
                                <option value=""><?= esc(lang('Sigra.allMagnitudes')) ?></option>
                                <option value="2.5">M2.5+</option>
                                <option value="3">M3+</option>
                                <option value="4">M4+</option>
                                <option value="5">M5+</option>
                                <option value="6">M6+</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label for="radio-filtro" class="form-label small fw-bold mb-1"><?= esc(lang('Sigra.filterRadius')) ?></label>
                            <select id="radio-filtro" class="form-select form-select-sm" aria-describedby="estado-ubicacion">
                                <option value="0"><?= esc(lang('Sigra.globalRadius')) ?></option>
                                <option value="50">50 km</option>
                                <option value="100">100 km</option>
                                <option value="250">250 km</option>
                                <option value="500">500 km</option>
                                <option value="1000"><?= esc(number_format(1000, 0, '.', $locale === 'en' ? ',' : '.')) ?> km</option>
                                <option value="2000"><?= esc(number_format(2000, 0, '.', $locale === 'en' ? ',' : '.')) ?> km</option>
                                <option value="5000"><?= esc(number_format(5000, 0, '.', $locale === 'en' ? ',' : '.')) ?> km</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><?= sigra_icon('search') ?></span>
                                <input id="filtro-municipio" class="form-control" type="search" placeholder="<?= esc(lang('Sigra.placePlaceholder')) ?>" autocomplete="off" aria-label="<?= esc(lang('Sigra.filterPlaceAria')) ?>">
                            </div>
                        </div>
                        <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                            <button id="btn-mi-ubicacion" class="btn btn-outline-secondary btn-sm" type="button"><?= sigra_icon('pin') ?> <span><?= esc(lang('Sigra.myLocation')) ?></span></button>
                            <button id="btn-limpiar-filtros" class="btn btn-link btn-sm p-0" type="button"><?= esc(lang('Sigra.clearFilters')) ?></button>
                            <span id="replay-estado" class="small text-secondary text-nowrap ms-auto"></span>
                        </div>
                        <p id="estado-ubicacion" class="small text-secondary mb-0"><?= esc(lang('Sigra.activateLocation')) ?></p>
                        <div class="col-12">
                            <button id="replay-actividad" class="btn btn-outline-secondary btn-sm w-100" type="button"><?= esc(lang('Sigra.replay')) ?></button>
                        </div>
                    </div>
                </div>
            </details>

            <nav id="catalog-pagination" class="catalog-pagination border-bottom p-3" aria-label="<?= esc(lang('Sigra.pagination')) ?>" hidden>
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <span id="catalog-page-range" class="small text-secondary" role="status" aria-live="polite"></span>
                    <label class="small text-secondary d-flex align-items-center gap-1" for="catalog-page-size">
                        <?= esc(lang('Sigra.perPage')) ?>
                        <select id="catalog-page-size" class="form-select form-select-sm w-auto">
                            <option value="20">20</option><option value="50">50</option><option value="100">100</option>
                        </select>
                    </label>
                </div>
                <div class="d-flex align-items-center justify-content-between gap-1">
                    <button id="catalog-page-first" class="btn btn-outline-secondary btn-sm" type="button" aria-label="<?= esc(lang('Sigra.firstPage')) ?>">«</button>
                    <button id="catalog-page-prev" class="btn btn-outline-secondary btn-sm" type="button" aria-label="<?= esc(lang('Sigra.previousPage')) ?>">‹</button>
                    <select id="catalog-page-number" class="form-select form-select-sm w-auto" aria-label="<?= esc(lang('Sigra.goToPageLabel')) ?>"></select>
                    <button id="catalog-page-next" class="btn btn-outline-secondary btn-sm" type="button" aria-label="<?= esc(lang('Sigra.nextPage')) ?>">›</button>
                    <button id="catalog-page-last" class="btn btn-outline-secondary btn-sm" type="button" aria-label="<?= esc(lang('Sigra.lastPage')) ?>">»</button>
                </div>
                <p class="small text-secondary mb-0 mt-2"><?= esc(lang('Sigra.mapAllFiltered')) ?></p>
            </nav>
            <div id="eventos-lista" class="p-3">
            <?php if (empty($sismos)): ?>
                <p class="small text-secondary mb-0"><?= esc(lang('Sigra.noReports')) ?></p>
            <?php endif; ?>

            <?php foreach (array_slice($sismos, 0, 20) as $loopIndex => $s): ?>
                <?php $metadata = json_decode((string) ($s['sgc_metadata'] ?? ''), true); $metadata = is_array($metadata) ? $metadata : []; ?>
                <article class="evento-item border-bottom pb-3 mb-3<?= $loopIndex === 0 ? ' evento-nuevo' : '' ?>" tabindex="0" role="link"
                         data-id="<?= esc($s['id']) ?>"
                         data-fecha="<?= esc((string) strtotime($s['fecha_hora'])) ?>"
                         data-magnitud="<?= esc((string) $s['magnitud']) ?>"
                         data-profundidad="<?= esc((string) $s['profundidad']) ?>"
                         data-lat="<?= esc((string) $s['latitud']) ?>"
                         data-lng="<?= esc((string) $s['longitud']) ?>"
                         data-fuente="<?= esc((string) $s['fuente']) ?>"
                         data-ubicacion="<?= esc(strtolower($s['ubicacion'])) ?>"
                         data-municipios="<?= esc(strtolower($s['ubicacion'] . ' ' . ($metadata['closerTowns'] ?? ''))) ?>"
                         data-cdi="<?= esc((string) ($metadata['cdi'] ?? '')) ?>"
                         data-mmi="<?= esc((string) ($metadata['mmi'] ?? '')) ?>"
                         data-pga="<?= esc((string) ($metadata['maxPGA'] ?? '')) ?>">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span class="badge rounded-pill bg-dark-subtle text-dark">M <?= esc($s['magnitud']) ?></span>
                        <span class="small text-secondary"><?= esc(lang('Sigra.sourceLabel', [implode(' · ', \App\Modules\SeismicCatalog\Domain\Services\SourceProvenance::sources($s))])) ?></span>
                    </div>
                    <h2 class="h6 mb-1"><?= esc($s['ubicacion']) ?></h2>
                    <?php if (($s['citizen_reports_count'] ?? 0) > 0): ?><p class="small text-success mb-1"><?= esc(lang('Sigra.reportedByUsers', [(string) $s['citizen_reports_count']])) ?></p><?php endif; ?>
                    <p class="small text-secondary mb-2"><?= esc(sigra_date((string) $s['fecha_hora'])) ?></p>
                    <p class="distancia-evento small text-secondary mb-2"><?= esc(lang('Sigra.activateLocation')) ?></p>
                    <div class="d-flex gap-3 small text-secondary">
                        <span><?= esc(lang('Sigra.depthKm', [(string) $s['profundidad']])) ?></span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2 small text-secondary">
                        <span><?= esc(lang('Sigra.feltCdi', [isset($metadata['cdi']) && $metadata['cdi'] !== '' && (float) $metadata['cdi'] > 0 ? (string) $metadata['cdi'] : lang('Sigra.noData')])) ?></span>
                        <span><?= esc(lang('Sigra.instrumentalMmi', [isset($metadata['mmi']) && $metadata['mmi'] !== '' && (float) $metadata['mmi'] > 0 ? (string) $metadata['mmi'] : lang('Sigra.noData')])) ?></span>
                        <span><?= esc(lang('Sigra.pga', [isset($metadata['maxPGA']) && $metadata['maxPGA'] !== '' && $metadata['maxPGA'] !== null ? (string) $metadata['maxPGA'] : lang('Sigra.noData')])) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </div>
    </aside>
    <div id="catalog-panel-resizer" class="catalog-panel-resizer" role="separator" tabindex="0" aria-orientation="vertical" aria-controls="catalog-panel" aria-label="<?= esc(lang('Sigra.catalogResize')) ?>" aria-valuemin="300" aria-valuemax="640" aria-valuenow="360"></div>
    <button id="catalog-panel-toggle" class="catalog-panel-toggle" type="button" aria-controls="catalog-panel" aria-expanded="true" aria-label="<?= esc(lang('Sigra.catalogCollapse')) ?>" data-collapse-label="<?= esc(lang('Sigra.catalogCollapse')) ?>" data-expand-label="<?= esc(lang('Sigra.catalogExpand')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m14 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>

    <section class="map-stage">
        <div class="map-engine-toolbar">
            <div class="map-tools-heading">
                <span class="map-tools-title"><?= esc(lang('Sigra.mapEngine')) ?></span>
                <button type="button" id="map-tools-toggle" class="map-tools-toggle" aria-expanded="true" aria-controls="map-tools-options" aria-label="<?= esc(lang('Sigra.collapseLayers')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 14 5-5 5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <div id="map-tools-options">
            <div class="map-engine-buttons" role="group" aria-label="<?= esc(lang('Sigra.mapEngine')) ?>">
                <button type="button" data-map-engine="cesium" aria-pressed="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/></svg>
                    <span><?= esc(lang('Sigra.mapGlobe')) ?></span>
                </button>
                <button type="button" data-map-engine="leaflet" aria-pressed="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z" stroke-linejoin="round"/><path d="M9 3v15M15 6v15"/></svg>
                    <span><?= esc(lang('Sigra.mapClassic')) ?></span>
                </button>
            </div>
            <div class="map-layer-section">
                <span class="map-layer-label" id="map-layer-label"><?= esc(lang('Sigra.mapBaseLayer')) ?></span>
                <div class="map-layer-options" role="group" aria-labelledby="map-layer-label">
                    <?php foreach (['osm' => 'layerMap', 'satellite' => 'layerSatellite', 'dark' => 'layerDark', 'topo' => 'layerTopo'] as $layer => $label): ?>
                    <button type="button" data-map-layer="<?= esc($layer) ?>" aria-pressed="false">
                        <span class="map-layer-preview map-layer-preview--<?= esc($layer) ?>" aria-hidden="true"><span></span></span>
                        <span class="map-layer-name"><?= esc(lang('Sigra.' . $label)) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="button" id="map-hazard-toggle" class="map-depth-toggle" aria-pressed="false" aria-controls="map-hazard-legend"><?= esc(lang('Sigra.mapHazard')) ?></button>
            <div id="map-hazard-legend" class="map-hazard-legend" hidden>
                <p id="map-hazard-summary" aria-live="polite"></p>
                <span class="map-hazard-gradient" aria-hidden="true"></span>
                <div class="map-hazard-labels"><span><?= esc(lang('Sigra.mapHazardLower')) ?></span><span><?= esc(lang('Sigra.mapHazardHigher')) ?></span></div>
                <p><?= esc(lang('Sigra.mapHazardScale')) ?></p>
                <a href="https://maps.openquake.org/map/gshm-v2026_1/" target="_blank" rel="noopener noreferrer"><?= esc(lang('Sigra.mapHazardSource')) ?></a>
                <p><?= esc(lang('Sigra.mapHazardNote')) ?></p>
            </div>
            <button type="button" id="map-depth-toggle" class="map-depth-toggle" aria-pressed="false" hidden><?= esc(lang('Sigra.mapDepthView')) ?></button>
            <div id="map-depth-legend" class="map-depth-legend" hidden>
                <span><i class="map-depth-surface" aria-hidden="true"></i><?= esc(lang('Sigra.mapDepthSurface')) ?></span>
                <span><i class="map-depth-focus" aria-hidden="true"></i><?= esc(lang('Sigra.mapDepthFocus')) ?></span>
            </div>
            <div class="map-engine-caption"><span class="map-engine-dot" aria-hidden="true"></span><span id="map-engine-status" role="status" aria-live="polite"></span></div>
            </div>
        </div>
        <div id="map-globe-popup" class="map-globe-popup" hidden>
            <button type="button" id="map-globe-close" aria-label="<?= esc(lang('Sigra.mapClosePopup')) ?>">×</button>
            <div data-popup-content></div>
        </div>
        <div id="map-info" class="map-info">
            <button id="map-info-toggle" class="map-info-toggle" type="button" aria-label="<?= esc(lang('Sigra.mapInfoTitle')) ?>" aria-controls="map-info-content" aria-expanded="false"></button>
            <div id="map-info-content" class="map-info-content" hidden>
                <strong><?= esc(lang('Sigra.mapInfoTitle')) ?></strong>
                <span><?= esc(lang('Sigra.mapInfoSubtitle')) ?></span>
                <div class="map-legend" aria-label="<?= esc(lang('Sigra.mapInfoTitle')) ?>">
                    <span class="map-legend-item"><i class="map-legend-dot small"></i> <?= esc(lang('Sigra.legendUnder')) ?></span>
                    <span class="map-legend-item"><i class="map-legend-dot medium"></i> <?= esc(lang('Sigra.legend25')) ?></span>
                    <span class="map-legend-item"><i class="map-legend-dot high"></i> <?= esc(lang('Sigra.legend35')) ?></span>
                    <span class="map-legend-item"><i class="map-legend-dot strong"></i> <?= esc(lang('Sigra.legend45')) ?></span>
                    <span class="map-legend-item"><i class="map-legend-dot deep"></i> <?= esc(lang('Sigra.legendDeep')) ?></span>
                </div>
            </div>
        </div>
        <div id="mapa"></div>
        <div id="map-navigation" class="map-navigation" role="group" aria-label="<?= esc(lang('Sigra.mapNavigation')) ?>" hidden>
            <button type="button" data-map-zoom="in" aria-label="<?= esc(lang('Sigra.mapZoomIn')) ?>" title="<?= esc(lang('Sigra.mapZoomIn')) ?>">+</button>
            <button type="button" data-map-zoom="out" aria-label="<?= esc(lang('Sigra.mapZoomOut')) ?>" title="<?= esc(lang('Sigra.mapZoomOut')) ?>">−</button>
            <button type="button" id="map-north" aria-label="<?= esc(lang('Sigra.mapNorth')) ?>" title="<?= esc(lang('Sigra.mapNorth')) ?>">N</button>
        </div>
    </section>
</div>

<script>
    const SISMOS = <?= json_encode($eventosIniciales, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= sigra_public_url('assets/js/mapa-sismos.js') ?>"></script>
<script>
    const SIGRA_MAP_TEXT = <?= json_encode([
        'catalogLoading' => lang('Sigra.catalogLoading'),
        'catalogUpdated' => lang('Sigra.catalogUpdated'),
        'catalogDegraded' => lang('Sigra.catalogDegraded'),
        'catalogProcessingFailed' => lang('Sigra.catalogProcessingFailed'),
        'catalogReportsUnavailable' => lang('Sigra.catalogReportsUnavailable'),
        'catalogSourceUnavailable' => lang('Sigra.catalogSourceUnavailable'),
        'catalogUnknown' => lang('Sigra.catalogUnknown'),
        'catalogUnavailable' => lang('Sigra.catalogUnavailable'),
        'locale' => $locale === 'en' ? 'en-US' : 'es-CO',
        'months' => $locale === 'en'
            ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
            : ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
        'activateLocation' => lang('Sigra.activateLocation'),
        'gettingLocation'  => lang('Sigra.gettingLocation'),
        'reportedByUsers'  => lang('Sigra.reportedByUsers'),
        'locationError'    => lang('Sigra.locationError'),
        'updateLocation'   => lang('Sigra.updateLocation'),
        'geoNotAllowed'    => lang('Sigra.geoNotAllowed'),
        'newQuake'         => lang('Sigra.newQuake'),
        'newQuakes'        => lang('Sigra.newQuakes'),
        'updatedToast'     => lang('Sigra.updatedToast'),
        'radiusStatus'     => lang('Sigra.radiusStatus'),
        'locationReady'    => lang('Sigra.locationReady'),
        'noEventsFiltered' => lang('Sigra.noEventsFiltered'),
        'sourceLabel'      => lang('Sigra.sourceLabel'),
        'sourceFrom'       => lang('Sigra.sourceFrom'),
        'depthKm'          => lang('Sigra.depthKm'),
        'feltCdi'          => lang('Sigra.feltCdi'),
        'instrumentalMmi'  => lang('Sigra.instrumentalMmi'),
        'pga'              => lang('Sigra.pga'),
        'feltBy'           => lang('Sigra.feltBy'),
        'noData'           => lang('Sigra.noData'),
        'kmFromYou'        => lang('Sigra.kmFromYou'),
        'replay'           => lang('Sigra.replay'),
        'replayStop'       => lang('Sigra.replayStop'),
        'replayAgain'      => lang('Sigra.replayAgain'),
        'replayDone'       => lang('Sigra.replayDone'),
        'showMapInfo'      => lang('Sigra.showMapInfo'),
        'hideMapInfo'      => lang('Sigra.hideMapInfo'),
        'paginationRange' => lang('Sigra.paginationRange'),
        'pageOf' => lang('Sigra.pageOf'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const SIGRA_MAP_URLS = <?= json_encode(['sync' => parse_url(site_url('sismos/sync'), PHP_URL_PATH), 'detail' => parse_url(site_url('sismos/detalle'), PHP_URL_PATH)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.SIGRA_GLOBE_CONFIG = <?= json_encode(['engine' => $mapEngine,
        'cesiumBaseUrl' => $mapSettings->cesiumBaseUrl,
        'osmUrl' => $mapSettings->osmUrl,
        'topoUrl' => $mapSettings->topoUrl,
        'satelliteUrl' => $mapSettings->satelliteUrl,
        'satelliteCredit' => $mapSettings->satelliteCredit,
        'satelliteMaxLevel' => $mapSettings->satelliteMaxLevel,
        'detail' => parse_url(site_url('sismos/detalle'), PHP_URL_PATH), 'text' => [
        'details' => lang('Sigra.mapEventDetails'),
        'loading' => lang('Sigra.mapGlobeLoading'),
        'ready' => lang('Sigra.mapGlobeReady'),
        'classic' => lang('Sigra.mapClassicReady'),
        'fallback' => lang('Sigra.mapGlobeFallback'),
        'imageryUnavailable' => lang('Sigra.mapImageryUnavailable'),
        'hazardSummary' => lang('Sigra.mapHazardSummary'),
        'hazardUnavailable' => lang('Sigra.mapHazardUnavailable'),
        'expand' => lang('Sigra.expandLayers'),
        'collapse' => lang('Sigra.collapseLayers'),
    ]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= sigra_public_url('assets/js/modules/seismic-map/hazard.js') ?>"></script>
<script src="<?= sigra_public_url('assets/js/modules/seismic-map/globe.js') ?>"></script>
<script src="<?= sigra_public_url('assets/js/modules/seismic-map/layout.js') ?>"></script>
<script src="<?= sigra_public_url('assets/js/modules/seismic-map/catalog.js') ?>"></script>
<?php
$contenido = ob_get_clean();
$titulo    = lang('Sigra.listTitle');
echo view('layouts/main', ['contenido' => $contenido, 'titulo' => $titulo]);
