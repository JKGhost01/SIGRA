<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\Contracts;

interface EventWriter
{
    /** Only catalog infrastructure may persist the normalized records. */
    public function save(array $events): int;
}
