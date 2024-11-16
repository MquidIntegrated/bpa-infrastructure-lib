<?php

namespace App\Events\Handlers;

abstract class BaseHandler
{
    abstract public function handle(array $payload): void;

    protected function log(string $message, array $context = []): void
    {
        \Log::info($message, $context);
    }
}