<?php

/**
 * sismos/edit/form.php
 * -------------------------------------------------------------------
 * Interfaz de edición. Recibe $datos (sismo actual, o valores enviados
 * si validator.php rechazó el POST) y $errores (opcional).
 */

$errores ??= [];
$datos   ??= [];

$fechaInput = '';
if (!empty($datos['fecha_hora'])) {
    $ts         = strtotime($datos['fecha_hora']);
    $fechaInput = $ts ? date('Y-m-d\TH:i', $ts) : '';
}

ob_start();
?>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-3"><?= esc(lang('Sigra.editTitle')) ?> <code><?= esc($datos['id'] ?? '') ?></code></h4>

                <?php if (!empty($errores)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errores as $err): ?>
                                <li><?= esc($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= site_url('sismos/edit/' . ($datos['id'] ?? '')) ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label"><?= esc(lang('Sigra.editMagnitude')) ?></label>
                        <input type="number" step="0.1" min="0" max="10" name="magnitud" class="form-control"
                               value="<?= esc($datos['magnitud'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= esc(lang('Sigra.editDepth')) ?></label>
                        <input type="number" step="0.1" min="0" name="profundidad" class="form-control"
                               value="<?= esc($datos['profundidad'] ?? '') ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= esc(lang('Sigra.reportLat')) ?></label>
                            <input type="text" name="latitud" id="latitud" class="form-control"
                                   value="<?= esc($datos['latitud'] ?? '') ?>" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= esc(lang('Sigra.reportLng')) ?></label>
                            <input type="text" name="longitud" id="longitud" class="form-control"
                                   value="<?= esc($datos['longitud'] ?? '') ?>" required>
                        </div>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="btn-usar-mapa">
                        <?= sigra_icon('pin') ?> <?= esc(lang('Sigra.editAdjustMap')) ?>
                    </button>

                    <div class="mb-3">
                        <label class="form-label"><?= esc(lang('Sigra.editLocation')) ?></label>
                        <input type="text" name="ubicacion" class="form-control"
                               value="<?= esc($datos['ubicacion'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= esc(lang('Sigra.editDate')) ?></label>
                        <input type="datetime-local" name="fecha_hora" class="form-control"
                               value="<?= esc($fechaInput) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= esc(lang('Sigra.editSource')) ?></label>
                        <input type="text" name="fuente" class="form-control"
                               value="<?= esc($datos['fuente'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= esc(lang('Sigra.editStatus')) ?></label>
                        <select name="estado" class="form-select">
                            <?php foreach (['activo' => 'statusActivo', 'revisado' => 'statusRevisado', 'pendiente' => 'statusPendiente'] as $op => $clave): ?>
                                <option value="<?= $op ?>" <?= (($datos['estado'] ?? '') === $op) ? 'selected' : '' ?>>
                                    <?= esc(lang('Sigra.' . $clave)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary"><?= esc(lang('Sigra.editSave')) ?></button>
                    <a href="<?= site_url('sismos') ?>" class="btn btn-outline-secondary"><?= esc(lang('Sigra.editCancel')) ?></a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <p class="text-muted small mb-2"><?= esc(lang('Sigra.editMapHint')) ?></p>
                <div id="mapa-elegir" style="height:420px;border-radius:.5rem;"></div>
            </div>
        </div>
    </div>
</div>

<script src="/public/assets/js/mapa-sismos.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        inicializarMapaSeleccion('mapa-elegir', 'latitud', 'longitud');
    });
</script>

<?php
$contenido = ob_get_clean();
$titulo    = lang('Sigra.editPageTitle');
return view('layouts/main', ['contenido' => $contenido, 'titulo' => $titulo]);
