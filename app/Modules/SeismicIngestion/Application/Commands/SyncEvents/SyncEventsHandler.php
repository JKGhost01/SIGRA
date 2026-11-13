<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Application\Commands\SyncEvents;

use App\Modules\SeismicIngestion\Domain\Services\EventDeduplicator;
use App\Modules\SeismicCatalog\Application\Contracts\EventWriter;
use App\Modules\CitizenReports\Application\Contracts\ReportReconciler;
use Psr\Log\LoggerInterface;

final class SyncEventsHandler
{
    /** @param list<\App\Modules\SeismicIngestion\Domain\Contracts\SeismicSource> $sources */
    public function __construct(private readonly array $sources, private readonly EventWriter $catalog,
        private readonly EventDeduplicator $deduplicator, private readonly bool $deduplicate,
        private readonly ReportReconciler $reports, private readonly LoggerInterface $logger,
        private readonly ?\App\Modules\Alerting\Application\Contracts\OfficialEventNotifier $notifier = null,
        private readonly ?\App\Modules\SeismicIngestion\Application\Contracts\IngestionAuditRepository $audit = null,
        private readonly ?\App\Shared\Domain\Clock $clock = null) {}
    public function handle(): array
    {
        $startTime = $this->clock?->now() ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $batchId = bin2hex(random_bytes(16));
        $started = microtime(true);
        $batches = ['SGC' => [], 'USGS' => []]; $errors = [];
        foreach ($this->sources as $source) {
            try { $batches[$source->name()] = $source->fetch(); }
            catch (\App\Modules\SeismicIngestion\Domain\Contracts\SourceUnavailable $error) {
                $errors[$source->name()] = 'Fuente temporalmente no disponible.';
                $this->logger->error('Ingestion {source}: {message}', ['source' => $source->name(), 'message' => $error->getMessage()]);
            }
        }
        if ($this->deduplicate) {
            $correlation = $this->deduplicator->correlate($batches['USGS'], $batches['SGC']);
            $batches['SGC'] = $correlation['reference'];
            $batches['USGS'] = $correlation['unique'];
        }
        $events = array_merge(...array_values($batches));
        $saved = 0;
        if ($events !== [] || $errors === []) {
            // External calls have finished before any catalog transaction starts.
            $saved = $this->catalog->save($events);
            try { $this->reports->reconcile(); }
            catch (\Throwable $error) {
                $errors['reportes'] = 'No se pudieron contrastar los reportes ciudadanos.';
                $this->logger->error('Report reconciliation: {message}', ['message' => $error->getMessage()]);
            }
        }
        $this->audit?->record([
            'batch_id' => $batchId, 'started_at_utc' => $startTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'finished_at_utc' => ($this->clock?->now() ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000), 'saved_count' => $saved,
            'summary' => json_encode(['sources' => array_map('count', $batches), 'failed_sources' => array_keys($errors),
                'correlations' => $correlation['decisions'] ?? []], JSON_THROW_ON_ERROR),
        ]);
        $this->notifier?->catalogUpdated($events);
        $this->logger->info('Ingestion batch {batch_id}', [
            'batch_id' => $batchId, 'module' => 'SeismicIngestion', 'operation' => 'SyncEvents',
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'sources' => array_map('count', $batches), 'saved' => $saved,
            'correlations' => count($correlation['decisions'] ?? []), 'failed_sources' => array_keys($errors),
        ]);
        return ['eventos' => $events, 'guardados' => $saved,
            'fuentes' => array_map('count', $batches), 'errores' => $errors];
    }
}
