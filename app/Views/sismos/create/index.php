<?php

/**
 * sismos/create/index.php
 * -------------------------------------------------------------------
 * Punto de entrada de la operación CREAR.
 *
 *   1. comprobar sesión
 *   2. comprobar permiso
 *   3. si no -> deny.php
 *   4. si es POST -> validator.php (que decide entre form.php o procesor.php)
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

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    return require __DIR__ . '/validator.php';
}

return require __DIR__ . '/form.php';
