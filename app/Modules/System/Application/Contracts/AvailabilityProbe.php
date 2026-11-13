<?php

declare(strict_types=1);

namespace App\Modules\System\Application\Contracts;

interface AvailabilityProbe
{
    public function available(): bool;
}
