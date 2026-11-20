<?php

declare(strict_types=1);

// HTML real con datos sintéticos; no lee .env, conecta bases ni consulta agencias.
$root = dirname(__DIR__, 2) . '/';
define('FCPATH', $root . 'public/');
define('ENVIRONMENT', 'production');
$_ENV['app.baseURL'] = $_SERVER['app.baseURL'] = 'http://127.0.0.1:8080/';
require $root . 'app/Config/Paths.php';
require $root . 'system/Boot.php';
$paths = new Config\Paths();
$paths->envDirectory = sys_get_temp_dir() . '/sigra-cesium-no-env';
CodeIgniter\Boot::bootConsole($paths);
$locale = $argv[1] ?? 'es';
$request = new CodeIgniter\HTTP\IncomingRequest(config(Config\App::class),
    new CodeIgniter\HTTP\SiteURI(config(Config\App::class), 'sismos'), '', new CodeIgniter\HTTP\UserAgent());
$request->setGlobal('get', []);
$request->setLocale($locale);
Config\Services::injectMock('request', $request);
service('language')->setLocale($locale);
$records = [];
foreach ([['SGC-demo', 4.6, -74.1, 4.2, 'SGC'], ['USGS-demo', 18.8925, 145.6025, 5.1, 'USGS'],
    ['EMSC-demo', -16.4, -71.5, 3.5, 'EMSC']] as [$id, $lat, $lng, $magnitude, $source]) {
    $records[] = new App\Modules\SeismicCatalog\Application\DTO\SeismicEventDTO([
        'id' => $id, 'latitud' => $lat, 'longitud' => $lng, 'magnitud' => $magnitude,
        'profundidad' => 12, 'fuente' => $source, 'fecha_hora' => '2026-11-19 07:23:00',
        'ubicacion' => $source === 'SGC' ? 'Colombia · Evento sintético' : 'Evento sintético ' . $source,
        'sgc_metadata' => '{}',
    ]);
}
echo view('App\Modules\SeismicCatalog\Presentation\Views\sismos\list\index', [
    'vm' => App\Modules\SeismicCatalog\Presentation\ViewModels\ListEventsViewModel::fromResult($records),
    'mapSettings' => config(Config\SeismicMap::class),
    'locale' => $locale, 'message' => null, 'mapEngine' => config(Config\SeismicMap::class)->engine,
]);
