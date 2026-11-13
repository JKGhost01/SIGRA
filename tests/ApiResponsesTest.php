<?php

declare(strict_types=1);

// Controller response contracts with isolated persistence; no MySQL or writes.
define('FCPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
putenv('APP_API_TOKEN=sigra-test-process-only');
putenv('red.appKey=sigra-test-process-only');
putenv('app.baseURL=http://localhost:8080/');
foreach (['APP_API_TOKEN' => 'sigra-test-process-only', 'red.appKey' => 'sigra-test-process-only',
    'app.baseURL' => 'http://localhost:8080/'] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
$paths = new Config\Paths();
$paths->envDirectory = sys_get_temp_dir() . '/sigra-tests-no-dotenv-' . bin2hex(random_bytes(8));
CodeIgniter\Boot::bootConsole($paths);

use App\Controllers\ApiRed;
use App\Controllers\Sismos;
use App\Libraries\ApiInput;
use App\Libraries\RedNetworkService;
use App\Models\SismoModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;

final class ApiFixtureSismos extends SismoModel
{
    public array $received = [];
    public bool $empty = false;
    public function __construct() {}
    public function obtenerRecientesFiltrados(int $horas = self::VENTANA_EVENTOS_HORAS,
        ?int $limite = null, ?float $minMagnitud = null, string $fuente = '',
        ?float $origenLat = null, ?float $origenLng = null, ?float $radioKm = null, string $orden = 'fecha'): array
    {
        $this->received = func_get_args();
        return $this->obtenerRecientes();
    }
    public function obtenerRecientes(int $horas = self::VENTANA_EVENTOS_HORAS): array
    {
        return $this->empty ? [] : [[
            'id' => 'us-test', 'latitud' => 4.6, 'longitud' => -74.1, 'magnitud' => 4.2,
            'profundidad' => 10, 'fuente' => 'USGS', 'ubicacion' => 'Fixture',
            'fecha_hora' => '2026-11-12 10:00:00',
            'sgc_metadata' => json_encode(['source_id' => 'us-test', 'mmi' => 3.5, 'url' => 'https://example.org/event']),
        ]];
    }
    public function obtenerPorId(string $id): ?array
    {
        return $id === 'us-test' ? $this->obtenerRecientes()[0] : null;
    }
}

final class ApiFixtureRed extends \App\Modules\SensorNetwork\Application\SensorNetworkService
{
    public array $received = [];
    public function __construct() {}
    public function autenticar(string $token): ?array { return $token === 'valid-test-device' ? ['id' => 1] : null; }
    public function registrarDispositivo(array $payload, ?string $existingToken = null): array
    {
        $this->received = $payload;
        return ['status' => 'ok', 'device_token' => 'fixture-token', 'device' => $payload];
    }
    public function latir(array $device, array $payload): array { return ['status' => 'ok', 'device' => $device]; }
    public function registrarDeteccion(array $device, array $payload): array { return ['status' => 'ok', 'accepted' => true]; }
    public function alertasCercanas(?array $device, float $lat, float $lng): array { return []; }
    public function desactivarDispositivo(array $device): void {}
}

$fixture = new ApiFixtureSismos();
$repository = new \App\Modules\SeismicCatalog\Infrastructure\Persistence\CiSeismicEventRepository($fixture);
Config\Services::injectMock('listEvents', new \App\Modules\SeismicCatalog\Application\Queries\ListEvents\ListEventsHandler($repository));
Config\Services::injectMock('getEvent', new \App\Modules\SeismicCatalog\Application\Queries\GetEvent\GetEventHandler($repository));
$sismos = new Sismos();
$redFixture = new ApiFixtureRed();
$red = new class($redFixture) extends ApiRed {
    public function __construct(private \App\Modules\SensorNetwork\Application\SensorNetworkService $fixture) {}
    protected function servicioRed(): \App\Modules\SensorNetwork\Application\SensorNetworkService { return $this->fixture; }
};

$checks = 0;
function verify(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function callApi($controller, string $action, array $query = [], string $body = '{}',
    array $args = [], string $device = ''): array
{
    $app = config(Config\App::class);
    $request = new IncomingRequest($app, new URI('http://localhost:8080/'), $body, new UserAgent());
    $request->setGlobal('get', $query);
    $request->setHeader('Authorization', 'bearer sigra-test-process-only');
    $request->setHeader('Content-Type', 'application/json');
    if ($device !== '') $request->setHeader('X-Device-Token', $device);
    $controller->initController($request, new Response($app), service('logger'));
    $response = $controller->$action(...$args);
    verify(str_contains($response->getHeaderLine('Cache-Control'), 'no-store'), 'Missing no-store');
    verify($response->getHeaderLine('X-SIGRA-Version') === config(Config\Sigra::class)->version, 'Missing app version');
    verify($response->getHeaderLine('X-API-Version') === 'v1', 'API contract version changed');
    return [$response->getStatusCode(), json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)];
}

[$status, $body] = callApi($sismos, 'apiRecent', [
    'hours' => '120', 'limit' => '10', 'min_magnitude' => '3', 'fuente' => 'USGS',
    'lat' => '4.6', 'lng' => '-74.1', 'radius_km' => '500', 'order' => 'distancia',
]);
verify($status === 200 && ($body['count'] ?? null) === 1, 'Valid recent request failed: ' . $status . ' ' . json_encode($body));
verify($fixture->received === [120, 10, 3.0, 'USGS', 4.6, -74.1, 500.0, 'distancia'], 'Filters were not forwarded');
verify($body['events'][0]['distancia_km'] == 0 && $body['events'][0]['fuente'] === 'USGS', 'Event serialization failed');
verify($body['events'][0]['mapa_sgc_url'] === null && $body['events'][0]['mmi'] === 3.5, 'Source metadata failed');
verify($body['origin']['radius_km'] == 500, 'Origin missing');
verify($body['events'][0]['citizen_reports_count'] === 0, 'Private report counter must default to zero');
[$status, $body] = callApi($sismos, 'apiDetalle', args: ['us-test']);
verify($status === 200 && $body['event']['id'] === 'us-test', 'Detail failed');
[$status] = callApi($sismos, 'apiDetalle', args: ['missing']);
verify($status === 404, 'Missing detail must be 404');
[$status, $body] = callApi($sismos, 'apiUltimo');
verify($status === 200 && $body['event']['id'] === 'us-test', 'Latest failed');
[$status, $body] = callApi($sismos, 'apiAlertas');
verify($status === 200 && $body['alerta'] === 'amarilla', 'Alert contract changed');
$fixture->empty = true;
[$status, $body] = callApi($sismos, 'apiUltimo');
verify($status === 200 && $body['event'] === null, 'Empty latest failed');
[$status, $body] = callApi($sismos, 'apiRecent');
verify($status === 200 && $body['events'] === [] && $body['count'] === 0, 'Empty list failed');

[$status] = callApi($red, 'registro', body: '{"device_uuid":"test-device","monitoring_enabled":"false","notifications_enabled":true}');
verify($status === 200 && $redFixture->received['monitoring_enabled'] === false, 'Boolean normalization failed');
foreach (['estado', 'deteccion', 'desactivar'] as $action) {
    [$status] = callApi($red, $action, device: 'valid-test-device');
    verify($status === 200, "Device action failed: {$action}");
}
[$status, $body] = callApi($red, 'alertas', ['lat' => '0', 'lng' => '0']);
verify($status === 200 && $body['alerts'] === [], 'Anonymous app-key alerts failed');
[$status] = callApi($red, 'alertas', ['lat' => '0', 'lng' => '0'], device: 'invalid-test-device');
verify($status === 401, 'An invalid optional device token must not become anonymous');
[$status] = callApi($red, 'alertas', ['lat' => '0', 'lng' => '0'], device: 'valid-test-device');
verify($status === 200, 'Valid device alerts failed');
echo "{$checks} isolated controller response checks passed (no live database).\n";
