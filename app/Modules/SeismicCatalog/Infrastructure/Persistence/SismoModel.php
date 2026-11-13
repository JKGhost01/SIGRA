<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Infrastructure\Persistence;

use CodeIgniter\Model;

/**
 * SismoModel
 * -------------------------------------------------------------------
 * REGLA ARQUITECTÓNICA: este modelo es EXCLUSIVAMENTE una capa de
 * acceso a datos sobre la tabla `sismos`.
 *
 * NO contiene:
 *   - validaciones de formulario
 *   - decisiones de permisos
 *   - procesamiento de peticiones
 *   - generación de HTML
 *   - generación de IDs de negocio (responsabilidad de las fuentes)
 *
 * Solo sabe consultar, insertar, actualizar y eliminar registros.
 */
class SismoModel extends Model
{
    public const VENTANA_EVENTOS_HORAS = 5 * 24;

    /** @deprecated Usa VENTANA_EVENTOS_HORAS. Se conserva por compatibilidad. */
    public const VENTANA_SGC_OFICIAL_HORAS = self::VENTANA_EVENTOS_HORAS;

    protected $table            = 'sismos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false; // ID alfanumérico generado desde PHP

    protected $returnType     = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'id',
        'fecha_hora',
        'latitud',
        'longitud',
        'magnitud',
        'profundidad',
        'ubicacion',
        'fuente',
        'estado',
        'sgc_metadata',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $dateFormat    = 'datetime';

    /**
     * Trae todos los sismos, más recientes primero.
     */
    private function eventosPublicos(\CodeIgniter\Database\BaseBuilder $query): \CodeIgniter\Database\BaseBuilder
    {
        return $query->whereIn('fuente', ['SGC', 'USGS'])->notLike('id', 'SGI', 'after');
    }

    public function obtenerTodos(): array
    {
        $this->eventosPublicos($this->builder());
        return $this->orderBy('fecha_hora', 'DESC')->findAll();
    }

    /**
     * Trae los eventos de la ventana activa del mapa.
     *
     * El feed oficial del SGC publica un resumen de cinco días, así que
     * la ventana activa debe respetar esa misma longitud para evitar que
     * los eventos desaparezcan del listado sin que se hayan borrado.
     */
    public function obtenerRecientes(int $horas = self::VENTANA_EVENTOS_HORAS): array
    {
        return $this->obtenerRecientesFiltrados($horas);
    }

    /**
     * Igual que obtenerRecientes, con filtros para la API móvil y para
     * el filtro de radio del navegador.
     *
     * El filtro geográfico se aplica en PHP sobre la ventana temporal:
     * la tabla conserva pocos cientos de eventos por ventana y así el
     * cálculo de distancia (Haversine) es exacto incluso cerca de la
     * línea internacional de cambio de fecha.
     *
     * @param string $orden fecha|distancia|magnitud
     */
    public function obtenerRecientesFiltrados(
        int $horas = self::VENTANA_EVENTOS_HORAS,
        ?int $limite = null,
        ?float $minMagnitud = null,
        string $fuente = '',
        ?float $origenLat = null,
        ?float $origenLng = null,
        ?float $radioKm = null,
        string $orden = 'fecha',
    ): array {
        $desde = date('Y-m-d H:i:s', time() - ($horas * 3600));
        $hasta = date('Y-m-d H:i:s');

        $consulta = $this->eventosPublicos($this->builder())
            ->where('fecha_hora >=', $desde)
            ->where('fecha_hora <=', $hasta);

        if ($minMagnitud !== null) {
            $consulta->where('magnitud >=', $minMagnitud);
        }
        if ($fuente !== '') {
            $consulta->where('fuente', $fuente);
        }

        $filas = $consulta->orderBy('fecha_hora', 'DESC')->get()->getResultArray();

        $filtrarPorRadio = $origenLat !== null && $origenLng !== null && $radioKm !== null && $radioKm > 0;
        if ($filtrarPorRadio) {
            $filas = array_values(array_filter(
                $filas,
                fn (array $fila): bool => self::distanciaKm(
                    $origenLat,
                    $origenLng,
                    (float) $fila['latitud'],
                    (float) $fila['longitud'],
                ) <= $radioKm,
            ));
        }

        if ($filtrarPorRadio || $orden === 'distancia') {
            foreach ($filas as &$fila) {
                if ($origenLat === null || $origenLng === null) {
                    $fila['distancia_km'] = null;

                    continue;
                }

                $fila['distancia_km'] = round(self::distanciaKm(
                    $origenLat,
                    $origenLng,
                    (float) $fila['latitud'],
                    (float) $fila['longitud'],
                ), 1);
            }
            unset($fila);
        }

        if ($orden === 'distancia' && $origenLat !== null && $origenLng !== null) {
            usort($filas, static fn (array $a, array $b): int => ($a['distancia_km'] ?? 0) <=> ($b['distancia_km'] ?? 0));
        } elseif ($orden === 'magnitud') {
            usort($filas, static fn (array $a, array $b): int => (float) $b['magnitud'] <=> (float) $a['magnitud']);
        }

        if ($limite !== null && $limite > 0) {
            $filas = array_slice($filas, 0, $limite);
        }

        return $filas;
    }

    /**
     * Distancia aproximada en kilómetros entre dos coordenadas.
     */
    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return \App\Shared\Domain\GeoDistance::km($lat1, $lng1, $lat2, $lng2);
    }

    /**
     * Guarda eventos normalizados de cualquier fuente sin duplicarlos.
     *
     * Hace una sola lectura por lote y solo escribe las filas nuevas o
     * realmente modificadas (el feed se consulta cada pocos segundos).
     *
     * @param list<array<string, mixed>> $eventos
     */
    public function guardarEventos(array $eventos): int
    {
        $filas = [];

        foreach ($eventos as $evento) {
            $id = trim((string) ($evento['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $evento['id'] = $id;
            unset($evento['source_id'], $evento['created_at'], $evento['updated_at']);
            $filas[$id] = $evento;
        }

        if ($filas === []) {
            return 0;
        }

        $existentes = $this->obtenerResumenPorIds(array_keys($filas));
        $ahora = date('Y-m-d H:i:s');
        $insertar = [];
        $actualizar = [];

        foreach ($filas as $id => $evento) {
            $evento['updated_at'] = $ahora;

            if (! isset($existentes[$id])) {
                $evento['created_at'] = $ahora;
                $insertar[] = $evento;

                continue;
            }

            if ($this->cambioRespectoA($existentes[$id], $evento)) {
                $actualizar[] = $evento;
            }
        }

        if ($insertar !== []) {
            $this->db->table('sismos')->insertBatch($insertar, null, 200);
        }
        if ($actualizar !== []) {
            $this->db->table('sismos')->updateBatch($actualizar, 'id', 200);
        }

        return count($insertar) + count($actualizar);
    }

    /**
     * @deprecated Usa guardarEventos(); se conserva por compatibilidad.
     *
     * @param list<array<string, mixed>> $eventos
     */
    public function guardarEventosSgc(array $eventos): int
    {
        return $this->guardarEventos($eventos);
    }

    /**
     * @param list<string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function obtenerResumenPorIds(array $ids): array
    {
        $resumen = [];

        foreach (array_chunk($ids, 500) as $lote) {
            $filas = $this->db->table('sismos')
                ->select('id, fecha_hora, latitud, longitud, magnitud, profundidad, ubicacion, fuente, estado, sgc_metadata')
                ->whereIn('id', $lote)
                ->get()
                ->getResultArray();

            foreach ($filas as $fila) {
                $resumen[(string) $fila['id']] = $fila;
            }
        }

        return $resumen;
    }

    /**
     * @param array<string, mixed> $anterior
     * @param array<string, mixed> $nuevo
     */
    private function cambioRespectoA(array $anterior, array $nuevo): bool
    {
        $numericos = ['latitud', 'longitud', 'magnitud', 'profundidad'];

        foreach ($numericos as $campo) {
            if (abs((float) ($anterior[$campo] ?? 0) - (float) ($nuevo[$campo] ?? 0)) > 0.0000001) {
                return true;
            }
        }

        foreach (['fecha_hora', 'ubicacion', 'fuente', 'estado', 'sgc_metadata'] as $campo) {
            if ((string) ($anterior[$campo] ?? '') !== (string) ($nuevo[$campo] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string|null> $filtros
     */
    public function obtenerHistorico(array $filtros, string $orden, string $direccion, int $limite = 500): array
    {
        $consulta = $this->eventosPublicos($this->builder());

        if (!empty($filtros['desde'])) {
            $consulta->where('fecha_hora >=', $filtros['desde'] . ' 00:00:00');
        }
        if (!empty($filtros['hasta'])) {
            $consulta->where('fecha_hora <=', $filtros['hasta'] . ' 23:59:59');
        }

        if ($limite > 0) {
            $consulta->limit($limite);
        }

        return $consulta
            ->orderBy($orden, $direccion)
            ->get()
            ->getResultArray();
    }

    /**
     * Trae un sismo por su ID alfanumérico.
     */
    public function obtenerPorId(string $id): ?array
    {
        return $this->eventosPublicos($this->builder())->where('id', $id)->get()->getRowArray();
    }
}
