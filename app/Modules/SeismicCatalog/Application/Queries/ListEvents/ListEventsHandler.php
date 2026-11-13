<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\Queries\ListEvents;

use App\Modules\SeismicCatalog\Application\Contracts\SeismicEventReader;

final class ListEventsHandler
{
    public function __construct(private readonly SeismicEventReader $events) {}
    public function handle(ListEventsQuery $query): array { return $this->events->list($query); }
    /** The web map retains the full existing 120-hour window. */
    public function recent(int $hours = 120): array { return $this->events->recent($hours); }
}
