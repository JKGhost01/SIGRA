<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure;

final class DatabaseAvailabilityProbe implements \App\Modules\System\Application\Contracts\AvailabilityProbe
{
    public function available(): bool
    {
        try { db_connect()->query('SELECT 1'); return true; }
        catch (\Throwable) { return false; }
    }
}
