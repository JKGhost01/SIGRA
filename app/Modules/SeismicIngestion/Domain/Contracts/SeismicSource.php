<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Domain\Contracts;

interface SeismicSource
{
    public function name(): string;
    /** @return list<array<string, mixed>> Normalized legacy catalog records. */
    public function fetch(): array;
}
