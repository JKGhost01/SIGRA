<?php

declare(strict_types=1);

$testDatabase = getenv('SIGRA_TEST_DATABASE');
if (!$testDatabase || !preg_match('/^sigra_[a-z0-9_]+_test$/D', $testDatabase)) {
    fwrite(STDERR, "Set SIGRA_TEST_DATABASE to an explicitly provisioned sigra_*_test database.\n");
    exit(2);
}
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
$paths = new Config\Paths();
// Never read the workspace .env in a database integration test.
$paths->envDirectory = sys_get_temp_dir() . '/sigra-tests-no-dotenv-' . bin2hex(random_bytes(8));
CodeIgniter\Boot::bootConsole($paths);
require dirname(__DIR__) . '/bootstrap.php';
$config = config(Config\Database::class);
$config->defaultGroup = 'default';
$config->default['hostname'] = getenv('database.default.hostname') ?: '127.0.0.1';
$config->default['username'] = getenv('database.default.username') ?: 'root';
$config->default['password'] = getenv('database.default.password') ?: '';
$config->default['database'] = $testDatabase;
$config->default['port'] = (int) (getenv('database.default.port') ?: 3306);
$config->default['DBDriver'] = 'MySQLi';
$config->default['DBPrefix'] = '';
