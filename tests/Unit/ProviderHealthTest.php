<?php

declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\SeismicIngestion\Infrastructure\Sources\{CiFeedTransport, FileProviderHealth};

$directory = sys_get_temp_dir() . '/sigra-health-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$clock = new class implements App\Shared\Domain\Clock {
    public int $timestamp = 1800000000;
    public function now(): DateTimeImmutable { return new DateTimeImmutable('@' . $this->timestamp); }
};
$providers = ['SGC' => ['url' => 'https://archive.sgc.gov.co/feed', 'enabled' => true]];
$reader = new FileProviderHealth($directory, $providers, $clock);
$path = $directory . '/' . hash('sha256', 'archive.sgc.gov.co') . '.json';
$status = 200;
$body = '{"features":[]}';
$calls = 0;
$transport = new CiFeedTransport($directory, $clock, static function () use (&$status, &$body, &$calls): array {
    $calls++;
    if ($status === 0) throw new RuntimeException('private connection information');
    return ['status' => $status, 'headers' => [], 'body' => $body];
});
$fetch = static function () use ($transport): void {
    try { $transport->json('https://archive.sgc.gov.co/feed', 10, []); }
    catch (RuntimeException) {}
};
try {
    expectArchitecture($reader->snapshot()[0]['state'] === 'unknown', 'Missing state shown as healthy');
    expectArchitecture(count(glob($directory . '/*')) === 0, 'Reading health created runtime files');
    $fetch();
    $row = $reader->snapshot()[0];
    expectArchitecture($row['state'] === 'ok' && $row['http'] === 200 && $row['last_check'] === $clock->timestamp,
        'Successful check metadata missing');
    $lastSuccess = $row['last_success'];
    $before = file_get_contents($path);
    $clock->timestamp += 899;
    $fetch();
    $reader->snapshot();
    expectArchitecture($calls === 1 && file_get_contents($path) === $before, 'Cached reads changed check times or triggered I/O');
    $clock->timestamp++;
    expectArchitecture($reader->snapshot()[0]['state'] === 'stale', 'Expired success shown as current');
    $status = 304;
    $fetch();
    expectArchitecture($reader->snapshot()[0]['http'] === 304, '304 result missing');
    $lastSuccess = $clock->timestamp;
    $clock->timestamp += 900;
    $status = 403;
    $fetch();
    $row = $reader->snapshot()[0];
    expectArchitecture($row['state'] === 'paused' && $row['http'] === 403 && $row['last_success'] === $lastSuccess
        && $row['last_attempt'] === $clock->timestamp, '403 diagnostic lost actual check or previous success');
    $clock->timestamp = $row['next_check'];
    expectArchitecture($reader->snapshot()[0]['state'] === 'error', 'Expired failed check shown as healthy');
    $status = 0;
    $fetch();
    $row = $reader->snapshot()[0];
    expectArchitecture($row['http'] === null && $row['outcome'] === 'connection_error'
        && $row['last_success'] === $lastSuccess, 'Network error invented HTTP code or lost last success');
    $clock->timestamp = $row['next_check'];
    $status = 200; $body = '{invalid';
    $fetch();
    expectArchitecture($reader->snapshot()[0]['outcome'] === 'invalid_response'
        && $reader->snapshot()[0]['http'] === 200, 'Invalid JSON shown as valid HTTP success');
    file_put_contents($path, json_encode(['next_at' => $clock->timestamp + 86400, 'error' => true, 'status' => 403]));
    $row = $reader->snapshot()[0];
    expectArchitecture($row['state'] === 'paused' && $row['last_check'] === null && $row['last_attempt'] === null,
        'Legacy state invented historical dates');
    file_put_contents($path, json_encode(['next_at' => $clock->timestamp + 900, 'error' => true, 'outcome' => 'pending',
        'last_attempt_at' => $clock->timestamp]));
    expectArchitecture($reader->snapshot()[0]['state'] === 'checking', 'Reserved request reported as a completed failure');
    file_put_contents($path, '{broken');
    expectArchitecture($reader->snapshot()[0]['state'] === 'invalid', 'Corrupt state shown as healthy');
    $disabled = new FileProviderHealth($directory, ['SGC' => ['url' => $providers['SGC']['url'], 'enabled' => false]], $clock);
    expectArchitecture($disabled->snapshot()[0]['state'] === 'disabled', 'Disabled source shown as enabled');
    expectArchitecture(!str_contains(json_encode($reader->snapshot()), 'private connection information'), 'Health leaked raw exception');
} finally {
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
echo "Provider health: read-only snapshots, check timestamps, 304, 403, stale data, transport/JSON errors, legacy and corrupt states passed.\n";
