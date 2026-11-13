<?php

declare(strict_types=1);

namespace App\Modules\SeismicIngestion\Domain\Contracts;

final class SourceUnavailable extends \RuntimeException
{
    public function __construct(public readonly string $source, ?\Throwable $previous = null)
    { parent::__construct('Fuente temporalmente no disponible.', 0, $previous); }
}
