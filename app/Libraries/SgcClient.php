<?php

namespace App\Libraries;

use Config\Sgc;
use Config\Services;
use RuntimeException;

class SgcClient
{
    /**
     * Claves de `properties` que la aplicación realmente muestra.
     * Guardar el objeto completo del SGC duplicaba datos y engordaba
     * la tabla sin aportar nada.
     */
    private const CLAVES_METADATA = [
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

    public function __construct(?Sgc $config = null)
    {
        $this->config = $config ?? config(Sgc::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function obtenerEventos(): array
    {
        $cliente = Services::curlrequest([
            'timeout'     => 10,
            'http_errors' => false,
            'verify'      => ENVIRONMENT !== 'development',
            'headers'     => [
                'Accept'     => 'application/json',
                'Cache-Control' => 'no-cache',
                'Referer'    => base_url(),
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140 Safari/537.36',
            ],
        ]);

        $separador = str_contains($this->config->feedUrl, '?') ? '&' : '?';
        $feedUrl = $this->config->feedUrl . $separador . '_cache_buster=' . rawurlencode((string) microtime(true));
        $respuesta = $cliente->get($feedUrl);
        if ($respuesta->getStatusCode() !== 200) {
            throw new RuntimeException('El SGC respondió con HTTP ' . $respuesta->getStatusCode());
        }

        $contenido = json_decode($respuesta->getBody(), true);
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
        if (!is_array($coordenadas) || count($coordenadas) < 2 || !is_array($propiedades)) {
            return null;
        }

        $fechaLocal = $propiedades['localTime'] ?? null;
        $fechaUtc = $propiedades['utcTime'] ?? null;
        $magnitud = $propiedades['mag'] ?? null;
        if (!is_numeric($coordenadas[0]) || !is_numeric($coordenadas[1]) || !is_numeric($magnitud) || (!$fechaLocal && !$fechaUtc)) {
            return null;
        }

        $zonaLocal = new \DateTimeZone(config('App')->appTimezone);
        if ($fechaLocal) {
            $fechaObjeto = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', (string) $fechaLocal, $zonaLocal);
        } else {
            $fechaObjeto = new \DateTimeImmutable((string) $fechaUtc, new \DateTimeZone('UTC'));
            $fechaObjeto = $fechaObjeto->setTimezone($zonaLocal);
        }
        if (!$fechaObjeto) {
            return null;
        }

        $id = (string) $evento['id'];
        $metadata = [];
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
