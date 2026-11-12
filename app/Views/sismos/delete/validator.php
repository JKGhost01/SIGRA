<?php

/**
 * sismos/delete/validator.php
 * -------------------------------------------------------------------
 * Valida que la eliminación venga confirmada explícitamente.
 * $sismo y $datos ya disponibles desde index.php.
 */

$datos = $sismo;

$errores = [];

if (empty($_POST['confirmar'])) {
    $errores[] = lang('Sigra.formConfirmDelete');
}

if (!empty($errores)) {
    return require __DIR__ . '/form.php';
}

return require __DIR__ . '/procesor.php';
