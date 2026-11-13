<?php

declare(strict_types=1);

namespace App\Modules\System\Application\Queries;

use App\Modules\System\Application\Contracts\AvailabilityProbe;
final class HealthHandler
{
    public function __construct(private readonly AvailabilityProbe $database) {}
    public function handle(): bool { return $this->database->available(); }
}
