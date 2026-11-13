<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración de las fuentes sísmicas globales.
 *
 * SIGRA unifica en una sola tabla y en una sola vista dos redes:
 *   - SGC  (Servicio Geológico Colombiano): detalle local y eventos de baja magnitud.
 *   - USGS (U.S. Geological Survey): cobertura mundial, magnitud 2.5+.
 *
 * Los valores se pueden sobrescribir desde .env sin tocar código:
 *
 *   quakes.usgsMinMagnitude = 2.5
 *   quakes.globalEnabled = true
 *   quakes.cacheSeconds = 60
 */
class Quakes extends BaseConfig
{
    /** Activa/desactiva la fuente global USGS. */
    public bool $globalEnabled = true;

    /** Activa/desactiva la fuente local SGC. */
    public bool $sgcEnabled = true;

    /** Endpoint FDSN de USGS (GeoJSON). */
    public string $usgsEndpoint = 'https://earthquake.usgs.gov/fdsnws/event/1/query';

    /** Magnitud mínima global. 2.5 mantiene el mapa legible y evita ruido. */
    public float $usgsMinMagnitude = 2.5;

    /** Máximo de eventos por consulta a USGS. */
    public int $usgsLimit = 2000;

    /** Ventana temporal compartida por todas las fuentes, en días. */
    public int $windowDays = 5;

    /** Intervalo mínimo entre descargas automáticas al consultar el mapa. */
    public int $cacheSeconds = 60;

    /** Evita duplicar el mismo sismo cuando aparece en SGC y en USGS. */
    public bool $dedupeCrossSource = true;

    /** Distancia máxima para considerar que dos eventos son el mismo. */
    public float $dedupeDistanceKm = 75.0;

    /** Diferencia máxima de tiempo para considerar que dos eventos son el mismo. */
    public int $dedupeSeconds = 120;

    /** Maximum magnitude difference when both providers supply a value. */
    public float $dedupeMagnitudeDelta = 1.0;

    public function __construct()
    {
        parent::__construct();

        $this->globalEnabled = filter_var(env('quakes.globalEnabled', $this->globalEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->sgcEnabled    = filter_var(env('quakes.sgcEnabled', $this->sgcEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->usgsEndpoint  = (string) env('quakes.usgsEndpoint', $this->usgsEndpoint);
        $this->usgsMinMagnitude = (float) env('quakes.usgsMinMagnitude', $this->usgsMinMagnitude);
        $this->usgsLimit        = (int) env('quakes.usgsLimit', $this->usgsLimit);
        $this->windowDays       = max(1, (int) env('quakes.windowDays', $this->windowDays));
        $this->cacheSeconds     = max(5, (int) env('quakes.cacheSeconds', $this->cacheSeconds));
        $this->dedupeCrossSource = filter_var(env('quakes.dedupeCrossSource', $this->dedupeCrossSource), FILTER_VALIDATE_BOOLEAN);
        $this->dedupeDistanceKm  = (float) env('quakes.dedupeDistanceKm', $this->dedupeDistanceKm);
        $this->dedupeMagnitudeDelta = max(0.0, (float) env('quakes.dedupeMagnitudeDelta', $this->dedupeMagnitudeDelta));
        $this->dedupeSeconds     = (int) env('quakes.dedupeSeconds', $this->dedupeSeconds);
    }
}
