<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Infrastructure\Sources;

final class UsgsSourceAdapter implements \App\Modules\SeismicIngestion\Domain\Contracts\SeismicSource
{
    public function __construct(private readonly \App\Libraries\UsgsClient $client) {}
    public function name(): string { return 'USGS'; }
    public function fetch(): array
    {
        try { return $this->client->obtenerEventos(); }
        catch (\RuntimeException $error) { throw new \App\Modules\SeismicIngestion\Domain\Contracts\SourceUnavailable($this->name(), $error); }
    }
}
