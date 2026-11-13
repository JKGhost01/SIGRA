<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\Contracts;

use App\Modules\SeismicCatalog\Application\DTO\SeismicEventDTO;
use App\Modules\SeismicCatalog\Application\Queries\ListEvents\ListEventsQuery;

interface SeismicEventReader
{
    /** @return list<SeismicEventDTO> */
    public function list(ListEventsQuery $query): array;
    /** @return list<SeismicEventDTO> */
    public function recent(int $hours): array;
    public function get(string $id): ?SeismicEventDTO;
    /** @return list<SeismicEventDTO> */
    public function history(array $filters, string $order, string $direction): array;
}
