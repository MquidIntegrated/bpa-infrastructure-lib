<?php

namespace BPA\InfrastructureLib\Contracts;

interface EventBrokerInterface
{
    public function publish(string $exchange, string $routingKey, array $data): void;
    public function subscribe(string $queue, callable $callback): void;
    public function createExchange(string $exchange, string $type = 'topic'): void;
    public function createQueue(string $queue, array $bindings = []): void;
    public function consumeEvents(): void;
}
