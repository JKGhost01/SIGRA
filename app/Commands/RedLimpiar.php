<?php

declare(strict_types=1);

namespace App\Commands;

use App\Models\RedAlertModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RedLimpiar extends BaseCommand
{
    protected $group = 'Red SIGRA';
    protected $name = 'red:limpiar';
    protected $description = 'Expira alertas y elimina detecciones y entregas antiguas.';
    protected $usage = 'red:limpiar [--dias 7]';
    protected $options = [
        '--dias' => 'Días de retención para detecciones y entregas (por defecto 7).',
    ];

    public function run(array $params)
    {
        $dias = max(1, (int) (CLI::getOption('dias') ?? 7));
        $limite = date('Y-m-d H:i:s', time() - ($dias * 86400));
        $limiteMs = (time() - ($dias * 86400)) * 1000;
        $db = db_connect();

        $expiradas = (new RedAlertModel())->expirarVencidas();

        $db->table('red_alert_deliveries')->where('created_at <', $limite)->delete();
        $entregas = $db->affectedRows();

        $db->table('red_detections')->where('detected_at_ms <', $limiteMs)->delete();
        $detecciones = $db->affectedRows();

        $db->table('red_alerts')->where('expires_at <', $limite)->delete();
        $alertas = $db->affectedRows();

        $db->query(
            'DELETE d FROM red_alert_deliveries d '
            . 'LEFT JOIN red_alerts a ON a.id = d.alert_id WHERE a.id IS NULL'
        );
        $huerfanas = $db->affectedRows();

        $db->table('red_devices')
            ->where('status', 'inactive')
            ->where('updated_at <', $limite)
            ->delete();
        $dispositivos = $db->affectedRows();

        CLI::write("Alertas expiradas: {$expiradas}", 'green');
        CLI::write("Entregas eliminadas: {$entregas}", 'green');
        CLI::write("Detecciones eliminadas: {$detecciones}", 'green');
        CLI::write("Alertas antiguas eliminadas: {$alertas}", 'green');
        CLI::write("Entregas huérfanas eliminadas: {$huerfanas}", 'green');
        CLI::write("Dispositivos inactivos eliminados: {$dispositivos}", 'green');
    }
}
