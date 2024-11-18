<?php
namespace BPA\InfrastructureLib\Providers;

use BPA\InfrastructureLib\Services\EventBroker\BroadcastListener;
use BPA\InfrastructureLib\Services\EventBroker\BroadcastManager;
use BPA\InfrastructureLib\Services\Monitoring\MetricsService;
use BPA\InfrastructureLib\Services\Monitoring\MicroserviceMetricsService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Debug\ExceptionHandler;
use BPA\InfrastructureLib\Services\ErrorHandling\ErrorHandlingService;
use Illuminate\Foundation\Application;
use BPA\InfrastructureLib\Events\EventManager;
use BPA\InfrastructureLib\Commands\ListenEventsCommand;
use Illuminate\Contracts\Http\Kernel;
use BPA\InfrastructureLib\Http\Middleware\MicroserviceMonitoringMiddleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Prometheus\Storage\Redis;

class InfrastructureLibServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/infrastructure.php', 'infrastructure'
        );
        
        $this->mergeConfigFrom(__DIR__ . '/../../config/core_dependencies.php', 'core_dependencies');

        // Register EventManager
        $this->app->singleton(EventManager::class, function ($app) {
            return new EventManager(config('infrastructure.events'));
        });

        // Register commands
        $this->commands([
            ListenEventsCommand::class
        ]);

        // Register the metrics service as a singleton
        $this->app->singleton(MicroserviceMetricsService::class, function ($app) {
            return new MicroserviceMetricsService([
                'service_name' => Config::get('app.name', 'unknown_service'),
                'storage' => [
                    'redis' => [
                        'host' => env('REDIS_HOST', 'bpa_redis'),
                        'port' => env('REDIS_PORT', 6379),
                        'password' => env('REDIS_PASSWORD', null),
                        'database' => env('REDIS_DATABASE', 0),
                    ]
                ]
            ]);
        });

        // Register the monitoring middleware
        $this->app->singleton(MicroserviceMonitoringMiddleware::class);
    }

    public function boot()
    {
        $this->publishConfig();
        $this->setupMonitoring();
        $this->registerMetricsEndpoint();
        $this->setupHealthCheck();
        $this->setupEventListeners();
    }

    protected function setupEventListeners()
    {
        $eventManager = $this->app->make(EventManager::class);

        // Register the event handlers from config
        $configuredHandlers = config('infrastructure.events.handlers', []);
        foreach ($configuredHandlers as $event => $handler) {
            $eventManager->registerHandler($event, $handler);
        }
    }

    protected function publishConfig()
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../../config/infrastructure.php' => config_path('infrastructure.php'),
        ], 'infrastructure-config');

        $this->publishes([
            __DIR__ . '/../../config/core_dependencies.php' => config_path('core_dependencies.php'),
        ]);
    }

    protected function setupMonitoring()
    {
        // Add monitoring middleware to the kernel
        $kernel = $this->app[Kernel::class];
        
        // Register as a global middleware to catch all requests
        $kernel->pushMiddleware(MicroserviceMonitoringMiddleware::class);

        // Set up terminating callback for final metrics
        $this->app->terminating(function () {
            if ($this->app->bound(MicroserviceMetricsService::class)) {
                $service = $this->app->make(MicroserviceMetricsService::class);
                
                // Update final metrics before the application terminates
                $service->updateHealthStatus('application', true);
                // $service->updateMemoryUsage();
            }
        });
    }

    protected function registerMetricsEndpoint()
    {
        // Register the metrics endpoint
        Route::get('/metrics', function () {
            $registry = \Prometheus\CollectorRegistry::getDefault();
            $renderer = new \Prometheus\RenderTextFormat();
            return response($renderer->render($registry->getMetricFamilySamples()))
                ->header('Content-Type', 'text/plain; version=0.0.4');
        })->middleware('metrics.auth');

        // Register middleware to protect metrics endpoint
        $this->app['router']->aliasMiddleware('metrics.auth', function ($request, $next) {
            // Basic authentication for metrics endpoint
            // You might want to implement more secure authentication
            if ($request->header('X-Metrics-Token') !== env('METRICS_TOKEN')) {
                abort(403, 'Unauthorized access to metrics');
            }
            return $next($request);
        });
    }

    protected function setupHealthCheck()
    {
        // Register health check endpoint
        Route::get('/health', function () {
            $service = app(MicroserviceMetricsService::class);
            
            // Perform basic health checks
            $checks = [
                'redis' => $this->checkRedisConnection(),
                'application' => true
            ];
            
            $isHealthy = !in_array(false, $checks, true);
            
            // Update health metric
            $service->updateHealthStatus('overall', $isHealthy);
            
            return response()->json([
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
                'service' => Config::get('app.name')
            ], $isHealthy ? 200 : 503);
        });
    }

    protected function checkRedisConnection(): bool
    {
        try {
            Redis::getDefaultOptions();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}