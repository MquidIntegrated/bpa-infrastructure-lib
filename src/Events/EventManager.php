<?php

namespace BPA\InfrastructureLib\Events;

use Illuminate\Support\Facades\Log;

class EventManager
{
    protected $config;
    protected $driver;
    protected $handlers = [];
    protected $retryStrategy;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initializeDriver();
        $this->initializeRetryStrategy();
    }

    protected function initializeDriver()
    {
        $driverName = ucfirst($this->config['driver']);
        $driverClass = "BPA\\InfrastructureLib\\Events\\Drivers\\{$driverName}Driver";
        $this->driver = new $driverClass($this->config[$this->config['driver']] ?? []);
    }

    protected function initializeRetryStrategy()
    {
        $this->retryStrategy = new RetryStrategy(
            $this->config['retry'] ?? [
                'max_attempts' => 3,
                'delay' => 5,
                'multiplier' => 2
            ]
        );
    }

    public function registerHandler(string $eventName, string $handlerClass)
    {
        $this->handlers[$eventName][] = $handlerClass;
    }

    public function hasHandler(string $eventName): bool
    {
        return isset($this->handlers[$eventName]) && !empty($this->handlers[$eventName]);
    }

    /**
     * Dispatch an event to the message queue
     */
    public function dispatch(string $eventName, array $payload)
    {
        try {
            $this->ensureConnection();
            
            // Wrap the payload with metadata
            $message = [
                'event' => $eventName,
                'timestamp' => now()->toIso8601String(),
                'payload' => $payload,
                'metadata' => [
                    'source' => config('app.name'),
                    'environment' => config('app.env'),
                    'version' => config('app.version', '1.0.0')
                ]
            ];

            $this->retryStrategy->execute(function () use ($eventName, $message) {
                $this->driver->dispatch($eventName, $message);
            });

            Log::info("Event dispatched: {$eventName}", ['payload' => $payload]);
        } catch (\Exception $e) {
            Log::error("Error dispatching event: {$eventName}", [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            throw $e;
        }
    }

    /**
     * Process a received event
     */
    public function processEvent(string $eventName, $payload)
    {
        try {
            if (!$this->hasHandler($eventName)) {
                Log::info("No handler found for event: {$eventName}");
                return;
            }

            foreach ($this->handlers[$eventName] as $handlerClass) {
                $this->retryStrategy->execute(function () use ($handlerClass, $payload) {
                    $handler = app($handlerClass);
                    $handler->handle($payload);
                });
            }
        } catch (\Exception $e) {
            Log::error("Error processing event: {$eventName}", [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            throw $e;
        }
    }

    /**
     * Start listening for events
     */
    public function listen()
    {
        $this->ensureConnection();
        
        Log::info("Starting event listener...", [
            'driver' => $this->config['driver'],
            'registered_events' => array_keys($this->handlers)
        ]);

        $this->driver->subscribe(array_keys($this->handlers));
        $this->driver->consume([$this, 'processEvent']);
    }

    /**
     * Ensure the driver is connected
     */
    protected function ensureConnection()
    {
        if (!$this->driver->isConnected()) {
            $this->driver->connect();
        }
    }
}