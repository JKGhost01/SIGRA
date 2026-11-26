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
 * Programación independiente del navegador: tools/install-sync-cron.py.
 *
 * Cada minuto se comprueba el intervalo compartido, sin forzar proveedores.
 */
class SigraSync extends BaseCommand
{
    protected $group       = 'SIGRA';
    protected $name        = 'sigra:sync';
    protected $description = 'Descarga y guarda los sismos de SGC, USGS, EMSC, GeoNet, GEOFON e INGV.';
    protected $usage       = 'sigra:sync';

    public function run(array $params): int
    {
        $inicio = microtime(true);
        CLI::write('Inicio UTC: ' . gmdate('c'));
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

            return EXIT_ERROR;
        }

        CLI::write('Listo en ' . number_format(microtime(true) - $inicio, 2) . ' s', 'green');
        return EXIT_SUCCESS;
    }
}
