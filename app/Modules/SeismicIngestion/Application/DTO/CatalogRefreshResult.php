<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Application\DTO;

final readonly class CatalogRefreshResult
{
    public function __construct(
        public bool $synced = false,
        public int $timestamp = 0,
        public int $saved = 0,
        public array $sources = [],
        public array $errors = [],
        public int $lastSuccessAt = 0,
    ) {}

    public function cached(): self
    {
        return new self(false, $this->timestamp, $this->saved, $this->sources, $this->errors, $this->lastSuccessAt);
    }
}
