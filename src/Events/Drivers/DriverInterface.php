<?php

namespace BPA\InfrastructureLib\Events\Drivers;

interface DriverInterface
{
    public function connect(): void;
    public function isConnected(): bool;
    public function subscribe(array $events): void;
    public function consume(callable $callback): void;
    public function dispatch(string $event, array $payload): void;
}