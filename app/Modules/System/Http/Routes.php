<?php

$routes->get('api/health', '\App\Modules\System\Http\Controllers\HealthController::index');
$routes->get('api/v1/health', '\App\Modules\System\Http\Controllers\HealthController::index');
$pages = '\App\Modules\System\Http\Controllers\PagesController';
foreach (['app-movil' => 'mobile', 'acerca-de' => 'about', 'politica-privacidad' => 'privacy', 'tratamiento-datos' => 'processing', 'politica-cookies' => 'cookies', 'terminos-uso' => 'terms'] as $path => $action) {
    $routes->get($path, $pages . '::' . $action);
}
