<?php

declare(strict_types=1);

namespace App\Libraries;

/** Validate API input before casting it or accessing persistence. */
final class ApiInput
{
    public static function number(mixed $value, string $field, float $min, float $max): float
    {
        if (!is_numeric($value) || !is_finite((float) $value)
            || (float) $value < $min || (float) $value > $max) {
            throw new RedApiException("{$field} debe ser un número entre {$min} y {$max}.");
        }
        return (float) $value;
    }

    public static function integer(mixed $value, string $field, int $min, int $max): int
    {
        $number = self::number($value, $field, $min, $max);
        if (floor($number) !== $number) {
            throw new RedApiException("{$field} debe ser un entero.");
        }
        return (int) $number;
    }

    public static function text(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new RedApiException("{$field} debe ser texto.");
        }
        return trim($value);
    }

    /** @return array{0: ?float, 1: ?float} */
    public static function coordinates(mixed $lat, mixed $lng, bool $required = false): array
    {
        if ($lat === null && $lng === null && !$required) return [null, null];
        if ($lat === null || $lng === null) {
            throw new RedApiException('Los parámetros lat y lng deben enviarse juntos.');
        }
        return [self::number($lat, 'lat', -90, 90), self::number($lng, 'lng', -180, 180)];
    }

    public static function networkPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (!is_string($key) || is_array($value) || is_object($value)) {
                throw new RedApiException('Los campos del cuerpo deben ser valores escalares.');
            }
        }
        foreach (['device_uuid', 'platform', 'manufacturer', 'model', 'os_version', 'app_version', 'fcm_token'] as $key) {
            if (isset($payload[$key])) $payload[$key] = self::text($payload[$key], $key);
        }
        $ranges = [
            'latitude' => [-90, 90], 'lat' => [-90, 90],
            'longitude' => [-180, 180], 'lng' => [-180, 180],
            'accuracy_m' => [0, PHP_FLOAT_MAX], 'battery_level' => [0, 100],
            'alert_radius_km' => [25, 1000], 'min_magnitude' => [1, 9],
            'confidence' => [0, 1], 'sta_lta_ratio' => [0, PHP_FLOAT_MAX],
            'peak_accel_mps2' => [0, PHP_FLOAT_MAX], 'dominant_freq_hz' => [0, PHP_FLOAT_MAX],
            'duration_ms' => [0, PHP_INT_MAX], 'detected_at_ms' => [0, PHP_INT_MAX],
        ];
        foreach ($ranges as $key => [$min, $max]) {
            if (isset($payload[$key])) self::number($payload[$key], $key, $min, $max);
        }
        self::coordinates($payload['latitude'] ?? $payload['lat'] ?? null,
            $payload['longitude'] ?? $payload['lng'] ?? null);
        foreach (['monitoring_enabled', 'notifications_enabled'] as $key) {
            if (!array_key_exists($key, $payload)) continue;
            $value = $payload[$key];
            if (!in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                throw new RedApiException("{$key} debe ser booleano.");
            }
            $payload[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        return $payload;
    }
}
