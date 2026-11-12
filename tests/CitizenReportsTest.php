<?php

declare(strict_types=1);

// MySQL integration on the existing SIGRA database. Fixture writes are rolled back.
define('FCPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
CodeIgniter\Boot::bootConsole(new Config\Paths());

use App\Libraries\CitizenReportService;
use App\Libraries\RedApiException;
use App\Libraries\ReportFormGuard;
use App\Models\CitizenReportStore;
use App\Models\SismoModel;
use Config\CitizenReports;
use Config\Database;

$db = db_connect();
try { $db->query('SELECT 1'); } catch (Throwable) {
    fwrite(STDERR, "No se pudo acceder a MySQL desde este entorno. Ejecuta esta prueba en el equipo donde corre SIGRA.\n");
    exit(2);
}
if ($db->DBDriver !== 'MySQLi' || !$db->tableExists('citizen_reports')) {
    throw new RuntimeException('Use MySQL y aplique primero php spark migrate --all.');
}
foreach (['sismos', 'citizen_reports'] as $table) {
    $row = $db->query('SHOW TABLE STATUS WHERE Name = ?', [$db->prefixTable($table)])->getRowArray();
    if (strtolower($row['Engine'] ?? '') !== 'innodb') throw new RuntimeException('Se requiere InnoDB para revertir las pruebas.');
}
$fixturePrefix = bin2hex(random_bytes(4));
$marker = 'SIGRA_TEST_' . $fixturePrefix;
function fixtureId(string $id): string {
    global $fixturePrefix;
    return substr((str_starts_with($id, 'SGI') ? 'SGI' : 'QA') . $fixturePrefix . '-' . $id, 0, 24);
}
$store = new class($db, $marker, 'QA' . $fixturePrefix) extends CitizenReportStore {
    public function __construct($db, private string $marker, private string $prefix) { parent::__construct($db); }
    public function recentReports(string $since): array {
        return $this->db->table('citizen_reports')->where('place', $this->marker)->where('status !=', 'expired')
            ->where('reported_at >=', $since)->orderBy('id', 'ASC')->get()->getResultArray();
    }
    public function sgcEvents(string $since): array {
        return $this->db->table('sismos')->like('id', $this->prefix, 'after')->where('fuente', 'SGC')
            ->where('fecha_hora >=', $since)->get()->getResultArray();
    }
    public function expireAndPurge(string $cutoff, string $purge): void {
        $this->db->table('citizen_reports')->where('place', $this->marker)->where('status', 'pending')
            ->where('reported_at <', $cutoff)->update(['status' => 'expired', 'event_id' => null]);
        $this->db->table('citizen_reports')->where('place', $this->marker)->where('created_at <', $purge)->delete();
    }
};
$reportTable = fn () => $db->table('citizen_reports')->where('place', $marker);
$eventTable = fn () => $db->table('sismos')->groupStart()->like('id', 'QA' . $fixturePrefix, 'after')
    ->orLike('id', 'SGI' . $fixturePrefix, 'after')->groupEnd();
$db->transBegin();
try {
$config = new CitizenReports();
$config->hashKey = str_repeat('test-secret-', 4);
$service = new CitizenReportService($store, $config);
$model = new SismoModel($db);
$now = strtotime('2001-01-01 10:10:00');
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function blocked(callable $action, int $code): void {
    try { $action(); } catch (RedApiException $error) { check($error->statusCode === $code, 'Wrong rejection code'); return; }
    throw new RuntimeException('Expected rejection ' . $code);
}
function input(int $time, float $lat = 4.6, float $lng = -74.1): array {
    global $marker;
    return ['latitud' => (string) $lat, 'longitud' => (string) $lng, 'magnitud' => '7',
        'ubicacion' => $marker, 'fecha_hora' => date('Y-m-d\TH:i', $time),
        'fuente' => 'SGC', 'estado' => 'activo', 'profundidad' => '100'];
}
function event(string $id, int $time, string $source = 'SGC', float $lat = 4.6): array {
    return ['id' => fixtureId($id), 'fecha_hora' => date('Y-m-d H:i:s', $time), 'latitud' => $lat,
        'longitud' => -74.1, 'magnitud' => 3.2, 'profundidad' => 20, 'ubicacion' => 'Evento oficial',
        'fuente' => $source, 'estado' => 'revisado', 'sgc_metadata' => '{}'];
}

// Reports cannot produce a map event, even with forged source/state/depth fields.
foreach ([1, 2, 3] as $i) $service->submit(input($now), 'visitor-' . $i, '192.0.2.' . $i, $now);
check($eventTable()->countAllResults() === 0, 'Unconfirmed reports created events');
check($reportTable()->where('status', 'pending')->countAllResults() === 3, 'Reports not pending');
$model->guardarEventos([event('us-test', $now, 'USGS')]);
$service->reconcile($now);
check($reportTable()->where('status', 'confirmed')->countAllResults() === 0, 'USGS confirmed reports');
$model->guardarEventos([event('SGC-test', $now)]);
$service->reconcile($now);
check((int) $model->obtenerPorId(fixtureId('SGC-test'))['citizen_reports_count'] === 3, 'SGC matching threshold failed');
check($reportTable()->where('status', 'confirmed')->countAllResults() === 3, 'Reports not confirmed');
check((float) $model->obtenerPorId(fixtureId('SGC-test'))['magnitud'] === 3.2, 'Felt intensity overwrote magnitude');
check($eventTable()->countAllResults() === 2, 'Matching created duplicate events');
$service->reconcile($now);
check((int) $model->obtenerPorId(fixtureId('SGC-test'))['citizen_reports_count'] === 3, 'Reconciliation is not idempotent');
blocked(fn () => $service->submit(input($now), 'visitor-1', '192.0.2.1', $now + 1), 429);
// A new cookie/IP cannot turn the same network/visitor into additional users.
$service->submit(input($now), 'new-cookie', '192.0.2.1', $now + 61);
$service->submit(input($now), 'visitor-1', '192.0.2.90', $now + 61);
check((int) $model->obtenerPorId(fixtureId('SGC-test'))['citizen_reports_count'] === 3, 'Duplicate visitor/network inflated count');
check($reportTable()->where('status', 'duplicate')->countAllResults() === 2, 'Duplicates not classified');
$service->submit(input($now), 'visitor-4', '192.0.2.4', $now + 61);
check((int) $model->obtenerPorId(fixtureId('SGC-test'))['citizen_reports_count'] === 4, 'Fourth participant missing');
$changed = event('SGC-test', $now); $changed['magnitud'] = 3.4;
$model->guardarEventos([$changed]);
check((int) $model->obtenerPorId(fixtureId('SGC-test'))['citizen_reports_count'] === 4, 'Feed update erased tally');

// Fewer than three, distant, old or ambiguous reports do not confirm an event.
$later = $now + 3600;
$model->guardarEventos([event('SGC-later', $later)]);
$service->submit(input($later), 'later-a', '198.51.100.1', $later);
$service->submit(input($later), 'later-b', '198.51.100.2', $later);
check((int) $model->obtenerPorId(fixtureId('SGC-later'))['citizen_reports_count'] === 0, 'Two participants reached threshold');
$service->submit(input($later, 40), 'far-away', '198.51.100.3', $later);
check((int) $model->obtenerPorId(fixtureId('SGC-later'))['citizen_reports_count'] === 0, 'Distant report confirmed event');
$model->guardarEventos([event('SGC-ambiguous', $later + 5)]);
$service->submit(input($later), 'later-c', '198.51.100.4', $later);
check((int) $model->obtenerPorId(fixtureId('SGC-later'))['citizen_reports_count'] === 0, 'Ambiguous events confirmed');
$db->table('sismos')->where('id', fixtureId('SGC-ambiguous'))->delete();
$service->reconcile($later);
check((int) $model->obtenerPorId(fixtureId('SGC-later'))['citizen_reports_count'] === 3, 'Pending reports were not retried');

// Legacy reports, including old records spoofing SGC, stay hidden in all public reads.
$db->table('sismos')->insert(event('SGI-old', time(), 'SGC'));
$db->table('sismos')->insert(event('legacy-report', time(), 'Reporte ciudadano'));
check($model->obtenerPorId(fixtureId('SGI-old')) === null && $model->obtenerPorId(fixtureId('legacy-report')) === null, 'Legacy detail leaked');
foreach ([$model->obtenerTodos(), $model->obtenerHistorico([], 'fecha_hora', 'DESC'), $model->obtenerRecientes(336)] as $rows) {
    check(!array_intersect([fixtureId('SGI-old'), fixtureId('legacy-report')], array_column($rows, 'id')), 'Legacy public listing leaked');
}

// IPv6 privacy-address rotation cannot inflate votes or bypass minute budgets.
$service->submit(input($later), 'ipv6-a', '2001:db8:abcd:1::1', $later);
blocked(fn () => $service->submit(input($later), 'ipv6-b', '2001:db8:abcd:1::2', $later + 1), 429);
$service->submit(input($later), 'ipv6-b', '2001:db8:abcd:1::2', $later + 61);
check((int) $model->obtenerPorId(fixtureId('SGC-later'))['citizen_reports_count'] === 4, 'IPv6 /64 inflated count');

blocked(fn () => $service->validate(input($now) + [], $now - 1000), 422);
$invalid = input($now); $invalid['latitud'] = ['4.6'];
blocked(fn () => $service->validate($invalid, $now), 422);
$invalid = input($now); $invalid['magnitud'] = '1e309';
blocked(fn () => $service->validate($invalid, $now), 422);
$invalid = input($now); $invalid['ubicacion'] = str_repeat('a', 151);
blocked(fn () => $service->validate($invalid, $now), 422);
$invalid = input($now); $invalid['fecha_hora'] = '2026-02-31T10:10';
blocked(fn () => $service->validate($invalid, $now), 422);
blocked(fn () => $service->validate(input($now - 90000), $now), 422);
blocked(fn () => $service->validate(input($now + 600), $now), 422);

$service->reconcile($later + 49 * 3600);
check((int) $model->obtenerPorId(fixtureId('SGC-test'))['citizen_reports_count'] === 4, 'Historical count was erased');
check($reportTable()->where('status', 'expired')->countAllResults() > 0, 'Unmatched reports did not expire');
$service->reconcile($later + 32 * 86400);
check($reportTable()->countAllResults() === 0, 'Private report retention failed');
check((int) $model->obtenerPorId(fixtureId('SGC-test'))['citizen_reports_count'] === 4, 'Purging private data erased historical count');

$token = ReportFormGuard::issue();
check(!ReportFormGuard::verify(['report_token' => $token], time()), 'Fast bot passed');
$token = ReportFormGuard::issue();
check(!ReportFormGuard::verify(['report_token' => $token, 'website' => 'spam'], time() + 4), 'Honeypot passed');
$token = ReportFormGuard::issue();
check(ReportFormGuard::verify(['report_token' => $token], time() + 4), 'Valid form challenge rejected');
check(!ReportFormGuard::verify(['report_token' => $token], time() + 4), 'Form replay passed');
$token = ReportFormGuard::issue();
check(!ReportFormGuard::verify(['report_token' => $token], time() + 3601), 'Expired form challenge passed');

check($db->transStatus(), 'MySQL fixture transaction failed');
echo "{$checks} citizen report MySQL checks passed (existing database, rolled-back fixtures).\n";

} finally {
    $db->transRollback();
}
