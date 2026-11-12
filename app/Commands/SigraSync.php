<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\QuakeSyncService;
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
    protected $description = 'Descarga y guarda los sismos de SGC y USGS.';
    protected $usage       = 'sigra:sync';

    public function run(array $params): void
    {
        $inicio = microtime(true);
        $resultado = (new QuakeSyncService())->sync();

        CLI::write('SIGRA · sincronización sísmica', 'yellow');
        CLI::write('SGC:  ' . ($resultado['fuentes']['SGC'] ?? 0) . ' eventos', 'white');
        CLI::write('USGS: ' . ($resultado['fuentes']['USGS'] ?? 0) . ' eventos', 'white');
        CLI::write('Filas nuevas o modificadas: ' . $resultado['guardados'], 'green');

        foreach ($resultado['errores'] as $fuente => $mensaje) {
            CLI::error($fuente . ': ' . $mensaje);
        }

        if ($resultado['eventos'] === [] && $resultado['errores'] !== []) {
            CLI::error('No se pudo sincronizar ninguna fuente.');
            CLI::write('Tiempo: ' . number_format(microtime(true) - $inicio, 2) . ' s', 'white');

            return;
        }

        CLI::write('Listo en ' . number_format(microtime(true) - $inicio, 2) . ' s', 'green');
    }
}
