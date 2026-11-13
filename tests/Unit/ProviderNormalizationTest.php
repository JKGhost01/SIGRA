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
    if ($source === 'USGS') $config->globalEnabled = true;
    $client = $source === 'SGC' ? new SgcClient($config, $transport) : new UsgsClient($config, $transport);
    $valid = $client->obtenerEventos();
    expectArchitecture(count($valid) === 1 && $valid[0]['fuente'] === $source, 'Valid provider observation rejected');
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
expectArchitecture(\App\Modules\SeismicIngestion\Domain\Services\ProviderEventValidator::scientificFields(90, 180, -2, null), 'Valid positive coordinate boundary rejected');
expectArchitecture(\App\Modules\SeismicIngestion\Domain\Services\ProviderEventValidator::scientificFields(-90, -180, 10, 1000), 'Valid negative coordinate boundary rejected');
foreach (['javascript:alert(1)', 'data:text/html,<script>', 'https://user:password@example.org/', "https://example.org/\nattack", ['url']] as $url) {
    expectArchitecture(ExternalUrl::http($url) === null, 'Unsafe external URL survived');
}
expectArchitecture(ExternalUrl::http('https://example.org/event?id=1') !== null, 'Valid external report URL rejected');
echo "Providers: public/synthetic fixtures, corrupt fields, impossible dates, IDs and safe URLs passed.\n";
