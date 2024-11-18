<?php

namespace BPA\InfrastructureLib\Services\Monitoring;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;
use Illuminate\Support\Facades\Config;

class MicroserviceMetricsService 
{
    private CollectorRegistry $registry;
    private array $metrics = [];
    private string $serviceName;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->serviceName = $config['service_name'] ?? Config::get('app.name', 'unknown_service');
        $this->setupRedisStorage();
        $this->registry = CollectorRegistry::getDefault();
        $this->initializeMetrics();
    }

    private function setupRedisStorage(): void
    {
        // Use the shared Redis instance for metrics storage
        Redis::setDefaultOptions([
            'host' => env('REDIS_HOST', 'bpa_redis'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'timeout' => 0.1,
            'read_timeout' => '10',
            'persistent_connections' => false,
            'prefix' => 'bpa_metrics:', // Shared prefix for all services
        ]);
    }

    private function initializeMetrics(): void
    {
        // All metrics include service_name label for service distinction
        $labels = ['service_name', 'method', 'endpoint', 'status_code'];
        $errorLabels = ['service_name', 'method', 'endpoint', 'error_type'];

        // Response time histogram
        $this->metrics['response_time'] = $this->registry->getOrRegisterHistogram(
            'bpa',  // Shared namespace for all services
            'service_request_duration_seconds',
            'Microservice request duration in seconds',
            $labels,
            [0.01, 0.05, 0.1, 0.2, 0.5, 1, 2, 5, 10]
        );

        // Request counter
        $this->metrics['request_total'] = $this->registry->getOrRegisterCounter(
            'bpa',
            'service_requests_total',
            'Total microservice requests',
            $labels
        );

        // Error counter with detailed error types
        $this->metrics['error_total'] = $this->registry->getOrRegisterCounter(
            'bpa',
            'service_errors_total',
            'Total microservice errors',
            $errorLabels
        );

        // Circuit breaker status
        $this->metrics['circuit_breaker_status'] = $this->registry->getOrRegisterGauge(
            'bpa',
            'service_circuit_breaker_status',
            'Circuit breaker status (0=open, 1=closed, 0.5=half-open)',
            ['service_name', 'circuit_name']
        );

        // Service health check
        $this->metrics['health_check'] = $this->registry->getOrRegisterGauge(
            'bpa',
            'service_health_status',
            'Service health status (0=unhealthy, 1=healthy)',
            ['service_name', 'check_name']
        );

        // Message queue metrics
        $this->metrics['queue_size'] = $this->registry->getOrRegisterGauge(
            'bpa',
            'service_queue_size',
            'Current queue size',
            ['service_name', 'queue_name']
        );

        // Dependency metrics
        $this->metrics['dependency_request_duration'] = $this->registry->getOrRegisterHistogram(
            'bpa',
            'service_dependency_request_duration_seconds',
            'External dependency request duration',
            ['service_name', 'dependency_name', 'operation'],
            [0.01, 0.05, 0.1, 0.2, 0.5, 1, 2, 5, 10]
        );
    }

    public function recordApiCall(string $method, string $endpoint, int $statusCode, float $duration, ?string $errorType = null): void
    {
        $labels = [
            'service_name' => $this->serviceName,
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => (string)$statusCode
        ];

        // Record response time and request count
        $this->metrics['response_time']->observe($duration, $labels);
        $this->metrics['request_total']->inc($labels);

        // Record errors with specific error type
        if ($statusCode >= 400) {
            $errorLabels = [
                'service_name' => $this->serviceName,
                'method' => $method,
                'endpoint' => $endpoint,
                'error_type' => $errorType ?? $this->determineErrorType($statusCode)
            ];
            $this->metrics['error_total']->inc($errorLabels);
        }
    }

    public function recordDependencyCall(string $dependencyName, string $operation, float $duration, bool $success): void
    {
        $this->metrics['dependency_request_duration']->observe(
            $duration,
            [
                'service_name' => $this->serviceName,
                'dependency_name' => $dependencyName,
                'operation' => $operation
            ]
        );
    }

    public function updateQueueSize(string $queueName, int $size): void
    {
        $this->metrics['queue_size']->set(
            $size,
            [
                'service_name' => $this->serviceName,
                'queue_name' => $queueName
            ]
        );
    }

    public function updateCircuitBreakerStatus(string $circuitName, string $status): void
    {
        $statusValue = match ($status) {
            'open' => 0,
            'half-open' => 0.5,
            'closed' => 1,
            default => -1
        };

        $this->metrics['circuit_breaker_status']->set(
            $statusValue,
            [
                'service_name' => $this->serviceName,
                'circuit_name' => $circuitName
            ]
        );
    }

    public function updateHealthStatus(string $checkName, bool $isHealthy): void
    {
        $this->metrics['health_check']->set(
            $isHealthy ? 1 : 0,
            [
                'service_name' => $this->serviceName,
                'check_name' => $checkName
            ]
        );
    }

    private function determineErrorType(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'server_error',
            $statusCode >= 400 => 'client_error',
            default => 'unknown_error'
        };
    }
}