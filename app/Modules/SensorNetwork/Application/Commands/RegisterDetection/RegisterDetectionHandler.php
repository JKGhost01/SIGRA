<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Application\Commands\RegisterDetection;

use App\Modules\SensorNetwork\Application\NetworkSettings;
use App\Modules\SensorNetwork\Application\Contracts\{DeviceRepository, DetectionRepository};
use App\Modules\Alerting\Application\AlertingService;
use App\Shared\Domain\Clock;
final class RegisterDetectionHandler
{
    public function __construct(private readonly NetworkSettings $config, private readonly DeviceRepository $devices,
        private readonly DetectionRepository $detections, private readonly AlertingService $alerting,
        private readonly Clock $clock, private readonly \DateTimeZone $storageTimezone) {}
    public function handle(array $device, array $payload): array
    {
        if (($payload['simulated'] ?? false) === true) {
            return ['status' => 'ok', 'accepted' => false, 'simulated' => true, 'alert' => null];
        }
        foreach (['latitude', 'lat', 'longitude', 'lng', 'confidence', 'detected_at_ms',
            'accuracy_m', 'sta_lta_ratio', 'peak_accel_mps2', 'dominant_freq_hz', 'duration_ms'] as $key) {
            if (isset($payload[$key]) && (!is_numeric($payload[$key]) || !is_finite((float) $payload[$key]))) {
                throw new \App\Shared\Application\OperationRejected('La señal contiene valores numéricos inválidos.', 422);
            }
        }
        $ahoraMs = $this->nowMs();
        $detectadoMs = $this->msDesdePayload($payload['detected_at_ms'] ?? null) ?? $ahoraMs;
        $edadSegundos = ($ahoraMs - $detectadoMs) / 1000;

        if ($edadSegundos > $this->config->maxDetectionAgeSeconds) {
            throw new \App\Shared\Application\OperationRejected('La detección es demasiado antigua para ser útil.', 422);
        }
        if ($edadSegundos < -$this->config->futureToleranceSeconds) {
            throw new \App\Shared\Application\OperationRejected('El reloj del teléfono está adelantado. Sincroniza la hora automática.', 422);
        }

        $lat = $this->numero($payload['latitude'] ?? $payload['lat'] ?? null);
        $lng = $this->numero($payload['longitude'] ?? $payload['lng'] ?? null);
        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new \App\Shared\Application\OperationRejected('Coordenadas inválidas.', 422);
        }

        $confianza = max(0.0, min(1.0, $this->numero($payload['confidence'] ?? null) ?? 0.5));
        if ($confianza < $this->config->minDetectionConfidence) {
            throw new \App\Shared\Application\OperationRejected('La detección tiene muy poca confianza para ingresar a la red.', 422);
        }

        $existente = $this->detections->findDuplicate((int) $device['id'], $detectadoMs);
        if ($existente !== null) {
            return [
                'status' => 'ok',
                'accepted' => false,
                'duplicate' => true,
                'detection_id' => (int) $existente['id'],
                'alert' => null,
            ];
        }

        // Anti-abuso: separación mínima y cuota por minuto.
        $ultima = $device['last_detection_at'] ? strtotime((string) $device['last_detection_at']) : null;
        if ($ultima !== null && ($this->clock->now()->getTimestamp() - $ultima) < $this->config->minSecondsBetweenDetections) {
            throw new \App\Shared\Application\OperationRejected('Espera unos segundos antes de enviar otra detección.', 429);
        }

        $enUltimoMinuto = $this->detections->contarDesde($ahoraMs - 60000, (int) $device['id']);
        if ($enUltimoMinuto >= $this->config->maxDetectionsPerMinute) {
            throw new \App\Shared\Application\OperationRejected('Demasiadas detecciones en un minuto. El teléfono fue limitado temporalmente.', 429);
        }

        $datos = [
            'device_id' => (int) $device['id'],
            'device_uuid' => (string) $device['device_uuid'],
            'detected_at_ms' => $detectadoMs,
            'received_at_ms' => $ahoraMs,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy_m' => $this->numero($payload['accuracy_m'] ?? null),
            'sta_lta_ratio' => $this->numero($payload['sta_lta_ratio'] ?? null),
            'peak_accel_mps2' => $this->numero($payload['peak_accel_mps2'] ?? null),
            'dominant_freq_hz' => $this->numero($payload['dominant_freq_hz'] ?? null),
            'duration_ms' => isset($payload['duration_ms']) ? max(0, (int) $payload['duration_ms']) : null,
            'confidence' => $confianza,
            'created_at' => $this->localDate($this->clock->now()->getTimestamp()),
        ];

        $deviceId = (int) $device['id'];
        $detectionId = (int) $this->detections->insert($datos);
        $datos['id'] = $detectionId;

        $this->devices->update($deviceId, [
            'last_detection_at' => $this->localDate($this->clock->now()->getTimestamp()),
            'last_seen_at' => $this->localDate($this->clock->now()->getTimestamp()),
        ]);

        $evaluacion = $this->alerting->evaluarEvento($datos);

        return [
            'status' => 'ok',
            'accepted' => true,
            'detection_id' => $detectionId,
            'event' => $evaluacion['event'] ?? null,
            'alert' => $evaluacion['alert'] ?? null,
            'message' => $evaluacion['message'] ?? null,
        ];
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
    private function nowMs(): int { return (int) $this->clock->now()->format('Uv'); }
    private function localDate(int $timestamp): string
    { return (new \DateTimeImmutable('@' . $timestamp))->setTimezone($this->storageTimezone)->format('Y-m-d H:i:s'); }

}
