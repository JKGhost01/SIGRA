<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Http\Requests;

use App\Shared\Http\ApiInput;
use App\Libraries\RedApiException;
use App\Modules\SeismicCatalog\Application\Queries\ListEvents\ListEventsQuery;
use CodeIgniter\HTTP\IncomingRequest;

final class ListEventsRequest
{
    public function parse(IncomingRequest $request): ListEventsQuery
    {
        $horas = $this->integer($request, 'hours', 120, 1, 24 * 14);
        $limite = $this->integer($request, 'limit', 300, 1, 1000);
        $minMagnitud = $this->number($request, 'min_magnitude', null);
        $fuente = ApiInput::text($request->getGet('fuente') ?? '', 'fuente');
        [$origenLat, $origenLng] = ApiInput::coordinates(
            $request->getGet('lat'), $request->getGet('lng'));
        $radioKm = $this->number($request, 'radius_km', null);
        $orden = ApiInput::text($request->getGet('order') ?? 'fecha', 'order');
        if (!in_array($orden, ['fecha', 'distancia', 'magnitud'], true)) {
            throw new RedApiException('order debe ser fecha, distancia o magnitud.');
        }
        if (($radioKm !== null || $orden === 'distancia') && $origenLat === null) {
            throw new RedApiException('El radio y el orden por distancia requieren lat y lng.');
        }
        if ($radioKm !== null) ApiInput::number($radioKm, 'radius_km', 0, 20000);
        if ($minMagnitud !== null) ApiInput::number($minMagnitud, 'min_magnitude', -2, 10);

        return new ListEventsQuery($horas, $limite, $minMagnitud, $fuente, $origenLat, $origenLng, $radioKm, $orden);
    }
    private function integer(IncomingRequest $request, string $key, int $default, int $min, int $max): int
    { $v = $request->getGet($key); return $v === null ? $default : ApiInput::integer($v, $key, $min, $max); }
    private function number(IncomingRequest $request, string $key, ?float $default): ?float
    { $v = $request->getGet($key); return $v === null ? $default : ApiInput::number($v, $key, -PHP_FLOAT_MAX, PHP_FLOAT_MAX); }
}
