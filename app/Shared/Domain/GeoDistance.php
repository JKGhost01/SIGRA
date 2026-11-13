<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final class GeoDistance
{
    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
        $a = max(0.0, min(1.0, $a));
        return 12742.0 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
