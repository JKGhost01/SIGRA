<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Contracts;

interface DeliveryRepository
{
    public function marcarVistas(int $deviceId, array $ids): void;
    public function findDelivery(int $alertId, int $deviceId): ?array;
    public function insert(array $data): int;
    public function update(int $id, array $data): void;
}
