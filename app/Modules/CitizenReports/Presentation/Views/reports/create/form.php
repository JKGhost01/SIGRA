<?php

/**
 * sismos/create/form.php
 * -------------------------------------------------------------------
 * Interfaz de usuario. Presenta el formulario de registro.
 * Recibe (opcionalmente, desde el controlador cuando los datos fallan):
 *   $errores  -> array de mensajes de validación
 *   $datos    -> array con los valores enviados para no perderlos
 */

$errores ??= [];

$datos   ??= [];

ob_start();
?>

<div class="report-shell">
    <section class="report-form-panel p-4 p-lg-5">
        <p class="text-uppercase small fw-bold text-secondary mb-2"><?= esc(lang('Sigra.reportKicker')) ?></p>
        <h1 class="h3 mb-2"><?= esc(lang('Sigra.reportTitle')) ?></h1>
        <p class="text-secondary mb-4"><?= esc(lang('Sigra.reportSubtitle')) ?></p>

        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errores as $err): ?>
                        <li><?= esc($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= site_url('sismos/create') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="report_token" value="<?= esc($reportToken, 'attr') ?>">
            <div style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
                <label for="report-website">Website</label>
                <input id="report-website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>
            <p class="small text-secondary"><?= esc(lang('Sigra.reportPendingHint')) ?></p>

            <div class="mb-4">
                <label class="form-label fw-semibold"><?= esc(lang('Sigra.reportWhere')) ?></label>
                <div class="location-actions mb-2">
                    <button type="button" class="btn btn-outline-success" id="btn-mi-ubicacion"><?= esc(lang('Sigra.reportUseLocation')) ?></button>
                    <button type="button" class="btn btn-outline-secondary" id="btn-usar-mapa"><?= esc(lang('Sigra.reportPickMap')) ?></button>
                </div>
                <p id="estado-ubicacion" class="small text-secondary mb-0"><?= esc(lang('Sigra.reportChooseHint')) ?></p>
            </div>

            <div class="location-coordinates row g-2 p-2 mb-4">
                <div class="col-6">
                    <label class="form-label small text-secondary mb-0"><?= esc(lang('Sigra.reportLat')) ?></label>
                    <input type="text" name="latitud" id="latitud" class="form-control" value="<?= esc($datos['latitud'] ?? '') ?>" readonly required>
                </div>
                <div class="col-6">
                    <label class="form-label small text-secondary mb-0"><?= esc(lang('Sigra.reportLng')) ?></label>
                    <input type="text" name="longitud" id="longitud" class="form-control" value="<?= esc($datos['longitud'] ?? '') ?>" readonly required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold"><?= esc(lang('Sigra.reportPlace')) ?></label>
                <input type="text" name="ubicacion" class="form-control" value="<?= esc($datos['ubicacion'] ?? '') ?>" placeholder="<?= esc(lang('Sigra.reportPlacePlaceholder')) ?>" maxlength="150" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold"><?= esc(lang('Sigra.reportStrength')) ?></label>
                    <input type="number" step="0.1" min="0" max="10" name="magnitud" class="form-control" value="<?= esc($datos['magnitud'] ?? '') ?>" placeholder="0 a 10" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold"><?= esc(lang('Sigra.reportWhen')) ?></label>
                    <input type="datetime-local" name="fecha_hora" class="form-control" value="<?= esc($datos['fecha_hora'] ?? date('Y-m-d\TH:i')) ?>" required>
                </div>
            </div>



            <button type="submit" class="btn btn-report w-100 mt-2"><?= esc(lang('Sigra.reportSubmit')) ?></button>
            <a href="<?= site_url('sismos') ?>" class="btn btn-link text-secondary w-100 mt-2"><?= esc(lang('Sigra.reportBack')) ?></a>
        </form>
    </section>

    <section class="report-map-panel">
        <div class="p-4 border-bottom">
            <p class="text-uppercase small fw-bold text-secondary mb-1"><?= esc(lang('Sigra.reportPointKicker')) ?></p>
            <h2 class="h5 mb-1"><?= esc(lang('Sigra.reportPointTitle')) ?></h2>
            <p class="small text-secondary mb-0"><?= esc(lang('Sigra.reportPointHint')) ?></p>
        </div>
        <div id="mapa-elegir"></div>
    </section>
</div>

<script src="<?= sigra_public_url('assets/js/mapa-sismos.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        inicializarMapaSeleccion('mapa-elegir', 'latitud', 'longitud');
    });
</script>

<?php
$contenido = ob_get_clean();
$titulo    = lang('Sigra.navReport');
echo view('layouts/main', ['contenido' => $contenido, 'titulo' => $titulo]);
