<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Infrastructure\Sources;

final class SgcSourceAdapter implements \App\Modules\SeismicIngestion\Domain\Contracts\SeismicSource
{
    public function __construct(private readonly \App\Libraries\SgcClient $client) {}
    public function name(): string { return 'SGC'; }
    public function fetch(): array
    {
        try { return $this->client->obtenerEventos(); }
        catch (\RuntimeException $error) { throw new \App\Modules\SeismicIngestion\Domain\Contracts\SourceUnavailable($this->name(), $error); }
    }
}
