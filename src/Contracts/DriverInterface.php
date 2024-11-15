<?php

namespace BPA\InfrastructureLib\Contracts;

interface DriverInterface
{
    /**
     * Publish a message to the message broker
     *
     * @param string $destination Exchange/Queue name
     * @param string $routingKey Routing or message key
     * @param array $data Message data
     * @param array $options Additional options
     * @return bool Success status
     */
    public function publish(string $destination, string $routingKey, array $data, array $options = []): bool;

    /**
     * Subscribe to messages from the message broker
     *
     * @param string $queue Queue name
     * @param callable $callback Callback function to process messages
     * @param array $options Additional options
     */
    public function subscribe(string $queue, callable $callback, array $options = []): void;

    /**
     * Create a queue
     *
     * @param string $queue Queue name
     * @param array $bindings Queue bindings configuration
     * @param array $options Additional options
     */
    public function createQueue(string $queue, array $bindings = [], array $options = []): void;
}