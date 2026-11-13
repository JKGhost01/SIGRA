<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Persistence;

use CodeIgniter\Model;

/**
 * Alertas sísmicas fusionadas por el servidor.
 */
class RedAlertModel extends Model
{
    protected $table = 'red_alerts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields = [
        'alert_uuid',
        'source',
        'source_id',
        'status',
        'latitude',
        'longitude',
        'depth_km',
        'magnitude_estimated',
        'confidence',
        'detection_count',
        'device_count',
        'radius_km',
        'origin_time_ms',
        'expires_at',
        'details',
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $dateFormat = 'datetime';

    /**
     * @return list<array<string, mixed>>
     */
    public function activas(int $limite = 20): array
    {
        return $this->where('status', 'active')
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->orderBy('created_at', 'DESC')
            ->findAll($limite);
    }

    public function buscarPorUuid(string $uuid): ?array
    {
        return $this->where('alert_uuid', $uuid)->first();
    }

    public function buscarPorSource(string $source, string $sourceId): ?array
    {
        return $this->where('source', $source)
            ->where('source_id', $sourceId)
            ->first();
    }

    /**
     * Alerta reciente y cercana para evitar duplicados por el mismo evento.
     */
    public function recienteCercana(float $lat, float $lng, float $radioKm, int $segundos = 90, string $source = 'crowd'): ?array
    {
        $desde = date('Y-m-d H:i:s', time() - $segundos);
        $grados = $radioKm / 111.0;

        $candidatas = $this->where('source', $source)
            ->where('created_at >=', $desde)
            ->where('latitude >=', $lat - $grados)
            ->where('latitude <=', $lat + $grados)
            ->where('longitude >=', $lng - $grados)
            ->where('longitude <=', $lng + $grados)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        foreach ($candidatas as $alerta) {
            $distancia = self::distanciaKm(
                $lat,
                $lng,
                (float) $alerta['latitude'],
                (float) $alerta['longitude'],
            );
            if ($distancia <= $radioKm) {
                return $alerta;
            }
        }

        return null;
    }

    public function expirarVencidas(): int
    {
        $this->where('status', 'active')
            ->where('expires_at <', date('Y-m-d H:i:s'))
            ->set('status', 'expired')
            ->update();

        return $this->db->affectedRows();
    }

    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierra = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $radioTierra * atan2(sqrt($a), sqrt(1 - $a));
    }
}
