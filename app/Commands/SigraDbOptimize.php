<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Purga datos viejos y compacta las tablas.
 *
 *   php spark sigra:db-optimize
 *   php spark sigra:db-optimize --optimize
 *
 * Pensado para el cron del hosting. Sin --optimize solo borra y analiza,
 * que es lo barato; OPTIMIZE reconstruye la tabla y necesita espacio libre.
 */
class SigraDbOptimize extends BaseCommand
{
    protected $group       = 'SIGRA';
    protected $name        = 'sigra:db-optimize';
    protected $description = 'Purga detecciones, entregas, alertas e histórico antiguo y compacta las tablas.';
    protected $usage       = 'sigra:db-optimize [--optimize]';
    protected $options     = [
        '--optimize' => 'Ejecuta OPTIMIZE TABLE después de ANALYZE (consume espacio temporal).',
    ];

    public function run(array $params): void
    {
        $db = db_connect();
        $sigra = config(\Config\Sigra::class);
        $red = config(\Config\Red::class);

        $diasRed = max(1, $red->retentionDays);
        $limiteRed = date('Y-m-d H:i:s', time() - ($diasRed * 86400));
        $limiteRedMs = (time() - ($diasRed * 86400)) * 1000;

        $db->table('red_alert_deliveries')->where('created_at <', $limiteRed)->delete();
        $entregas = $db->affectedRows();

        $db->table('red_detections')->where('detected_at_ms <', $limiteRedMs)->delete();
        $detecciones = $db->affectedRows();

        $db->table('red_alerts')->where('expires_at <', $limiteRed)->delete();
        $alertas = $db->affectedRows();

        // Entregas cuyo alerta ya no existe (no hay FOREIGN KEY a propósito).
        $db->query(
            'DELETE d FROM red_alert_deliveries d '
            . 'LEFT JOIN red_alerts a ON a.id = d.alert_id WHERE a.id IS NULL'
        );
        $huerfanas = $db->affectedRows();

        $db->table('red_devices')
            ->where('status', 'inactive')
            ->where('updated_at <', $limiteRed)
            ->delete();
        $dispositivos = $db->affectedRows();

        $sismos = 0;
        if ($sigra->sismosRetentionDays > 0) {
            $limiteSismos = date('Y-m-d H:i:s', time() - ($sigra->sismosRetentionDays * 86400));
            $db->table('sismos')->where('fecha_hora <', $limiteSismos)->delete();
            $sismos = $db->affectedRows();
        }

        CLI::write('Retención red: ' . $diasRed . ' días; histórico: '
            . ($sigra->sismosRetentionDays > 0 ? $sigra->sismosRetentionDays . ' días' : 'sin límite'), 'cyan');
        CLI::write("Entregas eliminadas: {$entregas}");
        CLI::write("Detecciones eliminadas: {$detecciones}");
        CLI::write("Alertas eliminadas: {$alertas}");
        CLI::write("Entregas huérfanas eliminadas: {$huerfanas}");
        CLI::write("Dispositivos inactivos eliminados: {$dispositivos}");
        CLI::write("Sismos históricos eliminados: {$sismos}");

        $tablas = $db->listTables();
        foreach ($tablas as $tabla) {
            $db->query('ANALYZE TABLE ' . $db->escapeIdentifiers($tabla));
        }

        if ((bool) CLI::getOption('optimize')) {
            CLI::write('Compactando tablas…', 'yellow');
            foreach ($tablas as $tabla) {
                $db->query('OPTIMIZE TABLE ' . $db->escapeIdentifiers($tabla));
            }
        }

        $this->mostrarTamano($db);
    }

    private function mostrarTamano($db): void
    {
        $filas = $db->query(
            'SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH '
            . 'FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() '
            . 'ORDER BY DATA_LENGTH + INDEX_LENGTH DESC'
        )->getResultArray();

        CLI::write('Tabla                    Filas      Datos      Índices    Total', 'cyan');
        foreach ($filas as $fila) {
            $datos = (int) $fila['DATA_LENGTH'];
            $indices = (int) $fila['INDEX_LENGTH'];
            CLI::write(sprintf(
                '%-24s %-10s %-10s %-10s %s',
                $fila['TABLE_NAME'],
                number_format((int) $fila['TABLE_ROWS'], 0, '.', ','),
                $this->bytes($datos),
                $this->bytes($indices),
                $this->bytes($datos + $indices),
            ));
        }
    }

    private function bytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
