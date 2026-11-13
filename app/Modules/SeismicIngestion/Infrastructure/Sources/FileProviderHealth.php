<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Infrastructure\Sources;

use App\Shared\Domain\Clock;

/** Read-only snapshot: viewing diagnostics never contacts a provider or changes its budget. */
final class FileProviderHealth
{
    /** @param array<string, array{url: string, enabled: bool}> $providers */
    public function __construct(private readonly string $directory, private readonly array $providers,
        private readonly Clock $clock) {}

    /** @return list<array<string, mixed>> */
    public function snapshot(): array
    {
        $rows = [];
        $now = $this->clock->now()->getTimestamp();
        foreach ($this->providers as $name => $provider) {
            $host = strtolower((string) parse_url($provider['url'], PHP_URL_HOST));
            $path = $this->directory . '/' . hash('sha256', $host) . '.json';
            $exists = is_file($path);
            $state = $exists ? json_decode((string) @file_get_contents($path), true) : [];
            $valid = is_array($state) && (!$exists || (isset($state['next_at'], $state['error'])
                && is_int($state['next_at']) && is_bool($state['error'])));
            if (!$valid) $state = [];
            $next = $this->timestamp($state['next_at'] ?? null);
            $status = 'unknown';
            if (!$provider['enabled']) {
                $status = 'disabled';
            } elseif (!$valid) {
                $status = 'invalid';
            } elseif ($exists) {
                if (($state['outcome'] ?? '') === 'pending' && $next !== null && $next > $now) {
                    $status = 'checking';
                } elseif (!empty($state['error'])) {
                    $status = $next !== null && $next > $now ? 'paused' : 'error';
                } else {
                    $status = $next !== null && $next <= $now ? 'stale' : 'ok';
                }
            }
            $http = $state['status'] ?? null;
            $outcome = $state['outcome'] ?? null;
            $rows[] = ['name' => $name, 'host' => $host, 'state' => $status,
                'http' => is_int($http) && $http >= 100 && $http <= 599 ? $http : null,
                'outcome' => in_array($outcome, ['pending', 'success', 'connection_error', 'invalid_response', 'http_error'], true) ? $outcome : null,
                'last_attempt' => $this->timestamp($state['last_attempt_at'] ?? null),
                'last_check' => $this->timestamp($state['last_checked_at'] ?? null),
                'last_success' => $this->timestamp($state['last_success_at'] ?? null),
                'next_check' => $next, 'retry_due' => $next !== null && $next <= $now];
        }
        return $rows;
    }

    private function timestamp(mixed $value): ?int
    {
        return is_int($value) && $value > 0 && $value <= 253402300799 ? $value : null;
    }
}
