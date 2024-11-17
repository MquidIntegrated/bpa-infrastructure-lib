<?php

namespace BPA\InfrastructureLib\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use BPA\InfrastructureLib\Services\Monitoring\MetricsService;

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
        $response = $next($request);
        $duration = microtime(true) - $start;

        if ($request->route()) {
            $this->metricsService->histogram(
                'http_request_duration_seconds',
                'HTTP request duration in seconds',
                [
                    'method' => $request->method(),
                    'route' => $request->route()->getName() ?? 'unnamed',
                    'status' => $response->getStatusCode()
                ],
                $duration
            );
        }

        return $response;
    }
}