<?php

declare(strict_types=1);

// Callback-backed port doubles. No framework, database, providers or real delivery.
final class FakeDeviceRepository implements \App\Modules\SensorNetwork\Application\Contracts\DeviceRepository
{
    public array $hooks = [];
    public array $calls = [];
    public function buscarPorUuid(string $uuid): ?array
    {
        $this->calls[] = ['buscarPorUuid', [$uuid]];
        return isset($this->hooks['buscarPorUuid']) ? ($this->hooks['buscarPorUuid'])($uuid) : null;
    }
    public function buscarPorToken(string $tokenHash): ?array
    {
        $this->calls[] = ['buscarPorToken', [$tokenHash]];
        return isset($this->hooks['buscarPorToken']) ? ($this->hooks['buscarPorToken'])($tokenHash) : null;
    }
    public function insert(array $data): int
    {
        $this->calls[] = ['insert', [$data]];
        return isset($this->hooks['insert']) ? ($this->hooks['insert'])($data) : 1;
    }
    public function update(int $id, array $data): void
    {
        $this->calls[] = ['update', [$id, $data]];
        isset($this->hooks['update']) ? ($this->hooks['update'])($id, $data) : null;
    }
    public function find(int $id): ?array
    {
        $this->calls[] = ['find', [$id]];
        return isset($this->hooks['find']) ? ($this->hooks['find'])($id) : null;
    }
    public function activosRecientes(int $minutes): array
    {
        $this->calls[] = ['activosRecientes', [$minutes]];
        return isset($this->hooks['activosRecientes']) ? ($this->hooks['activosRecientes'])($minutes) : [];
    }
    public function candidatosParaAlerta(float $magnitude, int $minutes): array
    {
        $this->calls[] = ['candidatosParaAlerta', [$magnitude, $minutes]];
        return isset($this->hooks['candidatosParaAlerta']) ? ($this->hooks['candidatosParaAlerta'])($magnitude, $minutes) : [];
    }
}
final class FakeDetectionRepository implements \App\Modules\SensorNetwork\Application\Contracts\DetectionRepository
{
    public array $hooks = [];
    public array $calls = [];
    public function contarDesde(int $sinceMs, ?int $deviceId = null): int
    {
        $this->calls[] = ['contarDesde', [$sinceMs, $deviceId]];
        return isset($this->hooks['contarDesde']) ? ($this->hooks['contarDesde'])($sinceMs, $deviceId) : 1;
    }
    public function findDuplicate(int $deviceId, int $detectedAtMs): ?array
    {
        $this->calls[] = ['findDuplicate', [$deviceId, $detectedAtMs]];
        return isset($this->hooks['findDuplicate']) ? ($this->hooks['findDuplicate'])($deviceId, $detectedAtMs) : null;
    }
    public function insert(array $data): int
    {
        $this->calls[] = ['insert', [$data]];
        return isset($this->hooks['insert']) ? ($this->hooks['insert'])($data) : 1;
    }
    public function recientes(int $sinceMs, int $untilMs): array
    {
        $this->calls[] = ['recientes', [$sinceMs, $untilMs]];
        return isset($this->hooks['recientes']) ? ($this->hooks['recientes'])($sinceMs, $untilMs) : [];
    }
    public function assignCluster(array $ids, int $alertId): void
    {
        $this->calls[] = ['assignCluster', [$ids, $alertId]];
        isset($this->hooks['assignCluster']) ? ($this->hooks['assignCluster'])($ids, $alertId) : null;
    }
}
final class FakeNetworkStatistics implements \App\Modules\SensorNetwork\Application\Contracts\NetworkStatistics
{
    public array $hooks = [];
    public array $calls = [];
    public function read(): array
    {
        $this->calls[] = ['read', []];
        return isset($this->hooks['read']) ? ($this->hooks['read'])() : [];
    }
}
final class FakeAlertRepository implements \App\Modules\Alerting\Application\Contracts\AlertRepository
{
    public array $hooks = [];
    public array $calls = [];
    public function buscarPorUuid(string $uuid): ?array
    {
        $this->calls[] = ['buscarPorUuid', [$uuid]];
        return isset($this->hooks['buscarPorUuid']) ? ($this->hooks['buscarPorUuid'])($uuid) : null;
    }
    public function activas(int $limit): array
    {
        $this->calls[] = ['activas', [$limit]];
        return isset($this->hooks['activas']) ? ($this->hooks['activas'])($limit) : [];
    }
    public function buscarPorSource(string $source, string $id): ?array
    {
        $this->calls[] = ['buscarPorSource', [$source, $id]];
        return isset($this->hooks['buscarPorSource']) ? ($this->hooks['buscarPorSource'])($source, $id) : null;
    }
    public function recienteCercana(float $lat, float $lng, float $radius, int $seconds): ?array
    {
        $this->calls[] = ['recienteCercana', [$lat, $lng, $radius, $seconds]];
        return isset($this->hooks['recienteCercana']) ? ($this->hooks['recienteCercana'])($lat, $lng, $radius, $seconds) : null;
    }
    public function insert(array $data): int
    {
        $this->calls[] = ['insert', [$data]];
        return isset($this->hooks['insert']) ? ($this->hooks['insert'])($data) : 1;
    }
    public function find(int $id): ?array
    {
        $this->calls[] = ['find', [$id]];
        return isset($this->hooks['find']) ? ($this->hooks['find'])($id) : null;
    }
    public function update(int $id, array $data): void
    {
        $this->calls[] = ['update', [$id, $data]];
        isset($this->hooks['update']) ? ($this->hooks['update'])($id, $data) : null;
    }
    public function countActive(): int
    {
        $this->calls[] = ['countActive', []];
        return isset($this->hooks['countActive']) ? ($this->hooks['countActive'])() : 1;
    }
    public function expirarVencidas(): void
    {
        $this->calls[] = ['expirarVencidas', []];
        isset($this->hooks['expirarVencidas']) ? ($this->hooks['expirarVencidas'])() : null;
    }
}
final class FakeDeliveryRepository implements \App\Modules\Alerting\Application\Contracts\DeliveryRepository
{
    public array $hooks = [];
    public array $calls = [];
    public function marcarVistas(int $deviceId, array $ids): void
    {
        $this->calls[] = ['marcarVistas', [$deviceId, $ids]];
        isset($this->hooks['marcarVistas']) ? ($this->hooks['marcarVistas'])($deviceId, $ids) : null;
    }
    public function findDelivery(int $alertId, int $deviceId): ?array
    {
        $this->calls[] = ['findDelivery', [$alertId, $deviceId]];
        return isset($this->hooks['findDelivery']) ? ($this->hooks['findDelivery'])($alertId, $deviceId) : null;
    }
    public function insert(array $data): int
    {
        $this->calls[] = ['insert', [$data]];
        return isset($this->hooks['insert']) ? ($this->hooks['insert'])($data) : 1;
    }
    public function update(int $id, array $data): void
    {
        $this->calls[] = ['update', [$id, $data]];
        isset($this->hooks['update']) ? ($this->hooks['update'])($id, $data) : null;
    }
}
final class FakePushPublisher implements \App\Modules\Alerting\Application\Contracts\PushPublisher
{
    public array $hooks = [];
    public array $calls = [];
    public function estaConfigurado(): bool
    {
        $this->calls[] = ['estaConfigurado', []];
        return isset($this->hooks['estaConfigurado']) ? ($this->hooks['estaConfigurado'])() : false;
    }
    public function enviar(string $token, string $title, string $message, array $data): bool
    {
        $this->calls[] = ['enviar', [$token, $title, $message, $data]];
        return isset($this->hooks['enviar']) ? ($this->hooks['enviar'])($token, $title, $message, $data) : false;
    }
}
final class FakeAlertDecisionLog implements \App\Modules\Alerting\Application\Contracts\AlertDecisionLog
{
    public array $hooks = [];
    public array $calls = [];
    public function record(array $decision): void
    {
        $this->calls[] = ['record', [$decision]];
        isset($this->hooks['record']) ? ($this->hooks['record'])($decision) : null;
    }
}
