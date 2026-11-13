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
