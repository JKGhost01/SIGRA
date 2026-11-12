<?php

/**
 * sismos/list/index.php
 * -------------------------------------------------------------------
 * Punto de entrada de la operación LISTAR.
 *
 * Responsabilidades (según arquitectura del proyecto):
 *   1. comprobar sesión (en esta versión no se exige login)
 *   2. comprobar permisos (la consulta pública está permitida)
 *   3. si no hay permiso -> deny.php
 *   4. si hay permiso -> consultar el modelo y mostrar el listado
 *
 * No hay formulario en list, por eso form.php/validator.php/procesor.php
 * de esta carpeta quedan como stubs (ver comentarios en cada archivo),
 * salvo que en el futuro se agreguen acciones masivas desde el listado.
 */

// 1. Sesión (consulta pública)
$sesionValida = true;

if (!$sesionValida) {
    return require __DIR__ . '/deny.php';
}

// 2. Permisos (consulta pública permitida)
$tienePermiso = true;

if (!$tienePermiso) {
    return require __DIR__ . '/deny.php';
}

// 3. Consulta al Model (la única capa que toca la base de datos)
$modeloSismos = new \App\Models\SismoModel();
$locale       = service('request')->getLocale();
$sismos       = $modeloSismos->obtenerRecientes(\App\Models\SismoModel::VENTANA_EVENTOS_HORAS);
$eventosIniciales = array_map(static function (array $sismo): array {
    $metadata = json_decode((string) ($sismo['sgc_metadata'] ?? ''), true);
    $metadata = is_array($metadata) ? $metadata : [];

    return [
        'id'                 => $sismo['id'],
        'lat'                => (float) $sismo['latitud'],
        'lng'                => (float) $sismo['longitud'],
        'magnitud'           => $sismo['magnitud'],
        'profundidad'        => $sismo['profundidad'],
        'ubicacion'          => $sismo['ubicacion'],
        'fecha_hora'         => $sismo['fecha_hora'],
        'fuente'             => $sismo['fuente'],
        'intensidadPercibida' => $metadata['cdi'] ?? null,
        'intensidadInstrumental' => $metadata['mmi'] ?? null,
        'aceleracion'        => $metadata['maxPGA'] ?? null,
        'citizen_reports_count' => (int) ($sismo['citizen_reports_count'] ?? 0),
        'sentido'            => $metadata['felt'] ?? null,
        'url'                => $metadata['url'] ?? null,
        'municipios'         => trim((string) $sismo['ubicacion'] . ' ' . (string) ($metadata['closerTowns'] ?? '')),
    ];
}, $sismos);

// 4. Render del HTML de listado + mapa general
ob_start();
?>

<?php if (session()->getFlashdata('mensaje')): ?>
    <div class="alert alert-success mb-3"><?= esc(session()->getFlashdata('mensaje')) ?></div>
<?php endif; ?>

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
    const SISMOS = <?= json_encode($eventosIniciales) ?>;
</script>
<script src="/public/assets/js/mapa-sismos.js"></script>
<script>
    const T = <?= json_encode([
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

    function tr(clave) {
        let texto = T[clave] || clave;

        for (let i = 1; i < arguments.length; i += 1) {
            texto = texto.replace(new RegExp('\\{' + (i - 1) + '\\}', 'g'), arguments[i]);
        }

        return texto;
    }

    function escaparHtml(valor) {
        return String(valor ?? '').replace(/[&<>"']/g, function (caracter) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[caracter];
        });
    }

    function fechaLocalTimestamp(valor) {
        const partes = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(String(valor));
        if (!partes) {
            return 0;
        }
        return Date.UTC(
            Number(partes[1]), Number(partes[2]) - 1, Number(partes[3]),
            Number(partes[4]) + 5, Number(partes[5]), Number(partes[6] || 0)
        );
    }

    function formatearFechaLocal(valor) {
        const partes = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(valor));
        if (!partes) {
            return valor;
        }
        const meses = T.months;
        return `${partes[3]} ${meses[Number(partes[2]) - 1]} ${partes[1]} · ${partes[4]}:${partes[5]}`;
    }

    const idsConocidos = new Set(SISMOS.map(function (evento) { return String(evento.id); }));
    let eventosActuales = SISMOS;
    let idsNuevos = new Set();
    let resaltadoNuevoActivo = true;
    let temporizadorResaltadoNuevo;
    let temporizadorAvisoNuevo;
    let temporizadorDatosActualizados;
    let replayTimer = null;
    let posicionUsuario = null;
    function leerRadioGuardado() {
        try { return localStorage.getItem('sigra-radio-km'); } catch (_) { return null; }
    }
    function guardarRadio(valor) {
        radioElegido = true;
        try {
            localStorage.setItem('sigra-radio-km', String(valor));
        } catch (_) { /* Los filtros funcionan también sin almacenamiento. */ }
    }
    const radioGuardado = leerRadioGuardado();
    let radioElegido = radioGuardado !== null;
    let radioKm = Number(radioGuardado) || 0;
    let ubicacionPendiente = false;
    let filtroFuente = '';
    let filtroMagnitud = '';
    let selectorFuente;
    let selectorMagnitud;
    let selectorRadio;
    let botonUbicacion;
    let estadoUbicacion;

    function mostrarAviso(elemento, mensaje, temporizador) {
        elemento.textContent = mensaje;
        elemento.classList.add('visible');
        window.clearTimeout(temporizador);
        return window.setTimeout(function () { elemento.classList.remove('visible'); }, 6000);
    }

    function iniciarTemporizadorResaltado() {
        temporizadorResaltadoNuevo = window.setTimeout(function () {
            resaltadoNuevoActivo = false;
            document.querySelectorAll('.evento-item.evento-nuevo').forEach(function (evento) {
                evento.classList.remove('evento-nuevo');
            });
        }, 60000);
    }

    function distanciaDeEvento(evento) {
        if (!posicionUsuario) {
            return null;
        }

        return calcularDistanciaKm(
            posicionUsuario.lat,
            posicionUsuario.lng,
            Number(evento.lat),
            Number(evento.lng)
        );
    }

    function eventosVisibles() {
        const filtro = filtroMunicipio ? filtroMunicipio.value.trim().toLowerCase() : '';

        return eventosActuales.filter(function (evento) {
            if (filtro && !String(evento.municipios || evento.ubicacion).toLowerCase().includes(filtro)) {
                return false;
            }
            if (filtroFuente && String(evento.fuente || 'SGC') !== filtroFuente) {
                return false;
            }
            if (filtroMagnitud && Number(evento.magnitud) < Number(filtroMagnitud)) {
                return false;
            }
            if (posicionUsuario && radioKm > 0) {
                const distancia = distanciaDeEvento(evento);
                return distancia !== null && distancia <= radioKm;
            }

            return true;
        });
    }

    function actualizarListaEventos(eventos) {
        if (Array.isArray(eventos)) {
            eventosActuales = eventos;
        }

        const listaEventos = document.getElementById('eventos-lista');
        const contadorEventos = document.getElementById('contador-eventos');
        const eventosFiltrados = eventosVisibles();

        listaEventos.innerHTML = eventosFiltrados.map(function (evento, indice) {
            const fechaTexto = formatearFechaLocal(evento.fecha_hora);
            const esNuevo = resaltadoNuevoActivo && indice === 0;
            const intensidadPercibida = evento.intensidadPercibida ?? '';
            const intensidadInstrumental = evento.intensidadInstrumental ?? '';
            const aceleracion = evento.aceleracion ?? '';
            const distancia = distanciaDeEvento(evento);
            const fuente = evento.fuente || 'SGC';
            const textoPercibida = intensidadPercibida !== '' && Number(intensidadPercibida) > 0 ? escaparHtml(intensidadPercibida) : escaparHtml(tr('noData'));
            const textoInstrumental = intensidadInstrumental !== '' && Number(intensidadInstrumental) > 0 ? escaparHtml(intensidadInstrumental) : escaparHtml(tr('noData'));
            const textoAceleracion = aceleracion !== '' && aceleracion !== null ? escaparHtml(aceleracion) : escaparHtml(tr('noData'));
            const textoDistancia = distancia === null
                ? tr('activateLocation')
                : tr('kmFromYou', distancia.toFixed(1));
            const textoSentido = Number(evento.sentido) > 0
                ? `<span>${escaparHtml(tr('feltBy', evento.sentido))}</span>`
                : '';

            return `
                <article class="evento-item border-bottom pb-3 mb-3${esNuevo ? ' evento-nuevo' : ''}" tabindex="0" role="link"
                         data-id="${escaparHtml(evento.id)}"
                         data-fecha="${fechaLocalTimestamp(evento.fecha_hora) / 1000}"
                         data-magnitud="${escaparHtml(evento.magnitud)}"
                         data-profundidad="${escaparHtml(evento.profundidad)}"
                         data-lat="${escaparHtml(evento.lat)}"
                         data-lng="${escaparHtml(evento.lng)}"
                         data-fuente="${escaparHtml(fuente)}"
                         data-distancia="${distancia === null ? '' : escaparHtml(distancia.toFixed(1))}"
                         data-ubicacion="${escaparHtml(String(evento.ubicacion).toLowerCase())}"
                         data-municipios="${escaparHtml(String(evento.municipios || evento.ubicacion).toLowerCase())}"
                         data-cdi="${escaparHtml(intensidadPercibida)}"
                         data-mmi="${escaparHtml(intensidadInstrumental)}"
                         data-pga="${escaparHtml(aceleracion)}">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <span class="badge rounded-pill bg-dark-subtle text-dark">M ${escaparHtml(evento.magnitud)}</span>
                        <span class="small text-secondary">${escaparHtml(tr('sourceLabel', fuente))}</span>
                    </div>
                    <h2 class="h6 mb-1">${escaparHtml(evento.ubicacion)}</h2>
                    ${Number(evento.citizen_reports_count) > 0 ? `<p class="small text-success mb-1">${escaparHtml(tr('reportedByUsers', Number(evento.citizen_reports_count)))}</p>` : ''}
                    <p class="small text-secondary mb-2">${escaparHtml(fechaTexto)}</p>
                    <p class="distancia-evento small text-secondary mb-2">${escaparHtml(textoDistancia)}</p>
                    <div class="d-flex gap-3 small text-secondary">
                        <span>${escaparHtml(tr('depthKm', evento.profundidad))}</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2 small text-secondary">
                        <span>${tr('feltCdi', textoPercibida)}</span>
                        <span>${tr('instrumentalMmi', textoInstrumental)}</span>
                        <span>${tr('pga', textoAceleracion)}</span>
                        ${textoSentido}
                    </div>
                </article>`;
        }).join('');

        if (eventosFiltrados.length === 0) {
            listaEventos.innerHTML = `<p class="small text-secondary mb-0">${escaparHtml(tr('noEventsFiltered'))}</p>`;
        }

        contadorEventos.textContent = eventosFiltrados.length;
        const contadorEventosMovil = document.getElementById('mobile-contador-eventos');
        if (contadorEventosMovil) {
            contadorEventosMovil.textContent = eventosFiltrados.length;
        }
        const resumenFiltros = document.getElementById('filtros-resumen');
        if (resumenFiltros) {
            const resumen = [String(eventosFiltrados.length)];
            if (filtroFuente) {
                resumen.push(filtroFuente);
            }
            if (filtroMagnitud) {
                resumen.push(`M${filtroMagnitud}+`);
            }
            if (posicionUsuario && radioKm > 0) {
                resumen.push(`${radioKm.toLocaleString(T.locale)} km`);
            }
            resumenFiltros.textContent = resumen.join(' · ');
        }
        ordenarEventos();
        actualizarDistanciaListado();

        if (!replayTimer) {
            pintarMarcadoresListado(eventosFiltrados, false);
        }
    }

    let selectorOrden;
    let filtroMunicipio;
    let listaEventos;

    function valorNumerico(elemento, atributo) {
        const valor = Number(elemento.dataset[atributo]);
        return Number.isFinite(valor) && elemento.dataset[atributo] !== '' ? valor : null;
    }

    function compararNumeros(a, b, atributo, direccion) {
        const valorA = valorNumerico(a, atributo);
        const valorB = valorNumerico(b, atributo);
        if (valorA === null && valorB === null) return 0;
        if (valorA === null) return 1;
        if (valorB === null) return -1;
        return (valorB - valorA) * direccion;
    }

    function ordenarEventos() {
        if (!listaEventos || !selectorOrden) {
            return;
        }

        const eventos = Array.from(listaEventos.querySelectorAll('.evento-item'));
        const orden = selectorOrden.value;

        eventos.sort(function (a, b) {
            if (orden === 'fecha-asc') return Number(a.dataset.fecha) - Number(b.dataset.fecha);
            if (orden === 'magnitud-desc') {
                return Number(b.dataset.magnitud) - Number(a.dataset.magnitud);
            }
            if (orden === 'magnitud-asc') return Number(a.dataset.magnitud) - Number(b.dataset.magnitud);
            if (orden === 'cdi-desc') return compararNumeros(a, b, 'cdi', 1);
            if (orden === 'mmi-desc') return compararNumeros(a, b, 'mmi', 1);
            if (orden === 'pga-desc') return compararNumeros(a, b, 'pga', 1);
            if (orden === 'pga-exists') return (a.dataset.pga === '' ? 1 : 0) - (b.dataset.pga === '' ? 1 : 0);
            if (orden === 'distancia-asc') return compararNumeros(a, b, 'distancia', 1);
            return Number(b.dataset.fecha) - Number(a.dataset.fecha);
        });

        eventos.forEach(function (evento) {
            listaEventos.appendChild(evento);
        });

        eventos.forEach(function (evento, indice) {
            evento.classList.toggle('evento-nuevo', resaltadoNuevoActivo && indice === 0);
        });
    }

    let contextoAudio;
    let alertaSismoPendiente = false;

    function desbloquearAudio() {
        if (!('AudioContext' in window || 'webkitAudioContext' in window)) {
            return;
        }
        const AudioContextConstructor = window.AudioContext || window.webkitAudioContext;
        contextoAudio = contextoAudio || new AudioContextConstructor();
        if (contextoAudio.state === 'suspended') {
            contextoAudio.resume().then(function () {
                if (alertaSismoPendiente) {
                    alertaSismoPendiente = false;
                    reproducirAlertaSismo();
                }
            }).catch(function () { /* El navegador puede bloquear el audio hasta una interacción. */ });
        }
    }

    function reproducirAlertaSismo() {
        desbloquearAudio();
        if (!contextoAudio || contextoAudio.state !== 'running') {
            alertaSismoPendiente = true;
            return;
        }

        const ahora = contextoAudio.currentTime;
        [660, 880, 1040].forEach(function (frecuencia, indice) {
            const oscilador = contextoAudio.createOscillator();
            const ganancia = contextoAudio.createGain();
            oscilador.type = 'sine';
            oscilador.frequency.value = frecuencia;
            ganancia.gain.setValueAtTime(0.0001, ahora + (indice * 0.16));
            ganancia.gain.exponentialRampToValueAtTime(0.24, ahora + (indice * 0.16) + 0.02);
            ganancia.gain.exponentialRampToValueAtTime(0.0001, ahora + (indice * 0.16) + 0.14);
            oscilador.connect(ganancia);
            ganancia.connect(contextoAudio.destination);
            oscilador.start(ahora + (indice * 0.16));
            oscilador.stop(ahora + (indice * 0.16) + 0.15);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        inicializarMapaListado('mapa', SISMOS, 'distancia-usuario');

        const mapInfo = document.getElementById('map-info');
        const mapInfoToggle = document.getElementById('map-info-toggle');
        const mapInfoContent = document.getElementById('map-info-content');
        if (mapInfo && mapInfoToggle && mapInfoContent) {
            mapInfoToggle.addEventListener('click', function () {
                const abierto = mapInfo.classList.toggle('is-open');
                mapInfoContent.hidden = !abierto;
                mapInfoToggle.setAttribute('aria-expanded', String(abierto));
                mapInfoToggle.setAttribute('aria-label', abierto ? T.hideMapInfo : T.showMapInfo);
            });
        }

        document.addEventListener('pointerdown', desbloquearAudio, { once: true });
        document.addEventListener('keydown', desbloquearAudio, { once: true });

        const eventosSidebar = document.querySelector('.event-sidebar');
        const mobileEventsToggle = document.getElementById('mobile-events-toggle');
        const mobileEventsClose = document.getElementById('mobile-events-close');
        if (eventosSidebar && mobileEventsToggle && mobileEventsClose) {
            function cerrarListadoMovil() {
                eventosSidebar.classList.remove('details-open');
                mobileEventsToggle.setAttribute('aria-expanded', 'false');
            }

            mobileEventsToggle.addEventListener('click', function () {
                eventosSidebar.classList.add('details-open');
                mobileEventsToggle.setAttribute('aria-expanded', 'true');
            });
            mobileEventsClose.addEventListener('click', cerrarListadoMovil);
        }

        const urlSincronizacion = <?= json_encode(parse_url(site_url('sismos/sync'), PHP_URL_PATH)) ?>;
        const urlDetalle = <?= json_encode(parse_url(site_url('sismos/detalle'), PHP_URL_PATH)) ?>;
        const avisoNuevo = document.getElementById('sismo-aviso');
        const datosActualizados = document.getElementById('datos-actualizados');
        const sincronizar = function () {
            fetch(urlSincronizacion, { headers: { Accept: 'application/json' }, cache: 'no-store' })
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (resultado) {
                    if (resultado.ok && Array.isArray(resultado.eventos)) {
                        eventosActuales = resultado.eventos;
                        const eventosNuevos = resultado.eventos.filter(function (evento) {
                            return !idsConocidos.has(String(evento.id));
                        });
                        eventosNuevos.forEach(function (evento) {
                            idsConocidos.add(String(evento.id));
                        });

                        if (eventosNuevos.length > 0) {
                            const ultimoSismo = eventosNuevos.reduce(function (ultimo, evento) {
                                return fechaLocalTimestamp(evento.fecha_hora) > fechaLocalTimestamp(ultimo.fecha_hora)
                                    ? evento
                                    : ultimo;
                            });
                            idsNuevos = new Set([String(ultimoSismo.id)]);
                        }

                        if (eventosNuevos.length > 0) {
                            const mensaje = eventosNuevos.length === 1
                                ? tr('newQuake')
                                : tr('newQuakes', eventosNuevos.length);
                            temporizadorAvisoNuevo = mostrarAviso(avisoNuevo, mensaje, temporizadorAvisoNuevo);
                            reproducirAlertaSismo();
                        }

                        actualizarListaEventos(resultado.eventos);
                        actualizarEstadoRadio();
                        temporizadorDatosActualizados = mostrarAviso(
                            datosActualizados,
                            tr('updatedToast'),
                            temporizadorDatosActualizados
                        );
                    }
                })
                .catch(function () { /* El mapa conserva el último estado válido. */ });
        };

        sincronizar();
        window.setInterval(sincronizar, 15000);
        iniciarTemporizadorResaltado();

            selectorOrden = document.getElementById('orden-eventos');
            filtroMunicipio = document.getElementById('filtro-municipio');
            listaEventos = document.getElementById('eventos-lista');

            selectorOrden.addEventListener('change', ordenarEventos);
            filtroMunicipio.addEventListener('input', function () {
                actualizarListaEventos(eventosActuales);
            });

            selectorFuente = document.getElementById('filtro-fuente');
            selectorMagnitud = document.getElementById('filtro-magnitud');
            selectorRadio = document.getElementById('radio-filtro');
            botonUbicacion = document.getElementById('btn-mi-ubicacion');
            estadoUbicacion = document.getElementById('estado-ubicacion');

            const panelFiltros = document.getElementById('filtros-panel');
            if (panelFiltros && window.matchMedia('(max-width: 850px)').matches) {
                panelFiltros.open = false;
            }

            // Firefox y otros navegadores restauran los <select> al recargar.
            // Sincronizamos el estado real del DOM con las variables del filtro.
            if (selectorRadio) {
                selectorRadio.disabled = false;
                selectorRadio.value = String(radioKm);
                const valorRadio = Number(selectorRadio.value);
                if (Number.isFinite(valorRadio)) {
                    radioKm = valorRadio;
                }
            }
            filtroFuente = selectorFuente ? selectorFuente.value : '';
            filtroMagnitud = selectorMagnitud ? selectorMagnitud.value : '';

            function actualizarEstadoRadio() {
                if (!estadoUbicacion) {
                    return;
                }
                if (ubicacionPendiente) {
                    estadoUbicacion.textContent = T.gettingLocation;
                    return;
                }
                if (!posicionUsuario) {
                    estadoUbicacion.textContent = T.activateLocation;
                    return;
                }

                const total = eventosVisibles().length;
                const fuenteTexto = filtroFuente ? tr('sourceFrom', filtroFuente) : '';
                estadoUbicacion.textContent = radioKm > 0
                    ? tr('radiusStatus', total, fuenteTexto, radioKm.toLocaleString(T.locale))
                    : tr('locationReady', total, fuenteTexto);
            }

            function refrescarVistaFiltros() {
                if (posicionUsuario && radioKm > 0) {
                    dibujarRadioListado(posicionUsuario, radioKm, true);
                } else {
                    if (posicionUsuario) {
                        dibujarRadioListado(posicionUsuario, 0, false);
                    }
                    ajustarVistaListado(eventosVisibles());
                }
            }

            function activarUbicacion(discreto) {
                if (ubicacionPendiente) return;
                if (!navigator.geolocation && typeof fetch !== 'function') {
                    if (estadoUbicacion && discreto !== true) {
                        estadoUbicacion.textContent = T.geoNotAllowed;
                    }
                    return;
                }

                ubicacionPendiente = true;
                if (botonUbicacion) {
                    botonUbicacion.disabled = true;
                }
                if (estadoUbicacion) {
                    estadoUbicacion.textContent = T.gettingLocation;
                }

                obtenerUbicacionUsuario()
                    .then(function (pos) {
                        ubicacionPendiente = false;
                        posicionUsuario = {
                            lat: Number(pos.coords.latitude),
                            lng: Number(pos.coords.longitude)
                        };

                        fijarUbicacionListado(posicionUsuario.lat, posicionUsuario.lng, pos.coords.accuracy);

                        if (selectorRadio) {
                            selectorRadio.disabled = false;
                        }
                        if (radioKm === 0 && !radioElegido) {
                            radioKm = 500;
                            if (selectorRadio) {
                                selectorRadio.value = '500';
                            }
                            guardarRadio(radioKm);
                        }

                        actualizarListaEventos();
                        refrescarVistaFiltros();
                        actualizarEstadoRadio();

                        if (botonUbicacion) {
                            botonUbicacion.querySelector('span').textContent = T.updateLocation;
                            botonUbicacion.disabled = false;
                        }
                    })
                    .catch(function () {
                        ubicacionPendiente = false;
                        if (estadoUbicacion) {
                            estadoUbicacion.textContent = T.locationError;
                        }
                        if (botonUbicacion) {
                            botonUbicacion.disabled = false;
                        }
                    });
            }

            if (selectorFuente) {
                selectorFuente.addEventListener('change', function () {
                    filtroFuente = selectorFuente.value;
                    actualizarListaEventos();
                    refrescarVistaFiltros();
                    actualizarEstadoRadio();
                });
            }

            if (selectorMagnitud) {
                selectorMagnitud.addEventListener('change', function () {
                    filtroMagnitud = selectorMagnitud.value;
                    actualizarListaEventos();
                    refrescarVistaFiltros();
                    actualizarEstadoRadio();
                });
            }

            if (selectorRadio) {
                selectorRadio.addEventListener('change', function () {
                    radioKm = Number(selectorRadio.value) || 0;
                    guardarRadio(radioKm);
                    if (radioKm > 0 && !posicionUsuario) {
                        activarUbicacion(false);
                    }
                    actualizarListaEventos();
                    refrescarVistaFiltros();
                    actualizarEstadoRadio();
                });
            }

            if (botonUbicacion) {
                botonUbicacion.addEventListener('click', function () { activarUbicacion(false); });
            }

            const botonLimpiar = document.getElementById('btn-limpiar-filtros');
            if (botonLimpiar) {
                botonLimpiar.addEventListener('click', function () {
                    if (filtroMunicipio) {
                        filtroMunicipio.value = '';
                    }
                    if (selectorFuente) {
                        selectorFuente.value = '';
                    }
                    if (selectorMagnitud) {
                        selectorMagnitud.value = '';
                    }
                    filtroFuente = '';
                    filtroMagnitud = '';
                    radioKm = 0;
                    if (selectorRadio) {
                        selectorRadio.value = '0';
                    }
                    guardarRadio(0);
                    actualizarListaEventos();
                    refrescarVistaFiltros();
                    actualizarEstadoRadio();
                });
            }

            // Si el usuario ya concedió geolocalización, activamos el filtro
            // sin volver a mostrar un diálogo (el permiso sigue siendo suyo).
            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'geolocation' })
                    .then(function (permiso) {
                        if (permiso.state === 'granted') {
                            activarUbicacion(true);
                        }
                    })
                    .catch(function () { /* El navegador puede no soportar esta consulta. */ });
            }

            actualizarListaEventos();
            actualizarEstadoRadio();

            const replayBoton = document.getElementById('replay-actividad');
            const replayEstado = document.getElementById('replay-estado');

            function detenerReplay() {
                if (!replayTimer) {
                    return;
                }
                window.clearInterval(replayTimer);
                replayTimer = null;
                if (replayBoton) replayBoton.textContent = T.replay;
                if (replayEstado) replayEstado.textContent = '';
            }

            if (replayBoton) {
                replayBoton.addEventListener('click', function () {
                    if (replayTimer) {
                        detenerReplay();
                        pintarMarcadoresListado(eventosVisibles(), false);
                        return;
                    }

                    const ordenados = eventosVisibles().slice().sort(function (a, b) {
                        return fechaLocalTimestamp(a.fecha_hora) - fechaLocalTimestamp(b.fecha_hora);
                    });
                    if (!ordenados.length) {
                        return;
                    }

                    let indice = 0;
                    const paso = Math.max(1, Math.ceil(ordenados.length / 80));
                    replayBoton.textContent = T.replayStop;
                    pintarMarcadoresListado([], false);

                    replayTimer = window.setInterval(function () {
                        indice += paso;
                        const lote = ordenados.slice(0, Math.min(indice, ordenados.length));
                        pintarMarcadoresListado(lote, false);
                        if (replayEstado) {
                            replayEstado.textContent = Math.min(indice, ordenados.length) + '/' + ordenados.length;
                        }
                        if (indice >= ordenados.length) {
                            window.clearInterval(replayTimer);
                            replayTimer = null;
                            replayBoton.textContent = T.replayAgain;
                            if (replayEstado) {
                                replayEstado.textContent = T.replayDone;
                            }
                        }
                    }, 150);
                });
            }

            listaEventos.addEventListener('click', function (evento) {
                const tarjeta = evento.target.closest('.evento-item[data-id]');
                if (tarjeta) {
                    window.location.href = urlDetalle + '/' + encodeURIComponent(tarjeta.dataset.id);
                }
            });

            listaEventos.addEventListener('keydown', function (evento) {
                if (evento.key !== 'Enter' && evento.key !== ' ') {
                    return;
                }

                const tarjeta = evento.target.closest('.evento-item[data-id]');
                if (tarjeta) {
                    evento.preventDefault();
                    window.location.href = urlDetalle + '/' + encodeURIComponent(tarjeta.dataset.id);
                }
            });
    });
</script>

<?php
$contenido = ob_get_clean();
$titulo    = lang('Sigra.listTitle');
return view('layouts/main', ['contenido' => $contenido, 'titulo' => $titulo]);
