<?php

declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\SeismicIngestion\Domain\Services\{CatalogConsolidator, EventDeduplicator};
use App\Modules\SeismicCatalog\Domain\Services\SourceProvenance;

$dedupe = new EventDeduplicator(120, 75);
$catalog = new CatalogConsolidator($dedupe);
$observation = static fn(string $source, string $id, array $changes = []): array => array_replace([
    'id' => $id, 'fuente' => $source, 'fecha_hora' => '2026-11-19 10:00:00',
    'latitud' => 4.6, 'longitud' => -74.1, 'magnitud' => 4.2, 'profundidad' => 10,
    'sgc_metadata' => json_encode(['source_id' => $id, 'url' => 'https://example.org/' . $id]),
], $changes);
$usgs = $observation('USGS', 'us-one');
$sgc = $observation('SGC', 'sgc-one');
$emsc = $observation('EMSC', 'em-one');
$first = $catalog->consolidate([$usgs], [$sgc]);
expectArchitecture(count($first['events']) === 1 && $first['aliases'] === ['us-one' => 'sgc-one'], 'Delayed SGC did not consolidate the stored USGS event');
$canonical = $first['events'][0];
expectArchitecture($catalog->consolidate($first['events'], [$sgc, $usgs])['events'] === $first['events'], 'Full replay rewrote original correlation evidence');
expectArchitecture(SourceProvenance::sources($canonical) === ['SGC', 'USGS'], 'Delayed provider provenance disappeared');
$partial = $catalog->consolidate([$canonical], [$emsc]);
expectArchitecture(count($partial['events']) === 1 && count(SourceProvenance::sources($partial['events'][0])) === 3, 'Partial refresh created another event');
expectArchitecture($partial['events'][0]['id'] === 'sgc-one', 'Missing preferred source changed canonical identity');
$revised = $observation('USGS', 'us-one', ['fecha_hora' => '2026-11-19 10:15:00', 'magnitud' => 5.5]);
$revision = $catalog->consolidate($partial['events'], [$revised]);
expectArchitecture(count($revision['events']) === 1 && $revision['events'][0]['magnitud'] === 4.2, 'Known secondary revision split identity or replaced preferred scientific fields');
$sgcRevision = array_replace($sgc, ['magnitud' => 4.3]);
$revision = $catalog->consolidate($partial['events'], [$sgcRevision]);
expectArchitecture($revision['events'][0]['magnitud'] === 4.3 && count(SourceProvenance::sources($revision['events'][0])) === 3, 'Preferred scientific revision lost values or sources');
$replay = $catalog->consolidate($revision['events'], [$sgcRevision]);
expectArchitecture($replay['events'] === $revision['events'], 'Partial replay is not idempotent');
$legacy = $catalog->consolidate([$canonical, $usgs, $emsc], [$sgc]);
expectArchitecture(count($legacy['events']) === 1 && count($legacy['aliases']) === 2, 'Existing duplicates were not consolidated');
$near = $observation('USGS', 'us-two');
expectArchitecture(count($catalog->consolidate([$usgs], [$near])['events']) === 2, 'Different IDs from the same provider merged');
expectArchitecture(count($catalog->consolidate([$sgc], [$usgs, $near])['events']) === 3, 'Two observations competing for one event were merged arbitrarily');
$secondSgc = $observation('SGC', 'sgc-two');
expectArchitecture(count($catalog->consolidate([$sgc, $secondSgc], [$usgs])['events']) === 3, 'One observation competing for two events merged arbitrarily');
// Nested provenance must survive merging already-correlated batches.
$group = $dedupe->correlate([$emsc], [$usgs])['reference'];
$nested = $catalog->consolidate($group, [$sgc]);
expectArchitecture(count(SourceProvenance::sources($nested['events'][0])) === 3, 'Merging correlated batches discarded nested sources');
echo "Catalog consolidation: delayed sources, outages, scientific revisions, replay, legacy duplicates and ambiguous neighbours passed.\n";
