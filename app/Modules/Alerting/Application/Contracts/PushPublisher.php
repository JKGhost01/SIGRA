<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Contracts;

interface PushPublisher
{
    public function estaConfigurado(): bool;
    public function enviar(string $token, string $title, string $message, array $data): bool;
}
