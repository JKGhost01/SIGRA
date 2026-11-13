<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Application\Contracts;

interface DeviceRepository
{
    public function buscarPorUuid(string $uuid): ?array;
    public function buscarPorToken(string $tokenHash): ?array;
    public function insert(array $data): int;
    public function update(int $id, array $data): void;
    public function find(int $id): ?array;
    public function activosRecientes(int $minutes): array;
    public function candidatosParaAlerta(float $magnitude, int $minutes): array;
}
