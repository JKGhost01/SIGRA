<?php

/**
 * sismos/list/deny.php
 * Salida terminal cuando no hay sesión válida o permiso para listar.
 */
ob_start();
?>
<div class="alert alert-danger">
    <strong><?= esc(lang('Sigra.denyListTitle')) ?></strong> <?= esc(lang('Sigra.denyListText')) ?>
</div>
<?php
$contenido = ob_get_clean();
return view('layouts/main', ['contenido' => $contenido, 'titulo' => lang('Sigra.denyAccessTitle')]);
