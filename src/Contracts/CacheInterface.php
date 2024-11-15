<?php

namespace BPA\InfrastructureLib\Contracts;

interface CacheInterface
{
    public function get(string $key, $default = null);
    public function set(string $key, $value, int $ttl = null): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function remember(string $key, int $ttl, callable $callback);
}
