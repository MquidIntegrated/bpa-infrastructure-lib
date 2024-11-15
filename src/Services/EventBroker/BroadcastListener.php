<?php

namespace BPA\InfrastructureLib\Services\EventBroker;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Aws\Sqs\SqsClient;
use Stomp\Client as StompClient;

class BroadcastListener
{
    private $manager;
    private $handlers = [];

    public function __construct(BroadcastManager $manager)
    {
        $this->manager = $manager;
    }

    public function listen()
    {
        $driver = $this->manager->driver();
        
        $driver->subscribe(function ($event, $payload) {
            $this->handleEvent($event, $payload);
        });
    }

    protected function handleEvent($event, $payload)
    {
        if ($this->hasHandler($event)) {
            Event::dispatch($event, $payload);
        }
    }

    protected function hasHandler($event)
    {
        return ! empty(Event::getListeners($event));
    }
}
