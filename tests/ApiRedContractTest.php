<?php

declare(strict_types=1);

/**
 * Contrato mínimo de la API de la red SIGRA.
 *
 * Ejecutar con:
 *   php tests/ApiRedContractTest.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

use App\Controllers\ApiRed;
use App\Libraries\PwaveFusion;
use App\Libraries\RedNetworkService;

$fallos = 0;
$comprobar = static function (string $nombre, bool $ok) use (&$fallos): void {
    if ($ok) {
        echo "OK  {$nombre}\n";
        return;
    }
    $fallos++;
    echo "FALLO {$nombre}\n";
};

$metodos = ['config', 'red', 'health', 'registro', 'estado', 'deteccion', 'alertas', 'desactivar'];
foreach ($metodos as $metodo) {
    $comprobar("ApiRed::{$metodo}", method_exists(ApiRed::class, $metodo));
}

$comprobar('PwaveFusion::estimarOrigen', method_exists(PwaveFusion::class, 'estimarOrigen'));
$comprobar('PwaveFusion::agrupar', method_exists(PwaveFusion::class, 'agrupar'));
$comprobar('RedNetworkService::registrarDeteccion', method_exists(RedNetworkService::class, 'registrarDeteccion'));
$comprobar('RedNetworkService::alertasCercanas', method_exists(RedNetworkService::class, 'alertasCercanas'));

echo $fallos === 0 ? "\nContrato de la API Red correcto.\n" : "\n{$fallos} comprobación(es) fallaron.\n";
exit($fallos === 0 ? 0 : 1);
