<?php

namespace BPA\InfrastructureLib\Contracts;

interface ErrorHandlingInterface
{
    public function report(\Throwable $exception, array $context = []): void;
    public function render(\Throwable $exception): array;
    public function shouldReport(\Throwable $exception): bool;
}