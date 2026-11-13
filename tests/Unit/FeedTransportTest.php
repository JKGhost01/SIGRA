<?php

declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\SeismicIngestion\Infrastructure\Sources\CiFeedTransport;

$directory = sys_get_temp_dir() . '/sigra-provider-policy-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$clock = new class implements App\Shared\Domain\Clock {
    public int $timestamp = 1800000000;
    public function now(): DateTimeImmutable { return new DateTimeImmutable('@' . $this->timestamp); }
};
$calls = [];
$response = ['status' => 200, 'headers' => ['ETag' => '"v1"', 'Last-Modified' => 'Wed, 12 Nov 2025 10:00:00 GMT'],
    'body' => '{"features":[]}'];
$failNetwork = false;
$http = static function ($url, $timeout, $headers) use (&$calls, &$response, &$failNetwork): array {
    $calls[] = [$url, $headers];
    if ($failNetwork) throw new RuntimeException('timeout');
    return $response;
};
$newTransport = static fn() => new CiFeedTransport($directory, $clock, $http);
$refused = static function (string $url) use ($newTransport): void {
    try { $newTransport()->json($url, 10, []); throw new LogicException('Expected refusal'); }
    catch (RuntimeException) {}
};
try {
    $sgcUrl = 'https://archive.sgc.gov.co/feed/v1.0.1/summary/five_days_2.json';
    $newTransport()->json($sgcUrl, 10, []);
    $sgcCalls = count($calls);
    $clock->timestamp += 899;
    $newTransport()->json($sgcUrl, 10, []);
    expectArchitecture(count($calls) === $sgcCalls, 'SGC queried before its 15-minute interval');
    $refused('https://archive.sgc.gov.co/another-feed');
    expectArchitecture(count($calls) === $sgcCalls, 'Alternate SGC URL bypassed host interval');
    $clock->timestamp++;
    $newTransport()->json($sgcUrl, 10, []);
    expectArchitecture(count($calls) === $sgcCalls + 1, 'SGC did not resume after 15 minutes');
    $calls = [];

    $url = 'https://success.example/feed';
    expectArchitecture($newTransport()->json($url, 10, []) === ['features' => []], 'Valid feed rejected');
    for ($i = 0; $i < 25; $i++) $newTransport()->json($url, 10, []);
    expectArchitecture(count($calls) === 1, 'Repeated workers/browser/cron multiplied downloads');
    expectArchitecture(str_contains($calls[0][1]['User-Agent'], 'SIGRA') && !isset($calls[0][1]['If-None-Match']), 'Client identity/first validators wrong');
    $clock->timestamp += 300;
    $response = ['status' => 304, 'headers' => [], 'body' => ''];
    expectArchitecture($newTransport()->json($url, 10, []) === ['features' => []], '304 lost cached feed');
    expectArchitecture($calls[1][1]['If-None-Match'] === '"v1"' && isset($calls[1][1]['If-Modified-Since']), 'Conditional request missing');
    $clock->timestamp += 300;
    $newTransport()->json($url, 10, []);
    expectArchitecture($calls[2][1]['If-None-Match'] === '"v1"', '304 without new validator discarded ETag');

    foreach ([401, 403] as $deniedStatus) {
        $url = 'https://blocked' . $deniedStatus . '.example/feed';
        $statePath = $directory . '/' . hash('sha256', 'blocked' . $deniedStatus . '.example') . '.json';
        $response = ['status' => $deniedStatus, 'headers' => [], 'body' => 'Denied'];
        $before = count($calls);
        $refused($url);
        $state = json_decode(file_get_contents($statePath), true);
        $delay = $state['next_at'] - $clock->timestamp;
        expectArchitecture($state['failures'] === 1 && $delay >= 7200 && $delay <= 7920,
            'First 401/403 must pause for two hours plus jitter');
        for ($i = 0; $i < 25; $i++) $refused('https://blocked' . $deniedStatus . '.example/another-feed');
        $clock->timestamp = $state['next_at'] - 1;
        $refused($url);
        expectArchitecture(count($calls) === $before + 1, 'First denial retried before its two-hour pause expired');
        $clock->timestamp++;
        $refused($url);
        $state = json_decode(file_get_contents($statePath), true);
        $delay = $state['next_at'] - $clock->timestamp;
        expectArchitecture($state['failures'] === 2 && $delay >= 86400 && $delay <= 95040,
            'Second consecutive denial must pause for 24 hours plus jitter');
        $clock->timestamp = $state['next_at'] - 1;
        $refused($url);
        expectArchitecture(count($calls) === $before + 2, 'Second denial retried before its 24-hour pause expired');
        $clock->timestamp++;
        $refused($url);
        $state = json_decode(file_get_contents($statePath), true);
        expectArchitecture($state['failures'] === 3 && $state['next_at'] - $clock->timestamp >= 86400,
            'Further consecutive denials must retain the 24-hour pause');
        $clock->timestamp = $state['next_at'];
        $response = ['status' => 200, 'headers' => [], 'body' => '{"features":[]}'];
        $newTransport()->json($url, 10, []);
        $state = json_decode(file_get_contents($statePath), true);
        expectArchitecture($state['failures'] === 0, 'Success did not reset consecutive failures');
        $clock->timestamp = $state['next_at'];
        $response = ['status' => $deniedStatus, 'headers' => [], 'body' => 'Denied'];
        $refused($url);
        $state = json_decode(file_get_contents($statePath), true);
        expectArchitecture($state['failures'] === 1 && $state['next_at'] - $clock->timestamp <= 7920,
            'First denial after recovery did not return to the two-hour pause');
    }

    foreach ([0, 1] as $index) {
        $retry = $index === 0 ? '120000' : gmdate('D, d M Y H:i:s \G\M\T', $clock->timestamp + 120000);
        $response = ['status' => 429, 'headers' => ['Retry-After' => $retry], 'body' => 'Limited'];
        $before = count($calls);
        $url = 'https://limited' . $index . '.example/feed';
        $refused($url);
        $clock->timestamp += 119999;
        $refused($url);
        expectArchitecture(count($calls) === $before + 1, 'Retry-After numeric/date was ignored');
        $clock->timestamp++;
        $refused($url);
        expectArchitecture(count($calls) === $before + 2, 'Provider did not resume after Retry-After');
    }

    $response = ['status' => 503, 'headers' => [], 'body' => 'Unavailable'];
    $url = 'https://outage.example/feed';
    $refused($url);
    $hostFile = $directory . '/' . hash('sha256', 'outage.example') . '.json';
    $state = json_decode(file_get_contents($hostFile), true);
    $delay = $state['next_at'] - $clock->timestamp;
    $clock->timestamp = $state['next_at'];
    $refused($url);
    $state = json_decode(file_get_contents($hostFile), true);
    expectArchitecture($state['next_at'] - $clock->timestamp > $delay, 'Repeated failures did not increase backoff');

    $failNetwork = true;
    $before = count($calls);
    $refused('https://timeout.example/feed');
    $refused('https://timeout.example/feed');
    expectArchitecture(count($calls) === $before + 1, 'Timeout triggered immediate retry');
    $failNetwork = false;

    $response = ['status' => 200, 'headers' => ['Cache-Control' => 'max-age=3600'], 'body' => '{"features":[]}'];
    $url = 'https://cache.example/feed';
    $newTransport()->json($url, 10, []);
    $clock->timestamp += 3599;
    $before = count($calls);
    $newTransport()->json($url, 10, []);
    expectArchitecture(count($calls) === $before, 'Provider Cache-Control ignored');

    $response = ['status' => 200, 'headers' => ['Cache-Control' => 'no-store'], 'body' => '{"features":[]}'];
    $url = 'https://private.example/feed';
    $newTransport()->json($url, 10, []);
    $before = count($calls);
    $refused($url);
    expectArchitecture(count($calls) === $before, 'Uncacheable response caused immediate re-entry');
    foreach (glob($directory . '/*.feed.json') as $file) {
        expectArchitecture(!str_contains(file_get_contents($file), 'private.example'), 'no-store response persisted');
    }

    $response = ['status' => 200, 'headers' => [], 'body' => '{"features":[]}'];
    $newTransport()->json('https://rolling.example/query?starttime=2026-11-08&endtime=2026-11-13', 10, []);
    $before = count($calls);
    $newTransport()->json('https://rolling.example/query?starttime=2026-11-09&endtime=2026-11-14', 10, []);
    expectArchitecture(count($calls) === $before, 'Rolling FDSN time window bypassed budget');
    $refused('https://rolling.example/query?starttime=2026-11-13&endtime=2026-11-14');

    $hostFile = $directory . '/' . hash('sha256', 'corrupt.example') . '.json';
    file_put_contents($hostFile, '{broken');
    $before = count($calls);
    $refused('https://corrupt.example/feed');
    expectArchitecture(count($calls) === $before, 'Corrupt budget failed open');
    $lock = fopen($directory . '/' . hash('sha256', 'locked.example') . '.lock', 'c');
    flock($lock, LOCK_EX | LOCK_NB);
    $refused('https://locked.example/feed');
    expectArchitecture(count($calls) === $before, 'Concurrent worker bypassed lock');
    flock($lock, LOCK_UN); fclose($lock);
} finally {
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
echo "Provider policy: shared cache/lock, conditional 304, 403 pause, Retry-After date/seconds, exponential backoff, timeouts, cache directives and fail-closed state passed.\n";
