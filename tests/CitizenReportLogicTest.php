<?php

declare(strict_types=1);

// Pure validation/matching/challenge tests: no database queries or database substitute.
define('FCPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
$paths = new Config\Paths();
$paths->envDirectory = sys_get_temp_dir() . '/sigra-tests-no-dotenv-' . bin2hex(random_bytes(8));
CodeIgniter\Boot::bootConsole($paths);

use App\Libraries\CitizenReportMatcher;
use App\Libraries\CitizenReportService;
use App\Libraries\RedApiException;
use App\Libraries\ReportFormGuard;
use Config\CitizenReports;

$config = new CitizenReports();
$matcher = new CitizenReportMatcher($config);
$service = new CitizenReportService(); // Validation does not open a connection or query MySQL.
$now = time();
$checks = 0;
function verifyReport(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function rejectedReport(callable $action): void {
    try { $action(); } catch (RedApiException $error) { verifyReport($error->statusCode === 422, 'Unexpected input rejection'); return; }
    throw new RuntimeException('Invalid report accepted');
}
$event = ['id' => 'SGC-test', 'fuente' => 'SGC', 'fecha_hora' => date('Y-m-d H:i:s', $now),
    'latitud' => 4.6, 'longitud' => -74.1, 'citizen_reports_count' => 0];
$reports = [];
foreach (range(1, 3) as $id) $reports[] = ['id' => $id, 'reported_at' => date('Y-m-d H:i:s', $now),
    'latitude' => 4.6, 'longitude' => -74.1, 'reporter_key' => 'visitor-' . $id,
    'network_key' => 'network-' . $id, 'status' => 'pending', 'event_id' => null];
$result = $matcher->classify($reports, []);
verifyReport($result['events'] === [] && $result['reports'] === [], 'Reports created an event without SGC');
$result = $matcher->classify($reports, [array_replace($event, ['fuente' => 'USGS'])]);
verifyReport($result['events'] === [], 'USGS confirmed reports');
$result = $matcher->classify(array_slice($reports, 0, 2), [$event]);
verifyReport($result['events'] === [], 'Two reports reached threshold');
$result = $matcher->classify($reports, [$event]);
verifyReport($result['events'] === [['id' => 'SGC-test', 'citizen_reports_count' => 3]], 'Threshold did not annotate SGC');
verifyReport(array_column($result['reports'], 'status') === ['confirmed', 'confirmed', 'confirmed'], 'Reports not confirmed');
$confirmed = [];
foreach ($reports as $i => $report) $confirmed[] = array_replace($report, $result['reports'][$i]);
verifyReport($matcher->classify($confirmed, [array_replace($event, ['citizen_reports_count' => 3])]) ===
    ['reports' => [], 'events' => []], 'Matching is not idempotent');
foreach (['network_key', 'reporter_key'] as $key) {
    $duplicate = $reports; $duplicate[2][$key] = $duplicate[0][$key];
    $result = $matcher->classify($duplicate, [$event]);
    verifyReport($result['events'] === [] && in_array('duplicate', array_column($result['reports'], 'status'), true), 'Duplicate identity inflated count');
}
$result = $matcher->classify($reports, [$event, array_replace($event, ['id' => 'SGC-second'])]);
verifyReport($result['events'] === [], 'Ambiguous event matched');
$far = $reports; $far[2]['latitude'] = 40;
verifyReport($matcher->classify($far, [$event])['events'] === [], 'Distant report confirmed event');
$old = $reports; $old[2]['reported_at'] = date('Y-m-d H:i:s', $now - 301);
verifyReport($matcher->classify($old, [$event])['events'] === [], 'Out-of-window report confirmed event');
$expired = $reports; $expired[2]['status'] = 'expired';
verifyReport($matcher->classify($expired, [$event])['events'] === [], 'Expired report confirmed event');
verifyReport($matcher->classify($reports, [array_replace($event, ['id' => 'SGI-forged'])])['events'] === [], 'Legacy spoofed SGC report confirmed event');
$config->minParticipants = 4;
verifyReport((new CitizenReportMatcher($config))->classify($reports, [$event])['events'] === [], 'Configured threshold ignored');

$input = ['latitud' => '4.6', 'longitud' => '-74.1', 'magnitud' => '7',
    'ubicacion' => 'Prueba', 'fecha_hora' => date('Y-m-d\TH:i', $now), 'fuente' => 'SGC', 'estado' => 'activo'];
$valid = $service->validate($input, $now);
verifyReport($valid['felt_intensity'] === 7.0 && !isset($valid['magnitud'], $valid['fuente'], $valid['estado']), 'Untrusted scientific fields survived');
foreach ([['latitud', ['4.6']], ['longitud', '181'], ['magnitud', '1e309'], ['ubicacion', '<script>'],
    ['ubicacion', str_repeat('a', 151)], ['fecha_hora', "2026-11-12T10:00\0"],
    ['fecha_hora', '2026-02-31T10:10'], ['fecha_hora', date('Y-m-d\TH:i', $now - 90000)],
    ['fecha_hora', date('Y-m-d\TH:i', $now + 600)]] as [$key, $value]) {
    rejectedReport(fn () => $service->validate(array_replace($input, [$key => $value]), $now));
}
$token = ReportFormGuard::issue();
verifyReport(!ReportFormGuard::verify(['report_token' => $token], time()), 'Immediate submission passed');
$token = ReportFormGuard::issue();
verifyReport(!ReportFormGuard::verify(['report_token' => $token, 'website' => 'spam'], time() + 4), 'Honeypot passed');
$token = ReportFormGuard::issue();
verifyReport(ReportFormGuard::verify(['report_token' => $token], time() + 4), 'Valid form rejected');
verifyReport(!ReportFormGuard::verify(['report_token' => $token], time() + 4), 'Replay passed');
$token = ReportFormGuard::issue();
verifyReport(!ReportFormGuard::verify(['report_token' => $token], time() + 3601), 'Expired challenge passed');
echo "{$checks} report logic checks passed (no SQL queries).\n";
