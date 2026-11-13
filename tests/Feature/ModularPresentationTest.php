<?php

declare(strict_types=1);
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
$_ENV['app.baseURL'] = $_SERVER['app.baseURL'] = 'http://localhost:8080/';
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
$paths = new Config\Paths();
$paths->envDirectory = sys_get_temp_dir() . '/sigra-tests-no-dotenv-' . bin2hex(random_bytes(8));
CodeIgniter\Boot::bootConsole($paths);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\SeismicCatalog\Application\Contracts\SeismicEventReader;
use App\Modules\SeismicCatalog\Application\DTO\SeismicEventDTO;
use App\Modules\SeismicCatalog\Application\Queries\ListEvents\{ListEventsHandler, ListEventsQuery};
use App\Modules\SeismicCatalog\Application\Queries\GetEvent\GetEventHandler;
use App\Modules\SeismicCatalog\Http\Controllers\Web\SeismicCatalogController;
use App\Modules\SeismicCatalog\Http\Resources\SeismicEventResource;
use CodeIgniter\HTTP\{IncomingRequest, Response, URI, UserAgent};

$fixture = json_decode(file_get_contents(dirname(__DIR__) . '/Contract/fixtures/legacy-event.json'), true, 512, JSON_THROW_ON_ERROR);
expectArchitecture(json_decode(json_encode((new SeismicEventResource())->serialize(new SeismicEventDTO($fixture['input']), 4.6, -74.1)), true) === $fixture['expected'], 'Original event JSON changed');
$reader = new class($fixture['input']) implements SeismicEventReader {
    public function __construct(public array $event) {}
    public function list(ListEventsQuery $query): array { return $this->recent(120); }
    public function recent(int $hours): array { return [new SeismicEventDTO($this->event)]; }
    public function get(string $id): ?SeismicEventDTO { return $id === $this->event['id'] ? new SeismicEventDTO($this->event) : null; }
    public function history(array $filters, string $order, string $direction): array { return $this->recent(120); }
};
Config\Services::injectMock('listEvents', new ListEventsHandler($reader));
Config\Services::injectMock('getEvent', new GetEventHandler($reader));
Config\Services::injectMock('listHistory', new App\Modules\SeismicCatalog\Application\Queries\ListHistory\ListHistoryHandler($reader));
$request = new IncomingRequest(config(Config\App::class), new URI('http://localhost:8080/'), '', new UserAgent());
$request->setGlobal('get', []);
$request->setLocale('es');
$controller = new SeismicCatalogController();
$controller->initController($request, new Response(config(Config\App::class)), service('logger'));
$html = $controller->index();
expectArchitecture(str_contains($html, 'id="mapa"') && str_contains($html, 'SGC-golden'), 'Map disappeared while resolving namespaced views');
expectArchitecture(str_contains($html, 'modules/seismic-map/catalog.js') && str_contains($html, 'const SIGRA_MAP_TEXT'), 'Map configuration or asset missing');
expectArchitecture(str_contains($controller->detail('SGC-golden'), 'SGC-golden'), 'Detail template did not render');
expectArchitecture(str_contains($controller->history(), 'SGC-golden') === false && str_contains($controller->history(), 'Fixture'), 'History did not render prepared records');
try { $controller->detail('missing'); throw new RuntimeException('Missing event rendered'); }
catch (CodeIgniter\Exceptions\PageNotFoundException) {}
$reader->event['ubicacion'] = '</script><script>alert(1)</script>';
$html = $controller->index();
expectArchitecture(!str_contains($html, '<script>alert(1)</script>'), 'Initial event JSON allows closing a script tag');
Config\Services::injectMock('catalogRefresh', new class {
    public function handle(): App\Modules\SeismicIngestion\Application\DTO\CatalogRefreshResult
    { return new App\Modules\SeismicIngestion\Application\DTO\CatalogRefreshResult(true, 1800000000, 1, ['SGC' => 1]); }
});
$sync = new App\Modules\SeismicCatalog\Http\Controllers\Web\CatalogPollingController();
$sync->initController($request, new Response(config(Config\App::class)), service('logger'));
$poll = json_decode($sync->index()->getBody(), true);
expectArchitecture($poll['ok'] && $poll['synced'] && $poll['saved'] === 1 && $poll['eventos'][0]['estado'] === 'automatico', 'Automatic polling compatibility failed');
Config\Services::injectMock('catalogRefresh', new class {
    public function handle(): never { throw new RuntimeException('private provider detail'); }
});
$poll = json_decode($sync->index()->getBody(), true);
expectArchitecture($poll['ok'] && !$poll['synced'] && count($poll['eventos']) === 1 && isset($poll['errors']['sync']), 'Refresh failure removed stored map events');
expectArchitecture(!str_contains(json_encode($poll), 'private provider detail'), 'Refresh failure leaked internals');
foreach (['about', 'privacy', 'cookies', 'terms'] as $method) {
    $pages = new App\Modules\System\Http\Controllers\PagesController();
    $pages->initController($request, new Response(config(Config\App::class)), service('logger'));
    expectArchitecture(strlen($pages->$method()) > 1000, 'Static page did not render');
}
echo "Modular views, XSS escaping, automatic polling with stale-data fallback and original JSON fixture passed.\n";
