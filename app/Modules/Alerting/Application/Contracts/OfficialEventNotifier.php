<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Contracts;

interface OfficialEventNotifier
{
    public function catalogUpdated(array $events): void;
}
