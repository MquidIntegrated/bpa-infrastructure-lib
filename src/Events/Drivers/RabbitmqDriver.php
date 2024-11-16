<?php

namespace BPA\InfrastructureLib\Events\Drivers;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitmqDriver implements DriverInterface
{
    protected $connection;
    protected $channel;
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): void
    {
        if (!$this->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password']
            );
            $this->channel = $this->connection->channel();
        }
    }

    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connection->isConnected();
    }

    public function subscribe(array $events): void
    {
        foreach ($events as $event) {
            $this->channel->queue_declare($event, false, true, false, false);
        }
    }

    public function consume(callable $callback): void
    {
        $this->channel->basic_consume(
            $this->config['queue'],
            '',
            false,
            true,
            false,
            false,
            function (AMQPMessage $message) use ($callback) {
                $callback($message->getRoutingKey(), json_decode($message->getBody(), true));
            }
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function dispatch(string $event, array $payload): void
    {
        $this->channel->queue_declare($event, false, true, false, false);
        
        $message = new AMQPMessage(
            json_encode($payload),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]
        );
        
        $this->channel->basic_publish($message, '', $event);
    }

    public function __destruct()
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}