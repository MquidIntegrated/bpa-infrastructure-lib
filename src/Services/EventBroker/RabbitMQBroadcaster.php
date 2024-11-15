<?php

namespace BPA\InfrastructureLib\Services\EventBroker;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use BPA\InfrastructureLib\Contracts\BaseBroadcaster;

class RabbitMQBroadcaster extends BaseBroadcaster
{
    private $connection;
    private $channel;

    public function __construct(AMQPStreamConnection $connection)
    {
        $this->connection = $connection;
        $this->channel = $connection->channel();
        $this->channel->exchange_declare('events', 'topic', false, true, false);
    }

    public function broadcast($event, $payload)
    {
        $this->channel->basic_publish(
            new AMQPMessage(json_encode($payload)),
            'events',
            $event
        );
    }

    public function subscribe($callback)
    {
        $queueName = 'app_' . uniqid();
        
        $this->channel->queue_declare($queueName, false, true, false, false);
        $this->channel->queue_bind($queueName, 'events', '#');
        
        $this->channel->basic_consume(
            $queueName,
            '',
            false,
            true,
            false,
            false,
            function ($message) use ($callback) {
                $routingKey = $message->getRoutingKey();
                $payload = json_decode($message->getBody(), true);
                $callback($routingKey, $payload);
            }
        );

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }
}
