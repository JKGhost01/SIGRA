<?php

declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

$root = dirname(__DIR__, 2);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Modules'));
$count = 0;
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;
    $relative = substr($file->getPathname(), strlen($root) + 1);
    $source = file_get_contents($file->getPathname());
    // PHP tokens avoid matching JavaScript, comments and documentation examples.
    $code = '';
    foreach (token_get_all($source) as $token) {
        if (!is_array($token)) { $code .= $token; continue; }
        if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML, T_CONSTANT_ENCAPSED_STRING], true)) continue;
        $code .= $token[1];
    }
    if (str_contains($relative, '/Domain/')) {
        expectArchitecture(!preg_match('/(?:CodeIgniter\\\\|\\bConfig\\\\|App\\\\Models\\\\|\\\\(?:Infrastructure|Http|Application)\\\\)/', $code), "$relative: domain dependency points outward");
        expectArchitecture(!preg_match('/\b(?:env|service|session|db_connect|config|cache)\s*\(|\$_(?:SERVER|GET|POST|SESSION)/', $code), "$relative: framework/global access in domain");
    }
    if (str_contains($relative, '/Application/')) {
        expectArchitecture(!preg_match('/(?:CodeIgniter\\\\|\\bConfig\\\\|App\\\\Models\\\\|\\\\Infrastructure\\\\|\\\\Http\\\\)/', $code), "$relative: application imports an adapter");
        expectArchitecture(!preg_match('/\b(?:env|service|session|db_connect|config|cache)\s*\(|->(?:where|select|query|table)\s*\(/', $code), "$relative: application performs infrastructure access");
    }
    if (str_contains($relative, '/Presentation/Views/')) {
        expectArchitecture(!preg_match('/\b(?:require|require_once|include|include_once|db_connect|service|session|config)\b|App\\\\Models\\\\|->(?:save|insert|update|delete|query)\s*\(/', $code), "$relative: executable behavior in template");
    }
    if (str_contains($relative, '/Http/Controllers/')) {
        expectArchitecture(!preg_match('/App\\\\Models\\\\|->(?:where|query|table|insert|update|delete|transBegin)\s*\(|\bdb_connect\s*\(/', $code), "$relative: controller performs persistence");
    }
    preg_match('~app/Modules/([^/]+)/~', $relative, $module);
    preg_match_all('/App\\\\Modules\\\\([^\\\\\s]+)\\\\Infrastructure\\\\/', $code, $imports);
    foreach ($imports[1] as $owner) expectArchitecture($owner === $module[1], "$relative: reaches into $owner infrastructure");
    $count++;
}
$autoload = file_get_contents($root . '/app/Config/Autoload.php');
expectArchitecture(str_contains($autoload, 'APP_NAMESPACE => APPPATH'), 'App PSR-4 mapping missing');
echo "Architecture: $count module files respect domain, application, controller, view and module dependency rules.\n";
