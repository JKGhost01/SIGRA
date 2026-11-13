<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Application\Contracts;

interface IngestionAuditRepository
{
    public function record(array $batch): void;
}
