<?php

declare(strict_types=1);
define('FCPATH', dirname(__DIR__, 2) . '/');
define('ENVIRONMENT', 'development');
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
$paths = new Config\Paths();
$paths->envDirectory = sys_get_temp_dir() . '/sigra-header-tests-no-env-' . bin2hex(random_bytes(8));
CodeIgniter\Boot::bootConsole($paths);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\SeismicIngestion\Infrastructure\Sources\CiFeedTransport;
use CodeIgniter\HTTP\Response;

$directory = sys_get_temp_dir() . '/sigra-header-tests-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$clock = new class implements App\Shared\Domain\Clock {
    public int $timestamp = 1800000000;
    public function now(): DateTimeImmutable { return new DateTimeImmutable('@' . $this->timestamp); }
};
$response = (new Response(config(Config\App::class)))->removeHeader('Cache-Control')->setStatusCode(200)->setBody('{"features":[]}')
    ->setHeader('Cache-Control', 'max-age=600')->addHeader('Cache-Control', 'public')->setHeader('ETag', '"v1"');
// Reproduce the production exception with a real CI response, not an array mock.
try { $response->getHeaderLine('Cache-Control'); throw new LogicException('Fixture lacks duplicate header objects'); }
catch (InvalidArgumentException $error) { expectArchitecture(str_contains($error->getMessage(), 'multiple headers'), 'Unexpected fixture exception'); }
$calls = [];
$http = static function ($url, $timeout, $headers) use (&$calls, &$response) {
    $calls[] = $headers;
    return $response;
};
$newTransport = static fn() => new CiFeedTransport($directory, $clock, $http);
$expectPaused = static function ($url) use ($newTransport): void {
    try { $newTransport()->json($url, 10, []); throw new LogicException('Expected provider pause'); }
    catch (RuntimeException) {}
};
try {
    $url = 'https://multiple.example/feed';
    expectArchitecture($newTransport()->json($url, 10, []) === ['features' => []], 'Duplicate Cache-Control rejected a successful provider');
    $clock->timestamp += 599;
    $newTransport()->json($url, 10, []);
    expectArchitecture(count($calls) === 1, 'Duplicate max-age was ignored');
    $clock->timestamp++;
    $response = (new Response(config(Config\App::class)))->removeHeader('Cache-Control')->setStatusCode(304)
        ->setHeader('Cache-Control', 'public')->addHeader('Cache-Control', 'max-age=600');
    expectArchitecture($newTransport()->json($url, 10, []) === ['features' => []]
        && end($calls)['If-None-Match'] === '"v1"', 'Duplicate headers broke conditional 304');

    $response = (new Response(config(Config\App::class)))->removeHeader('Cache-Control')->setStatusCode(200)->setBody('{"features":[]}')
        ->setHeader('Cache-Control', 'public')->addHeader('Cache-Control', 'no-store');
    $url = 'https://nostore.example/feed';
    $newTransport()->json($url, 10, []);
    $before = count($calls);
    $expectPaused($url);
    expectArchitecture(count($calls) === $before, 'Duplicate no-store caused a repeat download');
    foreach (glob($directory . '/*.feed.json') as $file) expectArchitecture(!str_contains(file_get_contents($file), 'nostore.example'), 'Duplicate no-store was ignored');

    $response = (new Response(config(Config\App::class)))->removeHeader('Cache-Control')->setStatusCode(429)
        ->setHeader('Retry-After', '600')->addHeader('Retry-After', gmdate('D, d M Y H:i:s \G\M\T', $clock->timestamp + 7200))
        ->setHeader('Cache-Control', 'public')->addHeader('Cache-Control', 'no-cache');
    $url = 'https://retry.example/feed';
    $expectPaused($url);
    $before = count($calls);
    $clock->timestamp += 7199;
    $expectPaused($url);
    expectArchitecture(count($calls) === $before, 'Repeated Retry-After lost the longer HTTP-date pause');

    $response = (new Response(config(Config\App::class)))->removeHeader('Cache-Control')->setStatusCode(200)->setBody('{"features":[]}')
        ->setHeader('ETag', '"one"')->addHeader('ETag', '"two"')
        ->setHeader('Last-Modified', 'Wed, 12 Nov 2025 10:00:00 GMT')->addHeader('Last-Modified', 'Thu, 13 Nov 2025 10:00:00 GMT');
    $url = 'https://validators.example/feed';
    $newTransport()->json($url, 10, []);
    $clock->timestamp += 300;
    $newTransport()->json($url, 10, []);
    expectArchitecture(!isset(end($calls)['If-None-Match'], end($calls)['If-Modified-Since']), 'Ambiguous validators were forwarded');
} finally {
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
echo "Provider headers: real CI responses, repeated Cache-Control/Retry-After, 304, no-store and ambiguous validators passed.\n";
