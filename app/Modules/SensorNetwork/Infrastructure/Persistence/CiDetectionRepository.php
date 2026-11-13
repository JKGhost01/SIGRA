<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Infrastructure\Persistence;

final class CiDetectionRepository implements \App\Modules\SensorNetwork\Application\Contracts\DetectionRepository
{
    public function __construct(private readonly \App\Models\RedDetectionModel $model) {}
    public function contarDesde(int $sinceMs, ?int $deviceId = null): int
    { return $this->model->contarDesde($sinceMs, $deviceId); }
    public function findDuplicate(int $deviceId, int $detectedAtMs): ?array
    { return $this->model->where('device_id', $deviceId)->where('detected_at_ms', $detectedAtMs)->first(); }
    public function insert(array $data): int
    {
        $id = $this->model->insert($data);
        if ($id === false) throw new \RuntimeException('Persistence insert failed.');
        return (int) $id;
    }
    public function recientes(int $sinceMs, int $untilMs): array
    { return $this->model->recientes($sinceMs, $untilMs); }
    public function assignCluster(array $ids, int $alertId): void
    { db_connect()->table('red_detections')->whereIn('id', $ids)->update(['cluster_id' => $alertId]); }
}
