<?php

declare(strict_types=1);

namespace App\Libraries;

use RuntimeException;

/**
 * Error controlado de la API de la Red SIGRA.
 */
class RedApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 422)
    {
        parent::__construct($message);
    }
}
