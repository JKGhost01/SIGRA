<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\Queries\ListHistory;

use App\Modules\SeismicCatalog\Application\Contracts\SeismicEventReader;
use App\Modules\SeismicCatalog\Application\DTO\{Pagination, PaginatedEvents};
final class ListHistoryHandler
{
    public function __construct(private readonly SeismicEventReader $events) {}
    public function handle(array $filters, string $order, string $direction): array
    { return $this->events->history($filters, $order, $direction); }
    public function page(array $filters, string $order, string $direction, int $page = 1, int $perPage = 20): PaginatedEvents
    {
        $pagination = new Pagination($this->events->countHistory($filters), $page, $perPage);
        return new PaginatedEvents($this->events->history($filters, $order, $direction, $pagination->perPage, $pagination->offset()), $pagination);
    }
}
