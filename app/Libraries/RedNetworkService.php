<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\RedAlertModel;
use App\Models\RedDeliveryModel;
use App\Models\RedDetectionModel;
use App\Models\RedDeviceModel;
use Config\Red;
use Throwable;

/**
 * RedNetworkService
 * -------------------------------------------------------------------
 * Orquesta el ciclo completo de la red ciudadana:
 *
 *   registro de teléfonos -> detecciones de onda P -> fusión TDOA ->
 *   alerta -> entrega por polling y/o FCM.
 *
 * Las reglas de negocio viven aquí, no en el controlador.
 */
class RedNetworkService
{
    private Red $config;
    private PwaveFusion $fusion;
    private RedDeviceModel $devices;
    private RedDetectionModel $detections;
    private RedAlertModel $alerts;
    private RedDeliveryModel $deliveries;

    public function __construct(?Red $config = null)
    {
        $this->config = $config ?? config(Red::class);
        $this->fusion = new PwaveFusion(
            $this->config->pWaveVelocityKmS,
            $this->config->sWaveVelocityKmS,
            $this->config->defaultDepthKm,
        );
        $this->devices = new RedDeviceModel();
        $this->detections = new RedDetectionModel();
        $this->alerts = new RedAlertModel();
        $this->deliveries = new RedDeliveryModel();
    }

    public function configPublica(): array
    {
        return [
            'status' => 'ok',
            'network' => 'SIGRA Red de teléfonos',
            'version' => config(\Config\Sigra::class)->version,
            'api_version' => config(\Config\Sigra::class)->apiVersion,
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
            'server_time_ms' => (int) round(microtime(true) * 1000),
        ];
    }

    /**
     * Registra o actualiza un teléfono y entrega un token de dispositivo.
     *
     * @return array{status: string, device_token: string, device: array<string, mixed>, config: array<string, mixed>}
     */
    public function registrarDispositivo(array $payload): array
    {
        $uuid = trim((string) ($payload['device_uuid'] ?? ''));
        if (! preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $uuid)) {
            throw new RedApiException('device_uuid inválido. Debe tener entre 8 y 64 caracteres seguros.', 422);
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
            'last_seen_at' => date('Y-m-d H:i:s'),
            'status' => 'active',
        ];

        $this->aplicarUbicacion($datos, $payload);

        $existente = $this->devices->buscarPorUuid($uuid);
        if ($existente === null) {
            $id = (int) $this->devices->insert($datos);
        } else {
            $id = (int) $existente['id'];
            $this->devices->update($id, $datos);
        }

        $device = $this->devices->find($id);
        if (! is_array($device)) {
            throw new RedApiException('No fue posible registrar el teléfono.', 500);
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

    /**
     * Latido periódico: ubicación, estado del monitoreo y batería.
     */
    public function latir(array $device, array $payload): array
    {
        $datos = [
            'last_seen_at' => date('Y-m-d H:i:s'),
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
            'server_time_ms' => (int) round(microtime(true) * 1000),
            'device' => is_array($actualizado) ? $this->serializarDispositivo($actualizado) : null,
            'config' => $this->configPublica(),
            'active_alerts' => $this->contarAlertasActivas(),
        ];
    }

    /**
     * Recibe una detección de onda P y evalúa si la red debe emitir alerta.
     */
    public function registrarDeteccion(array $device, array $payload): array
    {
        $ahoraMs = (int) round(microtime(true) * 1000);
        $detectadoMs = $this->msDesdePayload($payload['detected_at_ms'] ?? null) ?? $ahoraMs;
        $edadSegundos = ($ahoraMs - $detectadoMs) / 1000;

        if ($edadSegundos > $this->config->maxDetectionAgeSeconds) {
            throw new RedApiException('La detección es demasiado antigua para ser útil.', 422);
        }
        if ($edadSegundos < -$this->config->futureToleranceSeconds) {
            throw new RedApiException('El reloj del teléfono está adelantado. Sincroniza la hora automática.', 422);
        }

        $lat = $this->numero($payload['latitude'] ?? $payload['lat'] ?? null);
        $lng = $this->numero($payload['longitude'] ?? $payload['lng'] ?? null);
        if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new RedApiException('Coordenadas inválidas.', 422);
        }

        $confianza = max(0.0, min(1.0, $this->numero($payload['confidence'] ?? null) ?? 0.5));
        if ($confianza < $this->config->minDetectionConfidence) {
            throw new RedApiException('La detección tiene muy poca confianza para ingresar a la red.', 422);
        }

        // Anti-abuso: separación mínima y cuota por minuto.
        $ultima = $device['last_detection_at'] ? strtotime((string) $device['last_detection_at']) : null;
        if ($ultima !== null && (time() - $ultima) < $this->config->minSecondsBetweenDetections) {
            throw new RedApiException('Espera unos segundos antes de enviar otra detección.', 429);
        }

        $enUltimoMinuto = $this->detections->contarDesde($ahoraMs - 60000, (int) $device['id']);
        if ($enUltimoMinuto >= $this->config->maxDetectionsPerMinute) {
            throw new RedApiException('Demasiadas detecciones en un minuto. El teléfono fue limitado temporalmente.', 429);
        }

        $existente = $this->detections->where('device_id', (int) $device['id'])
            ->where('detected_at_ms', $detectadoMs)
            ->first();
        if ($existente !== null) {
            return [
                'status' => 'ok',
                'accepted' => false,
                'duplicate' => true,
                'detection_id' => (int) $existente['id'],
                'alert' => null,
            ];
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
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $deviceId = (int) $device['id'];
        $detectionId = (int) $this->detections->insert($datos);
        $datos['id'] = $detectionId;

        $this->devices->update($deviceId, [
            'last_detection_at' => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
        ]);

        $evaluacion = $this->evaluarEvento($datos);

        return [
            'status' => 'ok',
            'accepted' => true,
            'detection_id' => $detectionId,
            'event' => $evaluacion['event'] ?? null,
            'alert' => $evaluacion['alert'] ?? null,
            'message' => $evaluacion['message'] ?? null,
        ];
    }

    /**
     * Alertas activas que cubren la posición del usuario.
     *
     * @return list<array<string, mixed>>
     */
    public function alertasCercanas(?array $device, float $lat, float $lng): array
    {
        $this->expirarVencidasConLimite();
        $alertas = $this->alerts->activas(30);
        $resultado = [];

        foreach ($alertas as $alerta) {
            $distancia = RedAlertModel::distanciaKm(
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

        if ($device !== null && $resultado !== []) {
            $ids = array_map(static fn (array $alerta): int => (int) $alerta['db_id'], $resultado);
            $this->deliveries->marcarVistas((int) $device['id'], $ids);
        }

        return $resultado;
    }

    public function estadisticasPublicas(?float $latUsuario = null, ?float $lngUsuario = null): array
    {
        $datos = $this->datosEstadisticas();
        $ultima = $datos['ultima'];
        $recientes = $datos['recientes'];

        return [
            'status' => 'ok',
            'generated_at' => date('c'),
            'server_time_ms' => (int) round(microtime(true) * 1000),
            'operational' => $datos['online'] >= $this->config->minDevices,
            'stats' => [
                'devices_total' => $datos['total_dispositivos'],
                'devices_online' => $datos['online'],
                'devices_monitoring' => $datos['monitoreando'],
                'detections_24h' => $datos['detecciones24'],
                'alerts_24h' => $datos['alertas24'],
            ],
            'last_alert' => $ultima !== null ? $this->serializarAlerta($ultima, $latUsuario, $lngUsuario) : null,
            'recent_alerts' => array_map(fn (array $alerta): array => $this->serializarAlerta($alerta, $latUsuario, $lngUsuario), $recientes),
            'devices' => $datos['devices'],
            'detections' => $datos['detections'],
            'config' => $this->configPublica(),
        ];
    }

    /**
     * Agregados de la red con caché corta.
     *
     * El panel y la app consultan cada 10 s. Cachear los conteos evita
     * repetir cinco COUNT y dos SELECT grandes en cada visita.
     *
     * @return array<string, mixed>
     */
    private function datosEstadisticas(): array
    {
        $ttl = max(0, $this->config->statsCacheSeconds);
        $cache = $ttl > 0 ? cache() : null;
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
        $alertas24 = $db->table('red_alerts')
            ->where('created_at >=', $desde24)
            ->countAllResults();

        $ultima = $db->table('red_alerts')->orderBy('created_at', 'DESC')->get(1)->getRowArray();
        $recientes = $db->table('red_alerts')
            ->orderBy('created_at', 'DESC')
            ->get(10)
            ->getResultArray();

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

    /**
     * Convierte un evento oficial del SGC en una alerta informativa.
     */
    public function crearAlertaSgc(array $evento): ?array
    {
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

    public function desactivarDispositivo(array $device): void
    {
        $this->devices->update((int) $device['id'], [
            'status' => 'inactive',
            'monitoring_enabled' => 0,
            'fcm_token' => null,
            'last_seen_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array{lat: float, lng: float, origin_time_ms: int} $origen
     * @return array<string, mixed>
     */
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
            'expires_at' => date('Y-m-d H:i:s', time() + ($this->config->alertTtlMinutes * 60)),
            'details' => json_encode($details + [
                'algorithm' => 'STA/LTA + TDOA',
                'p_wave_velocity_km_s' => $this->config->pWaveVelocityKmS,
                's_wave_velocity_km_s' => $this->config->sWaveVelocityKmS,
                'residual_s' => $origen['residual_s'] ?? null,
                'uncertainty_km' => $origen['uncertainty_km'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $id = (int) $this->alerts->insert($datos);
        $alerta = $this->alerts->find($id);
        if (! is_array($alerta)) {
            throw new RedApiException('No fue posible crear la alerta.', 500);
        }

        $this->entregarAlerta($alerta);

        return $alerta;
    }

    /**
     * @param array<string, mixed> $semilla
     * @return array{event: array<string, mixed>|null, alert: array<string, mixed>|null, message: string}
     */
    private function evaluarEvento(array $semilla): array
    {
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
            return [
                'event' => $resumen,
                'alert' => null,
                'message' => 'Evento fusionado por debajo del umbral de alerta.',
            ];
        }

        $existente = $this->alerts->recienteCercana($origen['lat'], $origen['lng'], 100.0, 120);
        if ($existente !== null && $existente['status'] === 'active') {
            $this->alerts->update((int) $existente['id'], [
                'magnitude_estimated' => max((float) $existente['magnitude_estimated'], $magnitud),
                'confidence' => max((float) $existente['confidence'], $confianza),
                'detection_count' => (int) $existente['detection_count'] + $origen['detection_count'],
                'device_count' => max((int) $existente['device_count'], $origen['device_count']),
                'expires_at' => date('Y-m-d H:i:s', time() + ($this->config->alertTtlMinutes * 60)),
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
            db_connect()->table('red_detections')->whereIn('id', $ids)->update(['cluster_id' => (int) $alerta['id']]);
        }

        return [
            'event' => $resumen,
            'alert' => $this->serializarAlerta($alerta),
            'message' => 'Evento confirmado por la red de teléfonos.',
        ];
    }

    /**
     * Calcula destinatarios y entrega la alerta por polling y push.
     */
    private function entregarAlerta(array $alerta): void
    {
        $candidatos = $this->devices->candidatosParaAlerta((float) $alerta['magnitude_estimated'], $this->config->deviceOnlineMinutes);
        $fcm = new FcmService($this->config);
        $ahoraMs = (int) round(microtime(true) * 1000);
        $enviados = 0;

        foreach ($candidatos as $dispositivo) {
            $distancia = RedAlertModel::distanciaKm(
                (float) $alerta['latitude'],
                (float) $alerta['longitude'],
                (float) $dispositivo['latitude'],
                (float) $dispositivo['longitude'],
            );

            $radio = min((float) $alerta['radius_km'], (float) $dispositivo['alert_radius_km']);
            if ($distancia > $radio) {
                continue;
            }

            $existente = $this->deliveries->where('alert_id', (int) $alerta['id'])
                ->where('device_id', (int) $dispositivo['id'])
                ->first();
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
                'created_at' => date('Y-m-d H:i:s'),
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
                'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
                'error_message' => $ok ? null : 'FCM no pudo entregar el mensaje.',
            ]);
            $enviados++;
        }
    }

    /**
     * @param array<string, mixed> $alerta
     * @return array<string, mixed>
     */
    private function serializarAlerta(array $alerta, ?float $latUsuario = null, ?float $lngUsuario = null): array
    {
        $lat = (float) $alerta['latitude'];
        $lng = (float) $alerta['longitude'];
        $magnitud = (float) $alerta['magnitude_estimated'];
        $distancia = ($latUsuario !== null && $lngUsuario !== null)
            ? RedAlertModel::distanciaKm($latUsuario, $lngUsuario, $lat, $lng)
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
                ? $this->fusion->segundosHastaOndaS($origin, $distancia)
                : null,
            'severity' => $this->fusion->nivelAlerta($magnitud),
            'estimated' => true,
            'disclaimer' => 'Estimación experimental de SIGRA. No sustituye a las autoridades.',
        ];
    }

    /**
     * @param array<string, mixed> $dispositivo
     * @return array<string, mixed>
     */
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

    private function textoPush(array $alerta, float $distancia, float $eta): string
    {
        $magnitud = number_format((float) $alerta['magnitude_estimated'], 1, '.', '');
        $distanciaTexto = number_format($distancia, 0, '.', '');

        if ($eta > 0) {
            return sprintf('M%.1f estimada a %s km. Onda S en ~%d s. Protege tu cabeza y aléjate de ventanas.', $magnitud, $distanciaTexto, (int) round($eta));
        }

        return sprintf('M%.1f estimada a %s km. La onda S ya está llegando. Agáchate, cúbrete y sujétate.', $magnitud, $distanciaTexto);
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
        $datos['location_updated_at'] = date('Y-m-d H:i:s');
    }

    private function contarAlertasActivas(): int
    {
        return $this->alerts->where('status', 'active')
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->countAllResults();
    }

    /**
     * Evita un UPDATE en cada sondeo: como mucho una pasada por minuto.
     */
    private function expirarVencidasConLimite(): void
    {
        $throttle = max(0, $this->config->expireThrottleSeconds);
        if ($throttle === 0) {
            $this->alerts->expirarVencidas();

            return;
        }

        $cache = cache();
        $clave = 'sigra_expire_alerts_ts';
        $ultima = (int) $cache->get($clave);
        if ($ultima > time() - $throttle) {
            return;
        }

        $this->alerts->expirarVencidas();
        $cache->save($clave, time(), $throttle * 2);
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

    private function uuidV4(): string
    {
        $datos = random_bytes(16);
        $datos[6] = chr((ord($datos[6]) & 0x0f) | 0x40);
        $datos[8] = chr((ord($datos[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($datos), 4));
    }
}
