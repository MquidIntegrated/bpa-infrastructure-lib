<?php

namespace BPA\InfrastructureLib\Events\Drivers;

use Stomp\Client;
use Stomp\StatefulStomp;
use Stomp\Transport\Frame;

class ActiveMQDriver implements DriverInterface
{
    protected $client;
    protected $stomp;
    protected $config;
    protected $connected = false;
    protected $subscriptions = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): void
    {
        $hosts = is_array($this->config['host']) 
            ? $this->config['host'] 
            : [$this->config['host']];

        $failoverHosts = array_map(function($host) {
            return sprintf('tcp://%s:%s', $host, $this->config['port'] ?? 61613);
        }, $hosts);

        $connectionString = 'failover://(' . implode(',', $failoverHosts) . ')?randomize=false';

        $this->client = new Client($connectionString);
        
        if (isset($this->config['user'])) {
            $this->client->setLogin($this->config['user'], $this->config['password']);
        }

        $this->stomp = new StatefulStomp($this->client);
        $this->connected = true;
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->client->isConnected();
    }

    public function subscribe(array $events): void
    {
        foreach ($events as $event) {
            $destination = $this->getDestination($event);
            
            $headers = [
                'ack' => 'client-individual',
                'activemq.prefetchSize' => $this->config['prefetch_size'] ?? 1
            ];

            if (isset($this->config['durable_subscription']) && $this->config['durable_subscription']) {
                $headers['activemq.subscriptionName'] = $this->config['client_id'] . '_' . $event;
            }

            $this->stomp->subscribe($destination, $headers);
            $this->subscriptions[] = $destination;
        }
    }

    public function consume(callable $callback): void
    {
        while (true) {
            try {
                if ($frame = $this->stomp->read()) {
                    try {
                        $destination = $frame->getHeaders()['destination'];
                        $eventName = $this->extractEventName($destination);
                        $payload = json_decode($frame->getBody(), true);

                        $callback($eventName, $payload);
                        $this->stomp->ack($frame);
                    } catch (\Exception $e) {
                        $this->stomp->nack($frame);
                        throw $e;
                    }
                }
            } catch (\Exception $e) {
                if (!$this->isConnected()) {
                    $this->reconnect();
                } else {
                    throw $e;
                }
            }
        }
    }

    public function dispatch(string $event, array $payload): void
    {
        $destination = $this->getDestination($event);
        $headers = ['persistent' => 'true'];
        
        if (isset($this->config['delivery_mode'])) {
            $headers['delivery-mode'] = $this->config['delivery_mode'];
        }

        $this->stomp->send(
            $destination,
            json_encode($payload),
            $headers
        );
    }

    protected function getDestination(string $event): string
    {
        $prefix = $this->config['destination_type'] ?? 'queue';
        return "/{$prefix}/{$event}";
    }

    protected function extractEventName(string $destination): string
    {
        $parts = explode('/', $destination);
        return end($parts);
    }

    protected function reconnect(): void
    {
        $this->connect();
        foreach ($this->subscriptions as $destination) {
            $this->stomp->subscribe($destination);
        }
    }
}