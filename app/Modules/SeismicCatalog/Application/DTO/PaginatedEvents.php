<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\DTO;

final readonly class PaginatedEvents
{
    /** @param list<SeismicEventDTO> $events */
    public function __construct(public array $events, public Pagination $pagination) {}
}
