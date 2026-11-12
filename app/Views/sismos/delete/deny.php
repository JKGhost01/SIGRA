<?php

/**
 * sismos/delete/deny.php
 * Salida terminal: sin permiso, sin ID o sismo inexistente.
 */
ob_start();
?>
<div class="alert alert-danger">
    <strong><?= esc(lang('Sigra.denyDeleteTitle')) ?></strong> <?= esc(lang('Sigra.denyDeleteText')) ?>
</div>
<a href="<?= site_url('sismos') ?>" class="btn btn-outline-secondary btn-sm"><?= esc(lang('Sigra.denyBackList')) ?></a>
<?php
$contenido = ob_get_clean();
return view('layouts/main', ['contenido' => $contenido, 'titulo' => lang('Sigra.denyAccessTitle')]);
