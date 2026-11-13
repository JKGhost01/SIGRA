<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\CitizenReportStore;
use Config\CitizenReports;
use App\Modules\CitizenReports\Domain\ReportRejected;
use App\Modules\CitizenReports\Application\Commands\SubmitReport\SubmitReportCommand;
class CitizenReportService
{
    public function __construct(private readonly ?CitizenReportStore $store = null, private readonly ?CitizenReports $config = null) {}
    public function validate(array $input, ?int $now = null): array
    { return $this->legacy(fn() => \Config\Services::reportValidator(false, $this->config)->validate(\App\Modules\CitizenReports\Http\Requests\SubmitCitizenReportRequest::canonical($input), $now ?? time())); }
    public function submit(array $input, string $visitor, string $ip, ?int $now = null): int
    { return $this->legacy(fn() => \Config\Services::submitCitizenReport(false, $this->store, $this->config)->handle(new SubmitReportCommand(\App\Modules\CitizenReports\Http\Requests\SubmitCitizenReportRequest::canonical($input), $visitor, $ip), $now)); }
    public function reconcile(?int $now = null): void
    { \Config\Services::reconcileCitizenReports(false, $this->store, $this->config)->handle($now); }
    private function legacy(callable $action): mixed
    {
        try { return $action(); }
        catch (ReportRejected $error) { throw new RedApiException(lang($error->messageKey), $error->statusCode); }
    }
}
