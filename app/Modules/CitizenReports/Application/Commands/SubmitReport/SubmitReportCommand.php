<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Application\Commands\SubmitReport;

final readonly class SubmitReportCommand
{
    public function __construct(public array $input, public string $visitor, public string $ip) {}
}
