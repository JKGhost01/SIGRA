<?php

/**
 * sismos/delete/index.php
 * -------------------------------------------------------------------
 *   1. sesión + permiso
 *   2. obtener ID
 *   3. buscar sismo (para mostrar confirmación con sus datos)
 *   4. GET  -> form.php (pantalla de confirmación)
 *      POST -> validator.php -> procesor.php
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

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    return require __DIR__ . '/validator.php';
}

$datos = $sismo;

return require __DIR__ . '/form.php';
