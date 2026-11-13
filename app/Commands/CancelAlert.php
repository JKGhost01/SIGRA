<?php

declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\{BaseCommand, CLI};
final class CancelAlert extends BaseCommand
{
    protected $group = 'SIGRA';
    protected $name = 'sigra:alert-cancel';
    protected $description = 'Cancela una alerta activa con motivo de auditoría (solo consola del servidor).';
    protected $usage = 'sigra:alert-cancel <uuid> <motivo>';
    public function run(array $params): void
    {
        if (count($params) < 2) { CLI::error($this->usage); return; }
        service('alerting')->cancel($params[0], implode(' ', array_slice($params, 1)));
        CLI::write('Alerta cancelada; futuras consultas ya no la incluyen.');
    }
}
