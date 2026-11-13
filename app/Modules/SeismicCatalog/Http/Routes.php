<?php

use App\Modules\SeismicCatalog\Http\Controllers\Web\SeismicCatalogController;
use App\Modules\SeismicCatalog\Http\Controllers\Api\V1\SeismicEventsController;

$web = '\\' . SeismicCatalogController::class;
$api = '\\' . SeismicEventsController::class;
$routes->get('sismos', $web . '::index');
$routes->get('sismos/globales', $web . '::globales');
$routes->get('sismos/historico', $web . '::history');
$routes->get('sismos/detalle/(:segment)', $web . '::detail/$1');
foreach (['edit', 'delete'] as $operation) {
    $routes->match(['GET', 'POST'], 'sismos/' . $operation . '/(:segment)', $web . '::denied', ['filter' => 'denyeventwrites']);
}
foreach (['api/sismos', 'api/v1/sismos'] as $prefix) {
    foreach (['recent' => 'recent', 'ultimo' => 'latest', 'alertas' => 'alerts', 'detalle/(:segment)' => 'detail/$1'] as $path => $action) {
        $routes->get($prefix . '/' . $path, $api . '::' . $action, ['filter' => 'apiauth:catalog']);
    }
}

$routes->get('sismos/sync', '\\App\\Modules\\SeismicCatalog\\Http\\Controllers\\Web\\CatalogPollingController::index');
