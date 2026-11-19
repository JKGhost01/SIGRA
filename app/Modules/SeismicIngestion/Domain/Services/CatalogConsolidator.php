<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Domain\Services;

use App\Modules\SeismicCatalog\Domain\Services\SourceProvenance;

/** Reconcile a refresh with persisted observations, including delayed providers. */
final class CatalogConsolidator
{
    public function __construct(private readonly EventDeduplicator $deduplicator) {}

    public function consolidate(array $stored, array $incoming): array
    {
        $rows = [];
        foreach ($stored as $event) $rows[(string) $event['id']] = $event;
        foreach ($incoming as $event) {
            $id = (string) $event['id'];
            $rows[$id] = isset($rows[$id]) ? SourceProvenance::retain($rows[$id], $event) : $event;
        }
        $priority = array_flip(['SGC', 'USGS', 'EMSC', 'GEONET', 'GEOFON', 'INGV']);
        uasort($rows, static fn(array $a, array $b): int =>
            (($priority[$a['fuente']] ?? 99) <=> ($priority[$b['fuente']] ?? 99)) ?: strcmp((string) $a['id'], (string) $b['id']));
        $batches = [];
        foreach ($rows as $event) $batches[$event['fuente']][] = $event;
        $events = []; $aliases = [];
        foreach ($batches as $batch) {
            $result = $this->deduplicator->correlate($batch, $events);
            $events = array_merge($result['reference'], $result['unique']);
            foreach ($result['decisions'] as $decision) $aliases[$decision['correlated_id']] = $decision['preferred_id'];
        }
        // Compress alias chains when a preferred provider arrives later.
        foreach ($aliases as &$id) {
            while (isset($aliases[$id])) $id = $aliases[$id];
        }
        unset($id);
        return ['events' => $events, 'aliases' => $aliases];
    }
}
