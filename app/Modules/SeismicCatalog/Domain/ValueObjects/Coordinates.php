<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Domain\ValueObjects;

final readonly class Coordinates
{
    public function __construct(public float $latitude, public float $longitude)
    {
        if (!is_finite($latitude) || $latitude < -90 || $latitude > 90
            || !is_finite($longitude) || $longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException('Coordenadas fuera de rango.');
        }
    }
}
