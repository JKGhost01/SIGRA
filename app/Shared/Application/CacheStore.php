<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface CacheStore
{
    public function get(string $key): mixed;
    public function save(string $key, mixed $value, int $ttl): void;
}
