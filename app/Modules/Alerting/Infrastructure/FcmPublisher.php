<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure;

final class FcmPublisher implements \App\Modules\Alerting\Application\Contracts\PushPublisher
{
    public function __construct(private readonly \App\Libraries\FcmService $client) {}
    public function estaConfigurado(): bool { return $this->client->estaConfigurado(); }
    public function enviar(string $token, string $title, string $message, array $data): bool
    { return $this->client->enviar($token, $title, $message, $data); }
}
