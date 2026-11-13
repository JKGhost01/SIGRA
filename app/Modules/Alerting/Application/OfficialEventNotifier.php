<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application;

use App\Shared\Domain\Clock;
use Psr\Log\LoggerInterface;
final class OfficialEventNotifier implements \App\Modules\Alerting\Application\Contracts\OfficialEventNotifier
{
    public function __construct(private readonly AlertingService $alerting, private readonly Clock $clock,
        private readonly LoggerInterface $logger) {}
    public function catalogUpdated(array $events): void
    {
        foreach ($events as $event) {
            $timestamp = strtotime((string) ($event['fecha_hora'] ?? ''));
            if (($event['fuente'] ?? '') !== 'SGC' || $timestamp === false || $timestamp < $this->clock->now()->getTimestamp() - 900) continue;
            try { $this->alerting->crearAlertaSgc($event); }
            catch (\Throwable $error) { $this->logger->error('Official notification failed: {message}', ['message' => $error->getMessage()]); }
        }
    }
}
