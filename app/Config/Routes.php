<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', static fn () => redirect()->to('/sismos'));
$routes->get('language/(:segment)', 'Language::change/$1');

/**
 * Rutas del módulo Sismos.
 * El controlador Sismos.php SOLO enruta: cada método decide qué
 * archivo de vista ejecutar (ver app/Controllers/Sismos.php).
 */
$routes->get('sismos', 'Sismos::list');
$routes->get('sismos/sync', 'Sismos::sync');
$routes->get('api/sismos/recent', 'Sismos::apiRecent');
$routes->get('api/sismos/ultimo', 'Sismos::apiUltimo');
$routes->get('api/sismos/alertas', 'Sismos::apiAlertas');
$routes->get('api/sismos/detalle/(:segment)', 'Sismos::apiDetalle/$1');
$routes->get('api/health', 'Sismos::apiHealth');

/**
 * Red SIGRA de teléfonos (app Android y panel público).
 */
$routes->get('red', 'Red::index');
$routes->get('api/red/config', 'ApiRed::config');
$routes->get('api/red/red', 'ApiRed::red');
$routes->get('api/red/health', 'ApiRed::health');
$routes->post('api/red/registro', 'ApiRed::registro');
$routes->post('api/red/estado', 'ApiRed::estado');
$routes->post('api/red/deteccion', 'ApiRed::deteccion');
$routes->get('api/red/alertas', 'ApiRed::alertas');
$routes->post('api/red/desactivar', 'ApiRed::desactivar');

$routes->get('sismos/globales', 'Sismos::globales');
$routes->get('sismos/historico', 'Sismos::historico');
$routes->get('acerca-de', 'Sismos::acerca');
$routes->get('politica-privacidad', 'Sismos::privacidad');
$routes->get('politica-cookies', 'Sismos::cookies');
$routes->get('terminos-uso', 'Sismos::terminos');

$routes->get('sismos/create', 'Sismos::create', ['filter' => ['csrf', 'reportguard']]);
$routes->post('sismos/create', 'Sismos::create', ['filter' => ['csrf', 'reportguard']]);

$routes->get('sismos/edit/(:segment)', 'Sismos::edit/$1', ['filter' => 'denyeventwrites']);
$routes->post('sismos/edit/(:segment)', 'Sismos::edit/$1', ['filter' => 'denyeventwrites']);

$routes->get('sismos/delete/(:segment)', 'Sismos::delete/$1', ['filter' => 'denyeventwrites']);
$routes->post('sismos/delete/(:segment)', 'Sismos::delete/$1', ['filter' => 'denyeventwrites']);

$routes->get('sismos/detalle/(:segment)', 'Sismos::detalle/$1');
