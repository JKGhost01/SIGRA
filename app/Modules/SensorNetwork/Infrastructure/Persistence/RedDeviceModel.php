<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Infrastructure\Persistence;

use CodeIgniter\Model;

/**
 * Teléfonos participantes de la red ciudadana.
 */
class RedDeviceModel extends Model
{
    protected $table = 'red_devices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'device_uuid',
        'device_token_hash',
        'platform',
        'manufacturer',
        'model',
        'os_version',
        'app_version',
        'fcm_token',
        'latitude',
        'longitude',
        'location_accuracy_m',
        'location_updated_at',
        'monitoring_enabled',
        'notifications_enabled',
        'alert_radius_km',
        'min_magnitude',
        'battery_level',
        'last_seen_at',
        'last_detection_at',
        'status',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $dateFormat = 'datetime';

    public function buscarPorUuid(string $uuid): ?array
    {
        return $this->where('device_uuid', $uuid)->first();
    }

    public function buscarPorToken(string $tokenHash): ?array
    {
        return $this->where('device_token_hash', $tokenHash)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Teléfonos activos con ubicación reciente.
     *
     * @return list<array<string, mixed>>
     */
    public function activosRecientes(int $minutos = 10): array
    {
        return $this->where('status', 'active')
            ->where('last_seen_at >=', date('Y-m-d H:i:s', time() - ($minutos * 60)))
            ->where('latitude IS NOT NULL')
            ->where('longitude IS NOT NULL')
            ->findAll();
    }

    /**
     * Suscriptores potenciales de una alerta.
     *
     * @return list<array<string, mixed>>
     */
    public function candidatosParaAlerta(float $magnitud, int $minutos = 10): array
    {
        return $this->where('status', 'active')
            ->where('notifications_enabled', 1)
            ->where('last_seen_at >=', date('Y-m-d H:i:s', time() - ($minutos * 60)))
            ->where('latitude IS NOT NULL')
            ->where('longitude IS NOT NULL')
            ->where('min_magnitude <=', $magnitud)
            ->findAll();
    }
}
