<?php

/**
 * sismos/edit/index.php
 * -------------------------------------------------------------------
 *   1. sesión + permiso
 *   2. obtener ID desde la URL (llega como $id, pasado por el controlador)
 *   3. Model -> buscar sismo
 *   4. si no existe -> deny.php (reutilizado como mensaje de error simple)
 *   5. si es POST -> validator.php
 *      si es GET  -> form.php
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

// GET: precargar los datos actuales del sismo en el formulario
$datos = $sismo;

return require __DIR__ . '/form.php';
