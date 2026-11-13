<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Sincroniza las fuentes sísmicas sin depender de que alguien abra el mapa.
 *
 *   php spark sigra:sync
 *
 * Pensado para cron cada 2-5 minutos:
 *
 *   *‌/5 * * * * cd /ruta/sigra && php spark sigra:sync >> writable/logs/sync.log 2>&1
 */
class SigraSync extends BaseCommand
{
    protected $group       = 'SIGRA';
    protected $name        = 'sigra:sync';
    protected $description = 'Descarga y guarda los sismos de SGC, USGS, EMSC, GeoNet, GEOFON e INGV.';
    protected $usage       = 'sigra:sync';

    public function run(array $params): void
    {
        $inicio = microtime(true);
        $refresh = service('catalogRefresh')->handle();
        $resultado = ['fuentes' => $refresh->sources, 'guardados' => $refresh->synced ? $refresh->saved : 0, 'errores' => $refresh->errors];
        if (!$refresh->synced) {
            CLI::write('Se conserva el catálogo: intervalo, bloqueo o pausa del proveedor.', 'yellow');
        }

        CLI::write('SIGRA · sincronización sísmica', 'yellow');
        foreach ($resultado['fuentes'] as $fuente => $count) CLI::write($fuente . ': ' . $count . ' eventos', 'white');
        CLI::write('Filas nuevas o modificadas: ' . $resultado['guardados'], 'green');

        foreach ($resultado['errores'] as $fuente => $mensaje) {
            CLI::error($fuente . ': ' . $mensaje);
        }

        if (!$refresh->synced && $resultado['errores'] !== []) {
            CLI::error('No se pudo sincronizar ninguna fuente.');
            CLI::write('Tiempo: ' . number_format(microtime(true) - $inicio, 2) . ' s', 'white');

            return;
        }

        CLI::write('Listo en ' . number_format(microtime(true) - $inicio, 2) . ' s', 'green');
    }
}
