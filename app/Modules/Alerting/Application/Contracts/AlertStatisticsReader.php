<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Contracts;

interface AlertStatisticsReader
{
    public function summary(string $since): array;
}
