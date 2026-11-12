<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Entregas de una alerta a cada teléfono.
 */
class RedDeliveryModel extends Model
{
    protected $table = 'red_alert_deliveries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'alert_id',
        'device_id',
        'distance_km',
        'seconds_to_s_wave',
        'channel',
        'status',
        'sent_at',
        'seen_at',
        'error_message',
        'created_at',
    ];
    protected $useTimestamps = false;

    /**
     * @return list<array<string, mixed>>
     */
    public function paraDispositivo(int $deviceId, int $limite = 20): array
    {
        return $this->where('device_id', $deviceId)
            ->orderBy('created_at', 'DESC')
            ->findAll($limite);
    }

    public function marcarVistas(int $deviceId, array $alertIds): void
    {
        if ($alertIds === []) {
            return;
        }

        $this->where('device_id', $deviceId)
            ->whereIn('alert_id', $alertIds)
            ->where('seen_at', null)
            ->set('seen_at', date('Y-m-d H:i:s'))
            ->update();
    }
}
