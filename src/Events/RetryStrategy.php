<?php

namespace BPA\InfrastructureLib\Events;

class RetryStrategy
{
    protected $maxAttempts;
    protected $delay;
    protected $multiplier;

    public function __construct(array $config)
    {
        $this->maxAttempts = $config['max_attempts'];
        $this->delay = $config['delay'];
        $this->multiplier = $config['multiplier'];
    }

    public function execute(callable $callback)
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxAttempts) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;
                $attempts++;
                
                if ($attempts < $this->maxAttempts) {
                    sleep($this->delay * pow($this->multiplier, $attempts - 1));
                }
            }
        }

        throw $lastException;
    }
}