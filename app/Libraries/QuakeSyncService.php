<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\SismoModel;
use Config\Quakes;
use Config\Sgc;

/**
 * Orquesta la ingesta de todas las fuentes sísmicas.
 *
 * Responsabilidades:
 *   1. Descargar SGC y USGS de forma tolerante a fallos (una fuente caída
 *      no debe tumbar la otra).
 *   2. Eliminar duplicados entre fuentes (mismo sismo reportado por ambas).
 *   3. Persistir el lote normalizado en la tabla `sismos`.
 */
class QuakeSyncService
{
    private Quakes $quakes;
    private Sgc $sgc;

    public function __construct(?Quakes $quakes = null, ?Sgc $sgc = null)
    {
        $this->quakes = $quakes ?? config(Quakes::class);
        $this->sgc    = $sgc ?? config(Sgc::class);
    }

    /**
     * @return array{
     *     eventos: list<array<string, mixed>>,
     *     guardados: int,
     *     fuentes: array<string, int>,
     *     errores: array<string, string>
     * }
     */
    public function sync(): array
    {
        $sgcEventos  = [];
        $usgsEventos = [];
        $errores     = [];

        if ($this->quakes->sgcEnabled) {
            try {
                $sgcEventos = (new SgcClient($this->sgc))->obtenerEventos();
            } catch (\Throwable $exception) {
                $errores['SGC'] = $exception->getMessage();
                log_message('error', 'Sincronización SGC fallida: {message}', ['message' => $exception->getMessage()]);
            }
        }

        if ($this->quakes->globalEnabled) {
            try {
                $usgsEventos = (new UsgsClient($this->quakes))->obtenerEventos();
            } catch (\Throwable $exception) {
                $errores['USGS'] = $exception->getMessage();
                log_message('error', 'Sincronización USGS fallida: {message}', ['message' => $exception->getMessage()]);
            }
        }

        if ($sgcEventos === [] && $usgsEventos === [] && $errores !== []) {
            return [
                'eventos'   => [],
                'guardados' => 0,
                'fuentes'   => ['SGC' => 0, 'USGS' => 0],
                'errores'   => $errores,
            ];
        }

        $usgsUnicos = $this->quakes->dedupeCrossSource
            ? $this->quitarDuplicados($usgsEventos, $sgcEventos)
            : $usgsEventos;

        $eventos   = array_merge($sgcEventos, $usgsUnicos);
        $guardados = (new SismoModel())->guardarEventos($eventos);
        try {
            (new CitizenReportService())->reconcile();
        } catch (\Throwable $exception) {
            $errores['reportes'] = 'No se pudieron contrastar los reportes ciudadanos.';
            log_message('error', 'Citizen report reconciliation: {message}', ['message' => $exception->getMessage()]);
        }

        return [
            'eventos'   => $eventos,
            'guardados' => $guardados,
            'fuentes'   => [
                'SGC'  => count($sgcEventos),
                'USGS' => count($usgsUnicos),
            ],
            'errores'   => $errores,
        ];
    }

    /**
     * Descarta eventos globales que en realidad son el mismo sismo local
     * ya reportado por el SGC. El reporte local tiene prioridad porque
     * incluye intensidades, estaciones y municipios cercanos.
     *
     * @param list<array<string, mixed>> $candidatos
     * @param list<array<string, mixed>> $referencia
     * @return list<array<string, mixed>>
     */
    private function quitarDuplicados(array $candidatos, array $referencia): array
    {
        if ($referencia === []) {
            return $candidatos;
        }

        $unicos = [];
        foreach ($candidatos as $candidato) {
            $duplicado = false;

            foreach ($referencia as $evento) {
                $diferencia = abs(
                    strtotime((string) ($candidato['fecha_hora'] ?? '')) -
                    strtotime((string) ($evento['fecha_hora'] ?? ''))
                );

                if ($diferencia > $this->quakes->dedupeSeconds) {
                    continue;
                }

                $distancia = SismoModel::distanciaKm(
                    (float) ($candidato['latitud'] ?? 0),
                    (float) ($candidato['longitud'] ?? 0),
                    (float) ($evento['latitud'] ?? 0),
                    (float) ($evento['longitud'] ?? 0),
                );

                if ($distancia <= $this->quakes->dedupeDistanceKm) {
                    $duplicado = true;
                    break;
                }
            }

            if (! $duplicado) {
                $unicos[] = $candidato;
            }
        }

        return $unicos;
    }
}
