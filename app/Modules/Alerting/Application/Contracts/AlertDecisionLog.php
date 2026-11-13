<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Contracts;

interface AlertDecisionLog
{
    public function record(array $decision): void;
}
