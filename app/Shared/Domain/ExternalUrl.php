<?php

declare(strict_types=1);

namespace App\Shared\Domain;

final class ExternalUrl
{
    public static function http(mixed $value): ?string
    {
        if (!is_string($value) || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7F]/', $value)) return null;
        $parts = parse_url($value);
        if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) return null;
        return filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
    }
}
