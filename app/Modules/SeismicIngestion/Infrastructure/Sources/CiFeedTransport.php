<?php

declare(strict_types=1);
namespace App\Modules\SeismicIngestion\Infrastructure\Sources;

use App\Shared\Domain\Clock;
use App\Shared\Infrastructure\SystemClock;

/** Persistent host budget shared by browser, CLI, endpoints and PHP workers. */
final class CiFeedTransport implements FeedTransport
{
    private readonly string $directory;
    private readonly Clock $clock;

    /** The optional HTTP callback is used by offline regression tests. */
    public function __construct(?string $directory = null, ?Clock $clock = null,
        private readonly ?\Closure $request = null)
    {
        $this->directory = $directory ?? WRITEPATH . 'cache/providers';
        $this->clock = $clock ?? new SystemClock();
    }

    public function json(string $url, int $timeoutSeconds, array $headers): array
    {
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new \RuntimeException('El feed debe usar HTTPS.');
        }
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('No se pudo preparar la protección de proveedores.');
        }
        $hostname = strtolower($parts['host']);
        $minimumInterval = $hostname === 'archive.sgc.gov.co' ? 900 : 300;
        $host = hash('sha256', $hostname);
        // Rolling FDSN windows change the URL; they still share the cached catalogue.
        parse_str($parts['query'] ?? '', $query);
        if (isset($query['starttime'], $query['endtime'])) {
            $query['_window_seconds'] = (int) strtotime($query['endtime']) - (int) strtotime($query['starttime']);
        }
        unset($query['starttime'], $query['endtime']);
        ksort($query);
        $key = hash('sha256', $host . ($parts['path'] ?? '/') . http_build_query($query)
            . ($headers['Accept'] ?? ''));
        $statePath = $this->directory . '/' . $host . '.json';
        $cachePath = $this->directory . '/' . $key . '.feed.json';
        $lock = fopen($this->directory . '/' . $host . '.lock', 'c');
        if ($lock === false) throw new \RuntimeException('No se pudo bloquear el proveedor.');
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) throw new \RuntimeException('Ya hay una consulta a este proveedor.');
            $state = $this->read($statePath);
            $cache = $this->read($cachePath);
            $now = $this->clock->now()->getTimestamp();
            if (($state['next_at'] ?? 0) > $now) {
                if (empty($state['error']) && isset($cache['body']) && ($cache['reusable'] ?? true)) return $cache['body'];
                throw new \RuntimeException('Proveedor en pausa hasta ' . gmdate('c', (int) $state['next_at']) . '.');
            }
            $state['last_attempt_at'] = $now;
            // Persist before I/O: crashes/timeouts cannot cause immediate re-entry.
            $this->write($statePath, ['next_at' => $now + $minimumInterval, 'error' => true, 'failures' => $state['failures'] ?? 0,
                'last_attempt_at' => $now, 'last_success_at' => $state['last_success_at'] ?? null,
                'last_checked_at' => $state['last_checked_at'] ?? null, 'status' => null, 'outcome' => 'pending']);
            if (($cache['url'] ?? '') === $url) {
                if (!empty($cache['etag'])) $headers['If-None-Match'] = $cache['etag'];
                if (!empty($cache['modified'])) $headers['If-Modified-Since'] = $cache['modified'];
            }
            $headers['User-Agent'] = ($headers['User-Agent'] ?? 'SIGRA') . ' (+https://github.com/JKGhost01/SIGRA)';
            try {
                $response = $this->send($url, $timeoutSeconds, $headers);
                $now = $this->clock->now()->getTimestamp();
                $status = $response['status'];
                $responseHeaders = array_change_key_case($response['headers'], CASE_LOWER);
                if ($status === 304 && isset($cache['body']) && ($cache['url'] ?? '') === $url) {
                    $body = $cache['body'];
                } elseif ($status === 200) {
                    $body = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($body)) throw new \RuntimeException('Estructura de proveedor inválida.');
                } elseif ($status === 204) {
                    $body = ['type' => 'FeatureCollection', 'features' => []];
                } else {
                    $this->defer($statePath, $state, $status, $responseHeaders['retry-after'] ?? '', $now);
                    throw new \RuntimeException('El proveedor respondió con HTTP ' . $status . '.');
                }
            } catch (\Throwable $error) {
                // HTTP errors already recorded their server-directed or longer pause.
                if (!isset($status) || in_array($status, [200, 204, 304], true)) {
                    $this->defer($statePath, $state, $status ?? 0, '', $this->clock->now()->getTimestamp());
                }
                throw new \RuntimeException('No se pudo consultar el proveedor.', 0, $error);
            }
            $cacheControl = ($responseHeaders['cache-control'] ?? '') ?: ($status === 304 ? ($cache['cache_control'] ?? '') : '');
            if (!preg_match('/(?:^|,)\s*no-store\b/i', $cacheControl)) {
                $this->write($cachePath, ['url' => $url, 'body' => $body, 'cache_control' => $cacheControl,
                'reusable' => !preg_match('/(?:^|,)\s*no-cache\b/i', $cacheControl),
                'etag' => ($responseHeaders['etag'] ?? '') ?: ($status === 304 ? ($cache['etag'] ?? '') : ''),
                'modified' => ($responseHeaders['last-modified'] ?? '') ?: ($status === 304 ? ($cache['modified'] ?? '') : '')]);
            } elseif (is_file($cachePath)) {
                unlink($cachePath);
            }
            $delay = $minimumInterval;
            if (preg_match('/(?:^|,)\s*max-age=(\d+)/i', $cacheControl, $matches)) {
                $delay = max($delay, min(PHP_INT_MAX - $now, (int) $matches[1]));
            }
            $delay = max($delay, $this->retryDelay($responseHeaders['retry-after'] ?? '', $now));
            $this->write($statePath, ['next_at' => $now + $delay, 'error' => false, 'failures' => 0,
                'last_attempt_at' => $state['last_attempt_at'] ?? $now, 'last_checked_at' => $now,
                'last_success_at' => $now, 'status' => $status, 'outcome' => 'success']);
            return $body;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function send(string $url, int $timeoutSeconds, array $headers): array
    {
        if ($this->request !== null) {
            $response = ($this->request)($url, $timeoutSeconds, $headers);
        } else {
            $incoming = new \CodeIgniter\HTTP\Response(config(\Config\App::class));
            // Response defaults describe outgoing HTML pages, not the remote provider's cache policy.
            $incoming->removeHeader('Cache-Control');
            $incoming->removeHeader('Content-Type');
            $response = \Config\Services::curlrequest(['timeout' => $timeoutSeconds,
                'connect_timeout' => min(5, $timeoutSeconds), 'http_errors' => false,
                'allow_redirects' => false, 'verify' => true, 'headers' => $headers], $incoming, null, false)->get($url);
        }
        if (is_array($response)) return $response;
        $resultHeaders = [];
        foreach (['ETag', 'Last-Modified', 'Retry-After', 'Cache-Control'] as $name) {
            $resultHeaders[$name] = $this->responseHeader($response, $name);
        }
        return ['status' => $response->getStatusCode(), 'headers' => $resultHeaders, 'body' => $response->getBody()];
    }

    private function responseHeader(\CodeIgniter\HTTP\ResponseInterface $response, string $name): string
    {
        $header = $response->getHeader($name);
        if ($header === null) return '';
        $values = [];
        foreach (is_array($header) ? $header : [$header] as $entry) {
            $value = trim($entry->getValueLine());
            if ($value !== '') $values[] = $value;
        }
        if ($values === []) return '';
        if ($name === 'Cache-Control') return implode(', ', $values);
        if ($name === 'Retry-After') {
            // HTTP dates contain commas. Parse each header separately and respect the longest delay.
            $now = $this->clock->now()->getTimestamp();
            return (string) max(array_map(fn (string $value): int => $this->retryDelay($value, $now), $values));
        }
        // Different ETags or modification dates cannot form a valid conditional request.
        return count(array_unique($values)) === 1 ? $values[0] : '';
    }

    private function defer(string $path, array $state, int $status, string $retry, int $now): void
    {
        $failures = min(12, (int) ($state['failures'] ?? 0) + 1);
        $base = match ($status) { 401, 403 => $failures === 1 ? 7200 : 86400, 429 => 900, default => 600 };
        $delay = in_array($status, [401, 403], true) ? $base : min(21600, $base * (2 ** ($failures - 1)));
        $delay += random_int(0, (int) ($delay / 10));
        $delay = max($delay, $this->retryDelay($retry, $now));
        $this->write($path, ['next_at' => $now + $delay, 'error' => true, 'failures' => $failures, 'status' => $status,
            'last_attempt_at' => $state['last_attempt_at'] ?? $now, 'last_checked_at' => $now,
            'last_success_at' => $state['last_success_at'] ?? null,
            'outcome' => $status === 0 ? 'connection_error' : (in_array($status, [200, 204, 304], true) ? 'invalid_response' : 'http_error')]);
    }

    private function retryDelay(string $value, int $now): int
    {
        $value = trim($value);
        if (ctype_digit($value)) return min(PHP_INT_MAX - $now, (int) $value);
        $date = $value === '' ? false : strtotime($value);
        return $date === false ? 0 : max(0, $date - $now);
    }

    private function read(string $path): array
    {
        if (!is_file($path)) return [];
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) throw new \RuntimeException('Estado de protección inválido; consulta suspendida.');
        return $data;
    }

    private function write(string $path, array $data): void
    {
        $temp = tempnam($this->directory, 'provider-');
        if ($temp === false) throw new \RuntimeException('No se pudo reservar el estado del proveedor.');
        try {
            if (file_put_contents($temp, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temp, $path)) {
                throw new \RuntimeException('No se pudo guardar la protección del proveedor.');
            }
        } finally {
            if (is_file($temp)) unlink($temp);
        }
    }
}
