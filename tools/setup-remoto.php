<?php

declare(strict_types=1);

// Retired: a database reset must never be exposed through an HTTP upload.
if (PHP_SAPI !== 'cli') {
    http_response_code(410);
    header('Content-Type: text/plain; charset=utf-8');
}
echo "El reinicio remoto fue retirado. Usa migraciones aditivas o el SQL de deploy; consulta docs/INSTALLATION.md.\n";
exit(PHP_SAPI === 'cli' ? 1 : 0);
