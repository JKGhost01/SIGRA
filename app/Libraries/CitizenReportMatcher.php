<?php

declare(strict_types=1);

namespace App\Libraries;

/** Compatibility constructor; matching rules live in the pure domain service. */
class CitizenReportMatcher extends \App\Modules\CitizenReports\Domain\CitizenReportMatcher
{
    public function __construct(\Config\CitizenReports $config)
    { parent::__construct(\Config\Services::reportPolicy($config)); }
}
