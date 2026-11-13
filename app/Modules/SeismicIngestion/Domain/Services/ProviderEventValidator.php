<?php

declare(strict_types=1);
namespace App\Modules\SeismicIngestion\Domain\Services;

final class ProviderEventValidator
{
    public static function measurement(mixed $value, float $min, float $max): bool
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= $min && (float) $value <= $max;
    }
    public static function scientificFields(mixed $lat, mixed $lng, mixed $magnitude, mixed $depth): bool
    {
        return self::measurement($lat, -90, 90) && self::measurement($lng, -180, 180)
            && self::measurement($magnitude, -2, 10)
            && ($depth === null || self::measurement($depth, -10, 1000));
    }
    public static function sourceId(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || strlen($value) > 512 || preg_match('/[^\x21-\x7E]/', $value)) return null;
        return $value;
    }
}
