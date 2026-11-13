<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', static fn () => redirect()->to('/sismos'));
$routes->get('language/(:segment)', 'Language::change/$1');
foreach (['SeismicCatalog', 'CitizenReports', 'SensorNetwork', 'System'] as $module) {
    require APPPATH . 'Modules/' . $module . '/Http/Routes.php';
}
