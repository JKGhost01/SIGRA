<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Infrastructure\Sources;

use Config\Sgc;
use RuntimeException;
use App\Modules\SeismicIngestion\Domain\Services\ProviderEventValidator;

class SgcClient
{
    /**
     * Claves de `properties` que la aplicación realmente muestra.
     * Guardar el objeto completo del SGC duplicaba datos y engordaba
     * la tabla sin aportar nada.
     */
    private const CLAVES_METADATA = [
        'url',
        'agency',
        'cdi',
        'closerTowns',
        'felt',
        'gap',
        'localTime',
        'magType',
        'maxPGA',
        'mmi',
        'nst',
        'rms',
        'updated',
        'utcTime',
    ];

    private Sgc $config;

    public function __construct(?Sgc $config = null, private readonly ?FeedTransport $transport = null)
    {
        $this->config = $config ?? config(Sgc::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function obtenerEventos(): array
    {
        $contenido = ($this->transport ?? new CiFeedTransport())->json($this->config->feedUrl, 10, [
            'Accept' => 'application/json', 'User-Agent' => 'SIGRA/' . config(\Config\Sigra::class)->version,
            'Referer' => base_url(),
        ]);
        if (!is_array($contenido) || !isset($contenido['features']) || !is_array($contenido['features'])) {
            throw new RuntimeException('El SGC devolvió un formato de datos no esperado.');
        }

        return array_values(array_filter(array_map(
            fn (mixed $evento): ?array => $this->normalizarEvento($evento),
            $contenido['features'],
        )));
    }

    /**
     * @param mixed $evento
     * @return array<string, mixed>|null
     */
    private function normalizarEvento(mixed $evento): ?array
    {
        if (!is_array($evento) || !isset($evento['id'], $evento['geometry']['coordinates'], $evento['properties'])) {
            return null;
        }

        $coordenadas = $evento['geometry']['coordinates'];
        $propiedades = $evento['properties'];
        if (!is_array($coordenadas) || !isset($coordenadas[0], $coordenadas[1]) || !is_array($propiedades)) {
            return null;
        }

        $fechaLocal = $propiedades['localTime'] ?? null;
        $fechaUtc = $propiedades['utcTime'] ?? null;
        if (isset($propiedades['place']) && !is_string($propiedades['place'])) return null;
        $magnitud = $propiedades['mag'] ?? null;
        if (!ProviderEventValidator::scientificFields($coordenadas[0], $coordenadas[1], $magnitud, $coordenadas[2] ?? null)
            || (!is_string($fechaLocal) && !is_string($fechaUtc))) return null;

        $zonaLocal = new \DateTimeZone(config('App')->appTimezone);
        try {
            if (is_string($fechaLocal) && $fechaLocal !== '') {
                $fechaObjeto = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $fechaLocal, $zonaLocal);
                if (!$fechaObjeto || $fechaObjeto->format('Y-m-d H:i') !== $fechaLocal) return null;
            } else {
                if (!is_string($fechaUtc) || !preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $fechaUtc)) return null;
                $fechaObjeto = new \DateTimeImmutable($fechaUtc, new \DateTimeZone('UTC'));
                if (\DateTimeImmutable::getLastErrors() !== false) return null;
                $fechaObjeto = $fechaObjeto->setTimezone($zonaLocal);
            }
        } catch (\Exception) { return null; }
        $sourceId = ProviderEventValidator::sourceId($evento['id']);
        if ($sourceId === null) return null;
        $id = strlen($sourceId) <= 24 ? $sourceId : 'SGC-' . substr(sha1($sourceId), 0, 20);
        $metadata = ['source_id' => $sourceId, 'depth_known' => isset($coordenadas[2])];
        foreach (self::CLAVES_METADATA as $clave) {
            if (array_key_exists($clave, $propiedades)) {
                $metadata[$clave] = $propiedades[$clave];
            }
        }

        return [
            'id'          => $id,
            'fecha_hora'  => $fechaObjeto->format('Y-m-d H:i:s'),
            'latitud'     => round((float) $coordenadas[0], 6),
            'longitud'    => round((float) $coordenadas[1], 6),
            'magnitud'    => round((float) $magnitud, 1),
            'profundidad' => isset($coordenadas[2]) && is_numeric($coordenadas[2]) ? round((float) $coordenadas[2], 2) : 0,
            'ubicacion'   => (string) ($propiedades['place'] ?? 'Ubicación no informada'),
            'fuente'      => 'SGC',
            'estado'      => (string) ($propiedades['status'] ?? 'automatico'),
            'sgc_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }
}
