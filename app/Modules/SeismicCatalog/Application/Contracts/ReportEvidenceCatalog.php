<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\Contracts;

/** Public catalog port; only the catalog adapter writes its report annotations. */
interface ReportEvidenceCatalog
{
    public function sgcEvents(string $since): array;
    public function updateCounts(array $rows): void;
}
