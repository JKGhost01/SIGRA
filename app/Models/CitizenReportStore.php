<?php

declare(strict_types=1);

namespace App\Models;

/** Compatibility facade. New application code uses the module repository and catalog port. */
class CitizenReportStore extends \App\Modules\CitizenReports\Infrastructure\Persistence\CiCitizenReportRepository implements \App\Modules\SeismicCatalog\Application\Contracts\ReportEvidenceCatalog
{
    public function sgcEvents(string $since): array { return $this->catalog()->sgcEvents($since); }
    public function updateCounts(array $rows): void { $this->catalog()->updateCounts($rows); }
    private function catalog(): \App\Modules\SeismicCatalog\Infrastructure\Persistence\CiReportEvidenceCatalog
    { return new \App\Modules\SeismicCatalog\Infrastructure\Persistence\CiReportEvidenceCatalog($this->db); }
}
