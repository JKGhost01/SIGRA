<?php

declare(strict_types=1);

namespace App\Modules\SeismicCatalog\Application\DTO;

/** Immutable internal read record; HTTP must serialize through an explicit resource. */
final readonly class SeismicEventDTO
{
    /** @param array<string, mixed> $record */
    public function __construct(public array $record) {}
}
