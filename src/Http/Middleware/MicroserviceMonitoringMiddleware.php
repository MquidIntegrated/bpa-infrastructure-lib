<?php

namespace BPA\InfrastructureLib\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use BPA\InfrastructureLib\Services\Monitoring\MicroserviceMetricsService;
use Throwable;

class MicroserviceMonitoringMiddleware
{
    private MicroserviceMetricsService $metricsService;

    public function __construct(MicroserviceMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        try {
            $response = $next($request);
            
            $this->recordMetrics(
                $request,
                $response->getStatusCode(),
                microtime(true) - $startTime
            );
            
            return $response;
            
        } catch (Throwable $e) {
            $this->recordMetrics(
                $request,
                500,
                microtime(true) - $startTime,
                get_class($e)
            );
            
            throw $e;
        }
    }

    private function recordMetrics(Request $request, int $statusCode, float $duration, ?string $errorType = null): void
    {
        $endpoint = $this->getEndpointPattern($request);
        
        $this->metricsService->recordApiCall(
            $request->method(),
            $endpoint,
            $statusCode,
            $duration,
            $errorType
        );
    }

    private function getEndpointPattern(Request $request): string
    {
        if ($route = $request->route()) {
            // Use route pattern instead of actual URI to avoid high cardinality
            $uri = $route->uri();
            // Replace route parameters with placeholders to reduce cardinality
            return preg_replace('/\{[^\}]+\}/', '{param}', $uri);
        }
        return 'unknown';
    }
}