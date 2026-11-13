<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\Queries\ListEvents;

final readonly class ListEventsQuery
{
    public function __construct(
        public int $hours = 120,
        public int $limit = 300,
        public ?float $minMagnitude = null,
        public string $source = '',
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?float $radiusKm = null,
        public string $order = 'fecha',
    ) {
        if ($hours < 1 || $hours > 336 || $limit < 1 || $limit > 1000
            || !in_array($order, ['fecha', 'distancia', 'magnitud'], true)) {
            throw new \InvalidArgumentException('Consulta fuera de rango.');
        }
        if (($latitude === null) !== ($longitude === null)) throw new \InvalidArgumentException('Coordenadas incompletas.');
        if ($latitude !== null) new \App\Modules\SeismicCatalog\Domain\ValueObjects\Coordinates($latitude, $longitude);
        if (($radiusKm !== null || $order === 'distancia') && $latitude === null) throw new \InvalidArgumentException('Se requiere un origen.');
        if ($radiusKm !== null && (!is_finite($radiusKm) || $radiusKm < 0 || $radiusKm > 20000)) throw new \InvalidArgumentException('Radio fuera de rango.');
        if ($minMagnitude !== null && (!is_finite($minMagnitude) || $minMagnitude < -2 || $minMagnitude > 10)) throw new \InvalidArgumentException('Magnitud fuera de rango.');
    }
}
