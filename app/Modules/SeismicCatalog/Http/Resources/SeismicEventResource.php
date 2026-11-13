<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Http\Resources;

use App\Modules\SeismicCatalog\Application\DTO\SeismicEventDTO;
use App\Modules\Alerting\Domain\MagnitudeDisplay;
use App\Shared\Domain\GeoDistance;
final class SeismicEventResource
{
    public function serialize(SeismicEventDTO $dto, ?float $originLat = null, ?float $originLng = null): array
    {
        $evento = $dto->record;
        $latitud = (float) ($evento['latitud'] ?? 0);
        $longitud = (float) ($evento['longitud'] ?? 0);
        $magnitud = (float) ($evento['magnitud'] ?? 0);
        $distancia = ($originLat !== null && $originLng !== null ? GeoDistance::km($originLat, $originLng, $latitud, $longitud) : null);
        $metadata = json_decode((string) ($evento['sgc_metadata'] ?? ''), true);
        $metadata = is_array($metadata) ? $metadata : [];
        $fecha = (string) ($evento['fecha_hora'] ?? '');
        $fuente = (string) ($evento['fuente'] ?? 'SGC');

        return [
            'id' => (string) ($evento['id'] ?? ''),
            'citizen_reports_count' => (int) ($evento['citizen_reports_count'] ?? 0),
            'source_id' => (string) ($metadata['source_id'] ?? $evento['id'] ?? ''),
            'ubicacion' => (string) ($evento['ubicacion'] ?? 'Sin ubicación'),
            'latitud' => $latitud,
            'longitud' => $longitud,
            'magnitud' => $magnitud,
            'profundidad' => (float) ($evento['profundidad'] ?? 0),
            'fecha' => $fecha,
            'fecha_iso' => $fecha !== '' ? date('c', strtotime($fecha)) : null,
            'distancia_km' => $distancia !== null ? round($distancia, 1) : null,
            'alerta' => MagnitudeDisplay::color($magnitud),
            'nivel' => MagnitudeDisplay::level($magnitud),
            'estado' => (string) ($evento['estado'] ?? 'automatico'),
            'fuente' => $fuente,
            'cdi' => $this->numeroMetadata($metadata, 'cdi'),
            'mmi' => $this->numeroMetadata($metadata, 'mmi'),
            'pga' => $this->numeroMetadata($metadata, 'maxPGA'),
            'mag_type' => $metadata['magType'] ?? null,
            'agency' => $metadata['agency'] ?? $fuente,
            'felt' => isset($metadata['felt']) ? (int) $metadata['felt'] : null,
            'tsunami' => isset($metadata['tsunami']) ? (int) $metadata['tsunami'] : null,
            'significance' => isset($metadata['sig']) ? (int) $metadata['sig'] : null,
            'closer_towns' => $metadata['closerTowns'] ?? null,
            'official_url' => \App\Shared\Domain\ExternalUrl::http($metadata['url'] ?? null),
            'detalle_url' => site_url('sismos/detalle/' . rawurlencode((string) ($evento['id'] ?? ''))),
            'mapa_sgc_url' => $fuente === 'SGC' && ! empty($evento['id'])
                ? 'https://archive.sgc.gov.co/events/' . rawurlencode((string) $evento['id']) . '/map.png'
                : null,
        ];
    }

    private function numeroMetadata(array $metadata, string $clave): ?float
    {
        if (! isset($metadata[$clave]) || $metadata[$clave] === '' || $metadata[$clave] === null) {
            return null;
        }

        return is_numeric($metadata[$clave]) ? (float) $metadata[$clave] : null;
    }

}
