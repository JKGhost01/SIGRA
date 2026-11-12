<?php

declare(strict_types=1);

/**
 * Pruebas deterministas de la fusión TDOA de ondas P.
 *
 * Ejecutar con:
 *   php tests/PwaveFusionTest.php
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

use App\Libraries\PwaveFusion;

$fallos = 0;
$fusion = new PwaveFusion(6.0, 3.5);

function comprobar(string $nombre, bool $condicion, string $detalle = ''): void
{
    global $fallos;

    if ($condicion) {
        echo "OK  {$nombre}\n";
        return;
    }

    $fallos++;
    echo "FALLO {$nombre}" . ($detalle !== '' ? " ({$detalle})" : '') . "\n";
}

$epicentro = ['lat' => 4.60, 'lng' => -74.10];
$estaciones = [
    ['device_uuid' => 'a', 'lat' => 4.65, 'lng' => -74.10, 'peak' => 0.085, 'conf' => 0.82],
    ['device_uuid' => 'b', 'lat' => 4.60, 'lng' => -74.00, 'peak' => 0.048, 'conf' => 0.76],
    ['device_uuid' => 'c', 'lat' => 4.55, 'lng' => -74.05, 'peak' => 0.060, 'conf' => 0.71],
    ['device_uuid' => 'd', 'lat' => 4.52, 'lng' => -74.18, 'peak' => 0.040, 'conf' => 0.68],
];

$origenMs = 1_800_000_000_000;
$detecciones = [];
foreach ($estaciones as $estacion) {
    $distancia = PwaveFusion::distanciaKm($epicentro['lat'], $epicentro['lng'], $estacion['lat'], $estacion['lng']);
    $detecciones[] = [
        'device_uuid' => $estacion['device_uuid'],
        'lat' => $estacion['lat'],
        'lng' => $estacion['lng'],
        'detected_at_ms' => (int) round($origenMs + ($distancia / 6.0) * 1000),
        'peak_accel_mps2' => $estacion['peak'],
        'confidence' => $estacion['conf'],
    ];
}

$grupo = $fusion->agrupar($detecciones, $detecciones[0], 25, 80);
comprobar('agrupa las cuatro estaciones', count($grupo) === 4, 'grupo=' . count($grupo));

$origen = $fusion->estimarOrigen($detecciones);
comprobar('estima un origen', $origen !== null);
if ($origen !== null) {
    $errorKm = PwaveFusion::distanciaKm($epicentro['lat'], $epicentro['lng'], $origen['lat'], $origen['lng']);
    comprobar('epicentro con error menor a 15 km', $errorKm < 15.0, 'error=' . round($errorKm, 1) . ' km');
    comprobar('residual temporal menor a 0.5 s', $origen['residual_s'] < 0.5, 'residual=' . $origen['residual_s']);
    comprobar('usa cuatro teléfonos', $origen['device_count'] === 4);

    $magnitud = $fusion->estimarMagnitud($detecciones, $origen);
    comprobar('magnitud estimada entre 3.0 y 5.0', $magnitud >= 3.0 && $magnitud <= 5.0, 'M=' . $magnitud);

    $confianza = $fusion->estimarConfianza($detecciones, $origen);
    comprobar('confianza mayor a 0.6', $confianza > 0.6, 'confianza=' . $confianza);

    $eta = $fusion->segundosHastaOndaS($origenMs, 140.0, $origenMs + 5000);
    comprobar('ETA de onda S positiva a 140 km', $eta > 30.0 && $eta < 45.0, 'eta=' . $eta . ' s');
}

// Una detección anterior al primer arribo no debe mezclarse con el evento.
$anterior = $detecciones[1];
$anterior['detected_at_ms'] -= 10_000;
$grupoMezclado = $fusion->agrupar(array_merge([$anterior], $detecciones), $detecciones[0], 25, 80);
comprobar('descarta detecciones anteriores al primer arribo', ! in_array($anterior, $grupoMezclado, true));

comprobar('radio de alerta M4.0 razonable', $fusion->radioAlertaKm(4.0) >= 250.0 && $fusion->radioAlertaKm(4.0) <= 350.0);
comprobar('dos teléfonos no bastan para triangular', $fusion->estimarOrigen(array_slice($detecciones, 0, 2)) === null);

echo $fallos === 0 ? "\nTodas las pruebas de fusión pasaron.\n" : "\n{$fallos} prueba(s) fallaron.\n";
exit($fallos === 0 ? 0 : 1);
