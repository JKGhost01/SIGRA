<?php

declare(strict_types=1);
namespace App\Modules\SeismicIngestion\Infrastructure\Sources;

interface FeedTransport
{
    public function json(string $url, int $timeoutSeconds, array $headers): array;
}
