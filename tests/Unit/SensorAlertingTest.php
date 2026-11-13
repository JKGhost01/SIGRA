<?php

declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/Support/NetworkPorts.php';

use App\Modules\SensorNetwork\Application\{NetworkSettings, SensorNetworkService};
use App\Modules\Alerting\Application\AlertingService;
use App\Modules\Alerting\Application\Commands\DeliverAlert\AlertDeliveryHandler;
use App\Modules\Alerting\Domain\{AlertCandidate, CandidateState, PwaveFusion};
use App\Shared\Application\OperationRejected;

$clock = new class implements App\Shared\Domain\Clock {
    public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-11-12T15:00:00.000Z'); }
};
$transactions = new class implements App\Shared\Application\TransactionManager { public function run(callable $action): mixed { return $action(); } };
$settings = new NetworkSettings();
$devices = new FakeDeviceRepository();
$detections = new FakeDetectionRepository();
$alerts = new FakeAlertRepository();
$deliveries = new FakeDeliveryRepository();
$push = new FakePushPublisher();
$decisions = new FakeAlertDecisionLog();
$statistics = new FakeNetworkStatistics();
$zone = new DateTimeZone('America/Bogota');
$fusion = new PwaveFusion();
$delivery = new AlertDeliveryHandler($settings, $fusion, $devices, $deliveries, $push, $clock, $zone);
$evaluate = new AlertingService($settings, $fusion, $devices, $detections, $alerts, $deliveries, $delivery, $clock, $zone, $decisions, $transactions);
$network = new SensorNetworkService($settings, $devices, $detections, $evaluate, $statistics, $clock, $zone, 'test', 'v1',
    new App\Modules\SensorNetwork\Application\Commands\RegisterDetection\RegisterDetectionHandler($settings, $devices, $detections, $evaluate, $clock, $zone));
$device = ['id' => 1, 'device_uuid' => 'test-device', 'last_detection_at' => null];
$payload = ['latitude' => 4.6, 'longitude' => -74.1, 'confidence' => 0.9, 'detected_at_ms' => 1794495600000];
$payload['detected_at_ms'] = (int) $clock->now()->format('Uv');
// One phone produces provisional evidence, never delivery or an official event.
$detections->hooks['recientes'] = fn() => [$payload + ['device_uuid' => 'test-device']];
$result = $network->registrarDeteccion($device, $payload);
expectArchitecture($result['accepted'] && $result['alert'] === null, 'One phone published an alert');
expectArchitecture($push->calls === [] && $alerts->calls === [], 'A single signal touched notification channels');
expectArchitecture($decisions->calls[0][1][0]['state'] === 'candidate', 'Candidate evidence was not audited');
// A valid replay is recognized before minimum interval and quota rejection.
$detections->hooks['findDuplicate'] = fn() => ['id' => 123];
$replay = $network->registrarDeteccion(array_replace($device, ['last_detection_at' => '2026-11-12 10:00:00']), $payload);
expectArchitecture(($replay['duplicate'] ?? false) && $replay['detection_id'] === 123, 'Retry was rate-limited instead of idempotently acknowledged');
$detections->calls = [];
$devices->calls = [];
$simulated = $network->registrarDeteccion($device, $payload + ['simulated' => true]);
expectArchitecture($simulated['simulated'] && $detections->calls === [] && $devices->calls === [] && $push->calls === [], 'Simulation touched live repositories or delivery');
foreach ([['latitude' => INF], ['confidence' => NAN], ['detected_at_ms' => $payload['detected_at_ms'] - 121000]] as $change) {
    try { $network->registrarDeteccion($device, array_replace($payload, $change)); throw new RuntimeException('Invalid signal accepted'); }
    catch (OperationRejected $error) { expectArchitecture($error->statusCode === 422, 'Wrong invalid signal status'); }
}
$settingsOff = new NetworkSettings(alertingEnabled: false);
$off = new AlertingService($settingsOff, $fusion, $devices, $detections, $alerts, $deliveries, $delivery, $clock, $zone, $decisions, $transactions);
expectArchitecture($off->evaluarEvento(['id' => 2])['alert'] === null && $off->crearAlertaSgc(['magnitud' => 9]) === null, 'Kill switch failed');
expectArchitecture(end($decisions->calls)[1][0]['reason'] === 'kill_switch', 'Kill switch was not audited');
$candidate = new AlertCandidate('test');
$candidate = $candidate->transition(CandidateState::UnderReview)->transition(CandidateState::ConfirmedByPolicy)->transition(CandidateState::Cancelled);
expectArchitecture($candidate->state === CandidateState::Cancelled, 'Candidate cancellation failed');
try { $candidate->transition(CandidateState::ConfirmedByPolicy); throw new RuntimeException('Cancelled candidate revived'); }
catch (DomainException) {}
$alerts->hooks['buscarPorUuid'] = fn() => ['id' => 7, 'status' => 'active'];
$evaluate->cancel('test-uuid', 'controlled test');
expectArchitecture(end($alerts->calls) === ['update', [7, ['status' => 'cancelled']]], 'Cancellation did not persist');
expectArchitecture($push->calls === [], 'Cancellation sent a real notification');
echo "Sensors/alerting: provisional evidence, retry, simulation, validation, kill switch, decisions and cancellation passed.\n";
