<?php
$sismos = $vm->events;
$eventosIniciales = $vm->initialEvents;
ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-success mb-3"><?= esc($message) ?></div>
<?php endif; ?>

<p id="catalog-sync-status" class="small text-secondary mb-2" role="status" aria-live="polite"><?= esc(lang('Sigra.catalogLoading')) ?></p>
<div class="dashboard-shell">
    <aside class="event-sidebar">
        <div class="p-4 border-bottom">
            <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h4 mb-1"><?= esc(lang('Sigra.listTitle')) ?></h1>
                    <p class="small text-secondary mb-0"><span id="contador-eventos"><?= count($sismos) ?></span> <?= esc(lang('Sigra.eventsLast5Days')) ?></p>
                </div>
                <span class="rounded-circle bg-success p-2" title="<?= esc(lang('Sigra.dataActive')) ?>"></span>
            </div>
            <button id="mobile-events-toggle" class="mobile-events-toggle" type="button" aria-controls="eventos-detalle" aria-expanded="false"><span class="list-icon"><?= sigra_icon('list') ?></span><span><?= esc(lang('Sigra.mobileEvents')) ?></span></button>
        </div>

        <div id="eventos-detalle" class="event-details">
            <div class="event-details-header">
                <div>
                    <h2 class="h6 mb-1"><?= esc(lang('Sigra.listTitle')) ?></h2>
                    <p class="small text-secondary mb-0"><span id="mobile-contador-eventos"><?= count($sismos) ?></span> <?= esc(lang('Sigra.eventsLast5Days')) ?></p>
                </div>
                <button id="mobile-events-close" class="mobile-events-close" type="button" aria-label="<?= esc(lang('Sigra.mobileEventsClose')) ?>">×</button>
            </div>
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

            <div id="eventos-lista" class="p-3">
            <?php if (empty($sismos)): ?>
                <p class="small text-secondary mb-0"><?= esc(lang('Sigra.noReports')) ?></p>
            <?php endif; ?>

            <?php foreach ($sismos as $loopIndex => $s): ?>
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
                        <span class="small text-secondary"><?= esc(lang('Sigra.sourceLabel', [(string) $s['fuente']])) ?></span>
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

    <section class="map-stage">
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
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    const SIGRA_MAP_URLS = <?= json_encode(['sync' => parse_url(site_url('sismos/sync'), PHP_URL_PATH), 'detail' => parse_url(site_url('sismos/detalle'), PHP_URL_PATH)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= sigra_public_url('assets/js/modules/seismic-map/catalog.js') ?>"></script>
<?php
$contenido = ob_get_clean();
$titulo    = lang('Sigra.listTitle');
echo view('layouts/main', ['contenido' => $contenido, 'titulo' => $titulo]);
