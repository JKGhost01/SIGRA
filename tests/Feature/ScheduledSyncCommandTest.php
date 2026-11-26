<?php

declare(strict_types=1);

define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
$paths = new Config\Paths();
$paths->envDirectory = sys_get_temp_dir() . '/sigra-tests-no-env';
CodeIgniter\Boot::bootConsole($paths);
require dirname(__DIR__) . '/bootstrap.php';

use App\Modules\SeismicIngestion\Application\DTO\CatalogRefreshResult;
use App\Commands\SigraSync;

$command = new SigraSync(service('logger'), service('commands'));
foreach ([
    [new CatalogRefreshResult(true, 1800000000, 3, ['SGC' => 3], [], 1800000000), EXIT_SUCCESS],
    [new CatalogRefreshResult(false, 1800000000, 3, ['SGC' => 3], [], 1800000000), EXIT_SUCCESS],
    [new CatalogRefreshResult(true, 1800000000, 3, ['SGC' => 3], ['USGS' => 'fixture outage'], 1800000000), EXIT_SUCCESS],
    [new CatalogRefreshResult(false, 1800000300, 0, [], ['sync' => 'fixture outage'], 1800000000), EXIT_ERROR],
] as [$result, $expected]) {
    Config\Services::injectMock('catalogRefresh', new class($result) {
        public function __construct(private CatalogRefreshResult $result) {}
        public function handle(): CatalogRefreshResult { return $this->result; }
    });
    expectArchitecture($command->run([]) === $expected, 'Scheduler CLI returned an incorrect exit code');
}
echo "Scheduled CLI: success, cached interval, partial outage and total outage exit codes passed without HTTP.\n";
