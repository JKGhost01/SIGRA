<?php

/**
 * sismos/edit/validator.php
 * -------------------------------------------------------------------
 * Valida el POST de edición. $sismo (registro original) viene del
 * index.php. Mismas reglas que en create, pero conservando el ID.
 */

$datos = [
    'id'          => $sismo['id'],
    'magnitud'    => trim($_POST['magnitud'] ?? ''),
    'profundidad' => trim($_POST['profundidad'] ?? ''),
    'latitud'     => trim($_POST['latitud'] ?? ''),
    'longitud'    => trim($_POST['longitud'] ?? ''),
    'ubicacion'   => trim($_POST['ubicacion'] ?? ''),
    'fecha_hora'  => trim($_POST['fecha_hora'] ?? ''),
    'fuente'      => trim($_POST['fuente'] ?? ''),
    'estado'      => trim($_POST['estado'] ?? 'pendiente'),
];

$errores = [];

foreach (['magnitud', 'profundidad', 'latitud', 'longitud', 'ubicacion', 'fecha_hora', 'fuente'] as $campo) {
    if ($datos[$campo] === '') {
        $errores[] = lang('Sigra.formRequired', [$campo]);
    }
}

if ($datos['magnitud'] !== '') {
    if (!is_numeric($datos['magnitud'])) {
        $errores[] = lang('Sigra.formMagnitudeNumber');
    } elseif ($datos['magnitud'] < 0 || $datos['magnitud'] > 10) {
        $errores[] = lang('Sigra.formMagnitudeRange');
    }
}

if ($datos['profundidad'] !== '' && (!is_numeric($datos['profundidad']) || $datos['profundidad'] < 0)) {
    $errores[] = lang('Sigra.formDepthInvalid');
}

if ($datos['latitud'] !== '' && (!is_numeric($datos['latitud']) || $datos['latitud'] < -90 || $datos['latitud'] > 90)) {
    $errores[] = lang('Sigra.formLatInvalid');
}
if ($datos['longitud'] !== '' && (!is_numeric($datos['longitud']) || $datos['longitud'] < -180 || $datos['longitud'] > 180)) {
    $errores[] = lang('Sigra.formLngInvalid');
}

if ($datos['fecha_hora'] !== '' && strtotime($datos['fecha_hora']) === false) {
    $errores[] = lang('Sigra.formDateInvalid');
}

if (!in_array($datos['estado'], ['activo', 'revisado', 'pendiente'], true)) {
    $datos['estado'] = 'pendiente';
}

if (!empty($errores)) {
    return require __DIR__ . '/form.php';
}

return require __DIR__ . '/procesor.php';
