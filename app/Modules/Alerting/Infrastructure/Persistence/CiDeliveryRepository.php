<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Persistence;

final class CiDeliveryRepository implements \App\Modules\Alerting\Application\Contracts\DeliveryRepository
{
    public function __construct(private readonly \App\Models\RedDeliveryModel $model) {}
    public function marcarVistas(int $deviceId, array $ids): void
    { $this->model->marcarVistas($deviceId, $ids); }
    public function findDelivery(int $alertId, int $deviceId): ?array
    { return $this->model->where('alert_id', $alertId)->where('device_id', $deviceId)->first(); }
    public function insert(array $data): int
    {
        $id = $this->model->insert($data);
        if ($id === false) throw new \RuntimeException('Persistence insert failed.');
        return (int) $id;
    }
    public function update(int $id, array $data): void
    { if (!$this->model->update($id, $data)) throw new \RuntimeException('Persistence update failed.'); }
}
