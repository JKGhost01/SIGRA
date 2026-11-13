<?php

$controller = '\App\Modules\CitizenReports\Http\Controllers\CitizenReportController';
$routes->get('sismos/create', $controller . '::index', ['filter' => ['csrf', 'reportguard']]);
$routes->post('sismos/create', $controller . '::store', ['filter' => ['csrf', 'reportguard']]);
