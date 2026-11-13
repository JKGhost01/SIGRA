<?php

declare(strict_types=1);
namespace App\Modules\SeismicIngestion\Infrastructure\Sources;

/** Bounded retry within the original per-provider time budget; TLS is always verified. */
final class CiFeedTransport implements FeedTransport
{
    public function json(string $url, int $timeoutSeconds, array $headers): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $remaining = max(0.1, $deadline - microtime(true));
            $response = \Config\Services::curlrequest(['timeout' => $remaining,
                'connect_timeout' => min(5.0, $remaining), 'http_errors' => false,
                'verify' => true, 'headers' => $headers])->get($url);
            $status = $response->getStatusCode();
            if ($status === 200) {
                try { $body = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR); }
                catch (\JsonException $error) { throw new \RuntimeException('El proveedor devolvió JSON inválido.', 0, $error); }
                if (!is_array($body)) throw new \RuntimeException('El proveedor devolvió una estructura inválida.');
                return $body;
            }
            $retry = trim($response->getHeaderLine('Retry-After'));
            $delay = $retry === '' ? 0.25 : (ctype_digit($retry) ? max(0.25, (float) $retry) : INF);
            if ($attempt === 0 && in_array($status, [429, 502, 503, 504], true)
                && $delay <= 1 && microtime(true) + $delay + 0.1 < $deadline) {
                usleep((int) ($delay * 1000000));
                continue;
            }
            throw new \RuntimeException('El proveedor respondió con HTTP ' . $status . '.');
        }
        throw new \RuntimeException('Se agotó el presupuesto de consulta del proveedor.');
    }
}
