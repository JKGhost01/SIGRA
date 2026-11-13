<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Infrastructure\Scheduling;

use App\Modules\SeismicIngestion\Application\Contracts\RefreshCoordinator;
use App\Modules\SeismicIngestion\Application\DTO\CatalogRefreshResult;
use App\Shared\Domain\Clock;
use Psr\Log\LoggerInterface;

/** Limits browser-triggered refreshes and shares the CLI ingestion lock. */
final class FileRefreshCoordinator implements RefreshCoordinator
{
    public function __construct(
        private readonly string $directory,
        private readonly int $intervalSeconds,
        private readonly Clock $clock,
        private readonly LoggerInterface $logger,
    ) {}

    public function runIfDue(callable $operation, bool $force = false): CatalogRefreshResult
    {
        $previous = $this->read();
        if (!$force && $this->fresh($previous)) return $previous->cached();

        $lock = fopen($this->directory . '/ingestion.lock', 'c');
        if ($lock === false) throw new \RuntimeException('No se pudo abrir el bloqueo de ingesta.');
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return $previous->cached();
        }

        try {
            // Another request may have completed between the first read and lock acquisition.
            $previous = $this->read();
            if (!$force && $this->fresh($previous)) return $previous->cached();
            $attemptedAt = $this->clock->now()->getTimestamp();
            // Reserve the interval even if PHP is terminated while contacting a provider.
            $this->write(new CatalogRefreshResult(false, $attemptedAt, $previous->saved, $previous->sources, $previous->errors, $previous->lastSuccessAt));
            try {
                $result = $operation();
                $errors = $result['errores'] ?? [];
                $refreshed = ($result['eventos'] ?? []) !== [] || $errors === [];
                $current = new CatalogRefreshResult($refreshed, $attemptedAt,
                    (int) ($result['guardados'] ?? 0), $result['fuentes'] ?? [], $errors, $refreshed ? $attemptedAt : $previous->lastSuccessAt);
            } catch (\Throwable $error) {
                $this->logger->error('Browser catalog refresh failed: {message}', ['message' => $error->getMessage()]);
                $current = new CatalogRefreshResult(false, $attemptedAt, 0, [],
                    ['sync' => 'No se pudo actualizar; se conservan los eventos almacenados.'], $previous->lastSuccessAt);
            }
            $this->write($current);
            return $current;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function fresh(CatalogRefreshResult $result): bool
    {
        $age = $this->clock->now()->getTimestamp() - $result->timestamp;
        return $result->timestamp > 0 && $age >= 0 && $age < max(5, $this->intervalSeconds);
    }

    private function read(): CatalogRefreshResult
    {
        $path = $this->directory . '/catalog-refresh.json';
        $raw = is_file($path) ? file_get_contents($path) : false;
        $data = $raw === false ? null : json_decode($raw, true);
        if (!is_array($data)) return new CatalogRefreshResult();
        return new CatalogRefreshResult(false, (int) ($data['timestamp'] ?? 0), (int) ($data['saved'] ?? 0),
            is_array($data['sources'] ?? null) ? $data['sources'] : [],
            is_array($data['errors'] ?? null) ? $data['errors'] : [], (int) ($data['last_success_at'] ?? 0));
    }

    private function write(CatalogRefreshResult $result): void
    {
        $file = tempnam($this->directory, 'catalog-refresh-');
        if ($file === false) throw new \RuntimeException('No se pudo preparar el estado de ingesta.');
        try {
            $json = json_encode(['timestamp' => $result->timestamp, 'saved' => $result->saved,
                'sources' => $result->sources, 'errors' => $result->errors, 'last_success_at' => $result->lastSuccessAt], JSON_THROW_ON_ERROR);
            if (file_put_contents($file, $json) === false || !rename($file, $this->directory . '/catalog-refresh.json')) {
                throw new \RuntimeException('No se pudo guardar el estado de ingesta.');
            }
        } finally {
            if (is_file($file)) unlink($file);
        }
    }
}
