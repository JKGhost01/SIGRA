<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración de la Red SIGRA de teléfonos.
 *
 * Ningún secreto debe vivir aquí: los valores sensibles se leen de .env
 * (red.appKey, red.fcmCredentials).
 */
class Red extends BaseConfig
{
    /**
     * Clave pública de la aplicación móvil. Se envía en la cabecera
     * X-SIGRA-KEY. No es un secreto criptográfico: evita tráfico casual.
     * Si queda vacía se reutiliza APP_API_TOKEN.
     */
    public string $appKey = '';

    /** Teléfonos distintos mínimos para aceptar un evento ciudadano. */
    public int $minDevices = 3;

    /** Ventana temporal para agrupar detecciones de un mismo evento. */
    public int $clusterWindowSeconds = 25;

    /** Radio espacial para agrupar detecciones de un mismo evento. */
    public float $clusterRadiusKm = 80.0;

    /** Confianza mínima que debe reportar cada teléfono. */
    public float $minDetectionConfidence = 0.35;

    /** Magnitud mínima estimada para emitir una alerta. */
    public float $minAlertMagnitude = 3.0;

    /** Confianza mínima del evento fusionado para emitir alerta. */
    public float $minAlertConfidence = 0.45;

    /** Límite de detecciones por teléfono y minuto. */
    public int $maxDetectionsPerMinute = 30;

    /** Separación mínima entre dos detecciones del mismo teléfono. */
    public int $minSecondsBetweenDetections = 2;

    /** Tolerancia máxima de antigüedad de una detección. */
    public int $maxDetectionAgeSeconds = 120;

    /** Tolerancia de reloj hacia el futuro. */
    public int $futureToleranceSeconds = 30;

    /** Velocidad de la onda P usada para localizar el origen. */
    public float $pWaveVelocityKmS = 6.0;

    /** Velocidad de la onda S usada para calcular el tiempo restante. */
    public float $sWaveVelocityKmS = 3.5;

    /** Profundidad asumida cuando no hay información (km). */
    public float $defaultDepthKm = 10.0;

    /** Radio base de la zona de alerta (km). */
    public float $alertRadiusBaseKm = 80.0;

    /** Kilómetros extra de radio por unidad de magnitud. */
    public float $alertRadiusPerMagnitudeKm = 55.0;

    /** Radio máximo de alerta (km). */
    public float $alertMaxRadiusKm = 500.0;

    /** Vigencia de una alerta ciudadana (minutos). */
    public int $alertTtlMinutes = 10;

    /** Un teléfono se considera en línea si envió señal en estos minutos. */
    public int $deviceOnlineMinutes = 10;

    /** Decimales con los que se publican coordenadas de teléfonos (privacidad). */
    public int $publicDevicePrecision = 2;

    /** Push FCM (opcional). Si no hay credenciales, la app usa polling. */
    public bool $fcmEnabled = false;

    /** Ruta al JSON de la cuenta de servicio de Firebase. */
    public string $fcmCredentials = '';

    /** Identificador del proyecto Firebase. */
    public string $fcmProjectId = '';

    /** Tiempo máximo de espera al enviar un push (segundos). */
    public int $fcmTimeout = 4;

    /** URL pública del APK (opcional). Si está vacía, la web explica cómo compilarlo. */
    public string $apkUrl = '';

    /**
     * Segundos que se reutilizan las estadísticas públicas.
     * El panel consulta cada 10 s: sin caché cada visita ejecuta
     * cinco COUNT y dos SELECT sobre las tablas de la red.
     * 0 desactiva la caché.
     */
    public int $statsCacheSeconds = 10;

    /** Segundos mínimos entre pasadas de expiración de alertas. */
    public int $expireThrottleSeconds = 60;

    /** Días de retención de detecciones, entregas y alertas expiradas. */
    public int $retentionDays = 7;

    public function __construct()
    {
        parent::__construct();

        $appKey = (string) env('red.appKey', '');
        $this->appKey = $appKey !== '' ? $appKey : (string) env('APP_API_TOKEN', '');

        $this->minDevices                = (int) env('red.minDevices', $this->minDevices);
        $this->minAlertMagnitude         = (float) env('red.minAlertMagnitude', $this->minAlertMagnitude);
        $this->minAlertConfidence        = (float) env('red.minAlertConfidence', $this->minAlertConfidence);
        $this->maxDetectionsPerMinute    = (int) env('red.maxDetectionsPerMinute', $this->maxDetectionsPerMinute);
        $this->minSecondsBetweenDetections = (int) env('red.minSecondsBetweenDetections', $this->minSecondsBetweenDetections);
        $this->alertTtlMinutes           = (int) env('red.alertTtlMinutes', $this->alertTtlMinutes);
        $this->fcmEnabled                = filter_var(env('red.fcmEnabled', $this->fcmEnabled), FILTER_VALIDATE_BOOL);
        $this->fcmCredentials            = (string) env('red.fcmCredentials', $this->fcmCredentials);
        $this->fcmProjectId              = (string) env('red.fcmProjectId', $this->fcmProjectId);
        $this->apkUrl                    = (string) env('red.apkUrl', $this->apkUrl);
        $this->statsCacheSeconds         = (int) env('red.statsCacheSeconds', $this->statsCacheSeconds);
        $this->expireThrottleSeconds     = (int) env('red.expireThrottleSeconds', $this->expireThrottleSeconds);
        $this->retentionDays             = (int) env('red.retentionDays', $this->retentionDays);
    }
}
