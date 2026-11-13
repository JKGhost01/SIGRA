<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\Queries\ListHistory;

use App\Modules\SeismicCatalog\Application\Contracts\SeismicEventReader;
final class ListHistoryHandler
{
    public function __construct(private readonly SeismicEventReader $events) {}
    public function handle(array $filters, string $order, string $direction): array
    { return $this->events->history($filters, $order, $direction); }
}
