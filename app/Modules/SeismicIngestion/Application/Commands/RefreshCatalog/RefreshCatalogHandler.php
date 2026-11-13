<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Application\Commands\RefreshCatalog;

use App\Modules\SeismicIngestion\Application\Commands\SyncEvents\SyncEventsHandler;
use App\Modules\SeismicIngestion\Application\Contracts\RefreshCoordinator;
use App\Modules\SeismicIngestion\Application\DTO\CatalogRefreshResult;

final class RefreshCatalogHandler
{
    public function __construct(
        private readonly RefreshCoordinator $coordinator,
        private readonly SyncEventsHandler $sync,
    ) {}

    public function handle(bool $force = false): CatalogRefreshResult
    {
        return $this->coordinator->runIfDue(fn (): array => $this->sync->handle(), $force);
    }
}
