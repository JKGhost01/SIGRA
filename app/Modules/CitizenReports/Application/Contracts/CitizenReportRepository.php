<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Application\Contracts;

interface CitizenReportRepository
{
    public function transaction(callable $action): mixed;
    public function canSubmit(string $reporter, string $network, int $now, int $hourly, int $daily): bool;
    public function insert(array $report): int;
    public function recentReports(string $since): array;
    public function updateReports(array $rows): void;
    public function expireAndPurge(string $cutoff, string $purge): void;
}
