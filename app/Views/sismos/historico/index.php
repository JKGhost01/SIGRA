<?php

$ordenes = [
    'fecha_hora'  => lang('Sigra.colDate'),
    'magnitud'    => lang('Sigra.colMagnitude'),
    'profundidad' => lang('Sigra.colDepth'),
    'ubicacion'   => lang('Sigra.colLocation'),
    'fuente'      => lang('Sigra.colSource'),
];

$orden = $_GET['orden'] ?? 'fecha_hora';
$orden = array_key_exists($orden, $ordenes) ? $orden : 'fecha_hora';
$direccion = strtoupper($_GET['direccion'] ?? 'DESC');
$direccion = in_array($direccion, ['ASC', 'DESC'], true) ? $direccion : 'DESC';

$filtros = [
    'desde' => trim($_GET['desde'] ?? ''),
    'hasta' => trim($_GET['hasta'] ?? ''),
];

$eventos = (new \App\Models\SismoModel())->obtenerHistorico($filtros, $orden, $direccion);

ob_start();
?>

<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <p class="text-uppercase small fw-bold text-secondary mb-1"><?= esc(lang('Sigra.historyKicker')) ?></p>
            <h1 class="h3 mb-1"><?= esc(lang('Sigra.historyTitle')) ?></h1>
            <p class="text-secondary mb-0"><?= esc(lang('Sigra.historySubtitle')) ?></p>
        </div>
        <a href="<?= site_url('sismos') ?>" class="btn btn-outline-secondary"><?= esc(lang('Sigra.backToMap')) ?></a>
    </div>

    <form method="get" action="<?= site_url('sismos/historico') ?>" class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-2">
                    <label for="desde" class="form-label small fw-semibold"><?= esc(lang('Sigra.from')) ?></label>
                    <input type="date" id="desde" name="desde" class="form-control" value="<?= esc($filtros['desde']) ?>">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="hasta" class="form-label small fw-semibold"><?= esc(lang('Sigra.to')) ?></label>
                    <input type="date" id="hasta" name="hasta" class="form-control" value="<?= esc($filtros['hasta']) ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="orden" class="form-label small fw-semibold"><?= esc(lang('Sigra.listOrder')) ?></label>
                    <select id="orden" name="orden" class="form-select">
                        <?php foreach ($ordenes as $valor => $etiqueta): ?>
                            <option value="<?= esc($valor) ?>" <?= $orden === $valor ? 'selected' : '' ?>><?= esc($etiqueta) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="direccion" class="form-label small fw-semibold"><?= esc(lang('Sigra.direction')) ?></label>
                    <select id="direccion" name="direccion" class="form-select">
                        <option value="DESC" <?= $direccion === 'DESC' ? 'selected' : '' ?>><?= esc(lang('Sigra.descending')) ?></option>
                        <option value="ASC" <?= $direccion === 'ASC' ? 'selected' : '' ?>><?= esc(lang('Sigra.ascending')) ?></option>
                    </select>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-report flex-grow-1"><?= esc(lang('Sigra.applyFilters')) ?></button>
                    <a href="<?= site_url('sismos/historico') ?>" class="btn btn-outline-secondary"><?= esc(lang('Sigra.clearFilters')) ?></a>
                </div>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <p class="small text-secondary mb-0"><?= esc(lang('Sigra.eventsFound', [(string) count($eventos)])) ?></p>
        <span class="small text-secondary"><?= esc(lang('Sigra.includesSources')) ?></span>
    </div>

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= esc(lang('Sigra.colDate')) ?></th>
                        <th><?= esc(lang('Sigra.colMagnitude')) ?></th>
                        <th><?= esc(lang('Sigra.colDepth')) ?></th>
                        <th><?= esc(lang('Sigra.colLocation')) ?></th>
                        <th><?= esc(lang('Sigra.colSource')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($eventos)): ?>
                        <tr><td colspan="5" class="text-center text-secondary py-5"><?= esc(lang('Sigra.noEvents')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($eventos as $evento): ?>
                        <tr>
                            <td><?= esc(sigra_date((string) $evento['fecha_hora'])) ?></td>
                            <td><strong>M <?= esc($evento['magnitud']) ?></strong></td>
                            <td><?= esc($evento['profundidad']) ?> km</td>
                            <td><?= esc($evento['ubicacion']) ?><?php if (($evento['citizen_reports_count'] ?? 0) > 0): ?><br><small class="text-success"><?= esc(lang('Sigra.reportedByUsers', [(string) $evento['citizen_reports_count']])) ?></small><?php endif; ?></td>
                            <td><?= esc((string) ($evento['fuente'] ?? 'SGC')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = lang('Sigra.historyTitle');
return view('layouts/main', ['contenido' => $contenido, 'titulo' => $titulo]);
