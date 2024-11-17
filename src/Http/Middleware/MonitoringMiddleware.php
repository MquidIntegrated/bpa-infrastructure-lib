<?php

namespace BPA\InfrastructureLib\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use BPA\InfrastructureLib\Services\Monitoring\MetricsService;
use Illuminate\Support\Facades\Log;

class MonitoringMiddleware
{
    protected $metricsService;

    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        
        // Add debug logging
        Log::info('MonitoringMiddleware: Starting request', [
            'uri' => $request->getRequestUri(),
            'method' => $request->method(),
        ]);

        $response = $next($request);
        $duration = microtime(true) - $start;

        if ($request->route()) {
            $routeName = $request->route()->getName() ?? $request->route()->uri();
            
            // Add debug logging
            Log::info('MonitoringMiddleware: Recording metrics', [
                'route' => $routeName,
                'duration' => $duration,
                'status' => $response->getStatusCode()
            ]);

            // Record both the counter and histogram metrics
            $this->metricsService->recordHttpRequest(
                $request->method(),
                $routeName,
                $response->getStatusCode(),
                $duration
            );
        }

        return $response;
    }
}