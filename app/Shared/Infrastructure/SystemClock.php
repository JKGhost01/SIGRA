<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

final class SystemClock implements \App\Shared\Domain\Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable("now", new \DateTimeZone("UTC"));
    }
}
