<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Infrastructure\Persistence;

final class CiIngestionAuditRepository implements \App\Modules\SeismicIngestion\Application\Contracts\IngestionAuditRepository
{
    public function __construct(private readonly \CodeIgniter\Database\BaseConnection $db) {}
    public function record(array $batch): void
    {
        if (!$this->db->table('sigra_ingestion_batches')->insert($batch)) throw new \RuntimeException('No se pudo conservar la auditoría.');
    }
}
