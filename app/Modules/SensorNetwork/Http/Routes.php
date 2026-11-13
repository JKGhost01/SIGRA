<?php

$routes->get('red', '\\App\\Modules\\SensorNetwork\\Http\\Controllers\\Web\\SensorNetworkController::index');
$controller = '\App\Modules\SensorNetwork\Http\Controllers\Api\V1\SensorNetworkController';
foreach (['api/red', 'api/v1/red'] as $prefix) {
    foreach (['config', 'red', 'health'] as $action) $routes->get($prefix . '/' . $action, $controller . '::' . $action);
    $routes->get($prefix . '/alertas', $controller . '::alertas', ['filter' => 'apiauth:network']);
    foreach (['registro', 'estado', 'deteccion', 'desactivar'] as $action) {
        $routes->post($prefix . '/' . $action, $controller . '::' . $action, ['filter' => 'apiauth:network']);
    }
}
