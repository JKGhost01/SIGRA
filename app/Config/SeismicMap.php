<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/** Motor del mapa principal. Detalle, reportes y red mantienen su mapa actual. */
final class SeismicMap extends BaseConfig
{
    public string $engine = 'cesium';

    public function __construct()
    {
        parent::__construct();
        $engine = (string) env('seismicMap.engine', $this->engine);
        $this->engine = in_array($engine, ['cesium', 'leaflet'], true) ? $engine : 'leaflet';
    }
}
