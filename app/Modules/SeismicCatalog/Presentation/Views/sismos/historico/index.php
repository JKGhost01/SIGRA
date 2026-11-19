<?php

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
                <div class="col-sm-6 col-lg-2">
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
                <div class="col-sm-6 col-lg-2">
                    <label for="history-per-page" class="form-label small fw-semibold"><?= esc(lang('Sigra.perPage')) ?></label>
                    <select id="history-per-page" name="per_page" class="form-select">
                        <?php foreach ([20, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= $pagination->perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-report flex-grow-1"><?= esc(lang('Sigra.applyFilters')) ?></button>
                    <a href="<?= site_url('sismos/historico') ?>" class="btn btn-outline-secondary"><?= esc(lang('Sigra.clearFilters')) ?></a>
                </div>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <p class="small text-secondary mb-0" role="status"><?= esc(lang('Sigra.paginationRange', [$pagination->from, $pagination->to, $pagination->total])) ?></p>
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
                            <td><?= esc(implode(' · ', \App\Modules\SeismicCatalog\Domain\Services\SourceProvenance::sources($evento))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <nav class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3" aria-label="<?= esc(lang('Sigra.pagination')) ?>">
        <span class="small text-secondary"><?= esc(lang('Sigra.pageOf', [$pagination->page, $pagination->pages])) ?></span>
        <ul class="pagination pagination-sm flex-wrap mb-0">
            <li class="page-item <?= $pagination->page === 1 ? 'disabled' : '' ?>">
                <?php if ($pagination->page > 1): ?><a class="page-link" href="<?= esc($pageLinks[$pagination->page - 1]) ?>" rel="prev"><?= esc(lang('Sigra.previousPage')) ?></a><?php else: ?><span class="page-link"><?= esc(lang('Sigra.previousPage')) ?></span><?php endif; ?>
            </li>
            <?php $previousNumber = 0; foreach ($pageLinks as $number => $url): ?>
                <?php if ($previousNumber && $number > $previousNumber + 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item <?= $number === $pagination->page ? 'active' : '' ?>">
                    <?php if ($number === $pagination->page): ?><span class="page-link" aria-current="page"><?= $number ?></span><?php else: ?><a class="page-link" href="<?= esc($url) ?>" aria-label="<?= esc(lang('Sigra.goToPage', [$number])) ?>"><?= $number ?></a><?php endif; ?>
                </li>
            <?php $previousNumber = $number; endforeach; ?>
            <li class="page-item <?= $pagination->page === $pagination->pages ? 'disabled' : '' ?>">
                <?php if ($pagination->page < $pagination->pages): ?><a class="page-link" href="<?= esc($pageLinks[$pagination->page + 1]) ?>" rel="next"><?= esc(lang('Sigra.nextPage')) ?></a><?php else: ?><span class="page-link"><?= esc(lang('Sigra.nextPage')) ?></span><?php endif; ?>
            </li>
        </ul>
    </nav>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = lang('Sigra.historyTitle');
echo view('layouts/main', ['contenido' => $contenido, 'titulo' => $titulo]);
