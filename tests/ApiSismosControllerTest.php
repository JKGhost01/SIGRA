<?php

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

use App\Controllers\Sismos;

$checks = [
    ['apiRecent', 'recent endpoint'],
    ['apiUltimo', 'last endpoint'],
    ['apiAlertas', 'alerts endpoint'],
    ['apiDetalle', 'detail endpoint'],
    ['apiHealth', 'health endpoint'],
];

$hasFailure = false;
foreach ($checks as [$method, $label]) {
    if (!method_exists(Sismos::class, $method)) {
        fwrite(STDERR, "Missing $label: $method\n");
        $hasFailure = true;
    }
}

if ($hasFailure) {
    exit(1);
}

echo "API endpoints are present.\n";
exit(0);
