<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$database = $testDatabase;
$db = db_connect();
expectArchitecture($db->database === $database, 'Refusing to test an unexpected database');
$db->transBegin();
try {
    $input = json_decode(file_get_contents(dirname(__DIR__) . '/Contract/fixtures/legacy-event.json'), true)['input'];
    $input['id'] = 'QA-architecture';
    $input['fecha_hora'] = date('Y-m-d H:i:s');
    unset($input['citizen_reports_count']);
    $writer = new App\Modules\SeismicCatalog\Infrastructure\Persistence\CiEventWriter(new App\Models\SismoModel($db));
    expectArchitecture($writer->save([$input]) === 1, 'Catalog insert failed');
    expectArchitecture($writer->save([$input]) === 0, 'Catalog batch is not idempotent');
    $reader = new App\Modules\SeismicCatalog\Infrastructure\Persistence\CiSeismicEventRepository(new App\Models\SismoModel($db));
    expectArchitecture($reader->get('QA-architecture')?->record['ubicacion'] === 'Fixture', 'Catalog round trip failed');
    $references = [['source' => $input['fuente'], 'source_event_id' => $input['id']],
        ['source' => 'EMSC', 'source_event_id' => 'QA-emsc']];
    $input['sgc_metadata'] = json_encode(['source_id' => $input['id'], 'source_references' => $references]);
    expectArchitecture($writer->save([$input]) === 1, 'Could not record provider provenance');
    $input['magnitud'] = (float) $input['magnitud'] + 0.1;
    $input['sgc_metadata'] = json_encode(['source_id' => $input['id'], 'updated' => 'new provider revision']);
    expectArchitecture($writer->save([$input]) === 1, 'Scientific revision was not persisted');
    $stored = $reader->get('QA-architecture')->record;
    expectArchitecture(json_decode($stored['sgc_metadata'], true)['source_references'] === $references,
        'Partial refresh erased correlated provider references in MySQL');
    expectArchitecture(abs((float) $stored['magnitud'] - $input['magnitud']) < 0.000001,
        'Retaining provenance kept an outdated magnitude');
    expectArchitecture($writer->save([$input]) === 0, 'Repeated partial refresh is not idempotent');
    $secondary = array_replace($input, ['id' => 'QA-us', 'fuente' => 'USGS', 'latitud' => 20, 'longitud' => -100,
        'sgc_metadata' => json_encode(['source_id' => 'qa-us-external', 'url' => 'https://example.org/us'])]);
    $primary = array_replace($secondary, ['id' => 'QA-sgc', 'fuente' => 'SGC',
        'sgc_metadata' => json_encode(['source_id' => 'QA-sgc', 'url' => 'https://example.org/sgc'])]);
    expectArchitecture($writer->save([$secondary]) === 1, 'Could not store initial secondary observation');
    $db->table('citizen_reports')->insert(['reported_at' => date('Y-m-d H:i:s'), 'latitude' => 20, 'longitude' => -100,
        'felt_intensity' => 3, 'place' => 'QA-evidence', 'reporter_key' => str_repeat('c', 64),
        'network_key' => str_repeat('d', 64), 'event_id' => 'QA-us', 'status' => 'confirmed', 'created_at' => date('Y-m-d H:i:s')]);
    $reportId = $db->insertID();
    expectArchitecture($writer->save([$primary]) === 1, 'Delayed preferred source was not saved');
    expectArchitecture($db->table('citizen_reports')->where('id', $reportId)->get()->getRowArray()['event_id'] === 'QA-sgc',
        'Consolidation orphaned citizen evidence');
    expectArchitecture((int) $reader->get('QA-sgc')->record['citizen_reports_count'] === 1, 'Citizen evidence count was lost');
    $alias = $db->table('sismos')->where('id', 'QA-us')->get()->getRowArray();
    expectArchitecture($alias['canonical_event_id'] === 'QA-sgc', 'Original duplicate was not retained as an alias');
    expectArchitecture($reader->get('QA-us')?->record['id'] === 'QA-sgc', 'Old catalog URL no longer resolves');
    expectArchitecture($reader->get('qa-us-external')?->record['id'] === 'QA-sgc', 'Provider ID no longer resolves');
    $model = new App\Models\SismoModel($db);
    $filtered = $model->obtenerRecientesFiltrados(120, null, null, 'USGS');
    expectArchitecture(in_array('QA-sgc', array_column($filtered, 'id'), true)
        && !in_array('QA-us', array_column($filtered, 'id'), true), 'Secondary source filter excluded the canonical event or included an alias');
    $secondary['magnitud'] = 5.5;
    $secondary['fecha_hora'] = date('Y-m-d H:i:s', time() - 900);
    $writer->save([$secondary]);
    expectArchitecture($reader->get('QA-sgc')->record['magnitud'] == $primary['magnitud'], 'Secondary refresh replaced preferred magnitude');
    expectArchitecture($writer->save([$secondary]) === 0, 'Replayed secondary refresh changed the canonical event');
    // Simulate a duplicate already present before deploying this correction.
    $legacy = array_replace($primary, ['id' => 'QA-emsc', 'fuente' => 'EMSC',
        'sgc_metadata' => json_encode(['source_id' => 'qa-em-external'])]);
    $model->guardarEventos([$legacy]);
    $writer->save([$primary]);
    expectArchitecture($reader->get('QA-emsc')?->record['id'] === 'QA-sgc', 'Legacy duplicate survived a refresh');
    $refs = App\Modules\SeismicCatalog\Domain\Services\SourceProvenance::sources($reader->get('QA-sgc')->record);
    expectArchitecture($refs === ['SGC', 'USGS', 'EMSC'], 'Persistent consolidation lost source identities');
    expectArchitecture(count(array_filter($model->obtenerRecientes(), static fn(array $e): bool => in_array($e['id'], ['QA-sgc', 'QA-us', 'QA-emsc'], true))) === 1,
        'Map query still returns multiple rows for one earthquake');
    // A late secondary provider updates a stored snapshot while a different
    // provider revises a normalized row in the very same updateBatch().
    $snapshot = array_replace($input, ['id' => 'QA-mixed-sgc', 'latitud' => 10, 'longitud' => 10,
        'fuente' => 'SGC', 'sgc_metadata' => '{}']);
    $independent = array_replace($snapshot, ['id' => 'QA-mixed-emsc', 'latitud' => -30, 'longitud' => 70, 'fuente' => 'EMSC']);
    $model->guardarEventos([$snapshot, $independent]);
    $lateProvider = array_replace($snapshot, ['id' => 'QA-mixed-usgs', 'fuente' => 'USGS']);
    $revision = array_replace($independent, ['magnitud' => 4.9]);
    expectArchitecture($writer->save([$lateProvider, $revision]) === 2, 'Mixed stored/fresh observations rolled back the catalog transaction');
    expectArchitecture((float) $reader->get('QA-mixed-emsc')->record['magnitud'] === 4.9, 'Mixed batch lost the independent scientific revision');
    expectArchitecture(App\Modules\SeismicCatalog\Domain\Services\SourceProvenance::sources($reader->get('QA-mixed-sgc')->record) === ['SGC', 'USGS'],
        'Mixed batch lost the late provider reference');
    expectArchitecture($writer->save([$lateProvider, $revision]) === 0, 'Mixed batch replay is not idempotent');
    $audit = new App\Modules\SeismicIngestion\Infrastructure\Persistence\CiIngestionAuditRepository($db);
    $audit->record(['batch_id' => str_repeat('a', 32), 'started_at_utc' => gmdate('Y-m-d H:i:s'),
        'finished_at_utc' => gmdate('Y-m-d H:i:s'), 'duration_ms' => 1, 'saved_count' => 1, 'summary' => '{}']);
    $decisions = new App\Modules\Alerting\Infrastructure\Persistence\CiAlertDecisionLog($db);
    $decisions->record(['candidate_id' => 'QA-test', 'state' => 'candidate', 'algorithm_version' => 'test-v1',
        'reason' => 'test', 'evidence' => '{}', 'recorded_at_utc' => gmdate('Y-m-d H:i:s')]);
    expectArchitecture($db->table('sigra_alert_decisions')->where('candidate_id', 'QA-test')->countAllResults() === 1, 'Decision audit not persisted');
    expectArchitecture($db->table('sigra_ingestion_batches')->where('batch_id', str_repeat('a', 32))->countAllResults() === 1, 'Batch audit not persisted');
} finally {
    $db->transRollback();
}
expectArchitecture($db->table('sismos')->where('id', 'QA-architecture')->countAllResults() === 0, 'Integration transaction leaked fixtures');
echo "MySQL: catalog idempotency, repository round trips, audits and rollback passed.\n";
