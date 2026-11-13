<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands\DeliverAlert;

use App\Modules\SensorNetwork\Application\NetworkSettings;
use App\Modules\SensorNetwork\Application\Contracts\DeviceRepository;
use App\Modules\Alerting\Application\Contracts\{DeliveryRepository, PushPublisher};
use App\Modules\Alerting\Domain\PwaveFusion;
use App\Shared\Domain\Clock;
final class AlertDeliveryHandler
{
    public function __construct(private readonly NetworkSettings $config, private readonly PwaveFusion $fusion,
        private readonly DeviceRepository $devices, private readonly DeliveryRepository $deliveries,
        private readonly PushPublisher $push, private readonly Clock $clock, private readonly \DateTimeZone $storageTimezone) {}
    public function handle(array $alerta): void
    {
        $candidatos = $this->devices->candidatosParaAlerta((float) $alerta['magnitude_estimated'], $this->config->deviceOnlineMinutes);
        $fcm = $this->push;
        $ahoraMs = $this->nowMs();
        $enviados = 0;

        foreach ($candidatos as $dispositivo) {
            $distancia = \App\Shared\Domain\GeoDistance::km(
                (float) $alerta['latitude'],
                (float) $alerta['longitude'],
                (float) $dispositivo['latitude'],
                (float) $dispositivo['longitude'],
            );

            $radio = min((float) $alerta['radius_km'], (float) $dispositivo['alert_radius_km']);
            if ($distancia > $radio) {
                continue;
            }

            $existente = $this->deliveries->findDelivery((int) $alerta['id'], (int) $dispositivo['id']);
            if ($existente !== null) {
                continue;
            }

            $eta = $this->fusion->segundosHastaOndaS((int) ($alerta['origin_time_ms'] ?? $ahoraMs), $distancia, $ahoraMs);
            $deliveryId = (int) $this->deliveries->insert([
                'alert_id' => (int) $alerta['id'],
                'device_id' => (int) $dispositivo['id'],
                'distance_km' => round($distancia, 1),
                'seconds_to_s_wave' => $eta,
                'channel' => 'poll',
                'status' => 'pending',
                'created_at' => $this->localDate($this->clock->now()->getTimestamp()),
            ]);

            if ($enviados >= 200) {
                continue;
            }

            $token = (string) ($dispositivo['fcm_token'] ?? '');
            if ($token === '' || ! $fcm->estaConfigurado()) {
                continue;
            }

            $ok = $fcm->enviar(
                $token,
                sprintf('SIGRA · Sismo estimado M%.1f', (float) $alerta['magnitude_estimated']),
                $this->textoPush($alerta, $distancia, $eta),
                [
                    'type' => 'seismic_alert',
                    'alert_uuid' => (string) $alerta['alert_uuid'],
                    'magnitude' => (float) $alerta['magnitude_estimated'],
                    'latitude' => (float) $alerta['latitude'],
                    'longitude' => (float) $alerta['longitude'],
                    'distance_km' => round($distancia, 1),
                    'seconds_to_s_wave' => $eta,
                    'severity' => $this->fusion->nivelAlerta((float) $alerta['magnitude_estimated']),
                ],
            );

            $this->deliveries->update($deliveryId, [
                'channel' => 'push',
                'status' => $ok ? 'sent' : 'failed',
                'sent_at' => $ok ? $this->localDate($this->clock->now()->getTimestamp()) : null,
                'error_message' => $ok ? null : 'FCM no pudo entregar el mensaje.',
            ]);
            $enviados++;
        }
    }

    private function textoPush(array $alerta, float $distancia, float $eta): string
    {
        $magnitud = number_format((float) $alerta['magnitude_estimated'], 1, '.', '');
        $distanciaTexto = number_format($distancia, 0, '.', '');

        if ($eta > 0) {
            return sprintf('M%.1f estimada a %s km. Onda S en ~%d s. Protege tu cabeza y aléjate de ventanas.', $magnitud, $distanciaTexto, (int) round($eta));
        }

        return sprintf('M%.1f estimada a %s km. La onda S ya está llegando. Agáchate, cúbrete y sujétate.', $magnitud, $distanciaTexto);
    }

    private function nowMs(): int { return (int) $this->clock->now()->format('Uv'); }
    private function localDate(int $timestamp): string
    { return (new \DateTimeImmutable('@' . $timestamp))->setTimezone($this->storageTimezone)->format('Y-m-d H:i:s'); }
}
