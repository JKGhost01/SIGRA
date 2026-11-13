<?php

declare(strict_types=1);

/** Initialize missing example secrets locally; never print or replace existing real keys. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$target = $argv[1] ?? $root . '/.env';
if (!is_file($target)) {
    if (!copy($root . '/.env.example', $target)) throw new RuntimeException('No se pudo crear el archivo de entorno.');
}
$text = file_get_contents($target);
if ($text === false) throw new RuntimeException('No se pudo leer el archivo de entorno.');
$changed = [];
foreach (['APP_API_TOKEN', 'encryption.key'] as $key) {
    $pattern = '/^' . preg_quote($key, '/') . '[ \t]*=[ \t]*([^\r\n]*)\r?$/m';
    $count = preg_match_all($pattern, $text, $matches);
    if ($count > 1) throw new RuntimeException('Hay claves duplicadas en el entorno; revísalas manualmente.');
    $value = $count === 1 ? trim(trim($matches[1][0]), "\"'") : '';
    $placeholder = $value === '' || $value === 'cambia-este-token'
        || preg_match('/^(?:hex2bin:)?0{32,}$/D', $value);
    if (!$placeholder) continue;
    $new = ($key === 'encryption.key' ? 'hex2bin:' : '') . bin2hex(random_bytes(32));
    $line = $key . " = '" . $new . "'";
    $text = $count === 1 ? preg_replace($pattern, $line, $text, 1) : $text . "\n" . $line . "\n";
    $changed[] = $key;
}
if (file_put_contents($target, $text, LOCK_EX) === false) throw new RuntimeException('No se pudo guardar el entorno.');
chmod($target, 0600);
echo $changed === [] ? "Las claves existentes se conservaron.\n"
    : 'Configuradas: ' . implode(', ', $changed) . ". Los valores solo están en el archivo privado.\n";
