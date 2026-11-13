<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Application\Contracts;

use App\Modules\SeismicIngestion\Application\DTO\CatalogRefreshResult;

interface RefreshCoordinator
{
    /** @param callable(): array $operation The registered ingestion use case. */
    public function runIfDue(callable $operation, bool $force = false): CatalogRefreshResult;
}
