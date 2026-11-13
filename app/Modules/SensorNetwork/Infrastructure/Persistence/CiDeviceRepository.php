<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Infrastructure\Persistence;

final class CiDeviceRepository implements \App\Modules\SensorNetwork\Application\Contracts\DeviceRepository
{
    public function __construct(private readonly \App\Models\RedDeviceModel $model) {}
    public function buscarPorUuid(string $uuid): ?array
    { return $this->model->buscarPorUuid($uuid); }
    public function buscarPorToken(string $tokenHash): ?array
    { return $this->model->buscarPorToken($tokenHash); }
    public function insert(array $data): int
    {
        $id = $this->model->insert($data);
        if ($id === false) throw new \RuntimeException('Persistence insert failed.');
        return (int) $id;
    }
    public function update(int $id, array $data): void
    { if (!$this->model->update($id, $data)) throw new \RuntimeException('Persistence update failed.'); }
    public function find(int $id): ?array
    { return $this->model->find($id); }
    public function activosRecientes(int $minutes): array
    { return $this->model->activosRecientes($minutes); }
    public function candidatosParaAlerta(float $magnitude, int $minutes): array
    { return $this->model->candidatosParaAlerta($magnitude, $minutes); }
}
