<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Contracts;

interface AlertRepository
{
    public function buscarPorUuid(string $uuid): ?array;
    public function activas(int $limit): array;
    public function buscarPorSource(string $source, string $id): ?array;
    public function recienteCercana(float $lat, float $lng, float $radius, int $seconds): ?array;
    public function insert(array $data): int;
    public function find(int $id): ?array;
    public function update(int $id, array $data): void;
    public function countActive(): int;
    public function expirarVencidas(): void;
}
