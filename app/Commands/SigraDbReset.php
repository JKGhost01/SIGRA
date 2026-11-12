<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use Config\Database as DatabaseConfig;
use Throwable;

/**
 * Recrea la base de datos desde cero con el esquema optimizado.
 *
 *   php spark sigra:db-reset --force
 *   php spark sigra:db-reset --force --no-seed
 *
 * Elimina solo las tablas de SIGRA y la tabla de migraciones.
 */
class SigraDbReset extends BaseCommand
{
    protected $group       = 'SIGRA';
    protected $name        = 'sigra:db-reset';
    protected $description = 'Borra las tablas de SIGRA, aplica el esquema optimizado y siembra datos del SGC.';
    protected $usage       = 'sigra:db-reset [--force] [--no-seed]';
    protected $options     = [
        '--force'   => 'No pide confirmación (obligatorio en modo no interactivo).',
        '--no-seed' => 'No carga eventos del SGC después de migrar.',
    ];

    /** Tablas que pertenecen a SIGRA, incluidas las retiradas. */
    private const TABLAS = [
        'citizen_reports',
        'red_alert_deliveries',
        'red_alerts',
        'red_detections',
        'red_devices',
        'sismos',
        'sms_subscriptions',
        'migrations',
    ];

    /** Migraciones incrementales sustituidas por el esquema consolidado. */
    private const MIGRACIONES_OBSOLETAS = [
        '20260921000000_AddSgcSourceIndex.php',
        '20260921000001_AddSgcMetadata.php',
        '20260921000002_CreateSmsSubscriptions.php',
        '20260921000003_RepairSgcLocalTimes.php',
        '20261103000001_CreateRedNetworkTables.php',
        '20261103000002_DropSmsSubscriptions.php',
        '20261103000003_EnsureSismosTable.php',
    ];

    public function run(array $params): void
    {
        $force  = (bool) CLI::getOption('force');
        $noSeed = (bool) CLI::getOption('no-seed');

        if (! $force) {
            $respuesta = CLI::prompt(
                'Esto eliminará todas las tablas de SIGRA y sus datos. ¿Continuar?',
                ['y', 'n'],
                'n',
            );

            if (strtolower((string) $respuesta) !== 'y') {
                CLI::write('Cancelado.', 'yellow');

                return;
            }
        }

        $db = db_connect();
        CLI::write('Eliminando tablas…', 'yellow');

        // Si el servidor conserva los archivos de migración antiguos, se
        // ejecutarían antes del esquema nuevo. Se retiran primero.
        foreach (self::MIGRACIONES_OBSOLETAS as $archivo) {
            $ruta = APPPATH . 'Database/Migrations/' . $archivo;
            if (is_file($ruta)) {
                @unlink($ruta);
                CLI::write('Migración obsoleta eliminada: ' . $archivo, 'dark_gray');
            }
        }

        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLAS as $tabla) {
            $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($tabla));
        }
        $db->query('SET FOREIGN_KEY_CHECKS = 1');

        CLI::write('Aplicando migraciones…', 'yellow');
        $runner = service('migrations');
        $runner->latest();

        $tablas = $db->listTables();
        CLI::write('Tablas creadas: ' . implode(', ', $tablas), 'green');

        if (! $noSeed) {
            CLI::write('Sembrando eventos del SGC…', 'yellow');
            try {
                $seeder = new Seeder(new DatabaseConfig());
                $seeder->call('SigraSeeder');
            } catch (Throwable $exception) {
                CLI::write('El seeder falló: ' . $exception->getMessage(), 'red');
                CLI::write('La base quedó creada; el feed se sincronizará en la primera visita.', 'yellow');
            }
        }

        CLI::write('Analizando tablas…', 'yellow');
        foreach ($tablas as $tabla) {
            $db->query('ANALYZE TABLE ' . $db->escapeIdentifiers($tabla));
        }

        CLI::write('Base de datos recreada.', 'green');
    }
}
