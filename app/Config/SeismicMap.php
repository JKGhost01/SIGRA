<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/** Motor del mapa principal. Detalle, reportes y red mantienen su mapa actual. */
final class SeismicMap extends BaseConfig
{
    public string $engine = 'cesium';
    public string $osmUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    public string $topoUrl = 'https://tile.opentopomap.org/{z}/{x}/{y}.png';
    public string $satelliteUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
    public string $satelliteCredit = 'Tiles © Esri';
    public int $satelliteMaxLevel = 16;
    public string $cesiumBaseUrl = 'https://cesium.com/downloads/cesiumjs/releases/1.146/Build/Cesium/';

    public function __construct()
    {
        parent::__construct();
        $engine = (string) env('seismicMap.engine', $this->engine);
        $this->engine = in_array($engine, ['cesium', 'leaflet'], true) ? $engine : 'leaflet';
        foreach (['osmUrl', 'topoUrl', 'satelliteUrl'] as $field) {
            $candidate = (string) env('seismicMap.' . $field, $this->{$field});
            if (preg_match('~^(https?://|/)[^\s]+$~', $candidate)
                && str_contains($candidate, '{z}') && str_contains($candidate, '{x}') && str_contains($candidate, '{y}')) {
                $this->{$field} = $candidate;
            }
        }
        $this->satelliteCredit = (string) env('seismicMap.satelliteCredit', $this->satelliteCredit);
        $this->satelliteMaxLevel = max(1, min(22, (int) env('seismicMap.satelliteMaxLevel', $this->satelliteMaxLevel)));
        $assets = (string) env('seismicMap.cesiumBaseUrl', $this->cesiumBaseUrl);
        if (preg_match('~^(https?://|/)[^\s]+/$~', $assets)) $this->cesiumBaseUrl = $assets;
    }
}
