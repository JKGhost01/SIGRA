<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Application\Contracts;

interface DetectionRepository
{
    public function contarDesde(int $sinceMs, ?int $deviceId = null): int;
    public function findDuplicate(int $deviceId, int $detectedAtMs): ?array;
    public function insert(array $data): int;
    public function recientes(int $sinceMs, int $untilMs): array;
    public function assignCluster(array $ids, int $alertId): void;
}
