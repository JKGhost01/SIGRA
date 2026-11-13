<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application;

use App\Modules\SensorNetwork\Application\NetworkSettings;
use App\Modules\SensorNetwork\Application\Contracts\{DeviceRepository, DetectionRepository};
use App\Modules\Alerting\Application\Contracts\{AlertRepository, DeliveryRepository, PushPublisher};
use App\Modules\Alerting\Domain\PwaveFusion;
use App\Shared\Domain\Clock;
use Throwable;
class AlertingService
{
    public function __construct(private readonly NetworkSettings $config, private readonly PwaveFusion $fusion,
        private readonly DeviceRepository $devices, private readonly DetectionRepository $detections,
        private readonly AlertRepository $alerts, private readonly DeliveryRepository $deliveries,
        private readonly \App\Modules\Alerting\Application\Commands\DeliverAlert\AlertDeliveryHandler $delivery,
        private readonly Clock $clock,
        private readonly \DateTimeZone $storageTimezone,
        private readonly \App\Modules\Alerting\Application\Contracts\AlertDecisionLog $decisionLog,
        private readonly \App\Shared\Application\TransactionManager $transactions) {}
    public function alertasCercanas(?array $device, float $lat, float $lng): array
    {
        $alertas = $this->alerts->activas(30);
        $resultado = [];

        foreach ($alertas as $alerta) {
            $distancia = \App\Shared\Domain\GeoDistance::km(
                $lat,
                $lng,
                (float) $alerta['latitude'],
                (float) $alerta['longitude'],
            );

            if ($distancia > (float) $alerta['radius_km']) {
                continue;
            }

            if ($device !== null && $distancia > (float) ($device['alert_radius_km'] ?? 1000)) {
                continue;
            }

            $resultado[] = $this->serializarAlerta($alerta, $lat, $lng);
        }

        return $resultado;
    }

    public function crearAlertaSgc(array $evento): ?array
    {
        if (!$this->config->alertingEnabled) return null;
        $magnitud = (float) ($evento['magnitud'] ?? 0);
        if ($magnitud < $this->config->minAlertMagnitude) {
            return null;
        }

        $sourceId = (string) ($evento['id'] ?? $evento['source_id'] ?? '');
        if ($sourceId === '') {
            return null;
        }

        $existente = $this->alerts->buscarPorSource('sgc', $sourceId);
        if ($existente !== null) {
            return $existente;
        }

        $lat = (float) ($evento['latitud'] ?? 0);
        $lng = (float) ($evento['longitud'] ?? 0);
        $origenMs = (int) (strtotime((string) ($evento['fecha_hora'] ?? 'now')) * 1000);

        $origen = [
            'lat' => $lat,
            'lng' => $lng,
            'origin_time_ms' => $origenMs,
            'residual_s' => 0.0,
            'uncertainty_km' => 0.0,
            'detection_count' => 0,
            'device_count' => 0,
        ];

        return $this->crearAlerta(
            $origen,
            $magnitud,
        1.0,
            0,
            0,
            'sgc',
            $sourceId,
            [
                'official' => true,
                'agency' => 'SGC',
                'location' => (string) ($evento['ubicacion'] ?? ''),
                'depth_km' => (float) ($evento['profundidad'] ?? 0),
            ],
        );
    }

    private function crearAlerta(
        array $origen,
        float $magnitud,
        float $confianza,
        int $detecciones,
        int $dispositivos,
        string $source,
        string $sourceId,
        array $details,
    ): array {
        $radio = $this->fusion->radioAlertaKm(
            $magnitud,
            $this->config->alertRadiusBaseKm,
            $this->config->alertRadiusPerMagnitudeKm,
            $this->config->alertMaxRadiusKm,
        );

        $uuid = $this->uuidV4();
        $datos = [
            'alert_uuid' => $uuid,
            'source' => $source,
            'source_id' => $sourceId !== '' ? $sourceId : null,
            'status' => 'active',
            'latitude' => $origen['lat'],
            'longitude' => $origen['lng'],
            'depth_km' => (float) ($details['depth_km'] ?? $this->config->defaultDepthKm),
            'magnitude_estimated' => round($magnitud, 1),
            'confidence' => round($confianza, 3),
            'detection_count' => $detecciones,
            'device_count' => $dispositivos,
            'radius_km' => $radio,
            'origin_time_ms' => $origen['origin_time_ms'],
            'expires_at' => $this->localDate($this->clock->now()->getTimestamp() + ($this->config->alertTtlMinutes * 60)),
            'details' => json_encode($details + [
                'algorithm' => 'STA/LTA + TDOA',
                'p_wave_velocity_km_s' => $this->config->pWaveVelocityKmS,
                's_wave_velocity_km_s' => $this->config->sWaveVelocityKmS,
                'residual_s' => $origen['residual_s'] ?? null,
                'uncertainty_km' => $origen['uncertainty_km'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $alerta = $this->transactions->run(function () use ($datos, $source, $magnitud, $confianza, $dispositivos, $detecciones): array {
            $id = (int) $this->alerts->insert($datos);
            $alerta = $this->alerts->find($id);
            if (! is_array($alerta)) {
                throw new \App\Shared\Application\OperationRejected('No fue posible crear la alerta.', 500);
            }

            $this->recordDecision('alert:' . $alerta['alert_uuid'], 'confirmed_by_policy',
                $source === 'sgc' ? 'official_source_notification' : 'experimental_thresholds_met',
                ['source' => $source, 'magnitude' => $magnitud, 'confidence' => $confianza,
                    'devices' => $dispositivos, 'detections' => $detecciones]);
            return $alerta;
        });
        $this->delivery->handle($alerta);

        return $alerta;
    }

    public function evaluarEvento(array $semilla): array
    {
        $candidateId = 'detection:' . (string) ($semilla['id'] ?? hash('sha256', json_encode($semilla)));
        if (!$this->config->alertingEnabled) {
            $this->recordDecision($candidateId, 'rejected', 'kill_switch', []);
            return ['event' => null, 'alert' => null, 'message' => 'La publicación de alertas está desactivada.'];
        }
        $tiempoMs = (int) $semilla['detected_at_ms'];
        $ventanaMs = $this->config->clusterWindowSeconds * 1000;
        $recientes = $this->detections->recientes($tiempoMs - $ventanaMs, $tiempoMs + $ventanaMs);
        $semilla = [
            'lat' => (float) $semilla['latitude'],
            'lng' => (float) $semilla['longitude'],
            'detected_at_ms' => $tiempoMs,
            'device_uuid' => (string) $semilla['device_uuid'],
        ];

        $grupo = $this->fusion->agrupar(
            $recientes,
            $semilla,
            $this->config->clusterWindowSeconds,
            $this->config->clusterRadiusKm,
        );

        $dispositivos = [];
        foreach ($grupo as $deteccion) {
            $dispositivos[(string) $deteccion['device_uuid']] = true;
        }

        if (count($dispositivos) < $this->config->minDevices) {
            $this->recordDecision($candidateId, 'candidate', 'insufficient_devices', ['devices' => count($dispositivos)]);
            return [
                'event' => [
                    'devices' => count($dispositivos),
                    'needed' => $this->config->minDevices,
                ],
                'alert' => null,
                'message' => 'Detección registrada. La red necesita más teléfonos para confirmar el evento.',
            ];
        }

        $origen = $this->fusion->estimarOrigen($grupo);
        if ($origen === null) {
            $this->recordDecision($candidateId, 'under_review', 'origin_unresolved', []);
            return ['event' => null, 'alert' => null, 'message' => 'No fue posible triangular el origen.'];
        }

        $magnitud = $this->fusion->estimarMagnitud($grupo, $origen);
        $confianza = $this->fusion->estimarConfianza($grupo, $origen);

        $resumen = [
            'latitude' => $origen['lat'],
            'longitude' => $origen['lng'],
            'origin_time_ms' => $origen['origin_time_ms'],
            'magnitude' => $magnitud,
            'confidence' => $confianza,
            'devices' => $origen['device_count'],
            'detections' => $origen['detection_count'],
            'residual_s' => $origen['residual_s'],
            'uncertainty_km' => $origen['uncertainty_km'],
        ];

        if ($magnitud < $this->config->minAlertMagnitude || $confianza < $this->config->minAlertConfidence) {
            $this->recordDecision($candidateId, 'rejected', 'below_threshold', $resumen);
            return [
                'event' => $resumen,
                'alert' => null,
                'message' => 'Evento fusionado por debajo del umbral de alerta.',
            ];
        }

        $this->recordDecision($candidateId, 'confirmed_by_policy', 'experimental_thresholds_met', $resumen);
        $existente = $this->alerts->recienteCercana($origen['lat'], $origen['lng'], 100.0, 120);
        if ($existente !== null && $existente['status'] === 'active') {
            $this->alerts->update((int) $existente['id'], [
                'magnitude_estimated' => max((float) $existente['magnitude_estimated'], $magnitud),
                'confidence' => max((float) $existente['confidence'], $confianza),
                'detection_count' => (int) $existente['detection_count'] + $origen['detection_count'],
                'device_count' => max((int) $existente['device_count'], $origen['device_count']),
                'expires_at' => $this->localDate($this->clock->now()->getTimestamp() + ($this->config->alertTtlMinutes * 60)),
            ]);
            $existente = $this->alerts->find((int) $existente['id']);

            return [
                'event' => $resumen,
                'alert' => is_array($existente) ? $this->serializarAlerta($existente) : null,
                'message' => 'Evento actualizado en una alerta existente.',
            ];
        }

        $alerta = $this->crearAlerta($origen, $magnitud, $confianza, $origen['detection_count'], $origen['device_count'], 'crowd', '', $resumen);

        $ids = [];
        foreach ($grupo as $deteccion) {
            if (! empty($deteccion['id'])) {
                $ids[] = (int) $deteccion['id'];
            }
        }
        if ($ids !== []) {
            $this->detections->assignCluster($ids, (int) $alerta['id']);
        }

        return [
            'event' => $resumen,
            'alert' => $this->serializarAlerta($alerta),
            'message' => 'Evento confirmado por la red de teléfonos.',
        ];
    }

    public function serializarAlerta(array $alerta, ?float $latUsuario = null, ?float $lngUsuario = null): array
    {
        $lat = (float) $alerta['latitude'];
        $lng = (float) $alerta['longitude'];
        $magnitud = (float) $alerta['magnitude_estimated'];
        $distancia = ($latUsuario !== null && $lngUsuario !== null)
            ? \App\Shared\Domain\GeoDistance::km($latUsuario, $lngUsuario, $lat, $lng)
            : null;
        $origin = (int) ($alerta['origin_time_ms'] ?? (strtotime((string) $alerta['created_at']) * 1000));

        return [
            'db_id' => (int) ($alerta['id'] ?? 0),
            'uuid' => (string) ($alerta['alert_uuid'] ?? ''),
            'source' => (string) ($alerta['source'] ?? 'crowd'),
            'status' => (string) ($alerta['status'] ?? 'active'),
            'magnitude' => $magnitud,
            'latitude' => $lat,
            'longitude' => $lng,
            'depth_km' => (float) ($alerta['depth_km'] ?? $this->config->defaultDepthKm),
            'radius_km' => (float) ($alerta['radius_km'] ?? 0),
            'confidence' => (float) ($alerta['confidence'] ?? 0),
            'detection_count' => (int) ($alerta['detection_count'] ?? 0),
            'device_count' => (int) ($alerta['device_count'] ?? 0),
            'origin_time_ms' => $origin,
            'origin_time' => date('c', intdiv(max(0, $origin), 1000)),
            'created_at' => (string) ($alerta['created_at'] ?? ''),
            'expires_at' => (string) ($alerta['expires_at'] ?? ''),
            'distance_km' => $distancia !== null ? round($distancia, 1) : null,
            'seconds_to_s_wave' => $distancia !== null
                ? $this->fusion->segundosHastaOndaS($origin, $distancia, $this->nowMs())
                : null,
            'severity' => $this->fusion->nivelAlerta($magnitud),
            'estimated' => true,
            'disclaimer' => 'Estimación experimental de SIGRA. No sustituye a las autoridades.',
        ];
    }

    public function contarAlertasActivas(): int
    {
        return $this->alerts->countActive();
    }

    private function uuidV4(): string
    {
        $datos = random_bytes(16);
        $datos[6] = chr((ord($datos[6]) & 0x0f) | 0x40);
        $datos[8] = chr((ord($datos[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($datos), 4));
    }
    private function nowMs(): int { return (int) $this->clock->now()->format('Uv'); }
    private function localDate(int $timestamp): string
    { return (new \DateTimeImmutable('@' . $timestamp))->setTimezone($this->storageTimezone)->format('Y-m-d H:i:s'); }
    public function cancel(string $uuid, string $reason): void
    {
        if (trim($reason) === '' || strlen($reason) > 160) throw new \InvalidArgumentException('Indica un motivo de hasta 160 caracteres.');
        $alert = $this->alerts->buscarPorUuid($uuid);
        if ($alert === null) throw new \App\Shared\Application\OperationRejected('Alerta inexistente.', 404);
        if ($alert['status'] !== 'active') throw new \App\Shared\Application\OperationRejected('La alerta ya no está activa.', 409);
        $candidate = new \App\Modules\Alerting\Domain\AlertCandidate($uuid, \App\Modules\Alerting\Domain\CandidateState::ConfirmedByPolicy);
        $candidate->transition(\App\Modules\Alerting\Domain\CandidateState::Cancelled);
        $this->transactions->run(function () use ($uuid, $reason, $alert): void {
            $this->recordDecision('alert:' . $uuid, 'cancelled', $reason, ['actor' => 'server_cli']);
            $this->alerts->update((int) $alert['id'], ['status' => 'cancelled']);
        });
    }
    private function recordDecision(string $id, string $state, string $reason, array $evidence): void
    {
        $this->decisionLog->record(['candidate_id' => $id, 'state' => $state,
            'algorithm_version' => 'sta-lta-tdoa-v1', 'reason' => $reason,
            'evidence' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'recorded_at_utc' => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
    }
}
