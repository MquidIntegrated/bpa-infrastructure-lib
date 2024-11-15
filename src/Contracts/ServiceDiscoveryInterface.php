<?php

namespace BPA\InfrastructureLib\Contracts;

interface ServiceDiscoveryInterface
{
    public function register(string $serviceName, string $host, int $port, array $tags = []): void;
    public function deregister(string $serviceId): void;
    public function discover(string $serviceName): array;
    public function healthCheck(): bool;
}