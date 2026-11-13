<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Persistence;

final class CiAlertDecisionLog implements \App\Modules\Alerting\Application\Contracts\AlertDecisionLog
{
    public function __construct(private readonly \CodeIgniter\Database\BaseConnection $db) {}
    public function record(array $decision): void
    {
        if (!$this->db->table('sigra_alert_decisions')->insert($decision)) throw new \RuntimeException('No se pudo conservar la auditoría.');
    }
}
