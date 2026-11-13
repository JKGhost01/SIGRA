<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Presentation\ViewModels;

final readonly class ListEventsViewModel
{
    public function __construct(public array $events, public array $initialEvents) {}
    public static function fromResult(array $result): self
    {
        $rows = array_map(static fn($dto) => $dto->record, $result);
        return new self($rows, array_map(static function (array $sismo): array {
            $metadata = json_decode((string) ($sismo['sgc_metadata'] ?? ''), true);
            $metadata = is_array($metadata) ? $metadata : [];

            return [
                'id'                 => $sismo['id'],
                'lat'                => (float) $sismo['latitud'],
                'lng'                => (float) $sismo['longitud'],
                'magnitud'           => $sismo['magnitud'],
                'profundidad'        => $sismo['profundidad'],
                'ubicacion'          => $sismo['ubicacion'],
                'fecha_hora'         => $sismo['fecha_hora'],
                'fuente'             => $sismo['fuente'],
                'estado'             => $sismo['estado'] ?? 'automatico',
                'intensidadPercibida' => $metadata['cdi'] ?? null,
                'intensidadInstrumental' => $metadata['mmi'] ?? null,
                'aceleracion'        => $metadata['maxPGA'] ?? null,
                'citizen_reports_count' => (int) ($sismo['citizen_reports_count'] ?? 0),
                'sentido'            => $metadata['felt'] ?? null,
                'url'                => \App\Shared\Domain\ExternalUrl::http($metadata['url'] ?? null),
                'municipios'         => trim((string) $sismo['ubicacion'] . ' ' . (string) ($metadata['closerTowns'] ?? '')),
            ];
        }, $rows));
    }
}
