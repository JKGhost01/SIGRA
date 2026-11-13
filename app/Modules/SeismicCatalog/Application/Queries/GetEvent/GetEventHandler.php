<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\Queries\GetEvent;

use App\Modules\SeismicCatalog\Application\Contracts\SeismicEventReader;
use App\Modules\SeismicCatalog\Application\DTO\SeismicEventDTO;
final class GetEventHandler
{
    public function __construct(private readonly SeismicEventReader $events) {}
    public function handle(string $id): ?SeismicEventDTO { return $this->events->get($id); }
}
