<?php

/**
 * sismos/create/deny.php
 * Salida terminal cuando no hay sesión/permiso para registrar un sismo.
 */
ob_start();
?>
<div class="alert alert-danger">
    <strong><?= esc(lang('Sigra.denyListTitle')) ?></strong> <?= esc(lang('Sigra.denyCreateText')) ?>
</div>
<?php
$contenido = ob_get_clean();
return view('layouts/main', ['contenido' => $contenido, 'titulo' => lang('Sigra.denyAccessTitle')]);
