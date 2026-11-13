<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Application;

use App\Modules\SensorNetwork\Application\Contracts\{DeviceRepository, DetectionRepository, NetworkStatistics};
use App\Modules\Alerting\Application\AlertingService;
use App\Shared\Domain\Clock;
class SensorNetworkService
{
    public function __construct(private readonly NetworkSettings $config, private readonly DeviceRepository $devices,
        private readonly DetectionRepository $detections, private readonly AlertingService $alerting,
        private readonly NetworkStatistics $statistics, private readonly Clock $clock,
        private readonly \DateTimeZone $storageTimezone, private readonly string $version, private readonly string $apiVersion,
        private readonly \App\Modules\SensorNetwork\Application\Commands\RegisterDetection\RegisterDetectionHandler $registerDetection) {}
    public function configPublica(): array
    {
        return [
            'status' => 'ok',
            'network' => 'SIGRA Red de teléfonos',
            'version' => $this->version,
            'api_version' => $this->apiVersion,
            'min_devices' => $this->config->minDevices,
            'cluster_window_seconds' => $this->config->clusterWindowSeconds,
            'cluster_radius_km' => $this->config->clusterRadiusKm,
            'min_alert_magnitude' => $this->config->minAlertMagnitude,
            'min_alert_confidence' => $this->config->minAlertConfidence,
            'min_detection_confidence' => $this->config->minDetectionConfidence,
            'p_wave_velocity_km_s' => $this->config->pWaveVelocityKmS,
            's_wave_velocity_km_s' => $this->config->sWaveVelocityKmS,
            'poll_seconds' => 5,
            'heartbeat_seconds' => 30,
            'fcm_enabled' => $this->config->fcmEnabled,
            'apk_url' => $this->config->apkUrl,
            'disclaimer' => 'Estimación experimental. No sustituye al SGC ni a los sistemas oficiales de alerta temprana.',
            'server_time_ms' => $this->nowMs(),
        ];
    }

    public function registrarDispositivo(array $payload, ?string $existingToken = null): array
    {
        $uuid = trim((string) ($payload['device_uuid'] ?? ''));
        if (! preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $uuid)) {
            throw new \App\Shared\Application\OperationRejected('device_uuid inválido. Debe tener entre 8 y 64 caracteres seguros.', 422);
        }

        $existente = $this->devices->buscarPorUuid($uuid);
        if ($existente !== null) {
            $expected = (string) ($existente['device_token_hash'] ?? '');
            if ($existingToken === null || $existingToken === '' || $expected === ''
                || !hash_equals($expected, hash('sha256', $existingToken))) {
                throw new \App\Shared\Application\OperationRejected('Se requiere el token actual para volver a registrar este dispositivo.', 401);
            }
        }

        $token = bin2hex(random_bytes(32));
        $datos = [
            'device_uuid' => $uuid,
            'device_token_hash' => hash('sha256', $token),
            'platform' => substr(trim((string) ($payload['platform'] ?? 'android')), 0, 20),
            'manufacturer' => $this->textoOpcional($payload['manufacturer'] ?? null, 80),
            'model' => $this->textoOpcional($payload['model'] ?? null, 120),
            'os_version' => $this->textoOpcional($payload['os_version'] ?? null, 30),
            'app_version' => $this->textoOpcional($payload['app_version'] ?? null, 30),
            'fcm_token' => $this->textoOpcional($payload['fcm_token'] ?? null, 255),
            'monitoring_enabled' => ! empty($payload['monitoring_enabled']) ? 1 : 0,
            'notifications_enabled' => array_key_exists('notifications_enabled', $payload)
                ? (! empty($payload['notifications_enabled']) ? 1 : 0)
                : 1,
            'alert_radius_km' => max(25, min(1000, (int) ($payload['alert_radius_km'] ?? 250))),
            'min_magnitude' => max(1.0, min(9.0, (float) ($payload['min_magnitude'] ?? 3.0))),
            'last_seen_at' => $this->localDate($this->clock->now()->getTimestamp()),
            'status' => 'active',
        ];

        $this->aplicarUbicacion($datos, $payload);

        if ($existente === null) {
            $id = (int) $this->devices->insert($datos);
        } else {
            $id = (int) $existente['id'];
            $this->devices->update($id, $datos);
        }

        $device = $this->devices->find($id);
        if (! is_array($device)) {
            throw new \App\Shared\Application\OperationRejected('No fue posible registrar el teléfono.', 500);
        }

        return [
            'status' => 'ok',
            'device_token' => $token,
            'device' => $this->serializarDispositivo($device),
            'config' => $this->configPublica(),
        ];
    }

    public function autenticar(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return $this->devices->buscarPorToken(hash('sha256', $token));
    }

    public function latir(array $device, array $payload): array
    {
        $datos = [
            'last_seen_at' => $this->localDate($this->clock->now()->getTimestamp()),
            'monitoring_enabled' => ! empty($payload['monitoring_enabled']) ? 1 : 0,
        ];

        if (array_key_exists('notifications_enabled', $payload)) {
            $datos['notifications_enabled'] = ! empty($payload['notifications_enabled']) ? 1 : 0;
        }
        if (isset($payload['fcm_token'])) {
            $datos['fcm_token'] = $this->textoOpcional($payload['fcm_token'], 255);
        }
        if (isset($payload['app_version'])) {
            $datos['app_version'] = $this->textoOpcional($payload['app_version'], 30);
        }
        if (isset($payload['battery_level'])) {
            $datos['battery_level'] = max(0, min(100, (int) $payload['battery_level']));
        }
        if (isset($payload['alert_radius_km'])) {
            $datos['alert_radius_km'] = max(25, min(1000, (int) $payload['alert_radius_km']));
        }
        if (isset($payload['min_magnitude'])) {
            $datos['min_magnitude'] = max(1.0, min(9.0, (float) $payload['min_magnitude']));
        }

        $this->aplicarUbicacion($datos, $payload);
        $this->devices->update((int) $device['id'], $datos);

        $actualizado = $this->devices->find((int) $device['id']);

        return [
            'status' => 'ok',
            'server_time_ms' => $this->nowMs(),
            'device' => is_array($actualizado) ? $this->serializarDispositivo($actualizado) : null,
            'config' => $this->configPublica(),
            'active_alerts' => $this->alerting->contarAlertasActivas(),
        ];
    }

    public function registrarDeteccion(array $device, array $payload): array
    { return $this->registerDetection->handle($device, $payload); }

    public function estadisticasPublicas(?float $latUsuario = null, ?float $lngUsuario = null): array
    {
        $datos = $this->statistics->read();
        $ultima = $datos['ultima'];
        $recientes = $datos['recientes'];

        return [
            'status' => 'ok',
            'generated_at' => date('c'),
            'server_time_ms' => $this->nowMs(),
            'operational' => $datos['online'] >= $this->config->minDevices,
            'stats' => [
                'devices_total' => $datos['total_dispositivos'],
                'devices_online' => $datos['online'],
                'devices_monitoring' => $datos['monitoreando'],
                'detections_24h' => $datos['detecciones24'],
                'alerts_24h' => $datos['alertas24'],
            ],
            'last_alert' => $ultima !== null ? $this->alerting->serializarAlerta($ultima, $latUsuario, $lngUsuario) : null,
            'recent_alerts' => array_map(fn (array $alerta): array => $this->alerting->serializarAlerta($alerta, $latUsuario, $lngUsuario), $recientes),
            'devices' => $datos['devices'],
            'detections' => $datos['detections'],
            'config' => $this->configPublica(),
        ];
    }

    public function desactivarDispositivo(array $device): void
    {
        $this->devices->update((int) $device['id'], [
            'status' => 'inactive',
            'monitoring_enabled' => 0,
            'fcm_token' => null,
            'last_seen_at' => $this->localDate($this->clock->now()->getTimestamp()),
        ]);
    }

    private function serializarDispositivo(array $dispositivo): array
    {
        return [
            'id' => (int) $dispositivo['id'],
            'device_uuid' => (string) $dispositivo['device_uuid'],
            'platform' => (string) $dispositivo['platform'],
            'model' => $dispositivo['model'],
            'monitoring_enabled' => (bool) $dispositivo['monitoring_enabled'],
            'notifications_enabled' => (bool) $dispositivo['notifications_enabled'],
            'alert_radius_km' => (int) $dispositivo['alert_radius_km'],
            'min_magnitude' => (float) $dispositivo['min_magnitude'],
            'last_seen_at' => $dispositivo['last_seen_at'],
        ];
    }

    private function aplicarUbicacion(array &$datos, array $payload): void
    {
        $lat = $this->numero($payload['latitude'] ?? $payload['lat'] ?? null);
        $lng = $this->numero($payload['longitude'] ?? $payload['lng'] ?? null);

        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return;
        }

        $datos['latitude'] = $lat;
        $datos['longitude'] = $lng;
        $datos['location_accuracy_m'] = $this->numero($payload['accuracy_m'] ?? null);
        $datos['location_updated_at'] = $this->localDate($this->clock->now()->getTimestamp());
    }

    private function textoOpcional(mixed $valor, int $maximo): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : substr($texto, 0, $maximo);
    }

    private function numero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        if (is_string($valor) && is_numeric($valor)) {
            return (float) $valor;
        }

        return null;
    }

    private function msDesdePayload(mixed $valor): ?int
    {
        if (is_int($valor) || is_float($valor)) {
            return (int) $valor;
        }
        if (is_string($valor) && is_numeric($valor)) {
            return (int) $valor;
        }
        if (is_string($valor) && $valor !== '') {
            $timestamp = strtotime($valor);

            return $timestamp === false ? null : $timestamp * 1000;
        }

        return null;
    }
    public function alertasCercanas(?array $device, float $lat, float $lng): array { return $this->alerting->alertasCercanas($device, $lat, $lng); }
    public function crearAlertaSgc(array $event): ?array { return $this->alerting->crearAlertaSgc($event); }
    private function nowMs(): int { return (int) $this->clock->now()->format('Uv'); }
    private function localDate(int $timestamp): string
    { return (new \DateTimeImmutable('@' . $timestamp))->setTimezone($this->storageTimezone)->format('Y-m-d H:i:s'); }
}
