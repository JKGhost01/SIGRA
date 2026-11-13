<?php

declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\SeismicIngestion\Infrastructure\Scheduling\FileRefreshCoordinator;
use Psr\Log\NullLogger;

$directory = sys_get_temp_dir() . '/sigra-refresh-test-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$clock = new class implements App\Shared\Domain\Clock {
    public int $timestamp = 1800000000;
    public function now(): DateTimeImmutable { return new DateTimeImmutable('@' . $this->timestamp); }
};
$calls = 0;
$operation = static function () use (&$calls): array {
    $calls++;
    return ['eventos' => [['id' => 'test']], 'guardados' => 1, 'fuentes' => ['SGC' => 1], 'errores' => []];
};
$newCoordinator = static fn() => new FileRefreshCoordinator($directory, 60, $clock, new NullLogger());
try {
    $result = $newCoordinator()->runIfDue($operation);
    expectArchitecture($result->synced && $calls === 1 && $result->saved === 1 && $result->lastSuccessAt === $clock->timestamp, 'First visitor did not trigger ingestion');
    $cached = $newCoordinator()->runIfDue($operation);
    expectArchitecture(!$cached->synced && $calls === 1 && $cached->sources === ['SGC' => 1], 'A second request ignored the shared cache');
    $clock->timestamp += 59;
    $newCoordinator()->runIfDue($operation);
    expectArchitecture($calls === 1, 'Provider refreshed before the interval elapsed');
    $clock->timestamp++;
    $newCoordinator()->runIfDue($operation);
    expectArchitecture($calls === 2, 'Refresh did not resume after the interval');
    $clock->timestamp += 60;
    $lock = fopen($directory . '/ingestion.lock', 'c');
    flock($lock, LOCK_EX | LOCK_NB);
    $newCoordinator()->runIfDue($operation);
    expectArchitecture($calls === 2, 'A visitor started ingestion while CLI/another request held the lock');
    flock($lock, LOCK_UN);
    fclose($lock);
    $failedCalls = 0;
    $failure = static function () use (&$failedCalls): array { $failedCalls++; throw new RuntimeException('internal provider detail'); };
    $failed = $newCoordinator()->runIfDue($failure);
    expectArchitecture(!$failed->synced && isset($failed->errors['sync']) && !str_contains(json_encode($failed->errors), 'internal'), 'Failure leaked details or claimed a refresh');
    $newCoordinator()->runIfDue($failure);
    expectArchitecture($failedCalls === 1 && $failed->lastSuccessAt === 1800000060, 'Provider failure caused a retry storm');
    $clock->timestamp += 60;
    expectArchitecture($newCoordinator()->runIfDue($operation)->synced && $calls === 3, 'Failed operation did not release the lock');
    $newCoordinator()->runIfDue($operation, true);
    expectArchitecture($calls === 4, 'Explicit forced operation could not bypass TTL');
    file_put_contents($directory . '/catalog-refresh.json', '{invalid json');
    expectArchitecture($newCoordinator()->runIfDue($operation)->synced, 'Corrupt cache permanently disabled automatic ingestion');
} finally {
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
echo "Automatic refresh: first visit, TTL, shared lock, failure backoff and recovery passed.\n";
