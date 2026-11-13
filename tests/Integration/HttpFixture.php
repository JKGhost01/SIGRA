<?php

require __DIR__ . '/bootstrap.php';
$db = db_connect();
$id = 'QA-http-modular';
$device = $db->table('red_devices')->where('device_uuid', $id)->get()->getRowArray();
if ($device) {
    $ids = array_column($db->table('red_detections')->select('id')->where('device_id', $device['id'])->get()->getResultArray(), 'id');
    if ($ids !== []) $db->table('sigra_alert_decisions')->whereIn('candidate_id', array_map(static fn($id) => 'detection:' . $id, $ids))->delete();
    $db->table('red_detections')->where('device_id', $device['id'])->delete();
    $db->table('red_alert_deliveries')->where('device_id', $device['id'])->delete();
    $db->table('red_devices')->where('id', $device['id'])->delete();
}
$db->table('sismos')->where('id', $id)->delete();
$db->table('citizen_reports')->where('place', $id)->delete();
if (($argv[1] ?? '') === 'seed') {
    $db->table('sismos')->insert(['id' => $id, 'latitud' => 4.6, 'longitud' => -74.1, 'magnitud' => 4.2,
        'profundidad' => 10, 'fuente' => 'SGC', 'ubicacion' => $id, 'estado' => 'automatico',
        'fecha_hora' => (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d H:i:s'), 'sgc_metadata' => '{}']);
}
echo "HTTP fixtures prepared.\n";
