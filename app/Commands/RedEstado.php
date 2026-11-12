<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\RedNetworkService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RedEstado extends BaseCommand
{
    protected $group = 'Red SIGRA';
    protected $name = 'red:estado';
    protected $description = 'Muestra el estado de la red ciudadana de teléfonos.';

    public function run(array $params)
    {
        $stats = (new RedNetworkService())->estadisticasPublicas();

        CLI::write('Red SIGRA de teléfonos', 'cyan');
        CLI::write('Operativa: ' . ($stats['operational'] ? 'sí' : 'no'));
        CLI::write('Teléfonos en línea: ' . $stats['stats']['devices_online']);
        CLI::write('Monitoreando: ' . $stats['stats']['devices_monitoring']);
        CLI::write('Detecciones 24 h: ' . $stats['stats']['detections_24h']);
        CLI::write('Alertas 24 h: ' . $stats['stats']['alerts_24h']);

        if ($stats['last_alert'] !== null) {
            $ultima = $stats['last_alert'];
            CLI::write(sprintf(
                'Última alerta: M%.1f en %.4f, %.4f (%s)',
                $ultima['magnitude'],
                $ultima['latitude'],
                $ultima['longitude'],
                $ultima['created_at'],
            ), 'yellow');
        } else {
            CLI::write('Sin alertas registradas.', 'yellow');
        }
    }
}
