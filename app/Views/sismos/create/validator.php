<?php

use App\Libraries\CitizenReportService;
use App\Libraries\RedApiException;

$datos = [];
foreach (['latitud', 'longitud', 'magnitud', 'ubicacion', 'fecha_hora'] as $field) {
    $value = service('request')->getPost($field);
    $datos[$field] = is_string($value) ? $value : '';
}
$errores = [];
try {
    (new CitizenReportService())->validate($datos);
} catch (RedApiException $error) {
    service('response')->setStatusCode(422);
    $errores[] = $error->getMessage();
    return require __DIR__ . '/form.php';
}
return require __DIR__ . '/procesor.php';
