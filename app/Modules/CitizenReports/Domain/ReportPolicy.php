<?php

declare(strict_types=1);

namespace App\Modules\CitizenReports\Domain;

final readonly class ReportPolicy
{
    public function __construct(
        public int $maxAgeHours, public int $pendingHours, public int $retentionDays,
        public int $matchSeconds, public float $matchKm, public int $minParticipants,
        public int $hourlyLimit, public int $dailyLimit, public string $hashKey,
    ) {}
}
