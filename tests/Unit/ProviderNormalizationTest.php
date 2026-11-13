<?php

declare(strict_types=1);
define('FCPATH', dirname(__DIR__, 2) . '/');
define('ENVIRONMENT', 'development');
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
$paths = new Config\Paths();
$paths->envDirectory = sys_get_temp_dir() . '/sigra-provider-tests-no-env-' . bin2hex(random_bytes(8));
CodeIgniter\Boot::bootConsole($paths);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\SeismicIngestion\Infrastructure\Sources\{FeedTransport, SgcClient, UsgsClient};
use App\Shared\Domain\ExternalUrl;

foreach (['SGC' => 'sgc-synthetic-event.json', 'USGS' => 'usgs-public-event.json'] as $source => $file) {
    $fixture = json_decode(file_get_contents(dirname(__DIR__) . '/Contract/fixtures/providers/' . $file), true, 512, JSON_THROW_ON_ERROR);
    $transport = new class($fixture) implements FeedTransport {
        public function __construct(public array $payload) {}
        public function json(string $url, int $timeoutSeconds, array $headers): array { return $this->payload; }
    };
    $config = $source === 'SGC' ? new Config\Sgc() : new Config\Quakes();
    if ($source === 'USGS') { $config->globalEnabled = true; $config->usgsUseSummaryFeed = false; }
    $client = $source === 'SGC' ? new SgcClient($config, $transport) : new UsgsClient($config, $transport);
    $valid = $client->obtenerEventos();
    expectArchitecture(count($valid) === 1 && $valid[0]['fuente'] === $source, 'Valid provider observation rejected');
    if ($source === 'USGS') {
        expectArchitecture($valid[0]['latitud'] === 18.8925 && $valid[0]['longitud'] === 145.6025,
            'USGS GeoJSON longitude/latitude were inverted');
        $colombia = $fixture['features'][0];
        $colombia['geometry']['coordinates'] = [-74.1, 4.6, 10];
        $transport->payload['features'] = [$colombia];
        $local = $client->obtenerEventos()[0];
        expectArchitecture($local['latitud'] === 4.6 && $local['longitud'] === -74.1,
            'USGS Colombian event moved out of Colombia');
        expectArchitecture(json_decode($local['sgc_metadata'], true)['coordinate_order'] === 'lat-lon-v2', 'USGS coordinate version missing');
    }
    $base = $fixture['features'][0];
    foreach ([[], ['geometry' => []], ['properties' => []],
        array_replace_recursive($base, ['geometry' => ['coordinates' => [INF, 0]]]),
        array_replace_recursive($base, ['geometry' => ['coordinates' => [999, 999]]]),
        array_replace_recursive($base, ['properties' => ['mag' => null]]),
        array_replace_recursive($base, ['properties' => ['mag' => '1e309']]),
        array_replace_recursive($base, ['properties' => ['mag' => 11]]),
        array_replace_recursive($base, ['properties' => ['place' => ['invalid']]]),
        array_replace($base, ['id' => "bad\0id"])] as $invalid) {
        $transport->payload['features'] = [$invalid];
        expectArchitecture($client->obtenerEventos() === [], "$source accepted malformed observation");
    }
    if ($source === 'SGC') {
        foreach (['2026-02-31 10:00', '2026-11-12 99:00'] as $date) {
            $transport->payload['features'] = [array_replace_recursive($base, ['properties' => ['localTime' => $date]])];
            expectArchitecture($client->obtenerEventos() === [], 'Impossible SGC local date accepted');
        }
    } else {
        $transport->payload['features'] = [array_replace_recursive($base, ['properties' => ['time' => '1e309']])];
        expectArchitecture($client->obtenerEventos() === [], 'Unbounded USGS timestamp accepted');
    }
    $sourceId = str_repeat('long-id-', 8);
    $transport->payload['features'] = [array_replace($base, ['id' => $sourceId])];
    $first = $client->obtenerEventos()[0];
    $second = $client->obtenerEventos()[0];
    expectArchitecture(strlen($first['id']) <= 24 && $first['id'] === $second['id'], 'Source ID mapping is not bounded/deterministic');
    expectArchitecture(json_decode($first['sgc_metadata'], true)['source_id'] === $sourceId, 'Original source ID lost');
}
// A 403 must cause exactly one request, without trying another SGC endpoint.
$refused = new class implements FeedTransport {
    public int $calls = 0;
    public function json(string $url, int $timeoutSeconds, array $headers): array {
        $this->calls++;
        throw new RuntimeException('HTTP 403');
    }
};
try { (new SgcClient(new Config\Sgc(), $refused))->obtenerEventos(); throw new LogicException('Refusal accepted'); }
catch (RuntimeException $error) { expectArchitecture($refused->calls === 1 && $error->getMessage() === 'HTTP 403', 'SGC refusal triggered another request'); }

foreach (['EMSC', 'GEONET'] as $source) {
    $now = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
    $feature = ['id' => 'test-event', 'geometry' => ['coordinates' => [174.0, -40.0, -12.0]],
        'properties' => ['publicID' => 'test-event', 'evtype' => 'ke', 'quality' => 'best', 'time' => $now,
            'mag' => 4.2, 'magnitude' => 4.2, 'depth' => 12.0, 'flynn_region' => 'Test region', 'locality' => 'Test region']];
    $transport = new class implements FeedTransport {
        public array $payload = [];
        public function json(string $url, int $timeoutSeconds, array $headers): array { return $this->payload; }
    };
    $transport->payload = ['features' => [$feature]];
    $client = new App\Modules\SeismicIngestion\Infrastructure\Sources\AdditionalGeoJsonSource($source, new Config\Quakes(), $transport);
    $result = $client->fetch();
    expectArchitecture(count($result) === 1 && $result[0]['fuente'] === $source, 'New source observation rejected');
    expectArchitecture($result[0]['latitud'] === -40.0 && $result[0]['longitud'] === 174.0 && $result[0]['profundidad'] === 12.0, 'Coordinates/depth swapped');
    expectArchitecture(strlen($result[0]['id']) <= 24 && str_starts_with($result[0]['id'], $source . '-'), 'Provider ID collision risk');
    foreach ([['properties' => ['time' => '2026-02-31T00:00:00Z']],
        ['properties' => ['time' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600)]],
        ['properties' => ['time' => gmdate('Y-m-d\TH:i:s\Z', time() - 6 * 86400)]],
        ['properties' => ['mag' => 1, 'magnitude' => 1]],
        ['geometry' => ['coordinates' => [999, 999]]],
        ['properties' => ['depth' => '1e309']],
        ['properties' => ['locality' => [], 'flynn_region' => []]],
        ['properties' => ['evtype' => 'qb', 'quality' => 'deleted']]] as $invalid) {
        $transport->payload = ['features' => [array_replace_recursive($feature, $invalid)]];
        expectArchitecture($client->fetch() === [], 'Invalid/deleted/out-of-window observation accepted');
    }
    $transport->payload = ['not_features' => []];
    try { $client->fetch(); throw new LogicException('Invalid provider payload accepted'); }
    catch (App\Modules\SeismicIngestion\Domain\Contracts\SourceUnavailable) {}
}
foreach (['GEOFON', 'INGV'] as $source) {
    $fixture = json_decode(file_get_contents(dirname(__DIR__) . '/Contract/fixtures/providers/' . strtolower($source) . '-public-event.json'), true);
    $fixture['features'][0]['properties']['time'] = gmdate('Y-m-d\TH:i:s', time() - 60);
    $transport = new class($fixture) implements FeedTransport {
        public function __construct(public array $payload) {}
        public function json(string $url, int $timeoutSeconds, array $headers): array { return $this->payload; }
    };
    $client = new App\Modules\SeismicIngestion\Infrastructure\Sources\AdditionalGeoJsonSource($source, new Config\Quakes(), $transport);
    $events = $client->fetch();
    $c = $fixture['features'][0]['geometry']['coordinates'];
    expectArchitecture(count($events) === 1 && $events[0]['fuente'] === $source, 'New official source rejected');
    expectArchitecture($events[0]['latitud'] === $c[1] && $events[0]['longitud'] === $c[0]
        && $events[0]['profundidad'] === (float) $c[2], 'New provider coordinates/depth inverted');
    expectArchitecture(strlen($events[0]['id']) <= 24 && str_starts_with($events[0]['id'], $source . '-'), 'Source ID not isolated');
    $transport->payload['features'][0]['geometry']['coordinates'] = [999, 999, 10];
    expectArchitecture($client->fetch() === [], 'Invalid provider coordinates accepted');
    $transport->payload = $fixture;
    $transport->payload['features'][0]['properties']['time'] = '2026-02-31T00:00:00';
    expectArchitecture($client->fetch() === [], 'Impossible UTC date accepted');
    if ($source === 'INGV') {
        $transport->payload = $fixture;
        $transport->payload['features'][0]['properties']['type'] = 'explosion';
        expectArchitecture($client->fetch() === [], 'INGV explosion shown as earthquake');
    }
}

$usgsFixture = json_decode(file_get_contents(dirname(__DIR__) . '/Contract/fixtures/providers/usgs-public-event.json'), true);
$base = $usgsFixture['features'][0];
$recent = array_replace_recursive($base, ['properties' => ['time' => (time() - 60) * 1000]]);
$old = array_replace_recursive($base, ['properties' => ['time' => (time() - 6 * 86400) * 1000]]);
$future = array_replace_recursive($base, ['properties' => ['time' => (time() + 3600) * 1000]]);
$small = array_replace_recursive($recent, ['properties' => ['mag' => 2.0]]);
$summaryTransport = new class([$old, $future, $small, $recent]) implements FeedTransport {
    public string $url = '';
    public function __construct(private array $features) {}
    public function json(string $url, int $timeoutSeconds, array $headers): array {
        $this->url = $url;
        return ['features' => $this->features];
    }
};
$q = new Config\Quakes(); $q->globalEnabled = true; $q->usgsUseSummaryFeed = true;
$summary = (new UsgsClient($q, $summaryTransport))->obtenerEventos();
expectArchitecture(count($summary) === 1 && str_ends_with($summaryTransport->url, '/2.5_week.geojson'), 'USGS summary feed window/magnitude filtering failed');
$q->windowDays = 8;
(new UsgsClient($q, $summaryTransport))->obtenerEventos();
expectArchitecture(str_contains($summaryTransport->url, '/fdsnws/event/1/query?'), 'Custom USGS window lost FDSN support');
expectArchitecture(\App\Modules\SeismicIngestion\Domain\Services\ProviderEventValidator::scientificFields(90, 180, -2, null), 'Valid positive coordinate boundary rejected');
expectArchitecture(\App\Modules\SeismicIngestion\Domain\Services\ProviderEventValidator::scientificFields(-90, -180, 10, 1000), 'Valid negative coordinate boundary rejected');
foreach (['javascript:alert(1)', 'data:text/html,<script>', 'https://user:password@example.org/', "https://example.org/\nattack", ['url']] as $url) {
    expectArchitecture(ExternalUrl::http($url) === null, 'Unsafe external URL survived');
}
expectArchitecture(ExternalUrl::http('https://example.org/event?id=1') !== null, 'Valid external report URL rejected');
echo "Providers: public/synthetic fixtures, corrupt fields, impossible dates, IDs and safe URLs passed.\n";
