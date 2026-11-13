<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración de las fuentes sísmicas globales.
 *
 * SIGRA unifica seis catálogos oficiales en una tabla y una vista:
 *   - SGC  (Servicio Geológico Colombiano): detalle local y eventos de baja magnitud.
 *   - USGS (U.S. Geological Survey): cobertura mundial, magnitud 2.5+.
 *
 *   - EMSC: catálogo mundial del Centro Sismológico Euromediterráneo.
 *   - GeoNet: eventos recientes de Nueva Zelanda (máximo 100 del proveedor).
 *
 *   - GEOFON/GFZ: catálogo mundial reciente (feed limitado).
 *   - INGV: catálogo de Italia y su entorno.
 *
 * Los valores se pueden sobrescribir desde .env sin tocar código:
 *
 *   quakes.usgsMinMagnitude = 2.5
 *   quakes.globalEnabled = true
 *   quakes.cacheSeconds = 300
 */
class Quakes extends BaseConfig
{
    /** Activa/desactiva la fuente global USGS. */
    public bool $globalEnabled = true;

    /** Activa/desactiva la fuente local SGC. */
    public bool $sgcEnabled = true;

    /** Additional official catalogues, independently configurable. */
    public bool $emscEnabled = true;
    public bool $geonetEnabled = true;
    public bool $geofonEnabled = true;
    public bool $ingvEnabled = true;
    public string $geofonEndpoint = 'https://geofon.gfz.de/eqinfo/list.php?fmt=geojson';
    public string $ingvEndpoint = 'https://webservices.ingv.it/fdsnws/event/1/query';
    public string $emscEndpoint = 'https://www.seismicportal.eu/fdsnws/event/1/query';
    public string $geonetEndpoint = 'https://api.geonet.org.nz/quake';

    /** Endpoint FDSN de USGS (GeoJSON). */
    public string $usgsEndpoint = 'https://earthquake.usgs.gov/fdsnws/event/1/query';

    /** Use the static weekly feed for compatible USGS map queries. */
    public bool $usgsUseSummaryFeed = true;

    /** Magnitud mínima global. 2.5 mantiene el mapa legible y evita ruido. */
    public float $usgsMinMagnitude = 2.5;

    /** Máximo de eventos por consulta a USGS. */
    public int $usgsLimit = 2000;

    /** Ventana temporal compartida por todas las fuentes, en días. */
    public int $windowDays = 5;

    /** Intervalo mínimo entre descargas automáticas al consultar el mapa. */
    public int $cacheSeconds = 300;

    /** Evita duplicar el mismo sismo cuando aparece en varias fuentes. */
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
        $this->emscEnabled = filter_var(env('quakes.emscEnabled', $this->emscEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->geonetEnabled = filter_var(env('quakes.geonetEnabled', $this->geonetEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->geofonEnabled = filter_var(env('quakes.geofonEnabled', $this->geofonEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->ingvEnabled = filter_var(env('quakes.ingvEnabled', $this->ingvEnabled), FILTER_VALIDATE_BOOLEAN);
        $this->geofonEndpoint = (string) env('quakes.geofonEndpoint', $this->geofonEndpoint);
        $this->ingvEndpoint = (string) env('quakes.ingvEndpoint', $this->ingvEndpoint);
        $this->emscEndpoint = (string) env('quakes.emscEndpoint', $this->emscEndpoint);
        $this->geonetEndpoint = (string) env('quakes.geonetEndpoint', $this->geonetEndpoint);
        $this->usgsUseSummaryFeed = filter_var(env('quakes.usgsUseSummaryFeed', $this->usgsUseSummaryFeed), FILTER_VALIDATE_BOOLEAN);
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
