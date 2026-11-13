<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Infrastructure\Persistence;

use CodeIgniter\Model;

/**
 * Detecciones de onda P reportadas por teléfonos.
 */
class RedDetectionModel extends Model
{
    protected $table = 'red_detections';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'device_id',
        'device_uuid',
        'detected_at_ms',
        'received_at_ms',
        'latitude',
        'longitude',
        'accuracy_m',
        'sta_lta_ratio',
        'peak_accel_mps2',
        'dominant_freq_hz',
        'duration_ms',
        'confidence',
        'cluster_id',
        'created_at',
    ];
    protected $useTimestamps = false;

    /**
     * @return list<array<string, mixed>>
     */
    public function recientes(int $desdeMs, int $hastaMs): array
    {
        return $this->where('detected_at_ms >=', $desdeMs)
            ->where('detected_at_ms <=', $hastaMs)
            ->orderBy('detected_at_ms', 'ASC')
            ->findAll();
    }

    public function contarDesde(int $desdeMs, ?int $deviceId = null): int
    {
        if ($deviceId !== null) {
            $this->where('device_id', $deviceId);
        }

        return $this->where('detected_at_ms >=', $desdeMs)->countAllResults();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ultimas(int $limite = 50): array
    {
        return $this->orderBy('detected_at_ms', 'DESC')->findAll($limite);
    }
}
