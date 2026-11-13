<?php

declare(strict_types=1);

namespace App\Modules\SensorNetwork\Application\Contracts;

interface NetworkStatistics
{
    public function read(): array;
}
