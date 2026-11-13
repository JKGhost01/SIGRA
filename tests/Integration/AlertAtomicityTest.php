<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/Support/NetworkPorts.php';

$db = db_connect();
$settings = new App\Modules\SensorNetwork\Application\NetworkSettings();
$clock = new App\Shared\Infrastructure\SystemClock();
$zone = new DateTimeZone('America/Bogota');
$fusion = new App\Modules\Alerting\Domain\PwaveFusion();
$devices = new FakeDeviceRepository();
$detections = new FakeDetectionRepository();
$deliveries = new FakeDeliveryRepository();
$push = new FakePushPublisher();
$repository = new App\Modules\Alerting\Infrastructure\Persistence\CiAlertRepository(new App\Models\RedAlertModel($db));
$delivery = new App\Modules\Alerting\Application\Commands\DeliverAlert\AlertDeliveryHandler($settings, $fusion, $devices, $deliveries, $push, $clock, $zone);
$transactions = new App\Shared\Infrastructure\CiTransactionManager($db);
$failedLog = new FakeAlertDecisionLog();
$failedLog->hooks['record'] = static function (): void { throw new RuntimeException('Injected audit outage'); };
$event = ['id' => 'QA-atomic-audit', 'fuente' => 'SGC', 'magnitud' => 4.2, 'latitud' => 4.6,
    'longitud' => -74.1, 'fecha_hora' => '2026-11-12 10:00:00'];
$construct = static fn($log) => new App\Modules\Alerting\Application\AlertingService($settings, $fusion,
    $devices, $detections, $repository, $deliveries, $delivery, $clock, $zone, $log, $transactions);
expectArchitecture($repository->buscarPorSource('sgc', $event['id']) === null, 'Atomicity fixture already exists');
$uuid = null;
try {
    try { $construct($failedLog)->crearAlertaSgc($event); throw new RuntimeException('Audit outage did not fail closed'); }
    catch (RuntimeException $error) { expectArchitecture($error->getMessage() === 'Injected audit outage', 'Unexpected persistence error'); }
    expectArchitecture($repository->buscarPorSource('sgc', $event['id']) === null, 'Unaudited alert escaped the transaction');
    expectArchitecture($devices->calls === [] && $push->calls === [], 'Delivery began before decision audit committed');
    $service = $construct(new App\Modules\Alerting\Infrastructure\Persistence\CiAlertDecisionLog($db));
    $alert = $service->crearAlertaSgc($event);
    $uuid = $alert['alert_uuid'];
    expectArchitecture($alert['status'] === 'active', 'Audited alert did not persist');
    expectArchitecture($db->table('sigra_alert_decisions')->where('candidate_id', 'alert:' . $uuid)->countAllResults() === 1, 'Alert and decision did not commit together');
    $service->cancel($uuid, 'isolated atomicity check');
    expectArchitecture($repository->buscarPorUuid($uuid)['status'] === 'cancelled', 'Audited cancellation failed');
    expectArchitecture($db->table('sigra_alert_decisions')->where('candidate_id', 'alert:' . $uuid)->countAllResults() === 2, 'Cancellation decision missing');
    expectArchitecture($push->calls === [], 'Atomicity test reached a notification publisher');
} finally {
    if ($uuid !== null) $db->table('sigra_alert_decisions')->where('candidate_id', 'alert:' . $uuid)->delete();
    $db->table('red_alerts')->where('source', 'sgc')->where('source_id', $event['id'])->delete();
}
echo "MySQL: alert/audit atomicity, fail-closed delivery and cancellation passed.\n";
