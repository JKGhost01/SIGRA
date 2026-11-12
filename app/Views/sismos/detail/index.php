<?php

/**
 * sismos/detail/index.php
 * -------------------------------------------------------------------
 * Ficha de un sismo + mapa centrado en el epicentro.
 *   1. sesión + permiso
 *   2. obtener ID
 *   3. Model -> buscar sismo
 *   4. si no existe -> deny.php
 *   5. render de la ficha (form.php se usa como "vista de presentación",
 *      ya que no hay formulario editable en esta operación de solo lectura)
 */

$sesionValida = true;
if (!$sesionValida) {
    return require __DIR__ . '/deny.php';
}

$tienePermiso = true;
if (!$tienePermiso) {
    return require __DIR__ . '/deny.php';
}

$id ??= null;

if (empty($id)) {
    return require __DIR__ . '/deny.php';
}

$modeloSismos = new \App\Models\SismoModel();
$sismo        = $modeloSismos->obtenerPorId($id);

if ($sismo === null) {
    return require __DIR__ . '/deny.php';
}

$datos = $sismo;

return require __DIR__ . '/form.php';
