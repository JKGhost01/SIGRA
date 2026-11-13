<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Persistence;

final class CiAlertStatisticsReader implements \App\Modules\Alerting\Application\Contracts\AlertStatisticsReader
{
    public function __construct(private readonly \CodeIgniter\Database\BaseConnection $db) {}
    public function summary(string $since): array
    {
        return [
            'count' => $this->db->table('red_alerts')->where('created_at >=', $since)->countAllResults(),
            'last' => $this->db->table('red_alerts')->orderBy('created_at', 'DESC')->get(1)->getRowArray(),
            'recent' => $this->db->table('red_alerts')->orderBy('created_at', 'DESC')->get(10)->getResultArray(),
        ];
    }
}
