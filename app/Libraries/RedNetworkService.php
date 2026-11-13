<?php

declare(strict_types=1);

namespace App\Libraries;

/** Compatibility facade for old PHP consumers. New controllers resolve module application services. */
class RedNetworkService
{
    public function __construct(private readonly ?\Config\Red $config = null) {}
    private function service(): \App\Modules\SensorNetwork\Application\SensorNetworkService
    { return \Config\Services::sensorNetwork(false, $this->config); }
    private function call(string $operation, array $args): mixed
    {
        try { return $this->service()->$operation(...$args); }
        catch (\App\Shared\Application\OperationRejected $error) { throw new RedApiException($error->getMessage(), $error->statusCode); }
    }
    public function configPublica(): array
    { return $this->call('configPublica', []); }
    public function registrarDispositivo(array $payload, ?string $existingToken = null): array
    { return $this->call('registrarDispositivo', [$payload, $existingToken]); }
    public function autenticar(string $token): ?array
    { return $this->call('autenticar', [$token]); }
    public function latir(array $device, array $payload): array
    { return $this->call('latir', [$device, $payload]); }
    public function registrarDeteccion(array $device, array $payload): array
    { return $this->call('registrarDeteccion', [$device, $payload]); }
    public function alertasCercanas(?array $device, float $lat, float $lng): array
    { return $this->call('alertasCercanas', [$device, $lat, $lng]); }
    public function estadisticasPublicas(?float $latUsuario = null, ?float $lngUsuario = null): array
    { return $this->call('estadisticasPublicas', [$latUsuario, $lngUsuario]); }
    public function crearAlertaSgc(array $evento): ?array
    { return $this->call('crearAlertaSgc', [$evento]); }
    public function desactivarDispositivo(array $device): void
    { $this->call('desactivarDispositivo', [$device]); }
}
