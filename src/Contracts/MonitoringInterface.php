<?php
// src/Contracts/MonitoringInterface.php
namespace BPA\InfrastructureLib\Contracts;

interface MonitoringInterface
{
    public function counter(string $name, string $help, array $labels = []): void;
    public function gauge(string $name, string $help, array $labels = []): void;
    public function histogram(string $name, string $help, array $labels = [], array $buckets = null): void;
    public function pushMetrics(): void;
}