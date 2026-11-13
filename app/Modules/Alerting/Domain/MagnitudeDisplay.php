<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Domain;

/** Legacy display severity only; never confirms or publishes an operational alert. */
final class MagnitudeDisplay
{
    public static function color(float $magnitud): string
    {
        if ($magnitud >= 6.0) {
            return 'roja';
        }
        if ($magnitud >= 5.0) {
            return 'naranja';
        }
        if ($magnitud >= 4.0) {
            return 'amarilla';
        }
        if ($magnitud >= 3.0) {
            return 'verde';
        }

        return 'informativa';
    }

    public static function level(float $magnitud): int
    {
        if ($magnitud >= 6.0) {
            return 4;
        }
        if ($magnitud >= 5.0) {
            return 3;
        }
        if ($magnitud >= 4.0) {
            return 2;
        }
        if ($magnitud >= 3.0) {
            return 1;
        }

        return 0;
    }

}
