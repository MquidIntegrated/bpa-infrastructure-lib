<?php

namespace BPA\InfrastructureLib\Services\EventBroker;

use Stomp\Client as StompClient;
use BPA\InfrastructureLib\Contracts\BaseBroadcaster;

class ActiveMQBroadcaster extends BaseBroadcaster
{
    private $client;

    public function __construct(StompClient $client)
    {
        $this->client = $client;
    }

    public function broadcast($event, $payload)
    {
        $this->client->send(
            "/topic/{$event}",
            json_encode($payload)
        );
    }

    public function subscribe($callback)
    {
        $this->client->subscribe('/topic/#', function($frame) use ($callback) {
            $destination = $frame->getDestination();
            $event = str_replace('/topic/', '', $destination);
            $payload = json_decode($frame->getBody(), true);
            $callback($event, $payload);
        });

        while (true) {
            $this->client->getConnection()->readFrame();
        }
    }
}