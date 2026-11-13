<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Persistence;

final class CiAlertRepository implements \App\Modules\Alerting\Application\Contracts\AlertRepository
{
    public function __construct(private readonly \App\Models\RedAlertModel $model) {}
    public function buscarPorUuid(string $uuid): ?array { return $this->model->buscarPorUuid($uuid); }
    public function activas(int $limit): array
    { return $this->model->activas($limit); }
    public function buscarPorSource(string $source, string $id): ?array
    { return $this->model->buscarPorSource($source, $id); }
    public function recienteCercana(float $lat, float $lng, float $radius, int $seconds): ?array
    { return $this->model->recienteCercana($lat, $lng, $radius, $seconds); }
    public function insert(array $data): int
    {
        $id = $this->model->insert($data);
        if ($id === false) throw new \RuntimeException('Persistence insert failed.');
        return (int) $id;
    }
    public function find(int $id): ?array
    { return $this->model->find($id); }
    public function update(int $id, array $data): void
    { if (!$this->model->update($id, $data)) throw new \RuntimeException('Persistence update failed.'); }
    public function countActive(): int
    { return $this->model->where('status', 'active')->where('expires_at >=', date('Y-m-d H:i:s'))->countAllResults(); }
    public function expirarVencidas(): void
    { $this->model->expirarVencidas(); }
}
