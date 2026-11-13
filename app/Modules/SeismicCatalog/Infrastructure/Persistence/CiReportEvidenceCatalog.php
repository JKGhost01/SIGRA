<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Infrastructure\Persistence;

final class CiReportEvidenceCatalog implements \App\Modules\SeismicCatalog\Application\Contracts\ReportEvidenceCatalog
{
    public function __construct(private readonly \CodeIgniter\Database\BaseConnection $db) {}
    public function sgcEvents(string $since): array
    {
        return $this->db->table('sismos')->where('fuente', 'SGC')->notLike('id', 'SGI', 'after')
            ->where('fecha_hora >=', $since)->get()->getResultArray();
    }

    public function updateCounts(array $rows): void
    {
        if ($rows !== []) $this->db->table('sismos')->updateBatch($rows, 'id');
    }

}
