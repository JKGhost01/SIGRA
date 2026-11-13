<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

final class CiCacheStore implements \App\Shared\Application\CacheStore
{
    public function get(string $key): mixed { return cache()->get($key); }
    public function save(string $key, mixed $value, int $ttl): void { cache()->save($key, $value, $ttl); }
}
