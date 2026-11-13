<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Infrastructure\Persistence;

use App\Modules\SensorNetwork\Application\NetworkSettings;
use App\Modules\SensorNetwork\Application\Contracts\DeviceRepository;
use App\Shared\Application\CacheStore;
final class CiNetworkStatistics implements \App\Modules\SensorNetwork\Application\Contracts\NetworkStatistics
{
    public function __construct(private readonly NetworkSettings $config, private readonly DeviceRepository $devices,
        private readonly CacheStore $cache,
        private readonly \App\Modules\Alerting\Application\Contracts\AlertStatisticsReader $alerts) {}
    public function read(): array
    {
        $ttl = max(0, $this->config->statsCacheSeconds);
        $cache = $ttl > 0 ? $this->cache : null;
        $clave = 'sigra_red_stats_v1';

        if ($cache !== null) {
            $guardado = $cache->get($clave);
            if (is_array($guardado)) {
                return $guardado;
            }
        }

        $db = db_connect();
        $ahora = time();
        $onlineDesde = date('Y-m-d H:i:s', $ahora - ($this->config->deviceOnlineMinutes * 60));
        $desde24 = date('Y-m-d H:i:s', $ahora - 86400);

        $totalDispositivos = $db->table('red_devices')->where('status', 'active')->countAllResults();
        $online = $db->table('red_devices')
            ->where('status', 'active')
            ->where('last_seen_at >=', $onlineDesde)
            ->countAllResults();
        $monitoreando = $db->table('red_devices')
            ->where('status', 'active')
            ->where('monitoring_enabled', 1)
            ->where('last_seen_at >=', $onlineDesde)
            ->countAllResults();
        $detecciones24 = $db->table('red_detections')
            ->where('detected_at_ms >=', ($ahora - 86400) * 1000)
            ->countAllResults();
        $alerts = $this->alerts->summary($desde24);
        $alertas24 = $alerts['count']; $ultima = $alerts['last']; $recientes = $alerts['recent'];

        $dispositivos = $this->devices->activosRecientes($this->config->deviceOnlineMinutes);
        $puntos = [];
        foreach ($dispositivos as $dispositivo) {
            $puntos[] = [
                'lat' => round((float) $dispositivo['latitude'], $this->config->publicDevicePrecision),
                'lng' => round((float) $dispositivo['longitude'], $this->config->publicDevicePrecision),
                'monitoring' => (bool) $dispositivo['monitoring_enabled'],
            ];
        }

        $detecciones = $db->table('red_detections')
            ->where('detected_at_ms >=', ($ahora - 1800) * 1000)
            ->orderBy('detected_at_ms', 'DESC')
            ->get(80)
            ->getResultArray();

        $puntosDeteccion = [];
        foreach ($detecciones as $deteccion) {
            $puntosDeteccion[] = [
                'lat' => round((float) $deteccion['latitude'], 2),
                'lng' => round((float) $deteccion['longitude'], 2),
                'confidence' => round((float) $deteccion['confidence'], 2),
            ];
        }

        $datos = [
            'total_dispositivos' => $totalDispositivos,
            'online' => $online,
            'monitoreando' => $monitoreando,
            'detecciones24' => $detecciones24,
            'alertas24' => $alertas24,
            'ultima' => $ultima,
            'recientes' => $recientes,
            'devices' => $puntos,
            'detections' => $puntosDeteccion,
        ];

        if ($cache !== null) {
            $cache->save($clave, $datos, $ttl);
        }

        return $datos;
    }
}
