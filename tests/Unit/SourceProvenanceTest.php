<?php

declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\SeismicCatalog\Domain\Services\SourceProvenance;

$refs = [['source' => 'USGS', 'source_event_id' => 'us-one'], ['source' => 'EMSC', 'source_event_id' => 'em-one']];
$before = ['id' => 'us-one', 'fuente' => 'USGS', 'magnitud' => 4.2,
    'sgc_metadata' => json_encode(['source_id' => 'us-one', 'url' => 'https://old.example/', 'source_references' => $refs])];
$incoming = ['id' => 'us-one', 'fuente' => 'USGS', 'magnitud' => 4.3,
    'sgc_metadata' => json_encode(['source_id' => 'us-one', 'url' => 'https://updated.example/'])];
$retained = SourceProvenance::retain($before, $incoming);
$metadata = json_decode($retained['sgc_metadata'], true);
expectArchitecture($metadata['source_references'] === $refs, 'A partial refresh erased existing provider references');
expectArchitecture($retained['magnitud'] === 4.3 && $metadata['url'] === 'https://updated.example/', 'Provenance preservation blocked scientific corrections');
expectArchitecture(SourceProvenance::references($retained)[0]['url'] === 'https://updated.example/', 'Source reference kept an outdated preferred URL');
expectArchitecture(SourceProvenance::retain($retained, $incoming) === $retained, 'Repeated partial refresh changed metadata');
$extra = ['source' => 'GEOFON', 'source_event_id' => 'gfz-one'];
$incoming['sgc_metadata'] = json_encode(['source_id' => 'us-one', 'source_references' => [$refs[0], $extra]]);
$updated = SourceProvenance::retain($before, $incoming);
expectArchitecture(json_decode($updated['sgc_metadata'], true)['source_references'] === [...$refs, $extra], 'References duplicated or disappeared when a source returned');
$incoming['sgc_metadata'] = json_encode(['source_id' => 'another-event']);
expectArchitecture(SourceProvenance::retain($before, $incoming) === $incoming, 'Historical references leaked into a different source event');
$incoming['fuente'] = 'SGC';
expectArchitecture(SourceProvenance::retain($before, $incoming) === $incoming, 'Historical references leaked into another provider');
echo "Provenance: partial failures preserve historical references, scientific corrections apply, merges are idempotent and event identity is respected.\n";
