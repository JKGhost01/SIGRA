<?php

/**
 * sismos/detail/deny.php
 * Salida terminal: sin permiso, sin ID o sismo inexistente.
 */
ob_start();
?>
<div class="alert alert-danger">
    <strong>No disponible.</strong> No tienes permiso, o el sismo indicado no existe.
</div>
<a href="<?= site_url('sismos') ?>" class="btn btn-outline-secondary btn-sm">Volver al listado</a>
<?php
$contenido = ob_get_clean();
return view('layouts/main', ['contenido' => $contenido, 'titulo' => 'No disponible']);
