<?php

/**
 * sismos/detail/form.php
 * -------------------------------------------------------------------
 * Presenta la ficha del sismo ($datos) y el mapa centrado en el
 * epicentro, incluyendo la distancia aproximada desde la ubicación
 * actual del usuario (calculada en el navegador vía JavaScript).
 */

$sgc = json_decode($datos['sgc_metadata'] ?? '', true);
$sgc = is_array($sgc) ? $sgc : [];
$fuente = (string) ($datos['fuente'] ?? 'SGC');
$esSgc = $fuente === 'SGC' && !empty($sgc);
$mapaSgc = 'https://archive.sgc.gov.co/events/' . rawurlencode((string) $datos['id']) . '/map.png';
$enlaceOficial = \App\Shared\Domain\ExternalUrl::http($sgc['url'] ?? null) ?? '';

ob_start();
?>

<?php if (($datos['citizen_reports_count'] ?? 0) > 0): ?><p class="text-success"><?= esc(lang('Sigra.reportedByUsers', [(string) $datos['citizen_reports_count']])) ?></p><?php endif; ?>
<div class="row">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <h4><?= esc(lang('Sigra.detailQuake', [(string) $datos['id']])) ?></h4>

                <table class="table table-sm">
                    <tr><th><?= esc(lang('Sigra.magnitude')) ?></th><td><?= esc($datos['magnitud']) ?></td></tr>
                    <tr><th><?= esc(lang('Sigra.detailDepth')) ?></th><td><?= esc($datos['profundidad']) ?> km</td></tr>
                    <tr><th><?= esc(lang('Sigra.detailCoords')) ?></th><td><?= esc($datos['latitud']) ?>, <?= esc($datos['longitud']) ?></td></tr>
                    <tr><th><?= esc(lang('Sigra.location')) ?></th><td><?= esc($datos['ubicacion']) ?></td></tr>
                    <tr><th><?= esc(lang('Sigra.dateTime')) ?></th><td><?= esc($datos['fecha_hora']) ?></td></tr>
                    <tr><th><?= esc(lang('Sigra.source')) ?></th><td><?= esc($fuente) ?></td></tr>
                    <tr><th><?= esc(lang('Sigra.detailDistance')) ?></th><td id="distancia-detalle"><?= esc(lang('Sigra.loading')) ?></td></tr>
                </table>

                <?php if ($enlaceOficial !== ''): ?>
                    <a href="<?= esc($enlaceOficial) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><?= esc(lang('Sigra.viewOfficial')) ?></a>
                <?php endif; ?>
                <a href="<?= site_url('sismos') ?>" class="btn btn-sm btn-outline-secondary"><?= esc(lang('Sigra.back')) ?></a>
            </div>
        </div>

        <?php if ($esSgc): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <p class="text-uppercase small fw-bold text-secondary mb-1"><?= esc(lang('Sigra.detailInfoSgc')) ?></p>
                    <h5 class="mb-3"><?= esc(lang('Sigra.detailTechnical')) ?></h5>
                    <dl class="row small mb-0">
                        <?php if (isset($sgc['agency'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailAgency')) ?></dt><dd class="col-6"><?= esc($sgc['agency']) ?></dd><?php endif; ?>
                        <?php if (isset($sgc['nst'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailStations')) ?></dt><dd class="col-6"><?= esc($sgc['nst']) ?></dd><?php endif; ?>
                        <?php if (isset($sgc['rms'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailRms')) ?></dt><dd class="col-6"><?= esc($sgc['rms']) ?> s</dd><?php endif; ?>
                        <?php if (isset($sgc['gap'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailGap')) ?></dt><dd class="col-6"><?= esc($sgc['gap']) ?>°</dd><?php endif; ?>
                        <?php if (isset($sgc['magType'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailMagType')) ?></dt><dd class="col-6"><?= esc($sgc['magType']) ?></dd><?php endif; ?>
                        <?php if (isset($sgc['localTime'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailLocalTime')) ?></dt><dd class="col-6"><?= esc($sgc['localTime']) ?></dd><?php endif; ?>
                        <?php if (isset($sgc['utcTime'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailUtcTime')) ?></dt><dd class="col-6"><?= esc($sgc['utcTime']) ?></dd><?php endif; ?>
                        <?php if (isset($sgc['updated'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailUpdated')) ?></dt><dd class="col-6"><?= esc($sgc['updated']) ?></dd><?php endif; ?>
                        <dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailMmi')) ?></dt><dd class="col-6"><?= isset($sgc['mmi']) && (float) $sgc['mmi'] > 0 ? esc($sgc['mmi']) : esc(lang('Sigra.noData')) ?></dd>
                        <dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailCdi')) ?></dt><dd class="col-6"><?= isset($sgc['cdi']) && (float) $sgc['cdi'] > 0 ? esc($sgc['cdi']) : esc(lang('Sigra.noData')) ?></dd>
                        <?php if (isset($sgc['felt'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailFelt')) ?></dt><dd class="col-6"><?= esc($sgc['felt']) ?></dd><?php endif; ?>
                        <dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailPga')) ?></dt><dd class="col-6"><?= isset($sgc['maxPGA']) && $sgc['maxPGA'] !== null && $sgc['maxPGA'] !== '' ? esc($sgc['maxPGA']) : esc(lang('Sigra.noData')) ?></dd>
                    </dl>
                    <?php if (!empty($sgc['closerTowns'])): ?>
                        <p class="small text-secondary border-top pt-3 mt-3 mb-0"><strong><?= esc(lang('Sigra.detailTowns')) ?>:</strong><br><?= esc($sgc['closerTowns']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (! $esSgc && $sgc !== []): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <p class="text-uppercase small fw-bold text-secondary mb-1"><?= esc(lang('Sigra.detailInfoSource', [$fuente])) ?></p>
                    <h5 class="mb-3"><?= esc(lang('Sigra.detailTechnical')) ?></h5>
                    <dl class="row small mb-0">
                        <?php if (isset($sgc['agency'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailAgency')) ?></dt><dd class="col-6"><?= esc($sgc['agency']) ?></dd><?php endif; ?>
                        <?php if (isset($sgc['magType'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailMagType')) ?></dt><dd class="col-6"><?= esc($sgc['magType']) ?></dd><?php endif; ?>
                        <?php if (isset($sgc['felt'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailFelt')) ?></dt><dd class="col-6"><?= esc($sgc['felt']) ?></dd><?php endif; ?>
                        <?php if (isset($sgc['alert']) && $sgc['alert'] !== null): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailAlert')) ?></dt><dd class="col-6"><?= esc($sgc['alert']) ?></dd><?php endif; ?>
                        <?php if (isset($sgc['tsunami'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailTsunami')) ?></dt><dd class="col-6"><?= (int) $sgc['tsunami'] === 1 ? esc(lang('Sigra.yes')) : esc(lang('Sigra.no')) ?></dd><?php endif; ?>
                        <?php if (isset($sgc['sig'])): ?><dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailSignificance')) ?></dt><dd class="col-6"><?= esc($sgc['sig']) ?></dd><?php endif; ?>
                        <dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailMmi')) ?></dt><dd class="col-6"><?= isset($sgc['mmi']) && (float) $sgc['mmi'] > 0 ? esc($sgc['mmi']) : esc(lang('Sigra.noData')) ?></dd>
                        <dt class="col-6 text-secondary"><?= esc(lang('Sigra.detailCdi')) ?></dt><dd class="col-6"><?= isset($sgc['cdi']) && (float) $sgc['cdi'] > 0 ? esc($sgc['cdi']) : esc(lang('Sigra.noData')) ?></dd>
                    </dl>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div id="mapa"></div>
            </div>
        </div>
    </div>
</div>

<?php if ($esSgc): ?>
    <section class="card mt-4">
        <div class="card-body">
            <div class="mb-3">
                <div>
                    <p class="text-uppercase small fw-bold text-secondary mb-1"><?= esc(lang('Sigra.detailProducts')) ?></p>
                    <h5 class="mb-0"><?= esc(lang('Sigra.detailMapSgc')) ?></h5>
                </div>
            </div>
            <img src="<?= esc($mapaSgc) ?>" alt="<?= esc(lang('Sigra.detailMapSgc')) ?> <?= esc($datos['id']) ?>" class="img-fluid rounded border w-100">
        </div>
    </section>
<?php endif; ?>

<script>
    const SISMO_DETALLE = {
        id:          <?= json_encode($datos['id']) ?>,
        lat:         <?= (float) $datos['latitud'] ?>,
        lng:         <?= (float) $datos['longitud'] ?>,
        magnitud:    <?= json_encode($datos['magnitud']) ?>,
        profundidad: <?= json_encode($datos['profundidad']) ?>,
        ubicacion:   <?= json_encode($datos['ubicacion']) ?>,
        fecha_hora:  <?= json_encode($datos['fecha_hora']) ?>,
        fuente:      <?= json_encode($fuente) ?>,
        citizen_reports_count: <?= (int) ($datos['citizen_reports_count'] ?? 0) ?>,
        url:         <?= json_encode($enlaceOficial) ?>
    };
</script>
<script src="<?= sigra_public_url('assets/js/mapa-sismos.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        inicializarMapaDetalle('mapa', SISMO_DETALLE, 'distancia-detalle');
    });
</script>

<?php
$contenido = ob_get_clean();
$titulo    = lang('Sigra.detailQuake', [(string) $datos['id']]);
echo view('layouts/main', ['contenido' => $contenido, 'titulo' => $titulo]);
