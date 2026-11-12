<?php

/**
 * sismos/delete/form.php
 * -------------------------------------------------------------------
 * Pantalla de confirmación antes de eliminar. $datos = sismo actual.
 */

$errores ??= [];

ob_start();
?>

<div class="card mx-auto" style="max-width:560px;">
    <div class="card-body">
        <h4 class="mb-3 text-danger"><?= esc(lang('Sigra.deleteTitle')) ?></h4>

        <?php if (!empty($errores)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errores as $err): ?>
                    <div><?= esc($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p><?= esc(lang('Sigra.deleteConfirm')) ?></p>

        <ul class="list-group mb-3">
            <li class="list-group-item"><strong>ID:</strong> <code><?= esc($datos['id']) ?></code></li>
            <li class="list-group-item"><strong><?= esc(lang('Sigra.magnitude')) ?>:</strong> <?= esc($datos['magnitud']) ?></li>
            <li class="list-group-item"><strong><?= esc(lang('Sigra.location')) ?>:</strong> <?= esc($datos['ubicacion']) ?></li>
            <li class="list-group-item"><strong><?= esc(lang('Sigra.dateTime')) ?>:</strong> <?= esc($datos['fecha_hora']) ?></li>
        </ul>

        <form method="POST" action="<?= site_url('sismos/delete/' . $datos['id']) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="confirmar" value="1">
            <button type="submit" class="btn btn-danger"><?= esc(lang('Sigra.deleteYes')) ?></button>
            <a href="<?= site_url('sismos') ?>" class="btn btn-outline-secondary"><?= esc(lang('Sigra.deleteCancel')) ?></a>
        </form>
    </div>
</div>

<?php
$contenido = ob_get_clean();
$titulo    = lang('Sigra.deletePageTitle');
return view('layouts/main', ['contenido' => $contenido, 'titulo' => $titulo]);
