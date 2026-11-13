<?php

// The application boot and route/filter stack, with workspace .env deliberately excluded.
$projectRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
define('FCPATH', $projectRoot . (getenv('SIGRA_TEST_PUBLIC_ROOT') === '1' ? 'public/' : ''));
foreach (getenv() as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
require $projectRoot . 'app/Config/Paths.php';
$paths = new Config\Paths();
if (getenv('SIGRA_TEST_WRITEPATH')) $paths->writableDirectory = getenv('SIGRA_TEST_WRITEPATH');
$paths->envDirectory = sys_get_temp_dir() . '/sigra-http-tests-no-dotenv-' . bin2hex(random_bytes(8));
require $paths->systemDirectory . '/Boot.php';
exit(CodeIgniter\Boot::bootWeb($paths));
