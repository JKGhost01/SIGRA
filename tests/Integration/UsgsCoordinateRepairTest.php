<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once FCPATH . 'app/Database/Migrations/20261113000001_RepairUsgsCoordinates.php';
$db = db_connect();
expectArchitecture($db->database === $testDatabase, 'Unexpected repair test database');
$db->transBegin();
try {
    $base = ['fecha_hora' => date('Y-m-d H:i:s'), 'magnitud' => 3, 'profundidad' => 10,
        'ubicacion' => 'Synthetic migration fixture', 'estado' => 'automatic', 'fuente' => 'USGS'];
    $rows = [
        ['id' => 'QA-coord-colombia', 'latitud' => -74.1, 'longitud' => 4.6, 'sgc_metadata' => '{"source_id":"colombia"}'],
        ['id' => 'QA-coord-pacific', 'latitud' => 145.6025, 'longitud' => 18.8925, 'sgc_metadata' => '{"source_id":"pacific"}'],
        ['id' => 'QA-coord-fixed', 'latitud' => 4.6, 'longitud' => -74.1, 'sgc_metadata' => '{"coordinate_order":"lat-lon-v2"}'],
        ['id' => 'QA-coord-sgc', 'latitud' => 4.6, 'longitud' => -74.1, 'sgc_metadata' => null, 'fuente' => 'SGC'],
    ];
    $db->table('sismos')->insertBatch(array_map(static fn($row) => $row + $base, $rows));
    $migration = new App\Database\Migrations\RepairUsgsCoordinates(Config\Database::forge($db));
    $migration->up();
    $migration->up();
    foreach ($rows as $row) {
        $actual = $db->table('sismos')->where('id', $row['id'])->get()->getRowArray();
        expectArchitecture((float) $actual['latitud'] === (str_ends_with($row['id'], 'pacific') ? 18.8925 : 4.6), 'Migration latitude/idempotence incorrect');
        expectArchitecture((float) $actual['longitud'] === (str_ends_with($row['id'], 'pacific') ? 145.6025 : -74.1), 'Migration longitude/idempotence incorrect');
    }
    $fixed = $db->table('sismos')->where('id', 'QA-coord-colombia')->get()->getRowArray();
    $metadata = json_decode($fixed['sgc_metadata'], true);
    expectArchitecture($metadata['source_id'] === 'colombia' && (float) $metadata['coordinate_repair']['previous_latitude'] === -74.1, 'Original coordinates/provenance lost');
    // The phpMyAdmin alternative uses a snapshot to avoid MySQL assignment-order problems.
    $db->table('sismos')->where('id', 'QA-coord-colombia')->update($rows[0]);
    $sql = file_get_contents(FCPATH . 'deploy/corregir-coordenadas-usgs.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql);
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '' && !in_array($statement, ['START TRANSACTION', 'COMMIT'], true)) $db->query($statement);
    }
    $actual = $db->table('sismos')->where('id', 'QA-coord-colombia')->get()->getRowArray();
    expectArchitecture((float) $actual['latitud'] === 4.6 && (float) $actual['longitud'] === -74.1, 'phpMyAdmin SQL repair incorrect');
} finally {
    $db->transRollback();
}
echo "USGS repair: Colombia/Pacific, corrected and SGC rows, audit, repeated migration and SQL alternative passed with rollback.\n";
