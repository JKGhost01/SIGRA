<?php

declare(strict_types=1);

/**
 * Reinicio remoto de la base de datos por HTTP (sin SSH).
 *
 * Uso:
 *   1. Genera un token largo y ponlo en TOKEN_ESPERADO.
 *   2. Sube este archivo a la raíz del sitio como __setup.php.
 *   3. Abre https://tu-dominio/__setup.php?token=TU_TOKEN
 *      (o ejecuta `php __setup.php TU_TOKEN` si tienes shell).
 *      Añade &seed=0 si quieres la base vacía, sin eventos del SGC.
 *   4. BORRA __setup.php del servidor en cuanto termine.
 *
 * Hace lo mismo que `php spark sigra:db-reset --force`:
 * elimina las tablas de SIGRA, aplica el esquema y siembra el SGC.
 */

const TOKEN_ESPERADO = 'CAMBIAR_ESTE_TOKEN';

$token = (string) ($_GET['token'] ?? $_SERVER['argv'][1] ?? '');
if (TOKEN_ESPERADO === '' || ! hash_equals(TOKEN_ESPERADO, $token)) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require FCPATH . 'app/Config/Paths.php';
$paths = new \Config\Paths();
require $paths->systemDirectory . '/Boot.php';

// bootConsole() no define ENVIRONMENT; bootSpark() sí. Lo hacemos aquí
// para poder reutilizar el arranque de consola desde HTTP.
require_once $paths->systemDirectory . '/Config/DotEnv.php';
(new \CodeIgniter\Config\DotEnv($paths->appDirectory . '/../'))->load();
if (! defined('ENVIRONMENT')) {
    $entorno = $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'production';
    define('ENVIRONMENT', $entorno);
}

\CodeIgniter\Boot::bootConsole($paths);

$db = db_connect();

$migracionesObsoletas = [
    '20260921000000_AddSgcSourceIndex.php',
    '20260921000001_AddSgcMetadata.php',
    '20260921000002_CreateSmsSubscriptions.php',
    '20260921000003_RepairSgcLocalTimes.php',
    '20261103000001_CreateRedNetworkTables.php',
    '20261103000002_DropSmsSubscriptions.php',
    '20261103000003_EnsureSismosTable.php',
];
foreach ($migracionesObsoletas as $archivo) {
    $ruta = APPPATH . 'Database/Migrations/' . $archivo;
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}

$tablas = [
    'red_alert_deliveries',
    'red_alerts',
    'red_detections',
    'red_devices',
    'sismos',
    'sms_subscriptions',
    'migrations',
];

$db->query('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tablas as $tabla) {
    $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($tabla));
}
$db->query('SET FOREIGN_KEY_CHECKS = 1');

service('migrations')->latest();

$sembrar = (string) ($_GET['seed'] ?? '1') !== '0';
if ($sembrar) {
    $seeder = new \CodeIgniter\Database\Seeder(new \Config\Database());
    $seeder->call('SigraSeeder');
}

foreach ($db->listTables() as $tabla) {
    $db->query('ANALYZE TABLE ' . $db->escapeIdentifiers($tabla));
}

echo "OK: base de datos recreada.\n";
echo 'Tablas: ' . implode(', ', $db->listTables()) . "\n";
echo 'Sismos: ' . $db->table('sismos')->countAllResults() . "\n";
echo "\nBORRA __setup.php del servidor ahora.\n";
