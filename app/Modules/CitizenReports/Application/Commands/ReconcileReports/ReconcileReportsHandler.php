<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Application\Commands\ReconcileReports;

use App\Modules\CitizenReports\Application\Contracts\CitizenReportRepository;
use App\Modules\CitizenReports\Domain\CitizenReportMatcher;
use App\Modules\CitizenReports\Domain\ReportPolicy;
use App\Modules\SeismicCatalog\Application\Contracts\ReportEvidenceCatalog;
use App\Shared\Domain\Clock;
final class ReconcileReportsHandler implements \App\Modules\CitizenReports\Application\Contracts\ReportReconciler
{
    public function __construct(private readonly CitizenReportRepository $reports, private readonly ReportEvidenceCatalog $catalog,
        private readonly CitizenReportMatcher $matcher, private readonly ReportPolicy $policy, private readonly Clock $clock,
        private readonly \DateTimeZone $storageTimezone) {}
    public function reconcile(): void { $this->handle(); }
    public function handle(?int $now = null): void
    {
        $now ??= $this->clock->now()->getTimestamp();
        $this->reports->transaction(function () use ($now): void {
            $since = $now - $this->policy->pendingHours * 3600;
            $this->reports->expireAndPurge($this->format($since), $this->format($now - $this->policy->retentionDays * 86400));
            $events = $this->catalog->sgcEvents($this->format($since - $this->policy->matchSeconds));
            $reports = $this->reports->recentReports($this->format($since - 2 * $this->policy->matchSeconds));
            $result = $this->matcher->classify($reports, $events);
            $this->reports->updateReports($result['reports']);
            $this->catalog->updateCounts($result['events']);
        });
    }
    private function format(int $timestamp): string
    { return (new \DateTimeImmutable('@' . $timestamp))->setTimezone($this->storageTimezone)->format('Y-m-d H:i:s'); }
}
