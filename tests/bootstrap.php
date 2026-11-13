<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) require_once $path;
});
set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
    exit(1);
});
function expectArchitecture(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}
