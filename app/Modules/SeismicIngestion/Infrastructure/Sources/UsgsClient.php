<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Infrastructure\Sources;

use Config\Quakes;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use App\Modules\SeismicIngestion\Domain\Services\ProviderEventValidator;

/**
 * Cliente de la red global USGS.
 *
 * Descarga el feed FDSN en GeoJSON y lo normaliza al mismo contrato que
 * usa SgcClient, de modo que el modelo, la API y el frontend no necesitan
 * saber de qué red viene cada evento.
 */
class UsgsClient
{
    private const CLAVES_METADATA = [
        'alert',
        'cdi',
        'felt',
        'gap',
        'magType',
        'mmi',
        'nst',
        'rms',
        'sig',
        'tsunami',
        'type',
        'updated',
        'url',
    ];

    private Quakes $config;

    public function __construct(?Quakes $config = null, private readonly ?FeedTransport $transport = null)
    {
        $this->config = $config ?? config(Quakes::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function obtenerEventos(): array
    {
        if (! $this->config->globalEnabled) {
            return [];
        }

        $contenido = ($this->transport ?? new CiFeedTransport())->json($this->construirUrl(), 20, [
            'Accept' => 'application/json', 'User-Agent' => 'SIGRA/' . config(\Config\Sigra::class)->version,
        ]);
        if (! is_array($contenido) || ! isset($contenido['features']) || ! is_array($contenido['features'])) {
            throw new RuntimeException('USGS devolvió un formato de datos no esperado.');
        }

        $eventos = [];
        foreach ($contenido['features'] as $feature) {
            $evento = $this->normalizarEvento($feature);
            if ($evento !== null) {
                $eventos[] = $evento;
            }
        }

        return $eventos;
    }

    private function construirUrl(): string
    {
        $zonaUtc = new DateTimeZone('UTC');
        $ahora   = new DateTimeImmutable('now', $zonaUtc);

        return $this->config->usgsEndpoint . '?' . http_build_query([
            'format'       => 'geojson',
            'starttime'    => $ahora->modify('-' . $this->config->windowDays . ' days')->format('Y-m-d\TH:i:s'),
            'endtime'      => $ahora->format('Y-m-d\TH:i:s'),
            'minmagnitude' => $this->config->usgsMinMagnitude,
            'orderby'      => 'time',
            'limit'        => $this->config->usgsLimit,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizarEvento(mixed $evento): ?array
    {
        if (! is_array($evento) || ! isset($evento['geometry']['coordinates'], $evento['properties'])) {
            return null;
        }

        $coordenadas = $evento['geometry']['coordinates'];
        $propiedades = $evento['properties'];
        if (! is_array($coordenadas) || !isset($coordenadas[0], $coordenadas[1]) || ! is_array($propiedades)) {
            return null;
        }

        if (isset($propiedades['place']) && !is_string($propiedades['place'])) return null;
        $magnitud = $propiedades['mag'] ?? null;
        $tiempoMs = $propiedades['time'] ?? null;
        if (!ProviderEventValidator::scientificFields($coordenadas[1], $coordenadas[0], $magnitud, $coordenadas[2] ?? null)
            || !ProviderEventValidator::measurement($tiempoMs, 0, 253402300799000)) return null;
        $id = ProviderEventValidator::sourceId($evento['id'] ?? null);
        if ($id === null) return null;

        // La columna id es varchar(24). Los ids largos se acortan de forma
        // determinista y el id original queda en la metadata.
        $sourceId = $id;
        if (strlen($id) > 24) {
            $id = 'USGS-' . substr(sha1($sourceId), 0, 19);
        }

        $fecha = (new DateTimeImmutable('@' . intdiv((int) $tiempoMs, 1000)))
            ->setTimezone(new DateTimeZone(config(\Config\App::class)->appTimezone));

        $metadata = [
            'agency'    => 'USGS',
            'depth_known' => isset($coordenadas[2]),
            'source_id' => $sourceId,
        ];
        foreach (self::CLAVES_METADATA as $clave) {
            if (array_key_exists($clave, $propiedades)) {
                $metadata[$clave] = $propiedades[$clave];
            }
        }

        return [
            'id'           => $id,
            'fecha_hora'   => $fecha->format('Y-m-d H:i:s'),
            'latitud'      => round((float) $coordenadas[0], 6),
            'longitud'     => round((float) $coordenadas[1], 6),
            'magnitud'     => round((float) $magnitud, 1),
            'profundidad'  => isset($coordenadas[2]) && is_numeric($coordenadas[2]) ? round((float) $coordenadas[2], 2) : 0.0,
            'ubicacion'    => (string) ($propiedades['place'] ?? 'Ubicación no informada'),
            'fuente'       => 'USGS',
            'estado'       => (string) ($propiedades['status'] ?? 'automatic'),
            'sgc_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }
}
