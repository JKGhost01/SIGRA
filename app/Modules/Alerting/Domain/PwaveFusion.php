<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

/**
 * PwaveFusion
 * -------------------------------------------------------------------
 * Fusión de detecciones de onda P reportadas por teléfonos.
 *
 * El teléfono detecta un cambio brusco de aceleración (STA/LTA) y envía
 * la hora exacta a la que cree que llegó la onda P. El servidor recibe
 * esas horas y resuelve, por diferencias de tiempo de llegada (TDOA),
 * una posición aproximada del origen y el instante de origen.
 *
 * El resultado es una ESTIMACIÓN. No sustituye a SGC, USGS ni a los
 * sistemas oficiales de alerta temprana. Se documenta así en la API y
 * en la aplicación Android.
 */
class PwaveFusion
{
    private float $vp;
    private float $vs;
    private float $defaultDepthKm;

    public function __construct(
        float $pWaveVelocityKmS = 6.0,
        float $sWaveVelocityKmS = 3.5,
        float $defaultDepthKm = 10.0,
    ) {
        $this->vp = max(1.0, $pWaveVelocityKmS);
        $this->vs = max(1.0, $sWaveVelocityKmS);
        $this->defaultDepthKm = max(0.0, $defaultDepthKm);
    }

    /**
     * Estima epicentro, instante de origen y residual de un grupo de
     * detecciones. Devuelve null si no hay al menos tres teléfonos.
     *
     * @param list<array<string, mixed>> $detecciones
     * @return array{lat: float, lng: float, origin_time_ms: int, residual_s: float, uncertainty_km: float, detection_count: int, device_count: int}|null
     */
    public function estimarOrigen(array $detecciones): ?array
    {
        $normalizadas = [];
        $dispositivos = [];

        foreach ($detecciones as $deteccion) {
            $lat = $this->numero($deteccion['lat'] ?? $deteccion['latitude'] ?? null);
            $lng = $this->numero($deteccion['lng'] ?? $deteccion['longitude'] ?? null);
            $tiempo = (int) ($deteccion['detected_at_ms'] ?? 0);
            $device = (string) ($deteccion['device_uuid'] ?? $deteccion['device_id'] ?? '');

            if ($lat === null || $lng === null || $tiempo <= 0 || $device === '') {
                continue;
            }

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }

            $normalizadas[] = [
                'lat' => $lat,
                'lng' => $lng,
                'detected_at_ms' => $tiempo,
                'peak_accel_mps2' => $this->numero($deteccion['peak_accel_mps2'] ?? null) ?? 0.0,
                'confidence' => max(0.0, min(1.0, $this->numero($deteccion['confidence'] ?? null) ?? 0.5)),
                'device_uuid' => $device,
            ];
            $dispositivos[$device] = true;
        }

        $deviceCount = count($dispositivos);
        if ($deviceCount < 3 || count($normalizadas) < 3) {
            return null;
        }

        // Centro inicial ponderado por confianza.
        $pesoTotal = 0.0;
        $latCentro = 0.0;
        $lngCentro = 0.0;
        $minLat = 90.0;
        $maxLat = -90.0;
        $minLng = 180.0;
        $maxLng = -180.0;

        foreach ($normalizadas as $deteccion) {
            $peso = max(0.1, $deteccion['confidence']);
            $latCentro += $deteccion['lat'] * $peso;
            $lngCentro += $deteccion['lng'] * $peso;
            $pesoTotal += $peso;
            $minLat = min($minLat, $deteccion['lat']);
            $maxLat = max($maxLat, $deteccion['lat']);
            $minLng = min($minLng, $deteccion['lng']);
            $maxLng = max($maxLng, $deteccion['lng']);
        }

        $latCentro /= $pesoTotal;
        $lngCentro /= $pesoTotal;

        $latSpan = max(0.12, $maxLat - $minLat);
        $lngSpan = max(0.12, $maxLng - $minLng);
        $margenLat = max(0.35, min(1.4, $latSpan * 1.2));
        $margenLng = max(0.35, min(1.8, $lngSpan * 1.2));
        $paso = max(0.008, min(0.06, max($latSpan, $lngSpan) / 45));

        $mejor = $this->buscarMejorPunto(
            $normalizadas,
            $latCentro - $margenLat,
            $latCentro + $margenLat,
            $lngCentro - $margenLng,
            $lngCentro + $margenLng,
            $paso,
        );

        if ($mejor === null) {
            return null;
        }

        // Segunda pasada fina alrededor del mejor punto.
        $pasoFino = max(0.0015, $paso / 6);
        $mejorFino = $this->buscarMejorPunto(
            $normalizadas,
            $mejor['lat'] - ($paso * 3),
            $mejor['lat'] + ($paso * 3),
            $mejor['lng'] - ($paso * 3),
            $mejor['lng'] + ($paso * 3),
            $pasoFino,
        );

        if ($mejorFino !== null && $mejorFino['residual_s'] <= $mejor['residual_s']) {
            $mejor = $mejorFino;
        }

        return [
            'lat' => round($mejor['lat'], 6),
            'lng' => round($mejor['lng'], 6),
            'origin_time_ms' => (int) round($mejor['origin_time_ms']),
            'residual_s' => round($mejor['residual_s'], 3),
            'uncertainty_km' => round($mejor['residual_s'] * $this->vp, 1),
            'detection_count' => count($normalizadas),
            'device_count' => $deviceCount,
        ];
    }

    /**
     * Magnitud preliminar a partir de la mayor aceleración observada.
     * Es una regresión empírica simple; la app la etiqueta como estimada.
     *
     * @param list<array<string, mixed>> $detecciones
     * @param array{lat: float, lng: float} $origen
     */
    public function estimarMagnitud(array $detecciones, array $origen): float
    {
        $mayor = null;
        $distancia = 10.0;

        foreach ($detecciones as $deteccion) {
            $lat = $this->numero($deteccion['lat'] ?? $deteccion['latitude'] ?? null);
            $lng = $this->numero($deteccion['lng'] ?? $deteccion['longitude'] ?? null);
            $pico = $this->numero($deteccion['peak_accel_mps2'] ?? null);

            if ($lat === null || $lng === null || $pico === null || $pico <= 0) {
                continue;
            }

            if ($mayor === null || $pico > $mayor) {
                $mayor = $pico;
                $distancia = max(3.0, self::distanciaKm($origen['lat'], $origen['lng'], $lat, $lng));
            }
        }

        if ($mayor === null) {
            return 1.5;
        }

        // m/s² -> cm/s². Relación empírica de atenuación somera.
        $pgaCmS2 = max(0.1, $mayor * 100.0);
        $magnitud = 2.0 + log10($pgaCmS2) + log10($distancia);

        return round(max(1.5, min(7.5, $magnitud)), 1);
    }

    /**
     * Confianza global del evento fusionado (0..1).
     *
     * @param list<array<string, mixed>> $detecciones
     * @param array{residual_s: float} $origen
     */
    public function estimarConfianza(array $detecciones, array $origen): float
    {
        $confianzas = [];
        $dispositivos = [];

        foreach ($detecciones as $deteccion) {
            $confianzas[] = max(0.0, min(1.0, $this->numero($deteccion['confidence'] ?? null) ?? 0.5));
            $device = (string) ($deteccion['device_uuid'] ?? $deteccion['device_id'] ?? '');
            if ($device !== '') {
                $dispositivos[$device] = true;
            }
        }

        $promedio = $confianzas === [] ? 0.0 : array_sum($confianzas) / count($confianzas);
        $residual = max(0.0, (float) ($origen['residual_s'] ?? 3.0));
        $calidadTemporal = 1.0 - min(1.0, $residual / 3.0);
        $cantidad = min(0.3, 0.08 * count($dispositivos));

        $confianza = 0.25 + $cantidad + (0.25 * $promedio) + (0.2 * $calidadTemporal);

        return round(max(0.05, min(0.95, $confianza)), 3);
    }

    public function radioAlertaKm(float $magnitud, float $base = 80.0, float $porMagnitud = 55.0, float $maximo = 500.0): float
    {
        return round(max($base, min($maximo, $base + ($porMagnitud * max(0.0, $magnitud)))), 1);
    }

    /**
     * Segundos restantes hasta la onda S para un punto.
     */
    public function segundosHastaOndaS(int $originTimeMs, float $distanciaKm, ?int $ahoraMs = null): float
    {
        $ahoraMs ??= (int) round(microtime(true) * 1000);
        $llegada = $originTimeMs + (($distanciaKm / $this->vs) * 1000);

        return round(($llegada - $ahoraMs) / 1000, 1);
    }

    public function nivelAlerta(float $magnitud): string
    {
        if ($magnitud >= 5.5) {
            return 'roja';
        }
        if ($magnitud >= 4.5) {
            return 'naranja';
        }
        if ($magnitud >= 3.5) {
            return 'amarilla';
        }

        return 'informativa';
    }

    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radioTierra = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $radioTierra * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
    }

    /**
     * Reúne detecciones compatibles con la semilla: misma ventana de
     * tiempo, cerca del dispositivo que reportó primero y de teléfonos
     * distintos.
     *
     * @param list<array<string, mixed>> $detecciones
     * @return list<array<string, mixed>>
     */
    public function agrupar(array $detecciones, array $semilla, int $ventanaSegundos, float $radioKm): array
    {
        $latSemilla = $this->numero($semilla['lat'] ?? null);
        $lngSemilla = $this->numero($semilla['lng'] ?? null);
        $tiempoSemilla = (int) ($semilla['detected_at_ms'] ?? 0);

        if ($latSemilla === null || $lngSemilla === null || $tiempoSemilla <= 0) {
            return [];
        }

        $ventanaMs = $ventanaSegundos * 1000;
        $grupo = [];
        $dispositivos = [];

        foreach ($detecciones as $deteccion) {
            $lat = $this->numero($deteccion['lat'] ?? $deteccion['latitude'] ?? null);
            $lng = $this->numero($deteccion['lng'] ?? $deteccion['longitude'] ?? null);
            $tiempo = (int) ($deteccion['detected_at_ms'] ?? 0);
            $device = (string) ($deteccion['device_uuid'] ?? $deteccion['device_id'] ?? '');

            if ($lat === null || $lng === null || $tiempo <= 0 || $device === '') {
                continue;
            }

            // La primera detección es la estación más cercana. Las llegadas
            // posteriores pertenecen al mismo evento; las anteriores solo
            // pueden deberse a desfase de reloj (tolerancia de 2 s).
            if ($tiempo < $tiempoSemilla - 2000 || $tiempo > $tiempoSemilla + $ventanaMs) {
                continue;
            }
            if (self::distanciaKm($latSemilla, $lngSemilla, $lat, $lng) > $radioKm) {
                continue;
            }

            // Una detección por teléfono: la más cercana al instante semilla.
            if (isset($dispositivos[$device])) {
                $anterior = $grupo[$dispositivos[$device]];
                if (abs($tiempo - $tiempoSemilla) < abs((int) $anterior['detected_at_ms'] - $tiempoSemilla)) {
                    $grupo[$dispositivos[$device]] = $deteccion;
                }
                continue;
            }

            $dispositivos[$device] = count($grupo);
            $grupo[] = $deteccion;
        }

        return $grupo;
    }

    /**
     * @param list<array<string, mixed>> $detecciones
     * @return array{lat: float, lng: float, origin_time_ms: float, residual_s: float}|null
     */
    private function buscarMejorPunto(
        array $detecciones,
        float $minLat,
        float $maxLat,
        float $minLng,
        float $maxLng,
        float $paso,
    ): ?array {
        $mejor = null;
        $mejorResidual = INF;

        for ($lat = $minLat; $lat <= $maxLat; $lat += $paso) {
            for ($lng = $minLng; $lng <= $maxLng; $lng += $paso) {
                $t0s = [];
                foreach ($detecciones as $deteccion) {
                    $distancia = self::distanciaKm($lat, $lng, $deteccion['lat'], $deteccion['lng']);
                    $t0s[] = $deteccion['detected_at_ms'] - (($distancia / $this->vp) * 1000);
                }

                if ($t0s === []) {
                    continue;
                }

                $media = array_sum($t0s) / count($t0s);
                $varianza = 0.0;
                foreach ($t0s as $t0) {
                    $varianza += ($t0 - $media) ** 2;
                }
                $residual = sqrt($varianza / count($t0s)) / 1000; // segundos

                if ($residual < $mejorResidual) {
                    $mejorResidual = $residual;
                    $mejor = [
                        'lat' => $lat,
                        'lng' => $lng,
                        'origin_time_ms' => $media,
                        'residual_s' => $residual,
                    ];
                }
            }
        }

        return $mejor;
    }

    private function numero(mixed $valor): ?float
    {
        if ((is_int($valor) || is_float($valor)) && is_finite((float) $valor)) {
            return (float) $valor;
        }
        if (is_string($valor) && is_numeric($valor) && is_finite((float) $valor)) {
            return (float) $valor;
        }

        return null;
    }
}
